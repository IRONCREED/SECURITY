<?php
/** Provider contract and negative-security tests. */

use Ironcreed\Request_Log\Application\HTTP_Client;
use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Provider;
use PHPUnit\Framework\TestCase;

final class ProviderTest extends TestCase {
	public function test_provider_uses_exact_contract_and_streams_synthetic_archive(): void {
		$client = new Synthetic_HTTP_Client( 200, gzencode( '192.0.2.1 - - [28/Aug/2026:12:00:00 +0000] "GET /demo?token=x HTTP/1.1" 200 12 "-" "Synthetic Agent"' ) );
		$events = iterator_to_array( ( new Hosting_Ukraine_Provider( $client ) )->fetch_today( 12345, 'test-token-not-a-secret' ) );

		self::assertSame( Hosting_Ukraine_Provider::ENDPOINT, $client->url );
		self::assertSame( 20, $client->arguments['timeout'] );
		self::assertSame( 0, $client->arguments['redirection'] );
		self::assertSame( Hosting_Ukraine_Provider::MAX_COMPRESSED_BYTES + 1, $client->arguments['limit_response_size'] );
		self::assertSame( 'Bearer test-token-not-a-secret', $client->arguments['headers']['Authorization'] );
		self::assertSame( array( 'host_id' => 12345 ), $client->arguments['body'] );
		self::assertSame( 'token=%5Bredacted%5D', $events[0]['query'] );
		self::assertFileDoesNotExist( $client->file );
	}

	public function test_failure_exposes_neither_token_nor_response_body(): void {
		$client = new Synthetic_HTTP_Client( 500, 'secret provider response body' );
		try {
			iterator_to_array( ( new Hosting_Ukraine_Provider( $client ) )->fetch_today( 1, 'never-show-this-token' ) );
			self::fail( 'Expected provider failure.' );
		} catch ( RuntimeException $error ) {
			self::assertStringNotContainsString( 'never-show-this-token', $error->getMessage() );
			self::assertStringNotContainsString( 'secret provider response body', $error->getMessage() );
		}
	}

	public function test_malformed_gzip_fails_safely_and_removes_file(): void {
		$client = new Synthetic_HTTP_Client( 200, 'not-gzip' );
		$this->expectException( RuntimeException::class );
		try {
			iterator_to_array( ( new Hosting_Ukraine_Provider( $client ) )->fetch_today( 1, 'synthetic-token' ) );
		} finally {
			self::assertFileDoesNotExist( $client->file );
		}
	}

	public function test_oversized_compressed_response_is_rejected(): void {
		$client = new Synthetic_HTTP_Client( 200, gzencode( '' ), Hosting_Ukraine_Provider::MAX_COMPRESSED_BYTES + 1 );
		$this->expectException( RuntimeException::class );
		iterator_to_array( ( new Hosting_Ukraine_Provider( $client ) )->fetch_today( 1, 'synthetic-token' ) );
	}

	public function test_injected_stream_limits_cover_exact_and_first_excess_byte(): void {
		$line = '192.0.2.1 - - [28/Aug/2026:12:00:00 +0000] "GET /limit HTTP/1.1" 200 1 "-" "Synthetic Agent"' . "\n";
		$gzip = gzencode( $line );
		$limits = array( 'compressed' => strlen( $gzip ), 'decompressed' => strlen( $line ), 'records' => 2, 'line' => 256 );
		self::assertCount( 1, iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, $gzip ), $limits ) )->fetch_today( 1, 'synthetic-token' ) ) );

		$this->assertLimitFailure( $gzip, $limits + array(), array( 'compressed' => strlen( $gzip ) - 1 ) );
		$this->assertLimitFailure( $gzip, $limits + array(), array( 'decompressed' => strlen( $line ) - 1 ) );

		$two = gzencode( $line . str_replace( '/limit', '/second', $line ) . str_replace( '/limit', '/third', $line ) );
		$events = iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, $two ), array( 'compressed' => strlen( $two ), 'decompressed' => strlen( gzdecode( $two ) ), 'records' => 2, 'line' => 256 ) ) )->fetch_today( 1, 'synthetic-token' ) );
		self::assertCount( 2, $events );
		self::assertSame( array( '/limit', '/second' ), array_column( $events, 'path' ) );
	}

	private function assertLimitFailure( string $gzip, array $base, array $override ): void {
		try {
			iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, $gzip ), array_replace( $base, $override ) ) )->fetch_today( 7321, 'private-synthetic-token' ) );
			self::fail( 'Expected limit failure.' );
		} catch ( RuntimeException $error ) {
			self::assertStringNotContainsString( 'private-synthetic-token', $error->getMessage() );
			self::assertStringNotContainsString( '7321', $error->getMessage() );
		}
	}

	public function test_malformed_and_oversized_lines_do_not_stop_later_records(): void {
		$valid = '192.0.2.1 - - [28/Aug/2026:12:00:00 +0000] "GET /safe HTTP/1.1" 200 1 "-" "Synthetic\\x20Agent"';
		$body = gzencode( "malformed\n" . str_repeat( 'x', Hosting_Ukraine_Provider::MAX_LINE_BYTES + 20 ) . "\n{$valid}" );
		$events = iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, $body ) ) )->fetch_today( 1, 'synthetic-token' ) );
		self::assertCount( 1, $events );
		self::assertSame( 'Synthetic Agent', $events[0]['user_agent'] );
	}

	public function test_fingerprint_uses_redacted_canonical_event_and_occurrence(): void {
		$first = '192.0.2.1 - - [28/Aug/2026:12:00:00 +0000] "GET /demo?token=first HTTP/1.1" 200 12 "-" "Synthetic Agent"';
		$second = str_replace( 'token=first', 'token=second', $first );
		$event_a = iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, gzencode( $first ) ) ) )->fetch_today( 1, 'synthetic-token' ) )[0];
		$event_b = iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, gzencode( $second ) ) ) )->fetch_today( 1, 'synthetic-token' ) )[0];
		self::assertSame( $event_a['fingerprint'], $event_b['fingerprint'] );
		$duplicates = iterator_to_array( ( new Hosting_Ukraine_Provider( new Synthetic_HTTP_Client( 200, gzencode( $first . "\n" . $first ) ) ) )->fetch_today( 1, 'synthetic-token' ) );
		self::assertNotSame( $duplicates[0]['fingerprint'], $duplicates[1]['fingerprint'] );
	}
}

final class Synthetic_HTTP_Client implements HTTP_Client {
	public string $url = '';
	public array $arguments = array();
	public string $file;
	private int $code;
	private string $body;
	private ?int $reported_size;

	public function __construct( int $code, string $body, ?int $reported_size = null ) { $this->code = $code; $this->body = $body; $this->reported_size = $reported_size; }
	public function download( string $url, array $arguments ): array {
		$this->url = $url; $this->arguments = $arguments; $this->file = tempnam( sys_get_temp_dir(), 'icrl-' ); file_put_contents( $this->file, $this->body );
		return array( 'code' => $this->code, 'headers' => array(), 'file' => $this->file, 'size' => $this->reported_size ?? strlen( $this->body ) );
	}
}
