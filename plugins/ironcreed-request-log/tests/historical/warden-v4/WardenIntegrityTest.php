<?php
use PHPUnit\Framework\TestCase;

final class WardenIntegrityTest extends TestCase {
	private static function repository_root(): string { return dirname( __DIR__, 5 ); }
	public static function setUpBeforeClass(): void {
		defined( 'IRON_WARDEN_LIBRARY' ) || define( 'IRON_WARDEN_LIBRARY', true );
		require_once self::repository_root() . '/constitutional-guard/run.php';
		require_once self::repository_root() . '/constitutional-guard/testing-interface/phpunit-result.php';
		require_once self::repository_root() . '/constitutional-guard/testing-interface/process.php';
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


	public function test_process_capture_preserves_environment(): void {
		$old = getenv( 'WARDEN_CAPTURE_FIXTURE' ); putenv( 'WARDEN_CAPTURE_FIXTURE=synthetic' );
		try { list( $status, $output ) = iron_warden_capture( array( PHP_BINARY, '-r', 'echo getenv("WARDEN_CAPTURE_FIXTURE");' ) ); self::assertSame( 0, $status ); self::assertSame( 'synthetic', $output ); }
		finally { false === $old ? putenv( 'WARDEN_CAPTURE_FIXTURE' ) : putenv( 'WARDEN_CAPTURE_FIXTURE=' . $old ); }
	}

	public function test_integrity_hash_failure_prevents_every_later_phase(): void {
		list( $root, $manifest ) = $this->valid_fixture();
		try {
			$this->assert_valid_manifest( $root );
			$manifest['tests'][0]['sha256'] = str_repeat( '0', 64 ); $this->write_manifest( $root, $manifest );
			list( $status, $report ) = $this->run_fixture( $root, 'all' );
			self::assertSame( 1, $status ); self::assertSame( 'failed', $report['results'][0]['status'] );
			self::assertStringContainsString( 'Historical test integrity failed:', $report['results'][0]['detail'] );
			self::assertSame( array( 'integrity' ), array_values( array_unique( array_column( $report['results'], 'phase' ) ) ) );
			foreach ( array( 'current', 'active', 'superseded', 'build', 'postbuild' ) as $marker ) self::assertFileDoesNotExist( $root . '/build/' . $marker . '.marker' );
		} finally { $this->remove_fixture( $root ); }
	}

	public function test_real_prebuild_executes_only_active_historical_entries(): void {
		list( $root ) = $this->valid_fixture();
		try {
			$this->assert_valid_manifest( $root );
			list( $status, $report ) = $this->run_fixture( $root, 'prebuild' );
			self::assertSame( 1, $status ); self::assertFileExists( $root . '/build/current.marker' ); self::assertFileExists( $root . '/build/active.marker' ); self::assertFileDoesNotExist( $root . '/build/superseded.marker' );
			self::assertSame( 'passed', $this->gate_status( $report, 'historical prebuild corpus' ) );
		} finally { $this->remove_fixture( $root ); }
	}

	public function test_unavailable_prebuild_stops_all_before_build(): void {
		list( $root ) = $this->valid_fixture();
		try {
			$this->assert_valid_manifest( $root ); list( $status, $report ) = $this->run_fixture( $root, 'all' );
			self::assertSame( 1, $status ); self::assertSame( 'unavailable', $this->gate_status( $report, 'PHPUnit current tests' ) );
			self::assertFileExists( $root . '/build/active.marker' ); self::assertFileDoesNotExist( $root . '/build/build.marker' ); self::assertFileDoesNotExist( $root . '/build/postbuild.marker' );
			self::assertNotContains( 'build', array_column( $report['results'], 'phase' ) );
		} finally { $this->remove_fixture( $root ); }
	}

	public function test_failed_prebuild_stops_all_before_build(): void {
		list( $root ) = $this->valid_fixture();
		try {
			$this->assert_valid_manifest( $root ); file_put_contents( $root . '/scripts/validate-repository.py', "raise SystemExit(7)\n" );
			list( $status, $report ) = $this->run_fixture( $root, 'all' ); self::assertSame( 1, $status );
			self::assertSame( 'failed', $this->gate_status( $report, 'repository validation' ) );
			self::assertFileDoesNotExist( $root . '/build/build.marker' ); self::assertFileDoesNotExist( $root . '/build/postbuild.marker' );
		} finally { $this->remove_fixture( $root ); }
	}

	public function test_failed_build_discards_stale_zip(): void {
		list( $root ) = $this->valid_fixture();
		try {
			$this->assert_valid_manifest( $root ); $zip = $root . '/build/ironcreed-request-log-1.0.0.zip'; file_put_contents( $zip, 'stale' );
			list( $status, $report ) = $this->run_fixture( $root, 'postbuild' );
			self::assertSame( 1, $status ); self::assertSame( 'passed', $this->gate_status( $report, 'clean source tree' ) ); self::assertSame( 'failed', $this->gate_status( $report, 'production ZIP' ) );
			self::assertFileExists( $root . '/build/build.marker' ); self::assertFileDoesNotExist( $zip ); self::assertFileDoesNotExist( $root . '/build/postbuild.marker' ); self::assertNull( $report['zipSha256'] );
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
			'unregistered historical file' => static function ( array &$manifest, string $root ): void { mkdir( $root . '/constitutional-guard/history/prebuild/nested' ); file_put_contents( $root . '/constitutional-guard/history/prebuild/nested/unregistered.php', '<?php' ); },
			'empty phase' => static function ( array &$manifest, string $root ): void { unlink( $root . '/constitutional-guard/' . $manifest['tests'][1]['path'] ); array_splice( $manifest['tests'], 1, 1 ); },
			'missing successor' => static function ( array &$manifest ): void { unset( $manifest['tests'][2]['supersededBy'] ); },
			'unknown successor' => static function ( array &$manifest ): void { unset( $manifest['tests'][2]['supersededBy'] ); $manifest['tests'][2]['supersededBy'] = 'ics-warden-unknown-001'; },
			'cross-phase successor' => static function ( array &$manifest ): void { unset( $manifest['tests'][2]['supersededBy'] ); $manifest['tests'][2]['supersededBy'] = $manifest['tests'][1]['id']; },
			'malformed dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'] = array( array( 'path' => 'missing.php' ) ); },
			'duplicate dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][] = $manifest['tests'][0]['dependencies'][0]; },
			'dependency hash mismatch' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][0]['sha256'] = str_repeat( '0', 64 ); },
			'missing dependency' => static function ( array &$manifest ): void { $manifest['tests'][0]['dependencies'][0]['path'] = 'plugins/ironcreed-request-log/tests/missing.php'; },
			'duplicate interface' => static function ( array &$manifest ): void { $manifest['testingInterface'][] = $manifest['testingInterface'][0]; },
			'interface hash mismatch' => static function ( array &$manifest ): void { $manifest['testingInterface'][0]['sha256'] = str_repeat( '0', 64 ); },
			'missing top-level field' => static function ( array &$m ): void { unset( $m['scope'] ); },
			'superseded successor' => static function ( array &$m ): void { $m['tests'][2]['supersededBy'] = $m['tests'][2]['id']; },
			'duplicate dependency across entries' => static function ( array &$m ): void { $m['tests'][1]['dependencies'] = $m['tests'][0]['dependencies']; },
			'unregistered interface' => static function ( array &$m, string $r ): void { file_put_contents( $r . '/constitutional-guard/testing-interface/extra.php', '<?php' ); },
			'dependency file symlink' => static function ( array &$m, string $r ): void { $d = 'plugins/ironcreed-request-log/tests/'; symlink( $r . '/' . $d . 'dependency.php', $r . '/' . $d . 'link.php' ); $m['tests'][0]['dependencies'][0]['path'] = $d . 'link.php'; },
			'dependency parent symlink' => static function ( array &$m, string $r ): void { $d = 'plugins/ironcreed-request-log/tests/'; symlink( $r . '/' . $d, $r . '/' . $d . 'link' ); $m['tests'][0]['dependencies'][0]['path'] = $d . 'link/dependency.php'; },
			'historical file symlink' => static function ( array &$m, string $r ): void { $d = $r . '/constitutional-guard/'; $file = $d . $m['tests'][0]['path']; rename( $file, $d . 'history/target.php' ); symlink( $d . 'history/target.php', $file ); },
			'historical parent symlink' => static function ( array &$m, string $r ): void { $d = $r . '/constitutional-guard/history/'; rename( $d . 'prebuild', $d . 'real' ); symlink( $d . 'real', $d . 'prebuild' ); },
		);
		foreach ( $mutations as $label => $mutation ) {
			list( $root, $manifest ) = $this->valid_fixture();
			try {
				$this->assert_valid_manifest( $root );
				if ( 'duplicate id' === $label ) copy( $root . '/constitutional-guard/history/prebuild/active.php', $root . '/constitutional-guard/history/prebuild/second.php' );
				$mutation( $manifest, $root ); $this->write_manifest( $root, $manifest );
				list( $status, $report ) = $this->run_fixture( $root, 'integrity' );
				self::assertSame( 1, $status, $label ); self::assertSame( 'failed', $report['results'][0]['status'], $label );
				self::assertStringContainsString( $this->expected_diagnostic( $label ), $report['results'][0]['detail'], $label );
			} finally { $this->remove_fixture( $root ); }
		}
	}

	public function test_dependency_registry_is_reset_for_each_run(): void {
		list( $root ) = $this->valid_fixture();
		try {
			$runner = new Iron_Warden_Runner();
			foreach ( array( 'root' => $root, 'plugin' => $root . '/plugins/ironcreed-request-log' ) as $name => $value ) { $property = new ReflectionProperty( $runner, $name ); $property->setAccessible( true ); $property->setValue( $runner, $value ); }
			ob_start(); try { self::assertSame( 0, $runner->run( 'integrity' ) ); self::assertSame( 0, $runner->run( 'integrity' ) ); } finally { ob_end_clean(); }
		} finally { $this->remove_fixture( $root ); }
	}

	private function expected_diagnostic( string $label ): string {
		if ( str_contains( $label, 'symlink' ) ) return 'symbolic link';
		if ( str_contains( $label, 'successor' ) ) return 'active same-phase successor';
		$messages = array(
			'wrong schema' => 'schema or scope', 'wrong scope' => 'schema or scope', 'missing field' => 'Historical manifest field', 'missing top-level field' => 'top-level field',
			'malformed id' => 'value is invalid', 'malformed phase' => 'value is invalid', 'malformed status' => 'value is invalid', 'malformed hash' => 'value is invalid',
			'duplicate id' => 'ID is duplicated', 'duplicate path' => 'path is invalid or duplicated', 'phase mismatch' => 'path is invalid or duplicated', 'traversal' => 'path is invalid or duplicated',
			'missing historical file' => 'escapes its trusted directory', 'hash mismatch' => 'Historical test integrity failed', 'unregistered historical file' => 'Unregistered historical test', 'empty phase' => 'mandatory historical phase is empty',
			'malformed dependency' => 'declaration is malformed', 'duplicate dependency' => 'dependency path is invalid or duplicated', 'duplicate dependency across entries' => 'dependency path is invalid or duplicated',
			'dependency hash mismatch' => 'behavior dependency integrity failed', 'missing dependency' => 'dependency escapes',
			'duplicate interface' => 'Testing interface path is invalid', 'interface hash mismatch' => 'Testing interface integrity failed', 'unregistered interface' => 'Unregistered testing interface',
		);
		return $messages[ $label ];
	}

	private function gate_status( array $report, string $name ): string {
		foreach ( $report['results'] as $gate ) if ( $name === $gate['name'] ) return $gate['status'];
		self::fail( 'Expected gate was not executed: ' . $name );
	}
	private function run_fixture( string $root, string $phase ): array {
		list( $status ) = iron_warden_capture( array( PHP_BINARY, $root . '/constitutional-guard/run.php', $phase ), array( 'IRON_WARDEN_WORDPRESS_TESTS_DIR' => '', 'IRON_WARDEN_MYSQL_DSN' => '', 'IRON_WARDEN_DISPOSABLE_WORDPRESS' => '0', 'COMPOSER_ALLOW_SUPERUSER' => '1' ) );
		$report = json_decode( file_get_contents( $root . '/build/warden-report.json' ), true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 'pending', $report['releaseStatus'] ); return array( $status, $report );
	}
	private function assert_valid_manifest( string $root ): void {
		list( $status, $report ) = $this->run_fixture( $root, 'integrity' ); self::assertSame( 0, $status, json_encode( $report['results'] ) );
		self::assertSame( 'passed', $report['phaseStatus'] ); self::assertSame( 'passed', $report['results'][0]['status'] );
	}
	private function write_manifest( string $root, array $manifest ): void { file_put_contents( $root . '/constitutional-guard/history/manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); }
	private function valid_fixture(): array {
		$root = sys_get_temp_dir() . '/warden-fixture-' . bin2hex( random_bytes( 12 ) );
		try {
			foreach ( array( 'constitutional-guard/history/prebuild', 'constitutional-guard/history/postbuild', 'constitutional-guard/testing-interface', 'plugins/ironcreed-request-log/tests', 'plugins/ironcreed-request-log/tools', 'scripts', 'build' ) as $directory ) mkdir( $root . '/' . $directory, 0700, true );
			$interfaces = array();
			foreach ( array_merge( array( self::repository_root() . '/constitutional-guard/run.php' ), glob( self::repository_root() . '/constitutional-guard/testing-interface/*.php' ) ) as $file ) {
				$relative = substr( $file, strlen( self::repository_root() . '/constitutional-guard/' ) ); copy( $file, $root . '/constitutional-guard/' . $relative ); $interfaces[] = array( 'path' => $relative, 'sha256' => hash_file( 'sha256', $file ) );
			}
			$tests = array();
			foreach ( array( array( 'prebuild', 'active' ), array( 'postbuild', 'postbuild' ), array( 'prebuild', 'superseded' ) ) as $entry ) {
				list( $phase, $marker ) = $entry; $path = 'history/' . $phase . '/' . ( 'postbuild' === $marker ? 'active' : $marker ) . '.php';
				file_put_contents( $root . '/constitutional-guard/' . $path, '<?php file_put_contents(' . var_export( $root . '/build/' . $marker . '.marker', true ) . ', "executed");' );
				$tests[] = array( 'id' => 'ics-warden-' . $marker . '-001', 'path' => $path, 'phase' => $phase, 'sha256' => hash_file( 'sha256', $root . '/constitutional-guard/' . $path ), 'status' => 'superseded' === $marker ? 'superseded' : 'active' );
			}
			$tests[2]['supersededBy'] = $tests[0]['id'];
			$dependency = 'plugins/ironcreed-request-log/tests/dependency.php'; file_put_contents( $root . '/' . $dependency, '<?php' );
			$tests[0]['dependencies'] = array( array( 'path' => $dependency, 'sha256' => hash_file( 'sha256', $root . '/' . $dependency ) ) );
			$manifest = array( 'schemaVersion' => '1.0.0', 'scope' => 'plugins/ironcreed-request-log', 'tests' => $tests, 'testingInterface' => $interfaces ); $this->write_manifest( $root, $manifest );
			file_put_contents( $root . '/.gitignore', "build/\n" );
			file_put_contents( $root . '/scripts/validate-repository.py', 'from pathlib import Path; Path(' . json_encode( $root . '/build/current.marker', JSON_UNESCAPED_SLASHES ) . ').write_text("executed")' );
			file_put_contents( $root . '/plugins/ironcreed-request-log/tests/package-check.php', '<?php' );
			file_put_contents( $root . '/plugins/ironcreed-request-log/tools/build.sh', "#!/bin/sh\ntouch " . escapeshellarg( $root . '/build/build.marker' ) . "\nexit 7\n" );
			$composer = $root . '/plugins/ironcreed-request-log/composer.json'; file_put_contents( $composer, json_encode( array( 'name' => 'warden/fixture', 'description' => 'Isolated offline trust fixture', 'license' => 'MIT', 'require' => array( 'php' => '>=8.0' ) ) ) );
			$commands = array( array( 'composer', '--working-dir=' . dirname( $composer ), 'update', '--no-install', '--no-plugins', '--no-scripts', '--no-interaction', '--no-audit' ), array( 'git', '-C', $root, 'init', '-q' ), array( 'git', '-C', $root, 'add', '.' ), array( 'git', '-C', $root, '-c', 'user.name=Warden', '-c', 'user.email=warden@example.test', 'commit', '-qm', 'fixture' ) );
			foreach ( $commands as $command ) { list( $status, $stdout, $stderr ) = iron_warden_capture( $command, array( 'COMPOSER_DISABLE_NETWORK' => '1', 'COMPOSER_ALLOW_SUPERUSER' => '1' ) ); if ( 0 !== $status ) throw new RuntimeException( 'Fixture setup failed: ' . $stdout . $stderr ); }
			return array( $root, $manifest );
		} catch ( Throwable $error ) { $this->remove_fixture( $root ); throw $error; }
	}
	private function remove_fixture( string $root ): void {
		if ( ! is_dir( $root ) ) return;
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) { $path = $file->getPathname(); if ( is_link( $path ) || ! $file->isDir() ) unlink( $path ); else rmdir( $path ); }
		rmdir( $root );
	}
}
