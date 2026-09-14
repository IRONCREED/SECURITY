<?php
/** Event persistence adapter. @package Ironcreed_Request_Log */

namespace Ironcreed\Request_Log\Infrastructure;

use RuntimeException;

/** Stores, queries, deduplicates, and bounds request events. */
final class Event_Repository {
	private const IMPORT_BATCH = 250;
	private \wpdb $database;
	private string $table;

	/** @param \wpdb $database WordPress database connection. */
	public function __construct( \wpdb $database ) {
		$this->database = $database;
		$this->table    = $database->prefix . 'ironcreed_request_log_events';
	}

	/** Insert one Runtime event; NULL fingerprints never deduplicate. */
	public function insert_runtime( array $event ): bool {
		$event['fingerprint'] = null;
		$this->with_write_lock( function () use ( $event ): void {
			$this->begin();
			try {
			if ( false === $this->database->insert( $this->table, $event, $this->formats() ) ) {
				throw new RuntimeException( 'The request event could not be stored.' );
			}
			$this->enforce_cap();
			$this->commit();
			} catch ( \Throwable $error ) {
				$this->rollback_preserving( $error );
			}
		} );
		return true;
	}

	/** Normalize outside transactions and commit provider rows in bounded batches. */
	public function import( iterable $events ): int {
		$inserted = 0;
		$batch    = array();
		foreach ( $events as $event ) {
			$batch[] = $event;
			if ( self::IMPORT_BATCH === count( $batch ) ) {
				$inserted += $this->insert_provider_batch( $batch );
				$batch     = array();
			}
		}
		if ( $batch ) {
			$inserted += $this->insert_provider_batch( $batch );
		}
		return $inserted;
	}

