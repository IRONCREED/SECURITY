<?php
/** Repository transaction and deduplication regression tests. */

use Ironcreed\Request_Log\Infrastructure\Event_Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase {
	public function test_multiple_runtime_events_use_null_fingerprints(): void {
		$db = new Repository_Database();
		$repository = new Event_Repository( $db );
		self::assertTrue( $repository->insert_runtime( $this->event( 'wordpress-runtime', '' ) ) );
		self::assertTrue( $repository->insert_runtime( $this->event( 'wordpress-runtime', '' ) ) );
		self::assertCount( 2, $db->rows );
		self::assertNull( $db->rows[0]['fingerprint'] );
	}

	public function test_provider_duplicate_is_not_counted_as_database_error(): void {
		$db = new Repository_Database();
		$repository = new Event_Repository( $db );
		$event = $this->event( 'hosting-ukraine-nginx', str_repeat( 'a', 64 ) );
		self::assertSame( 1, $repository->import( array( $event, $event ) ) );
		self::assertContains( 'COMMIT', $db->transactions );
		self::assertNotContains( 'ROLLBACK', $db->transactions );
	}

	public function test_database_error_rolls_back_current_batch(): void {
		$db = new Repository_Database();
		$db->fail_insert = true;
		$repository = new Event_Repository( $db );
		$this->expectException( RuntimeException::class );
		try {
			$repository->import( array( $this->event( 'hosting-ukraine-nginx', str_repeat( 'b', 64 ) ) ) );
		} finally {
			self::assertContains( 'ROLLBACK', $db->transactions );
		}
	}

	public function test_second_insert_failure_restores_original_rows(): void {
		$db = new Repository_Database();
		$db->fail_insert_number = 2;
		$repository = new Event_Repository( $db );
		$first = $this->event( 'hosting-ukraine-nginx', str_repeat( 'c', 64 ) );
		$second = $this->event( 'hosting-ukraine-nginx', str_repeat( 'd', 64 ) );
		try { $repository->import( array( $first, $second ) ); self::fail( 'Expected failure.' ); } catch ( RuntimeException $error ) { self::assertSame( 'A provider event batch could not be stored.', $error->getMessage() ); }
		self::assertSame( array(), $db->rows );
	}

	public function test_lock_acquisition_failure_is_release_safe(): void {
		$db = new Repository_Database(); $db->lock_available = false;
		$this->expectExceptionMessage( 'The request log write lock is unavailable.' );
		( new Event_Repository( $db ) )->insert_runtime( $this->event( 'wordpress-runtime', '' ) );
	}

	public function test_transaction_and_release_failures_preserve_state_and_priority(): void {
		foreach ( array( 'START TRANSACTION', 'COMMIT' ) as $failure ) {
			$db = new Repository_Database(); $db->fail_transaction = $failure;
			try { ( new Event_Repository( $db ) )->insert_runtime( $this->event( 'wordpress-runtime', '' ) ); self::fail( 'Expected failure.' ); } catch ( RuntimeException $error ) { self::assertStringContainsString( 'transaction', $error->getMessage() ); }
			self::assertSame( array(), $db->rows ); self::assertSame( 1, $db->release_attempts );
		}
		$db = new Repository_Database(); $db->fail_insert = true; $db->fail_transaction = 'ROLLBACK'; $db->release_throws = true;
		try { ( new Event_Repository( $db ) )->import( array( $this->event( 'hosting-ukraine-nginx', str_repeat( 'e', 64 ) ) ) ); self::fail( 'Expected failure.' ); } catch ( RuntimeException $error ) { self::assertSame( 'A provider event batch could not be stored.', $error->getMessage() ); }
		$db = new Repository_Database(); $db->release_available = false; $this->expectExceptionMessage( 'write lock could not be released' ); ( new Event_Repository( $db ) )->insert_runtime( $this->event( 'wordpress-runtime', '' ) );
	}

	public function test_prune_failure_rolls_back_runtime_row(): void {
		$GLOBALS['test_options']['ironcreed_request_log_cap'] = 100; $db = new Repository_Database();
		for ( $i = 0; $i < 100; ++$i ) $db->rows[] = $this->event( 'wordpress-runtime', '' ) + array( 'event_id' => $i + 1 );
		$db->fail_prune = true; try { ( new Event_Repository( $db ) )->insert_runtime( $this->event( 'wordpress-runtime', '' ) ); self::fail( 'Expected failure.' ); } catch ( RuntimeException $error ) { self::assertStringContainsString( 'hard cap', $error->getMessage() ); }
		self::assertCount( 100, $db->rows );
	}

	public function test_cleanup_failures_restore_rows_and_preserve_error(): void {
		$db = new Repository_Database(); $db->rows[] = $this->event( 'wordpress-runtime', '' ) + array( 'event_id' => 1 ); $db->fail_retention = true; $db->fail_transaction = 'ROLLBACK';
		try { ( new Event_Repository( $db ) )->cleanup(); self::fail( 'Expected failure.' ); } catch ( RuntimeException $error ) { self::assertSame( 'Expired request events could not be removed.', $error->getMessage() ); }
		self::assertCount( 1, $db->rows );
		$db = new Repository_Database(); $db->release_available = false; $this->expectExceptionMessage( 'write lock could not be released' ); ( new Event_Repository( $db ) )->cleanup();
	}

	public function test_large_import_keeps_exact_canonical_newest_cap(): void {
		$GLOBALS['test_options']['ironcreed_request_log_cap'] = 100;
		$db = new Repository_Database();
		$repository = new Event_Repository( $db );
		$events = array();
		for ( $index = 0; $index < 150; ++$index ) {
			$event = $this->event( 'hosting-ukraine-nginx', hash( 'sha256', (string) $index ) );
			$event['observed_at'] = gmdate( 'Y-m-d H:i:s', 1700000000 + $index );
			$events[] = $event;
		}
		self::assertSame( 150, $repository->import( $events ) );
		self::assertCount( 100, $db->rows );
		self::assertSame( $events[50]['observed_at'], min( array_column( $db->rows, 'observed_at' ) ) );
	}

	private function event( string $source, string $fingerprint ): array {
		return array(
			'observed_at' => '2026-08-28 12:00:00', 'method' => 'GET', 'path' => '/', 'query' => '',
			'status' => 200, 'duration_ms' => 1, 'route_kind' => 'front-end', 'response_bytes' => 0,
			'client_ip' => '', 'user_agent' => '', 'referer' => '', 'source' => $source, 'fingerprint' => $fingerprint,
		);
	}
}

