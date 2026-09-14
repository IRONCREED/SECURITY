<?php
use Ironcreed\Request_Log\Lifecycle;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase {
	public function test_schema_one_migrates_runtime_fingerprints_to_nullable_schema_two(): void {
		@mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
		@file_put_contents( ABSPATH . 'wp-admin/includes/upgrade.php', "<?php\n" );
		$GLOBALS['test_options']['ironcreed_request_log_schema'] = '1';
		$GLOBALS['wpdb'] = new Migration_Database();
		Lifecycle::maybe_upgrade();
		self::assertStringContainsString( 'fingerprint char(64) NULL', $GLOBALS['test_dbdelta'] );
		self::assertStringContainsString( "fingerprint = NULL WHERE source = 'wordpress-runtime'", $GLOBALS['wpdb']->queries[0] );
		self::assertSame( '2', $GLOBALS['test_options']['ironcreed_request_log_schema'] );
	}
}

final class Migration_Database extends wpdb {
	public array $queries = array();
	public function query( $sql ) { $this->queries[] = $sql; return 1; }
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { return 'InnoDB'; }
}
