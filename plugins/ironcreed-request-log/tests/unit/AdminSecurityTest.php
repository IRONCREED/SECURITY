<?php
/** Administrative security regression tests. */

use Ironcreed\Request_Log\Admin\Admin_Controller;
use PHPUnit\Framework\TestCase;

final class AdminSecurityTest extends TestCase {
	public function test_clear_source_uses_a_strict_allowlist(): void {
		self::assertTrue( Admin_Controller::valid_clear_source( 'wordpress-runtime' ) );
		self::assertTrue( Admin_Controller::valid_clear_source( 'hosting-ukraine-nginx' ) );
		self::assertFalse( Admin_Controller::valid_clear_source( '' ) );
		self::assertFalse( Admin_Controller::valid_clear_source( 'all' ) );
		self::assertFalse( Admin_Controller::valid_clear_source( 'wordpress-runtime OR 1=1' ) );
	}
}
