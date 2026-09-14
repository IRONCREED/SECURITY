<?php
use PHPUnit\Framework\TestCase;

final class WardenIntegrityTest extends TestCase {
	public static function setUpBeforeClass(): void {
		define( 'IRON_WARDEN_LIBRARY', true );
		require_once dirname( __DIR__, 5 ) . '/constitutional-guard/run.php';
		require_once dirname( __DIR__, 5 ) . '/constitutional-guard/testing-interface/phpunit-result.php';
		require_once dirname( __DIR__, 5 ) . '/constitutional-guard/testing-interface/process.php';
	}

	public function test_phpunit_result_requires_tests_assertions_and_success(): void {
		self::assertTrue( iron_warden_phpunit_passed( 0, "PHPUnit 9.6\n.\n\nOK (1 test, 1 assertion)\n" ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'OK (1 test, 0 assertions)' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'OK (0 tests, 0 assertions)' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, '' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'No tests executed!' ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, "OK (1 test, 1 assertion)\nFAILURES!\nTests: 1, Assertions: 1, Failures: 1." ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, "OK (1 test, 1 assertion)\nOK (2 tests, 2 assertions)" ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, "test output says OK (1 test, 1 assertion) but continues" ) );
		self::assertFalse( iron_warden_phpunit_passed( 0, 'malformed' ) );
		self::assertFalse( iron_warden_phpunit_passed( 1, 'OK (1 test, 1 assertion)' ) );
	}

