<?php
/**
 * Explicitly authorized manual and scheduled imports.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Infrastructure;

use RuntimeException;
use Throwable;

/** Coordinates downloads without holding the event-table writer lock. */
final class Provider_Import {

	public const HOOK      = 'ironcreed_request_log_import';
	public const INTERVALS = array( 60, 300, 900, 3600 );

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb Database connection for the provider-operation reservation.
	 */
	private \wpdb $database;
	/**
	 * Replaceable provider import operation.
	 *
	 * @var callable Import operation, replaceable in tests.
	 */
	private $import;

	/**
	 * Initialize the adapter dependencies.
	 *
	 * @param \wpdb         $database Database connection.
	 * @param callable|null $import   Testable import operation.
	 */
	public function __construct( \wpdb $database, ?callable $import = null ) {
		$this->database = $database;
		$this->import   = $import ?? static function ( array $credentials ) use ( $database ): int {
			$provider = new Hosting_Ukraine_Provider( new WP_HTTP_Client() );
			return ( new Event_Repository( $database ) )->import( $provider->fetch_today( $credentials['host_id'], $credentials['token'] ) );
		};
	}

	/**
	 * Read and validate the saved site-local connection.
	 *
	 * @return array Valid site-local connection or an empty array.
	 */
	public static function credentials(): array {
		$value = get_option( 'ironcreed_request_log_credentials', array() );
		if ( ! is_array( $value ) || ! is_int( $value['host_id'] ?? null ) || $value['host_id'] < 1 || ! is_string( $value['token'] ?? null ) || ! Hosting_Ukraine_Provider::valid_token( $value['token'] ) ) {
			return array();
		}
		return $value;
	}

	/**
	 * Record opt-in and schedule a future attempt, without HTTP.
	 *
	 * @param int $seconds Zero disables; otherwise an allowlisted interval.
	 * @throws RuntimeException On invalid settings or scheduling failure.
	 */
	public static function configure( int $seconds ): void {
		if ( 0 !== $seconds && ( ! in_array( $seconds, self::INTERVALS, true ) || ! self::credentials() ) ) {
			throw new RuntimeException( 'The import schedule is invalid.' );
		}
		wp_clear_scheduled_hook( self::HOOK );
		update_option( 'ironcreed_request_log_import_interval', $seconds, false );
		if ( (int) get_option( 'ironcreed_request_log_import_interval', -1 ) !== $seconds ) {
			throw new RuntimeException( 'The import consent could not be saved.' );
		}
		if ( $seconds && ! wp_schedule_single_event( time() + $seconds, self::HOOK ) ) {
			update_option( 'ironcreed_request_log_import_interval', 0, false );
			throw new RuntimeException( 'The import could not be scheduled.' );
		}
	}

	/**
	 * Re-arm an existing consent after activation; fresh sites have no consent.
	 */
	public static function restore_schedule(): void {
		$interval = (int) get_option( 'ironcreed_request_log_import_interval', 0 );
		if ( in_array( $interval, self::INTERVALS, true ) && self::credentials() && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $interval, self::HOOK );
		}
	}

	/**
	 * Run one scheduled attempt and arrange the next opportunity with bounded backoff.
	 */
	public function scheduled(): void {
		$interval = (int) get_option( 'ironcreed_request_log_import_interval', 0 );
		if ( ! in_array( $interval, self::INTERVALS, true ) || ! self::credentials() ) {
			return;
		}
		try {
			$this->run();
		} catch ( Throwable $error ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- run() persists safe failure state; cron must still arrange the retry.
			// run() records only safe status fields; never persist exception messages.
		} finally {
			self::refresh_connection_options();
			$current = (int) get_option( 'ironcreed_request_log_import_interval', 0 );
			if ( $current === $interval && self::credentials() && ! wp_next_scheduled( self::HOOK ) ) {
				$status = (array) get_option( 'ironcreed_request_log_import_status', array() );
				$delay  = min( 3600, $interval * ( 2 ** min( 6, (int) ( $status['failures'] ?? 0 ) ) ) );
				$delay  = max( $delay, min( 86400, (int) ( $status['retry_after'] ?? 0 ) ) );
				wp_schedule_single_event( time() + $delay, self::HOOK );
			}
		}
	}

	/**
	 * Import once under a separate nonblocking download reservation.
	 *
	 * @return int Number of new records.
	 * @throws RuntimeException On missing consent details, busy import or failure.
	 */
	public function run(): int {
		$credentials = self::credentials();
		if ( ! $credentials || '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			update_option(
				'ironcreed_request_log_import_status',
				array(
					'state'      => 'failed',
					'attempt_at' => time(),
					'failures'   => 1,
				),
				false
			);
			throw new RuntimeException( 'The connection or storage is unavailable.' );
		}
		$previous = (array) get_option( 'ironcreed_request_log_import_status', array() );
		if ( (int) ( $previous['attempt_at'] ?? 0 ) + (int) ( $previous['retry_after'] ?? 0 ) > time() ) {
			throw new RuntimeException( 'The provider requested a retry delay.' );
		}
		$lock = 'icrl-fetch-' . hash( 'sha256', ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . $this->database->prefix );
		$lock = substr( $lock, 0, 64 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-scoped reservation; caching would invalidate mutual exclusion.
		$held = $this->database->get_var( $this->database->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
		if ( '1' !== (string) $held ) {
			throw new RuntimeException( 'An import is already running.' );
		}
		$status               = (array) get_option( 'ironcreed_request_log_import_status', array() );
		$status['attempt_at'] = time();
		$status['state']      = 'running';
		update_option( 'ironcreed_request_log_import_status', $status, false );
		$failed         = false;
		$release_failed = false;
		$count          = 0;
		try {
			$count  = (int) call_user_func( $this->import, $credentials );
			$status = array(
				'attempt_at' => $status['attempt_at'],
				'success_at' => time(),
				'state'      => 'success',
				'count'      => $count,
				'failures'   => 0,
			);
		} catch ( Throwable $error ) {
			$status['state']       = 'failed';
			$status['failures']    = min( 6, (int) ( $status['failures'] ?? 0 ) + 1 );
			$status['retry_after'] = $error instanceof Provider_Rate_Limit ? $error->retry_after() : 0;
			$failed                = true;
		} finally {
			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the connection-scoped reservation exactly once.
				$released       = $this->database->get_var( $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
				$release_failed = '1' !== (string) $released;
			} catch ( Throwable $release_error ) {
				$release_failed = true;
			}
			if ( $release_failed && ! $failed ) {
				$status['state']    = 'failed';
				$status['failures'] = 1;
			}
			// Discard request-local cached values before observing a disconnect.
			self::refresh_connection_options();
			if ( self::credentials() === $credentials ) {
				update_option( 'ironcreed_request_log_import_status', $status, false );
			}
		}
		if ( $failed || $release_failed ) {
			throw new RuntimeException( 'The provider import failed safely.' );
		}
		return $count;
	}

	/**
	 * Re-read non-autoloaded consent and credentials after a long HTTP request.
	 */
	private static function refresh_connection_options(): void {
		wp_cache_delete( 'ironcreed_request_log_credentials', 'options' );
		wp_cache_delete( 'ironcreed_request_log_import_interval', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
