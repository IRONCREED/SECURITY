<?php
use PHPUnit\Framework\TestCase;

final class WardenIntegrityTest extends TestCase {
	public static function setUpBeforeClass(): void {
		define( 'IRON_WARDEN_LIBRARY', true );
		require_once dirname( __DIR__, 5 ) . '/constitutional-guard/run.php';
		require_once dirname( __DIR__, 5 ) . '/constitutional-guard/testing-interface/phpunit-result.php';
	}

	public function test_phpunit_result_requires_tests_assertions_and_success(): void {
		self::assertTrue( iron_warden_phpunit_passed( 0, 'OK (1 test, 1 assertion)' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'OK (1 test, 0 assertions)' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'OK (0 tests, 0 assertions)' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'Warning: no tests executed' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'malformed' ) );
		self::assertFalse( iron_warden_phpunit_passed( 1, 'OK (1 test, 1 assertion)' ) );
	}

	public function test_integrity_failure_refuses_before_historical_execution(): void {
		$root = sys_get_temp_dir() . '/warden-integrity-' . getmypid();
		@mkdir( $root . '/constitutional-guard/history/prebuild', 0777, true );
		@mkdir( $root . '/constitutional-guard/history/postbuild', 0777, true );
		@mkdir( $root . '/constitutional-guard/testing-interface', 0777, true );
		@mkdir( $root . '/plugins/ironcreed-request-log', 0777, true );
		$marker = $root . '/executed';
		$file = $root . '/constitutional-guard/history/prebuild/rejected.php';
		file_put_contents( $file, '<?php file_put_contents(' . var_export( $marker, true ) . ", 'yes');" );
		file_put_contents( $root . '/constitutional-guard/history/postbuild/rejected.php', '<?php' );
		file_put_contents( $root . '/constitutional-guard/testing-interface/driver.php', '<?php' );
		$manifest = array( 'tests' => array( array( 'id' => 'broken', 'path' => 'history/prebuild/rejected.php', 'phase' => 'prebuild', 'sha256' => str_repeat( '0', 64 ), 'status' => 'active' ) ) );
		file_put_contents( $root . '/constitutional-guard/history/manifest.json', json_encode( $manifest ) );
		file_put_contents( $root . '/plugins/ironcreed-request-log/composer.lock', '{}' );
		$runner = new Iron_Warden_Runner();
		$this->set_property( $runner, 'root', $root );
		$this->set_property( $runner, 'plugin', $root . '/plugins/ironcreed-request-log' );
		self::assertSame( 1, $runner->run( 'all' ) );
		self::assertFileDoesNotExist( $marker );
	}

	public function test_historical_command_contains_only_active_manifest_entries(): void {
		$runner = new Iron_Warden_Runner();
		$this->set_property( $runner, 'manifest', array( 'tests' => array(
			array( 'path' => 'history/prebuild/active.php', 'phase' => 'prebuild', 'status' => 'active' ),
			array( 'path' => 'history/prebuild/old.php', 'phase' => 'prebuild', 'status' => 'superseded' ),
		) ) );
		$method = new ReflectionMethod( $runner, 'historical_command' );
		$method->setAccessible( true );
		$command = $method->invoke( $runner, 'prebuild' );
		self::assertStringContainsString( 'active.php', $command );
		self::assertStringNotContainsString( 'old.php', $command );
	}

	public function test_arbitrary_legacy_command_cannot_pass_typed_runner(): void {
		putenv( 'IRON_WARDEN_WORDPRESS_TEST_COMMAND=true' );
		$runner = dirname( __DIR__, 5 ) . '/constitutional-guard/testing-interface/integration-runner.php';
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $runner ) . ' wordpress-current', $output, $status );
		putenv( 'IRON_WARDEN_WORDPRESS_TEST_COMMAND' );
		self::assertSame( 2, $status );
	}

	public function test_dependency_symlink_escape_is_rejected(): void {
		$root = sys_get_temp_dir() . '/warden-symlink-' . getmypid(); @mkdir( $root . '/plugins/ironcreed-request-log/tests', 0777, true ); file_put_contents( $root . '/outside.php', '<?php' ); symlink( $root . '/outside.php', $root . '/plugins/ironcreed-request-log/tests/escape.php' );
		$runner = new Iron_Warden_Runner(); $this->set_property( $runner, 'root', $root ); $this->set_property( $runner, 'plugin', $root . '/plugins/ironcreed-request-log' );
		$method = new ReflectionMethod( $runner, 'trusted_repository_file' ); $method->setAccessible( true ); $this->expectException( RuntimeException::class ); $method->invoke( $runner, 'plugins/ironcreed-request-log/tests/escape.php' );
	}

	public function test_failed_build_discards_stale_zip(): void {
		$root = sys_get_temp_dir() . '/warden-stale-' . getmypid();
		@mkdir( $root . '/plugins/ironcreed-request-log/tools', 0777, true ); @mkdir( $root . '/build', 0777, true );
		file_put_contents( $root . '/.gitignore', "build/\n" ); file_put_contents( $root . '/plugins/ironcreed-request-log/tools/build.sh', "#!/bin/sh\nexit 7\n" ); file_put_contents( $root . '/build/ironcreed-request-log-1.0.0.zip', 'stale' );
		exec( 'git -C ' . escapeshellarg( $root ) . ' init -q && git -C ' . escapeshellarg( $root ) . ' config user.email warden@example.test && git -C ' . escapeshellarg( $root ) . ' config user.name Warden && git -C ' . escapeshellarg( $root ) . ' add . && git -C ' . escapeshellarg( $root ) . ' commit -qm fixture' );
		$runner = new Iron_Warden_Runner(); $this->set_property( $runner, 'root', $root ); $this->set_property( $runner, 'plugin', $root . '/plugins/ironcreed-request-log' );
		$method = new ReflectionMethod( $runner, 'build' ); $method->setAccessible( true ); self::assertFalse( $method->invoke( $runner ) ); self::assertFileDoesNotExist( $root . '/build/ironcreed-request-log-1.0.0.zip' );
		$zip = new ReflectionProperty( $runner, 'zip' ); $zip->setAccessible( true ); self::assertNull( $zip->getValue( $runner ) );
		$results = new ReflectionProperty( $runner, 'results' ); $results->setAccessible( true ); self::assertNotContains( 'postbuild', array_column( $results->getValue( $runner ), 'phase' ) );
	}

	private function set_property( object $object, string $name, mixed $value ): void {
		$property = new ReflectionProperty( $object, $name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}
}
