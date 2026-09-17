<?php
use Ironcreed\Request_Log\Plugin;
use PHPUnit\Framework\TestCase;

final class RuntimePrivacyTest extends TestCase {
    public function test_observer_preserves_encoded_path_and_redacts_encoded_key(): void {
        $GLOBALS['wpdb'] = $db = new Runtime_Capture_Database();
        $_SERVER['REQUEST_URI'] = '/%D1%82%D0%B5%D1%81%D1%82?%74oken=secret&page=two%20words';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $plugin = new Plugin();
        $started = new ReflectionProperty( Plugin::class, 'started' );
        $started->setAccessible( true );
        $started->setValue( $plugin, hrtime( true ) / 1e9 );
        $plugin->observe();
        self::assertSame( '/%D1%82%D0%B5%D1%81%D1%82', $db->row['path'] );
        self::assertSame( 'token=%5Bredacted%5D&page=two%20words', $db->row['query'] );
        self::assertStringNotContainsString( 'secret', json_encode( $db->row ) );
    }
    public function test_suite_page_is_excluded_from_its_own_runtime_log(): void {
        $GLOBALS['test_admin'] = true;
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=ironcreed-security';
        $_GET['page'] = 'ironcreed-security';
        $method = new ReflectionMethod( Plugin::class, 'is_plugin_request' );
        $method->setAccessible( true );
        try { self::assertTrue( $method->invoke( null ) ); }
        finally { $GLOBALS['test_admin'] = false; $_GET = []; }
    }
}
final class Runtime_Capture_Database extends wpdb {
    public array $row = [];
    public function query( $sql ) { return true; }
    public function insert( $table, $data, $formats ) { $this->row = $data; return 1; }
    public function get_row( $query, $output = null ) { return null; }
    public function get_var( $query ) { return 'SELECT DATABASE()' === $query ? 'synthetic' : 1; }
    public function prepare( $query, ...$args ) { return $query; }
}