	public function test_process_capture_drains_stdout_and_stderr_concurrently(): void {
		$script = '$chunk=str_repeat("o",8192);$error=str_repeat("e",8192);for($i=0;$i<40;$i++){fwrite(STDOUT,$chunk);fwrite(STDERR,$error);}exit(7);';
		list( $status, $stdout, $stderr ) = iron_warden_capture( array( PHP_BINARY, '-r', $script ) );
		self::assertSame( 7, $status );
		self::assertSame( 327680, strlen( $stdout ) );
		self::assertSame( 327680, strlen( $stderr ) );
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

	public function test_parent_directory_symlink_escape_is_rejected(): void {
		$root = sys_get_temp_dir() . '/warden-parent-symlink-' . getmypid(); @mkdir( $root . '/plugins/ironcreed-request-log/tests', 0777, true ); @mkdir( $root . '/outside', 0777, true ); file_put_contents( $root . '/outside/escape.php', '<?php' ); symlink( $root . '/outside', $root . '/plugins/ironcreed-request-log/tests/link' );
		try {
			$runner = new Iron_Warden_Runner(); $this->set_property( $runner, 'root', $root ); $this->set_property( $runner, 'plugin', $root . '/plugins/ironcreed-request-log' ); $method = new ReflectionMethod( $runner, 'trusted_repository_file' ); $method->setAccessible( true );
			try { $method->invoke( $runner, 'plugins/ironcreed-request-log/tests/link/escape.php' ); self::fail( 'Expected parent symlink refusal.' ); } catch ( ReflectionException $error ) { throw $error; } catch ( RuntimeException $error ) { self::assertStringContainsString( 'symbolic link', $error->getMessage() ); }
		} finally { $this->remove_fixture( $root ); }
	}

	public function test_manifest_contract_rejects_single_condition_mutations(): void {
		$mutations = array(
			'wrong schema' => static function ( array &$manifest ): void { $manifest['schemaVersion'] = '2.0.0'; },
			'wrong scope' => static function ( array &$manifest ): void { $manifest['scope'] = 'plugins/other'; },
			'missing field' => static function ( array &$manifest ): void { unset( $manifest['tests'][0]['id'] ); },
			'malformed id' => static function ( array &$manifest ): void { $manifest['tests'][0]['id'] = 'bad'; },
			'malformed phase' => static function ( array &$manifest ): void { $manifest['tests'][0]['phase'] = 'other'; },
			'malformed status' => static function ( array &$manifest ): void { $manifest['tests'][0]['status'] = 'unknown'; },
			'malformed hash' => static function ( array &$manifest ): void { $manifest['tests'][0]['sha256'] = 'bad'; },
			'duplicate id' => static function ( array &$manifest ): void { $copy = $manifest['tests'][0]; $copy['path'] = 'history/prebuild/second.php'; $manifest['tests'][] = $copy; },
			'duplicate path' => static function ( array &$manifest ): void { $copy = $manifest['tests'][0]; $copy['id'] = 'ics-warden-second-001'; $manifest['tests'][] = $copy; },
			'phase mismatch' => static function ( array &$manifest ): void { $manifest['tests'][0]['phase'] = 'postbuild'; },
			'traversal' => static function ( array &$manifest ): void { $manifest['tests'][0]['path'] = 'history/prebuild/../postbuild/active.php'; },
			'missing historical file' => static function ( array &$manifest ): void { $manifest['tests'][0]['path'] = 'history/prebuild/missing.php'; },
			'hash mismatch' => static function ( array &$manifest ): void { $manifest['tests'][0]['sha256'] = str_repeat( '0', 64 ); },
			'unregistered historical file' => static function ( array &$manifest, string $root ): void { file_put_contents( $root . '/constitutional-guard/history/prebuild/unregistered.php', '<?php' ); },
			'empty phase' => static function ( array &$manifest ): void { $manifest['tests'][1]['status'] = 'superseded'; $manifest['tests'][1]['supersededBy'] = $manifest['tests'][0]['id']; },
			'missing successor' => static function ( array &$manifest ): void { $manifest['tests'][0]['status'] = 'superseded'; },
			'unknown successor' => static function ( array &$manifest ): void { $manifest['tests'][0]['status'] = 'superseded'; $manifest['tests'][0]['supersededBy'] = 'ics-warden-unknown-001'; },
			'cross-phase successor' => static function ( array &$manifest ): void { $manifest['tests'][0]['status'] = 'superseded'; $manifest['tests'][0]['supersededBy'] = $manifest['tests'][1]['id']; },
			'malformed dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'] = array( array( 'path' => 'missing.php' ) ); },
			'duplicate dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][] = $manifest['tests'][0]['dependencies'][0]; },
			'dependency hash mismatch' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][0]['sha256'] = str_repeat( '0', 64 ); },
			'missing dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][0]['path'] = 'plugins/ironcreed-request-log/tests/missing.php'; },
			'duplicate interface' => static function ( array &$manifest ): void { $manifest['testingInterface'][] = $manifest['testingInterface'][0]; },
			'interface hash mismatch' => static function ( array &$manifest ): void { $manifest['testingInterface'][0]['sha256'] = str_repeat( '0', 64 ); },
		);
		foreach ( $mutations as $label => $mutation ) {
			list( $root, $manifest ) = $this->valid_fixture( $label );
			try {
				if ( 'duplicate id' === $label ) file_put_contents( $root . '/constitutional-guard/history/prebuild/second.php', '<?php' );
				$mutation( $manifest, $root ); file_put_contents( $root . '/constitutional-guard/history/manifest.json', json_encode( $manifest ) );
				$runner = new Iron_Warden_Runner(); $this->set_property( $runner, 'root', $root ); $this->set_property( $runner, 'plugin', $root . '/plugins/ironcreed-request-log' ); $runner->run( 'integrity' );
				$results = new ReflectionProperty( $runner, 'results' ); $results->setAccessible( true ); self::assertSame( 'failed', $results->getValue( $runner )[0]['status'], $label );
			} finally {
				$this->remove_fixture( $root );
			}
		}
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

	private function valid_fixture( string $label ): array {
		$root = sys_get_temp_dir() . '/warden-fixture-' . preg_replace( '/[^a-z]+/', '-', $label ) . '-' . getmypid();
		@mkdir( $root . '/constitutional-guard/history/prebuild', 0777, true ); @mkdir( $root . '/constitutional-guard/history/postbuild', 0777, true ); @mkdir( $root . '/constitutional-guard/testing-interface', 0777, true ); @mkdir( $root . '/plugins/ironcreed-request-log/tests', 0777, true );
		foreach ( array( 'prebuild/active.php', 'postbuild/active.php' ) as $path ) file_put_contents( $root . '/constitutional-guard/history/' . $path, '<?php' );
		file_put_contents( $root . '/constitutional-guard/testing-interface/driver.php', '<?php' );
		file_put_contents( $root . '/plugins/ironcreed-request-log/tests/dependency.php', '<?php' );
		$tests = array(); foreach ( array( 'prebuild', 'postbuild' ) as $phase ) { $path = 'history/' . $phase . '/active.php'; $tests[] = array( 'id' => 'ics-warden-' . $phase . '-001', 'path' => $path, 'phase' => $phase, 'sha256' => hash_file( 'sha256', $root . '/constitutional-guard/' . $path ), 'status' => 'active' ); }
		$tests[0]['dependencies'] = array( array( 'path' => 'plugins/ironcreed-request-log/tests/dependency.php', 'sha256' => hash_file( 'sha256', $root . '/plugins/ironcreed-request-log/tests/dependency.php' ) ) );
		$manifest = array( 'schemaVersion' => '1.0.0', 'scope' => 'plugins/ironcreed-request-log', 'tests' => $tests, 'testingInterface' => array( array( 'path' => 'testing-interface/driver.php', 'sha256' => hash_file( 'sha256', $root . '/constitutional-guard/testing-interface/driver.php' ) ) ) );
		return array( $root, $manifest );
	}

	private function remove_fixture( string $root ): void {
		if ( ! is_dir( $root ) ) return; $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $file ) $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); rmdir( $root );
	}
}
