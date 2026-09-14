<?php
/** @group wordpress-current */
final class WordPressLifecycleTest extends WP_UnitTestCase {
	public function test_plugin_bootstraps_in_wordpress(): void { self::assertTrue( defined( 'IRONCREED_REQUEST_LOG_PRODUCT_ID' ) ); }
}
/** @group multisite-current */
final class MultisiteLifecycleTest extends WP_UnitTestCase {
	public function test_multisite_environment_is_real(): void { self::assertTrue( is_multisite() ); }
}
