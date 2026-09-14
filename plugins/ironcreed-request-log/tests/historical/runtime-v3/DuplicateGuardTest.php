<?php
/** Duplicate Product ID bootstrap and activation tests. */

use PHPUnit\Framework\TestCase;

final class DuplicateGuardTest extends TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_renamed_copy_rejects_normal_activation_before_active_state_change(): void {
		$this->assert_conflicting_activation_is_rejected();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_renamed_copy_rejects_network_activation_before_active_state_change(): void {
		$this->assert_conflicting_activation_is_rejected( true );
	}

	private function assert_conflicting_activation_is_rejected( bool $network_wide = false ): void {
		$GLOBALS['ironcreed_request_log_instance'] = 'ironcreed-request-log/ironcreed-request-log.php';
		include dirname( __DIR__, 3 ) . '/ironcreed-request-log.php';
		self::assertIsCallable( $GLOBALS['test_activation_callback'] );
		$this->expectException( RuntimeException::class );
		call_user_func( $GLOBALS['test_activation_callback'], $network_wide );
	}
}