final class Repository_Database extends wpdb {
	public array $rows = array();
	public array $transactions = array();
	public bool $fail_insert = false;
	public int $fail_insert_number = 0;
	public bool $lock_available = true;
	public bool $release_available = true;
	public bool $release_throws = false;
	public bool $fail_prune = false;
	public bool $fail_retention = false;
	public string $fail_transaction = '';
	public int $release_attempts = 0;
	public string $database_name = 'synthetic';
	private int $next_id = 1;
	private int $insert_number = 0;
	private array $snapshot = array();
	public function insert( $table, $event, $formats ) { $event['event_id'] = $this->next_id++; $this->rows[] = $event; return 1; }
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function get_var( $query ) { $sql = is_array( $query ) ? $query[0] : $query; if ( 'SELECT DATABASE()' === $sql ) return $this->database_name; if ( str_contains( $sql, 'GET_LOCK(' ) ) return $this->lock_available ? 1 : 0; if ( str_contains( $sql, 'RELEASE_LOCK(' ) ) { ++$this->release_attempts; if ( $this->release_throws ) throw new RuntimeException( 'synthetic release detail' ); return $this->release_available ? 1 : 0; } return null; }
	public function get_row( $query, $output = null ) {
		$rows = $this->rows;
		usort( $rows, static fn( $a, $b ) => array( $b['observed_at'], $b['event_id'] ) <=> array( $a['observed_at'], $a['event_id'] ) );
		return $rows[ $query[1][0] ] ?? null;
	}
	public function query( $query ) {
		if ( is_string( $query ) ) { $this->transactions[] = $query; if ( $query === $this->fail_transaction ) return false; if ( 'START TRANSACTION' === $query ) $this->snapshot = $this->rows; if ( 'ROLLBACK' === $query ) $this->rows = $this->snapshot; return true; }
		if ( str_starts_with( $query[0], 'DELETE FROM' ) ) {
			if ( str_contains( $query[0], 'LIMIT 5000' ) ) { if ( $this->fail_retention ) return false; return 0; }
			if ( $this->fail_prune ) return false;
			list( $time, $same_time, $id ) = $query[1];
			$this->rows = array_values( array_filter( $this->rows, static fn( $row ) => $row['observed_at'] > $time || ( $row['observed_at'] === $same_time && $row['event_id'] >= $id ) ) );
			return 1;
		}
		++$this->insert_number;
		if ( $this->fail_insert || $this->fail_insert_number === $this->insert_number ) return false;
		$fingerprint = $query[1][12];
		foreach ( $this->rows as $row ) if ( ( $row['fingerprint'] ?? null ) === $fingerprint ) return 0;
		$this->rows[] = array( 'event_id' => $this->next_id++, 'observed_at' => $query[1][0], 'source' => $query[1][11], 'fingerprint' => $fingerprint ); return 1;
	}
}
