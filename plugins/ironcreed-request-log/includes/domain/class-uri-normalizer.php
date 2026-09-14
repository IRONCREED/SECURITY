<?php
/**
 * URI normalization and query redaction.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Domain;

/** Normalizes untrusted request targets before persistence. */
final class URI_Normalizer {
	private const MAX_PATH  = 2048;
	private const MAX_QUERY = 4096;

	/** @var string[] Mandatory keys which filters cannot remove. */
	private const SENSITIVE = array(
		'password', 'pass', 'pwd', 'token', 'access_token', 'refresh_token',
		'api_key', 'apikey', 'secret', 'nonce', '_wpnonce', 'authorization',
		'auth', 'key', 'signature', 'sig', 'code', 'email',
	);

	/**
	 * Normalize an untrusted request target.
	 *
	 * @param string   $uri                  Request target or absolute URI.
	 * @param string[] $additional_sensitive Additional sensitive keys.
	 * @return array{path:string,query:string}
	 */
	public static function normalize( string $uri, array $additional_sensitive = array() ): array {
		$uri   = preg_replace( '/[\x00-\x1F\x7F]/', '', $uri ) ?? '';
		$parts = wp_parse_url( $uri );

		if ( false === $parts ) {
			return array( 'path' => '/', 'query' => '' );
		}

		$path = self::truncate_encoded( (string) ( $parts['path'] ?? '/' ), self::MAX_PATH );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return array(
			'path'  => $path,
			'query' => self::redact_query( (string) ( $parts['query'] ?? '' ), $additional_sensitive ),
		);
	}

	/**
	 * Redact a bounded query while retaining pair order and duplicates.
	 *
	 * @param string   $query                Raw query.
	 * @param string[] $additional_sensitive Additional sensitive keys.
	 */
	public static function redact_query( string $query, array $additional_sensitive = array() ): string {
		$sensitive = self::sensitive_keys( $additional_sensitive );
		$output    = array();

		foreach ( array_slice( explode( '&', $query ), 0, 100 ) as $pair ) {
			$parts       = explode( '=', $pair, 2 );
			$encoded_key = self::truncate_encoded( $parts[0], 384 );
			$key         = self::decoded_key( $encoded_key );

			if ( '' === $key ) {
				continue;
			}

			$value = in_array( self::base_key( $key ), $sensitive, true )
				? '[redacted]'
				: self::clean_text( rawurldecode( $parts[1] ?? '' ), 512 );

			$output[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		return self::truncate_encoded( implode( '&', $output ), self::MAX_QUERY );
	}

	/** @param string[] $additional Additional sensitive keys. */
	private static function sensitive_keys( array $additional ): array {
		$filtered = array_filter( $additional, 'is_string' );
		$filtered = array_map( 'strtolower', $filtered );
		return array_values( array_unique( array_merge( self::SENSITIVE, $filtered ) ) );
	}

	/** Decode nested and percent-encoded key syntax. */
	private static function decoded_key( string $key ): string {
		for ( $iteration = 0; $iteration < 2; ++$iteration ) {
			$decoded = rawurldecode( $key );
			if ( $decoded === $key ) {
				break;
			}
			$key = $decoded;
		}

		return self::clean_text( $key, 128 );
	}

	/** Return the root name from token, token[], or token[name]. */
	private static function base_key( string $key ): string {
		$position = strpos( $key, '[' );
		if ( false !== $position ) {
			$key = substr( $key, 0, $position );
		}

		return strtolower( $key );
	}

	/** Remove invalid text and apply a character bound. */
	private static function clean_text( string $value, int $length ): string {
		$value = wp_check_invalid_utf8( $value, true );
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '';
		return mb_substr( $value, 0, $length, 'UTF-8' );
	}

	/** Truncate without leaving an incomplete percent escape. */
	private static function truncate_encoded( string $value, int $length ): string {
		$value = self::clean_text( $value, $length );
		$value = preg_replace( '/%(?:[0-9A-Fa-f]?)$/', '', $value ) ?? '';
		return $value;
	}
}
