<?php
/** Runtime route classification tests. */

use Ironcreed\Request_Log\Plugin;
use PHPUnit\Framework\TestCase;

final class RuntimeRouteTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['test_ajax'] = false;
		$GLOBALS['test_cron'] = false;
		$GLOBALS['test_admin'] = false;
		$_SERVER['REQUEST_URI'] = '/';
	}

	public function test_front_end_route(): void { self::assertSame( 'front-end', Plugin::classify_route() ); }
	public function test_admin_route(): void { $GLOBALS['test_admin'] = true; $_SERVER['REQUEST_URI'] = '/wp-admin/edit.php'; self::assertSame( 'admin', Plugin::classify_route() ); }
	public function test_ajax_route(): void { $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php'; self::assertSame( 'ajax', Plugin::classify_route() ); }
	public function test_cron_route(): void { $GLOBALS['test_cron'] = true; self::assertSame( 'cron', Plugin::classify_route() ); }
	public function test_login_route(): void { $_SERVER['REQUEST_URI'] = '/wp-login.php'; self::assertSame( 'login', Plugin::classify_route() ); }
	public function test_login_path_in_query_does_not_spoof_route(): void { $_SERVER['REQUEST_URI'] = '/?next=/wp-login.php'; self::assertSame( 'front-end', Plugin::classify_route() ); }
	public function test_rest_path_in_query_does_not_spoof_route(): void { $_SERVER['REQUEST_URI'] = '/?return=/wp-json/example'; self::assertSame( 'front-end', Plugin::classify_route() ); }

	public function test_forged_frontend_page_and_action_do_not_bypass_observer(): void {
		$_SERVER['REQUEST_URI'] = '/?page=ironcreed-request-log&action=ironcreed_request_log_fetch';
		$_GET['page'] = 'ironcreed-request-log';
		$_REQUEST['action'] = 'ironcreed_request_log_fetch';
		$method = new ReflectionMethod( Plugin::class, 'is_plugin_request' );
		$method->setAccessible( true );
		self::assertFalse( $method->invoke( null ) );
	}
}
