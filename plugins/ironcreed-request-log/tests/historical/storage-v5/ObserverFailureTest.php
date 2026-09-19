<?php
use Ironcreed\Request_Log\Plugin;
use PHPUnit\Framework\TestCase;

final class ObserverFailureTest extends TestCase {
	public function test_storage_failure_does_not_escape_shutdown_observer(): void {
		$GLOBALS['wpdb'] = new Observer_Failing_Database();
		$_SERVER['REQUEST_URI'] = '/synthetic';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$plugin = new Plugin();
		$started = new ReflectionProperty( Plugin::class, 'started' );
		$started->setAccessible( true );
		$started->setValue( $plugin, hrtime( true ) / 1e9 );
		$plugin->observe();
		self::assertSame( 'storage-failure', $GLOBALS['test_options']['ironcreed_request_log_runtime_diagnostic'] );
	}
}

final class Observer_Failing_Database extends wpdb {
	public function query( $sql ) { return true; }
	public function insert( $table, $data, $formats ) { return false; }
	public function get_row( $query, $output = null ) { return null; }
	public function get_var( $query ) { return 'SELECT DATABASE()' === $query ? 'synthetic' : 1; }
	public function prepare( $query, ...$args ) { return $query; }
}
