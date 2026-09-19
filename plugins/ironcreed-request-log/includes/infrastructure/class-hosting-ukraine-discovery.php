<?php
/**
 * Read-only Hosting Ukraine site lookup.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Infrastructure;

use Ironcreed\Request_Log\Application\HTTP_Client;
use RuntimeException;

/** Resolves a hosting virtual host from a domain using the fixed API. */
final class Hosting_Ukraine_Discovery {

	public const ENDPOINT   = 'https://adm.tools/action/get_services/';
	private const MAX_BYTES = 65536;

	/**
	 * Bounded HTTP download adapter.
	 *
	 * @var HTTP_Client Bounded HTTP adapter.
	 */
	private HTTP_Client $client;

	/**
	 * Initialize the adapter dependencies.
	 *
	 * @param HTTP_Client $client Bounded HTTP adapter.
	 */
	public function __construct( HTTP_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Accept a domain or site URL without credentials, query or fragment.
	 *
	 * @param string $value Administrator-entered domain.
	 * @return string ASCII domain, or an empty string on invalid input.
	 */
	public static function domain( string $value ): string {
		$value = trim( $value );
		$parts = wp_parse_url( str_contains( $value, '://' ) ? $value : 'https://' . $value );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'https', 'http' ), true ) || isset( $parts['user'], $parts['pass'] ) || isset( $parts['user'] ) || isset( $parts['port'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		$domain = strtolower( rtrim( $parts['host'] ?? '', '.' ) );
		if ( strlen( $domain ) > 253 || ! str_contains( $domain, '.' ) || false !== filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		foreach ( explode( '.', $domain ) as $label ) {
			if ( 1 !== preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label ) ) {
				return '';
			}
		}
		return $domain;
	}

	/**
	 * Resolve exactly one site ID from the token-visible host-service list.
	 *
	 * The provider receives only type=host. The administrator-entered domain is
	 * matched locally against response[].host and is never sent as a lookup field.
	 *
	 * @param string $domain Hosting site domain.
	 * @param string $token  Bearer credential.
	 * @return int Positive hosting site ID.
	 * @throws RuntimeException On invalid input or unusable response.
	 */
	public function find( string $domain, string $token ): int {
		$domain = self::domain( $domain );
		if ( '' === $domain || ! Hosting_Ukraine_Provider::valid_token( $token ) ) {
			throw new RuntimeException( 'The lookup details are invalid.' );
		}
		$response = $this->client->download(
			self::ENDPOINT,
			array(
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES + 1,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
				'body'                => array(
					'type' => 'host',
				),
			)
		);
		$file     = $response['file'];
		try {
			if ( $response['code'] < 200 || $response['code'] >= 300 || $response['size'] > self::MAX_BYTES ) {
				throw new RuntimeException( 'The site ID lookup failed.' );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only a bounded local temporary response.
			$body = file_get_contents( $file, false, null, 0, self::MAX_BYTES + 1 );
			$data = is_string( $body ) && strlen( $body ) <= self::MAX_BYTES ? json_decode( $body, true, 16 ) : null;
			if (
				! is_array( $data )
				|| true !== ( $data['result'] ?? null )
				|| ! isset( $data['response'] )
				|| ! is_array( $data['response'] )
			) {
				throw new RuntimeException( 'The site service lookup returned an unusable response.' );
			}
			return self::match_service_id( $data['response'], $domain );
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Match one service by exact host first, then by one leading www alias.
	 *
	 * @param array<int,mixed> $services Provider host-service list.
	 * @param string           $domain   Normalized requested domain.
	 * @return int Positive hosting site ID.
	 * @throws RuntimeException When no unique service can be selected.
	 */
	private static function match_service_id( array $services, string $domain ): int {
		$exact   = array();
		$aliases = array();

		foreach ( $services as $service ) {
			if (
				! is_array( $service )
				|| ! isset( $service['host'], $service['id'] )
				|| ! is_string( $service['host'] )
			) {
				continue;
			}
			$host = self::domain( $service['host'] );
			$id   = is_int( $service['id'] ) || is_string( $service['id'] )
				? filter_var( $service['id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) )
				: false;
			if ( '' === $host || false === $id ) {
				continue;
			}

			if ( $host === $domain ) {
				$exact[] = (int) $id;
			} elseif ( self::without_www( $host ) === self::without_www( $domain ) ) {
				$aliases[] = (int) $id;
			}
		}

		$candidates = $exact ? $exact : $aliases;
		$candidates = array_values( array_unique( $candidates ) );
		if ( 1 !== count( $candidates ) ) {
			throw new RuntimeException( 'No unambiguous hosting site ID was returned.' );
		}
		return $candidates[0];
	}

	/**
	 * Remove one conventional www label for a bounded fallback comparison.
	 *
	 * @param string $domain Normalized ASCII domain.
	 */
	private static function without_www( string $domain ): string {
		return str_starts_with( $domain, 'www.' ) ? substr( $domain, 4 ) : $domain;
	}
}
