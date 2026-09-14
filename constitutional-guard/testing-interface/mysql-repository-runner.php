<?php
/** Real Event_Repository concurrency boundary for MySQL/MariaDB. */
if ( ! extension_loaded( 'pdo_mysql' ) || ! function_exists( 'pcntl_fork' ) || ! function_exists( 'posix_kill' ) || ! function_exists( 'stream_socket_pair' ) ) exit( 2 );
define( 'ARRAY_A', 'ARRAY_A' ); define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['warden_options'] = array( 'ironcreed_request_log_cap' => 100, 'ironcreed_request_log_retention' => 720 );
function get_option( $name, $default = false ) { return $GLOBALS['warden_options'][ $name ] ?? $default; }
class wpdb {
	public string $prefix; private PDO $pdo; private $on_locked; private $on_waiting;
	public function __construct( PDO $pdo, string $prefix, ?callable $on_locked = null, ?callable $on_waiting = null ) { $this->pdo = $pdo; $this->prefix = $prefix; $this->on_locked = $on_locked; $this->on_waiting = $on_waiting; }
	public function prepare( $sql, ...$args ) { if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0]; foreach ( $args as $arg ) $sql = preg_replace( '/%[sd]/', is_int( $arg ) ? (string) $arg : $this->pdo->quote( (string) $arg ), $sql, 1 ); return $sql; }
	public function query( $sql ) { try { $affected = $this->pdo->exec( $sql ); return false === $affected ? false : $affected; } catch ( Throwable $error ) { return false; } }
	public function insert( $table, $data, $formats ) { $columns = array_keys( $data ); $statement = $this->pdo->prepare( 'INSERT INTO `' . $table . '` (`' . implode( '`,`', $columns ) . '`) VALUES (' . implode( ',', array_fill( 0, count( $columns ), '?' ) ) . ')' ); return $statement->execute( array_values( $data ) ) ? 1 : false; }
	public function delete( $table, $where, $formats ) { $statement = $this->pdo->prepare( 'DELETE FROM `' . $table . '` WHERE source = ?' ); return $statement->execute( array( $where['source'] ) ) ? $statement->rowCount() : false; }
	public function get_var( $sql ) {
		$is_lock = str_contains( $sql, 'GET_LOCK(' );
		if ( $is_lock && null !== $this->on_waiting ) {
			$probe = preg_replace( '/,\s*5\s*\)/', ', 0)', $sql );
			if ( $probe === $sql || 0 !== (int) $this->pdo->query( $probe )->fetchColumn() ) throw new RuntimeException( 'Contention was not established.' );
			( $this->on_waiting )( (int) $this->pdo->query( 'SELECT CONNECTION_ID()' )->fetchColumn() );
		}
		$value = $this->pdo->query( $sql )->fetchColumn();
		if ( $is_lock && 1 === (int) $value && null !== $this->on_locked ) ( $this->on_locked )();
		return false === $value ? null : $value;
	}
	public function get_row( $sql, $output = null ) { $row = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC ); return false === $row ? null : $row; }
}
require dirname( __DIR__, 2 ) . '/plugins/ironcreed-request-log/includes/infrastructure/class-event-repository.php';
use Ironcreed\Request_Log\Infrastructure\Event_Repository;
$dsn = (string) getenv( 'IRON_WARDEN_MYSQL_DSN' ); $user = (string) getenv( 'IRON_WARDEN_MYSQL_USER' ); $password = (string) getenv( 'IRON_WARDEN_MYSQL_PASSWORD' ); if ( '' === $dsn ) exit( 2 );
$options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ); $prefix = 'icrlw_' . substr( hash( 'sha256', getmypid() . random_bytes( 16 ) ), 0, 12 ) . '_'; $table = $prefix . 'ironcreed_request_log_events'; $failure = false; $cleanup_failed = false; $children = array();
$connect = static fn(): PDO => new PDO( $dsn, $user, $password, $options );
$event = static function ( int $index, string $source ): array { return array( 'observed_at' => gmdate( 'Y-m-d H:i:s', 1700000000 + intdiv( $index, 2 ) ), 'method' => 'GET', 'path' => '/event/' . $index, 'query' => '', 'status' => 200, 'duration_ms' => 1, 'route_kind' => 'front-end', 'response_bytes' => 0, 'client_ip' => '', 'user_agent' => '', 'referer' => '', 'source' => $source, 'fingerprint' => 'wordpress-runtime' === $source ? null : hash( 'sha256', 'provider-' . $index ) ); };
try {
	$setup = $connect();
	$setup->exec( "CREATE TABLE `$table` (event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, observed_at DATETIME NOT NULL, method VARCHAR(12) NOT NULL, path VARCHAR(2048) NOT NULL, query TEXT NOT NULL, status SMALLINT UNSIGNED NOT NULL, duration_ms INT UNSIGNED NOT NULL, route_kind VARCHAR(20) NOT NULL, response_bytes BIGINT UNSIGNED NOT NULL, client_ip VARCHAR(45) NOT NULL, user_agent VARCHAR(512) NOT NULL, referer VARCHAR(2048) NOT NULL, source VARCHAR(40) NOT NULL, fingerprint CHAR(64) NULL, UNIQUE KEY source_fingerprint(source,fingerprint)) ENGINE=InnoDB" );
	$setup = null;
	$children = array();
	foreach ( array( 'wordpress-runtime', 'hosting-ukraine-nginx' ) as $offset => $source ) {
		$pid = pcntl_fork();
		if ( -1 === $pid ) throw new RuntimeException( 'fork' );
		if ( 0 === $pid ) {
			try { $repository = new Event_Repository( new wpdb( $connect(), $prefix ) ); for ( $i = 0; $i < 75; ++$i ) { $value = $event( $offset * 75 + $i, $source ); 'wordpress-runtime' === $source ? $repository->insert_runtime( $value ) : $repository->import( array( $value ) ); } exit( 0 ); } catch ( Throwable $error ) { exit( 1 ); }
		}
		$children[] = $pid;
	}
	while ( $children ) { $pid = $children[0]; $waited = pcntl_waitpid( $pid, $child ); if ( $waited === $pid ) array_shift( $children ); if ( $waited !== $pid || ! pcntl_wifexited( $child ) || 0 !== pcntl_wexitstatus( $child ) || pcntl_wifsignaled( $child ) ) throw new RuntimeException( 'child' ); }
	$verify = $connect();
	$rows = $verify->query( "SELECT event_id,observed_at,path,source,fingerprint FROM `$table` ORDER BY observed_at DESC,event_id DESC" )->fetchAll( PDO::FETCH_ASSOC );
	if ( 100 !== count( $rows ) ) throw new RuntimeException( 'count' );
	$actual = array_map( static fn( array $row ): int => (int) substr( $row['path'], 7 ), $rows ); $expected = range( 50, 149 ); $present = $actual; sort( $present );
	if ( $present !== $expected ) throw new RuntimeException( 'newest' );
	for ( $i = 1; $i < count( $rows ); ++$i ) if ( array( $rows[$i - 1]['observed_at'], (int) $rows[$i - 1]['event_id'] ) < array( $rows[$i]['observed_at'], (int) $rows[$i]['event_id'] ) ) throw new RuntimeException( 'order' );
	foreach ( $rows as $row ) { $index = (int) substr( $row['path'], 7 ); $expected_event = $event( $index, $index < 75 ? 'wordpress-runtime' : 'hosting-ukraine-nginx' ); if ( $row['observed_at'] !== $expected_event['observed_at'] || $row['source'] !== $expected_event['source'] ) throw new RuntimeException( 'event value' ); if ( 'wordpress-runtime' === $row['source'] && null !== $row['fingerprint'] ) throw new RuntimeException( 'runtime fingerprint' ); if ( 'hosting-ukraine-nginx' === $row['source'] && hash( 'sha256', 'provider-' . $index ) !== $row['fingerprint'] ) throw new RuntimeException( 'provider fingerprint' ); }
	$verify = null;
	$run_ordered = static function ( string $first_operation, int $index ) use ( $connect, $prefix, $event, $table ): bool {
		$holder = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP );
		$waiter = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP );
		$pids = array();
		$send = static function ( $socket, string $message ): void { if ( strlen( $message ) !== fwrite( $socket, $message ) || ! fflush( $socket ) ) throw new RuntimeException( 'IPC write failed.' ); };
		$receive = static function ( $socket, string $expected ): void { if ( $expected !== fread( $socket, 1 ) ) throw new RuntimeException( 'IPC acknowledgement failed.' ); };
		try {
			if ( false === $holder || false === $waiter ) throw new RuntimeException( 'IPC unavailable.' );
			foreach ( array_merge( $holder, $waiter ) as $socket ) stream_set_timeout( $socket, 10 );
			$first = pcntl_fork(); if ( -1 === $first ) throw new RuntimeException( 'fork' );
			if ( 0 === $first ) {
				try {
					$locked = static function () use ( $holder, $send, $receive ): void { $send( $holder[1], 'L' ); $receive( $holder[1], 'G' ); };
					$repository = new Event_Repository( new wpdb( $connect(), $prefix, $locked ) );
					'clear' === $first_operation ? $repository->clear_source( 'hosting-ukraine-nginx' ) : $repository->import( array( $event( $index, 'hosting-ukraine-nginx' ) ) );
					exit( 0 );
				} catch ( Throwable $error ) { exit( 1 ); }
			}
			$pids[] = $first;
			$receive( $holder[0], 'L' );
			$second = pcntl_fork(); if ( -1 === $second ) throw new RuntimeException( 'fork' );
			if ( 0 === $second ) {
				try {
					$waiting = static function ( int $connection ) use ( $waiter, $send ): void { $send( $waiter[1], 'W' . $connection . "\n" ); };
					$repository = new Event_Repository( new wpdb( $connect(), $prefix, null, $waiting ) );
					'clear' === $first_operation ? $repository->import( array( $event( $index, 'hosting-ukraine-nginx' ) ) ) : $repository->clear_source( 'hosting-ukraine-nginx' );
					exit( 0 );
				} catch ( Throwable $error ) { exit( 1 ); }
			}
			$pids[] = $second;
			$receive( $waiter[0], 'W' );
			$line = fgets( $waiter[0], 32 );
			if ( false === $line || ! preg_match( '/^[1-9][0-9]*\n$/', $line ) ) throw new RuntimeException( 'IPC connection acknowledgement failed.' );
			// Created after both forks. The barrier stays closed until the server itself
			// reports writer two waiting in the production GET_LOCK query.
			$observer = $connect();
			$statement = $observer->prepare( 'SELECT STATE, INFO FROM information_schema.PROCESSLIST WHERE ID = ?' );
			$deadline = microtime( true ) + 3; $blocked = false;
			do {
				$statement->execute( array( (int) trim( $line ) ) ); $process = $statement->fetch( PDO::FETCH_ASSOC );
				$blocked = $process && in_array( strtolower( (string) $process['STATE'] ), array( 'user lock', 'waiting for user lock' ), true ) && str_contains( (string) $process['INFO'], 'GET_LOCK(' );
				if ( ! $blocked ) usleep( 1000 );
			} while ( ! $blocked && microtime( true ) < $deadline );
			$statement = null; $observer = null;
			if ( ! $blocked ) throw new RuntimeException( 'Writer lock wait was not observed.' );
			$send( $holder[0], 'G' );
			while ( $pids ) {
				$pid = $pids[0]; $waited = pcntl_waitpid( $pid, $child );
				if ( $waited === $pid ) array_shift( $pids );
				if ( $waited !== $pid || ! pcntl_wifexited( $child ) || pcntl_wifsignaled( $child ) || 0 !== pcntl_wexitstatus( $child ) ) throw new RuntimeException( 'child' );
			}
			$check = $connect(); $paths = $check->query( "SELECT path FROM `$table` WHERE source='hosting-ukraine-nginx' ORDER BY event_id" )->fetchAll( PDO::FETCH_COLUMN ); $check = null;
			return 'clear' === $first_operation ? array( '/event/' . $index ) === $paths : array() === $paths;
		} finally {
			foreach ( $pids as $pid ) { posix_kill( $pid, SIGTERM ); pcntl_waitpid( $pid, $unused ); }
			foreach ( array_merge( $holder ?: array(), $waiter ?: array() ) as $socket ) if ( is_resource( $socket ) ) fclose( $socket );
		}
	};
	if ( ! $run_ordered( 'clear', 200 ) || ! $run_ordered( 'import', 201 ) ) throw new RuntimeException( 'clear import serialization' );
} catch ( Throwable $error ) { $failure = true; }
finally {
	foreach ( $children as $pid ) { posix_kill( $pid, SIGTERM ); pcntl_waitpid( $pid, $unused ); }
	try { $cleanup = $connect(); $cleanup->exec( "DROP TABLE IF EXISTS `$table`" ); $cleanup = null; } catch ( Throwable $error ) { $cleanup_failed = true; }
}
if ( $failure ) fwrite( STDERR, "Repository concurrency verification failed safely.\n" );
if ( $cleanup_failed ) fwrite( STDERR, "Repository concurrency cleanup failed safely.\n" );
exit( $failure || $cleanup_failed ? 1 : 0 );
