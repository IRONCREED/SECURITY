<?php
use Ironcreed\Request_Log\Lifecycle;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase {
	protected function setUp(): void { $GLOBALS['test_options'] = array(); $GLOBALS['test_update_fail'] = ''; }
	public function test_schema_one_migrates_runtime_fingerprints_to_nullable_schema_two(): void {
		$GLOBALS['test_options']['ironcreed_request_log_schema'] = '1';
		$GLOBALS['wpdb'] = new Migration_Database();
		Lifecycle::maybe_upgrade();
		self::assertStringContainsString( 'fingerprint char(64) NULL', $GLOBALS['test_dbdelta'] );
		self::assertTrue( (bool) array_filter( $GLOBALS['wpdb']->queries, static fn( string $query ): bool => str_contains( $query, "fingerprint = NULL WHERE source = 'wordpress-runtime'" ) ) );
		self::assertSame( '2', $GLOBALS['test_options']['ironcreed_request_log_schema'] );
	}

	public function test_myisam_conversion_and_safe_failures(): void {
		$db = new Migration_Database(); $db->engines = array( 'MyISAM', 'InnoDB' ); $GLOBALS['wpdb'] = $db; self::assertTrue( Lifecycle::maybe_upgrade() ); self::assertStringContainsString( 'ENGINE=InnoDB', implode( '\n', $db->queries ) );
		$GLOBALS['test_options'] = array(); $db = new Migration_Database(); $db->engines = array( 'MyISAM' ); $db->failure = 'ENGINE=InnoDB'; $GLOBALS['wpdb'] = $db; self::assertFalse( Lifecycle::maybe_upgrade() ); self::assertSame( 'engine-conversion-failed', $GLOBALS['test_options']['ironcreed_request_log_storage_diagnostic'] );
		foreach ( array( 'fingerprint' => 'fingerprint-schema-failed', 'runtime' => 'fingerprint-migration-failed' ) as $failure => $diagnostic ) { $GLOBALS['test_options'] = array(); $db = new Migration_Database(); $db->failure = $failure; $GLOBALS['wpdb'] = $db; self::assertFalse( Lifecycle::maybe_upgrade() ); self::assertSame( $diagnostic, $GLOBALS['test_options']['ironcreed_request_log_storage_diagnostic'] ); }
		foreach ( array( 'ironcreed_request_log_schema' => 'schema-version-write-failed', 'ironcreed_request_log_storage_ready' => 'readiness-write-failed' ) as $option => $diagnostic ) { $GLOBALS['test_options'] = array(); $GLOBALS['test_update_fail'] = $option; $GLOBALS['wpdb'] = new Migration_Database(); self::assertFalse( Lifecycle::maybe_upgrade() ); self::assertSame( $diagnostic, $GLOBALS['test_options']['ironcreed_request_log_storage_diagnostic'] ); }
	}

}

final class Migration_Database extends wpdb {
	public array $queries = array();
	public array $engines = array( 'InnoDB' );
	public string $failure = '';
	public function query( $sql ) { $this->queries[] = $sql; if ( 'ENGINE=InnoDB' === $this->failure && str_contains( $sql, 'ENGINE=InnoDB' ) ) return false; if ( 'fingerprint' === $this->failure && str_contains( $sql, 'MODIFY fingerprint' ) ) return false; if ( 'runtime' === $this->failure && str_contains( $sql, "source = 'wordpress-runtime'" ) ) return false; return 1; }
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { if ( str_contains( is_array( $sql ) ? $sql[0] : $sql, 'IS_NULLABLE' ) ) return 'YES'; return array_shift( $this->engines ) ?? 'InnoDB'; }
}
