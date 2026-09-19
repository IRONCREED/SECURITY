<?php
/**
 * Hosting Ukraine nginx access-log provider.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Infrastructure;

use DateTimeImmutable;
use Ironcreed\Request_Log\Application\HTTP_Client;
use Ironcreed\Request_Log\Application\Provider;
use Ironcreed\Request_Log\Domain\Event;
use Ironcreed\Request_Log\Domain\URI_Normalizer;
use RuntimeException;

/** Reads the fixed, read-only Hosting Ukraine API method. */
final class Hosting_Ukraine_Provider implements Provider {
	public const ENDPOINT               = 'https://adm.tools/action/hosting/log/web/nginx/';
	public const MAX_COMPRESSED_BYTES   = 33554432;
	public const MAX_DECOMPRESSED_BYTES = 134217728;
	public const MAX_RECORDS            = 100000;
	public const MAX_LINE_BYTES         = 16384;

	/**
	 * Bounded HTTP download adapter.
	 *
	 * @var HTTP_Client
	 */
	private HTTP_Client $client;
	/**
	 * Validated provider safety limits.
	 *
	 * @var array<string,int>
	 */
	private array $limits;

	/**
	 * Initialize the adapter dependencies.
	 *
	 * @param HTTP_Client       $client HTTP adapter.
	 *
	 * @param array<string,int> $limits Testable local bounds.
	 * @throws RuntimeException When the operation cannot complete safely.
	 */
	public function __construct( HTTP_Client $client, array $limits = array() ) {
		$defaults = array(
			'compressed'   => self::MAX_COMPRESSED_BYTES,
			'decompressed' => self::MAX_DECOMPRESSED_BYTES,
			'records'      => self::MAX_RECORDS,
			'line'         => self::MAX_LINE_BYTES,
		);
		if ( array_diff_key( $limits, $defaults ) ) {
			throw new RuntimeException( 'The provider limit policy is invalid.' );
		}
		foreach ( $limits as $key => $value ) {
			if ( ! is_int( $value ) || $value < 1 || $value > $defaults[ $key ] ) {
				throw new RuntimeException( 'The provider limit policy is invalid.' );
			}
		}
		$this->client = $client;
		$this->limits = array_merge( $defaults, $limits );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $host_id Positive hosting site ID.
	 * @param string $token Bearer credential.
	 * @throws RuntimeException When the operation cannot complete safely.
	 * @throws Provider_Rate_Limit On a bounded provider retry delay.
	 */
	public function fetch_today( int $host_id, string $token ): iterable {
		if ( $host_id < 1 || ! self::valid_token( $token ) ) {
			throw new RuntimeException( 'Connection details are incomplete or invalid.' );
		}

		$response = $this->client->download(
			self::ENDPOINT,
			array(
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => $this->limits['compressed'] + 1,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/gzip, application/octet-stream',
				),
				'body'                => array( 'host_id' => $host_id ),
			)
		);

		$file = $response['file'];
		try {
			if ( 429 === $response['code'] ) {
				require_once __DIR__ . '/class-provider-rate-limit.php';
				$retry = 60;
				foreach ( $response['headers'] as $name => $value ) {
					if ( 'retry-after' === strtolower( (string) $name ) && is_numeric( $value ) ) {
						$retry = max( 60, min( 86400, (int) $value ) );
					}
				}
				throw new Provider_Rate_Limit( $retry );
			}
			if ( $response['code'] < 200 || $response['code'] >= 300 || $response['size'] > $this->limits['compressed'] || ! self::supported_content_type( $response['headers'] ) ) {
				throw new RuntimeException( 'The provider returned an unusable response.' );
			}

			yield from $this->read_archive( $file );
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Accept absent or explicitly supported binary response media types.
	 *
	 * @param array $headers Provider response headers.
	 */
	private static function supported_content_type( array $headers ): bool {
		$type = '';
		foreach ( $headers as $name => $value ) {
			if ( 'content-type' === strtolower( (string) $name ) ) {
				$type = strtolower( trim( explode( ';', (string) $value )[0] ) );
				break;
			}
		}

		return '' === $type || in_array( $type, array( 'application/gzip', 'application/x-gzip', 'application/octet-stream' ), true );
	}

	/**
	 * Validate token conservatively without transforming it.
	 *
	 * @param string $token Bearer credential.
	 */
	public static function valid_token( string $token ): bool {
		return '' !== $token && strlen( $token ) <= 4096 && 1 !== preg_match( '/[\x00-\x20\x7F]/', $token );
	}

	/**
	 * Stream a gzip archive within decompressed and record limits.
	 *
	 * @param string $file Temporary downloaded archive.
	 * @throws RuntimeException When the operation cannot complete safely.
	 */
	private function read_archive( string $file ): iterable {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the two-byte gzip signature from the local bounded download.
		$header = file_get_contents( $file, false, null, 0, 2 );
		if ( "\x1f\x8b" !== $header ) {
			throw new RuntimeException( 'The provider returned an unusable archive.' );
		}

		$stream = gzopen( $file, 'rb' );
		if ( false === $stream ) {
			throw new RuntimeException( 'The provider returned an unusable archive.' );
		}

		$bytes   = 0;
		$count   = 0;
		$ordinal = 0;
		try {
			while ( ! gzeof( $stream ) ) {
				++$ordinal;
				$line = gzgets( $stream, $this->limits['line'] + 2 );
				if ( false === $line ) {
					throw new RuntimeException( 'The provider archive could not be read.' );
				}

				$bytes += strlen( $line );
				if ( $bytes > $this->limits['decompressed'] ) {
					throw new RuntimeException( 'The provider archive exceeds the local decompressed limit.' );
				}

				if ( strlen( $line ) > $this->limits['line'] && ! str_ends_with( $line, "\n" ) ) {
					$this->discard_line_remainder( $stream, $bytes );
					if ( $bytes > $this->limits['decompressed'] ) {
						throw new RuntimeException( 'The provider archive exceeds the local decompressed limit.' );
					}
					continue;
				}

				$event = $this->parse_line( rtrim( $line, "\r\n" ), $ordinal );
				if ( null !== $event ) {
					yield $event;
					++$count;
					if ( $count >= $this->limits['records'] ) {
						break;
					}
				}
			}
		} finally {
			gzclose( $stream );
		}
	}

	/**
	 * Discard the remainder of an oversized line.
	 *
	 * @param resource $stream Open gzip stream.
	 * @param int      $bytes  Decompressed bytes consumed so far.
	 */
	private function discard_line_remainder( $stream, int &$bytes ): void {
		while ( ! gzeof( $stream ) ) {
			$chunk = gzgets( $stream, $this->limits['line'] + 2 );
			if ( false === $chunk ) {
				break;
			}
			$bytes += strlen( $chunk );
			if ( $bytes > $this->limits['decompressed'] || str_ends_with( $chunk, "\n" ) ) {
				break;
			}
		}
	}

	/**
	 * Parse one nginx combined-log line.
	 *
	 * @param string $line Synthetic or provider log line.
	 * @param int    $ordinal Record ordinal within the archive.
	 */
	private function parse_line( string $line, int $ordinal ): ?array {
		if ( '' === $line || strlen( $line ) > $this->limits['line'] || ! wp_check_invalid_utf8( $line ) ) {
			return null;
		}

		$quoted  = '"((?:\\\\.|[^"\\\\])*)"';
		$pattern = '/^(\S+) \S+ \S+ \[([^]]+)\] ' . $quoted . ' (\d{3}) (\d+|-) ' . $quoted . ' ' . $quoted . '$/D';
		if ( 1 !== preg_match( $pattern, $line, $matches ) ) {
			return null;
		}

		$timestamp = DateTimeImmutable::createFromFormat( 'd/M/Y:H:i:s O', $matches[2] );
		$errors    = DateTimeImmutable::getLastErrors();
		if ( false === $timestamp || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			return null;
		}

		$request = self::unescape_nginx( $matches[3] );
		if ( 1 !== preg_match( '/^([A-Z]+) (\S+) HTTP\/\d(?:\.\d)?$/D', $request, $request_parts ) ) {
			return null;
		}

		$uri     = URI_Normalizer::normalize( $request_parts[2] );
		$referer = '-' === $matches[6] ? array(
			'path'  => '',
			'query' => '',
		) : URI_Normalizer::normalize( self::unescape_nginx( $matches[6] ) );

		$event                         = Event::validate(
			array(
				'timestamp'      => $timestamp->getTimestamp(),
				'method'         => $request_parts[1],
				'path'           => $uri['path'],
				'query'          => $uri['query'],
				'status'         => (int) $matches[4],
				'response_bytes' => '-' === $matches[5] ? 0 : (int) $matches[5],
				'client_ip'      => $matches[1],
				'referer'        => $referer['path'] . ( $referer['query'] ? '?' . $referer['query'] : '' ),
				'user_agent'     => self::unescape_nginx( $matches[7] ),
				'source'         => 'hosting-ukraine-nginx',
				'fingerprint'    => '',
			)
		);
		$canonical                     = $event;
		$canonical['provider_ordinal'] = $ordinal;
		$event['fingerprint']          = hash( 'sha256', (string) wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES ) );
		return $event;
	}

	/**
	 * Decode nginx quoted-string escapes.
	 *
	 * @param string $value Untrusted input value.
	 */
	private static function unescape_nginx( string $value ): string {
		return preg_replace_callback(
			'/\\\\(x[0-9A-Fa-f]{2}|.)/s',
			static function ( array $parts ): string {
				return str_starts_with( $parts[1], 'x' )
					? chr( hexdec( substr( $parts[1], 1 ) ) )
					: $parts[1];
			},
			$value
		) ?? '';
	}
}
