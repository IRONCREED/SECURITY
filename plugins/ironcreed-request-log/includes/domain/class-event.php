<?php
/**
 * Validated request event.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Domain;

/** Converts mixed adapter values into the closed event schema. */
final class Event {
	public const SOURCES = array( 'wordpress-runtime', 'hosting-ukraine-nginx' );
	public const ROUTES  = array( 'front-end', 'rest', 'ajax', 'cron', 'xml-rpc', 'login', 'admin', 'unknown' );
	public const METHODS = array( 'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'CONNECT', 'TRACE', 'UNKNOWN' );

	/**
	 * Validate and bound an event record.
	 *
	 * @param array $event Normalized event values.
	 */
	public static function validate( array $event ): array {
		$method = strtoupper( (string) ( $event['method'] ?? '' ) );
		$route  = (string) ( $event['route_kind'] ?? 'unknown' );
		$source = (string) ( $event['source'] ?? '' );

		return array(
			'observed_at'    => gmdate( 'Y-m-d H:i:s', (int) ( $event['timestamp'] ?? time() ) ),
			'method'         => in_array( $method, self::METHODS, true ) ? $method : 'UNKNOWN',
			'path'           => (string) ( $event['path'] ?? '/' ),
			'query'          => (string) ( $event['query'] ?? '' ),
			'status'         => self::range( $event['status'] ?? 0, 100, 599, 0 ),
			'duration_ms'    => max( 0, min( 86400000, (int) ( $event['duration_ms'] ?? 0 ) ) ),
			'route_kind'     => in_array( $route, self::ROUTES, true ) ? $route : 'unknown',
			'response_bytes' => max( 0, (int) ( $event['response_bytes'] ?? 0 ) ),
			'client_ip'      => substr( sanitize_text_field( (string) ( $event['client_ip'] ?? '' ) ), 0, 45 ),
			'user_agent'     => substr( sanitize_text_field( (string) ( $event['user_agent'] ?? '' ) ), 0, 512 ),
			'referer'        => substr( sanitize_text_field( (string) ( $event['referer'] ?? '' ) ), 0, 2048 ),
			'source'         => in_array( $source, self::SOURCES, true ) ? $source : 'wordpress-runtime',
			'fingerprint'    => substr( sanitize_key( (string) ( $event['fingerprint'] ?? '' ) ), 0, 64 ),
		);
	}

	/**
	 * Validate an integer range.
	 *
	 * @param mixed $value Untrusted input value.
	 * @param int   $minimum Minimum allowed value.
	 * @param int   $maximum Maximum allowed value.
	 * @param int   $fallback Fallback for invalid values.
	 */
	private static function range( mixed $value, int $minimum, int $maximum, int $fallback ): int {
		$value = (int) $value;
		return $value >= $minimum && $value <= $maximum ? $value : $fallback;
	}
}