	/** Return a prepared, bounded page and matching count. */
	public function page( string $source, int $page, int $per_page, array $filters = array() ): array {
		list( $where, $arguments ) = $this->where( $source, $filters );
		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where}";
		$total     = (int) $this->database->get_var( $this->database->prepare( $count_sql, $arguments ) );
		$query_sql  = "SELECT * FROM {$this->table} {$where} ORDER BY observed_at DESC, event_id DESC LIMIT %d OFFSET %d";
		$query_args = array_merge( $arguments, array( $per_page, ( $page - 1 ) * $per_page ) );
		$items      = $this->database->get_results( $this->database->prepare( $query_sql, $query_args ), ARRAY_A );
		return array( 'items' => $items, 'total' => $total );
	}

	/** Delete one explicitly selected source. */
	public function clear_source( string $source ): int {
		$deleted = 0;
		$this->with_write_lock( function () use ( $source, &$deleted ): void {
			$this->begin();
			try {
				$result = $this->database->delete( $this->table, array( 'source' => $source ), array( '%s' ) );
				if ( false === $result ) throw new RuntimeException( 'The selected request log could not be cleared.' );
				$deleted = (int) $result;
				$this->commit();
			} catch ( \Throwable $error ) {
				$this->rollback_preserving( $error );
			}
		} );
		return $deleted;
	}

	/** Delete expired rows in a bounded operation. */
	public function cleanup(): void {
		$this->with_write_lock( function (): void {
			$this->begin();
			try {
				$hours = max( 1, min( 720, (int) get_option( 'ironcreed_request_log_retention', 24 ) ) );
				$sql   = "DELETE FROM {$this->table} WHERE observed_at < %s LIMIT 5000";
				if ( false === $this->database->query( $this->database->prepare( $sql, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * $hours ) ) ) ) throw new RuntimeException( 'Expired request events could not be removed.' );
				$this->enforce_cap();
				$this->commit();
			} catch ( \Throwable $error ) {
				$this->rollback_preserving( $error );
			}
		} );
	}

	/** Insert one prepared provider batch atomically. */
	private function insert_provider_batch( array $batch ): int {
		$inserted = 0;
		$this->with_write_lock( function () use ( $batch, &$inserted ): void {
			$this->begin();
			try {
			foreach ( $batch as $event ) {
				$fingerprint = (string) ( $event['fingerprint'] ?? '' );
				if ( '' === $fingerprint ) {
					throw new RuntimeException( 'A provider event fingerprint is missing.' );
				}
				$columns      = array_keys( $event );
				$placeholders = $this->formats();
				$sql = "INSERT INTO {$this->table} (`" . implode( '`,`', $columns ) . "`) VALUES (" . implode( ',', $placeholders ) . ') ON DUPLICATE KEY UPDATE event_id = event_id';
				$result = $this->database->query( $this->database->prepare( $sql, array_values( $event ) ) );
				if ( false === $result ) {
					throw new RuntimeException( 'A provider event batch could not be stored.' );
				}
				$inserted += 1 === $result ? 1 : 0;
			}
			$this->enforce_cap();
			$this->commit();
			} catch ( \Throwable $error ) {
				$this->rollback_preserving( $error );
			}
		} );
		return $inserted;
	}

	/** Build an allowlisted filter clause. */
	private function where( string $source, array $filters ): array {
		$where = 'WHERE source = %s';
		$args  = array( $source );
		if ( ! empty( $filters['path'] ) ) {
			$where .= ' AND path LIKE %s';
			$args[] = '%' . $this->database->esc_like( $filters['path'] ) . '%';
		}
		if ( ! empty( $filters['method'] ) ) {
			$where .= ' AND method = %s';
			$args[] = $filters['method'];
		}
		if ( ! empty( $filters['status_class'] ) ) {
			$minimum = (int) $filters['status_class'] * 100;
			$where .= ' AND status BETWEEN %d AND %d';
			$args[] = $minimum;
			$args[] = $minimum + 99;
		}
		return array( $where, $args );
	}

	/** Delete every row older than the newest configured cap. */
	private function enforce_cap(): void {
		$cap      = max( 100, min( 100000, (int) get_option( 'ironcreed_request_log_cap', 10000 ) ) );
		$boundary = $this->database->get_row( $this->database->prepare( "SELECT observed_at, event_id FROM {$this->table} ORDER BY observed_at DESC, event_id DESC LIMIT 1 OFFSET %d", $cap - 1 ), ARRAY_A );
		if ( is_array( $boundary ) ) {
			$sql = "DELETE FROM {$this->table} WHERE observed_at < %s OR (observed_at = %s AND event_id < %d)";
			$deleted = $this->database->query( $this->database->prepare( $sql, $boundary['observed_at'], $boundary['observed_at'], (int) $boundary['event_id'] ) );
			if ( false === $deleted ) {
				throw new RuntimeException( 'The request log hard cap could not be enforced.' );
			}
		}
	}

	private function begin(): void {
		if ( false === $this->database->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'The request log transaction could not start.' );
	}

	private function commit(): void {
		if ( false === $this->database->query( 'COMMIT' ) ) throw new RuntimeException( 'The request log transaction could not commit.' );
	}

	private function rollback(): void {
		if ( false === $this->database->query( 'ROLLBACK' ) ) throw new RuntimeException( 'The request log transaction could not roll back.' );
	}

	private function rollback_preserving( \Throwable $original ): never {
		try { $this->rollback(); } catch ( \Throwable $rollback_error ) { /* Preserve the operation failure. */ }
		throw $original;
	}

	private function with_write_lock( callable $operation ): void {
		$database = $this->database->get_var( 'SELECT DATABASE()' );
		if ( ! is_string( $database ) || '' === $database ) throw new RuntimeException( 'The request log database identity is unavailable.' );
		$name = 'icrl_' . substr( hash( 'sha256', $database . "\0" . $this->table . "\0ironcreed/request-log" ), 0, 59 );
		$acquired = $this->database->get_var( $this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) );
		if ( '1' !== (string) $acquired ) throw new RuntimeException( 'The request log write lock is unavailable.' );
		$failure = null;
		try {
			$operation();
		} catch ( \Throwable $error ) {
			$failure = $error;
		}
		try {
			$released = $this->database->get_var( $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		} catch ( \Throwable $release_error ) {
			if ( null !== $failure ) throw $failure;
			throw new RuntimeException( 'The request log write lock could not be released.' );
		}
		if ( null !== $failure ) throw $failure;
		if ( '1' !== (string) $released ) throw new RuntimeException( 'The request log write lock could not be released.' );
	}

	/** Return event-column placeholders in stable schema order. */
	private function formats(): array {
		return array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );
	}
}
