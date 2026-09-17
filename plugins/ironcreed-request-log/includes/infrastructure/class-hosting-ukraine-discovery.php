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

	public const ENDPOINT   = 'https://adm.tools/action/get_id/';
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
	 * Resolve exactly one site ID; never infer account/user IDs.
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
					'name' => $domain,
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
			$id   = is_array( $data ) && false !== ( $data['result'] ?? true ) ? ( $data['response']['host_id'] ?? null ) : null;
			if ( ! ( is_int( $id ) || is_string( $id ) ) || false === filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
				throw new RuntimeException( 'No unambiguous hosting site ID was returned.' );
			}
			return (int) $id;
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
