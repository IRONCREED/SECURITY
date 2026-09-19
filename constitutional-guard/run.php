<?php
/** IRON WARDEN runner for IRONCREED Security. */

final class Iron_Warden_Runner {
	private string $root;
	private string $plugin;
	private array $results = array();
	private array $manifest = array();
	private ?string $zip = null;
	private array $dependency_paths = array();

	public function __construct() {
		$this->root   = dirname( __DIR__ );
		$this->plugin = $this->root . '/plugins/ironcreed-request-log';
	}

	public function run( string $phase ): int {
		$this->results = array(); $this->manifest = array(); $this->dependency_paths = array(); $this->zip = null;
		if ( ! in_array( $phase, array( 'integrity', 'prebuild', 'postbuild', 'all' ), true ) ) {
			fwrite( STDERR, "Usage: php constitutional-guard/run.php [integrity|prebuild|postbuild|all]\n" );
			return 2;
		}

		$this->integrity();
		if ( $this->has_integrity_refusal() ) {
			return $this->report( $phase );
		}
		if ( 'prebuild' === $phase || 'all' === $phase ) {
			$this->prebuild();
		}
		if ( 'all' === $phase && ! $this->has_phase_refusal( 'prebuild' ) ) {
			$this->build();
		}
		if ( 'postbuild' === $phase || ( 'all' === $phase && ! $this->has_phase_refusal( 'prebuild' ) ) ) {
			if ( 'postbuild' === $phase ) {
				$this->build();
			}
			if ( null !== $this->zip ) $this->postbuild();
			else $this->unavailable( 'postbuild', 'production artifact checks', 'The current run did not produce a trusted artifact.' );
		}

		return $this->report( $phase );
	}

	private function integrity(): void {
		$this->gate( 'integrity', 'historical manifest', function (): void {
			$manifest_file = $this->root . '/constitutional-guard/history/manifest.json';
			$manifest = json_decode( (string) file_get_contents( $manifest_file ), true, 512, JSON_THROW_ON_ERROR );
			foreach ( array( 'schemaVersion', 'scope', 'tests', 'testingInterface' ) as $field ) if ( ! array_key_exists( $field, $manifest ) ) throw new RuntimeException( 'Manifest top-level field is missing: ' . $field );
			if ( '1.0.0' !== $manifest['schemaVersion'] || 'plugins/ironcreed-request-log' !== $manifest['scope'] || ! is_array( $manifest['tests'] ) || ! is_array( $manifest['testingInterface'] ) ) throw new RuntimeException( 'Manifest schema or scope is unsupported.' );
			$registered = array();
			$by_id = array();
			$paths = array();
			$phases = array( 'prebuild' => 0, 'postbuild' => 0 );
			foreach ( $manifest['tests'] ?? array() as $test ) {
				foreach ( array( 'id', 'path', 'phase', 'sha256', 'status' ) as $field ) {
					if ( ! isset( $test[ $field ] ) ) throw new RuntimeException( 'Historical manifest field is missing: ' . $field );
				}
				if ( ! preg_match( '/^ics-warden-[a-z0-9-]+-[0-9]{3}$/', $test['id'] ) || ! isset( $phases[ $test['phase'] ] ) || ! in_array( $test['status'], array( 'active', 'superseded' ), true ) || ! preg_match( '/^[a-f0-9]{64}$/', $test['sha256'] ) ) throw new RuntimeException( 'Historical manifest value is invalid.' );
				$expected = 'history/' . $test['phase'] . '/';
				if ( ! str_starts_with( $test['path'], $expected ) || str_contains( $test['path'], '..' ) || isset( $paths[ $test['path'] ] ) ) throw new RuntimeException( 'Historical path is invalid or duplicated.' );
				$file = $this->trusted_file( $test['path'], 'history/' );
				if ( ! is_file( $file ) || hash_file( 'sha256', $file ) !== $test['sha256'] ) throw new RuntimeException( 'Historical test integrity failed: ' . $test['id'] );
				if ( isset( $by_id[ $test['id'] ] ) ) throw new RuntimeException( 'Historical test ID is duplicated.' );
				$by_id[ $test['id'] ] = $test;
				$registered[] = realpath( $file ); $paths[ $test['path'] ] = true;
				if ( 'active' === $test['status'] ) ++$phases[ $test['phase'] ];
				foreach ( $test['dependencies'] ?? array() as $dependency ) {
					if ( ! is_array( $dependency ) || array( 'path', 'sha256' ) !== array_keys( $dependency ) ) throw new RuntimeException( 'Historical dependency declaration is malformed.' );
					$dependency_file = $this->trusted_repository_file( $dependency['path'] ?? '' );
					if ( ! preg_match( '/^[a-f0-9]{64}$/', $dependency['sha256'] ?? '' ) || hash_file( 'sha256', $dependency_file ) !== $dependency['sha256'] ) throw new RuntimeException( 'Historical behavior dependency integrity failed.' );
				}
			}
			foreach ( $by_id as $test ) {
				if ( 'superseded' === $test['status'] && ( empty( $test['supersededBy'] ) || 'active' !== ( $by_id[ $test['supersededBy'] ]['status'] ?? null ) || $test['phase'] !== ( $by_id[ $test['supersededBy'] ]['phase'] ?? null ) ) ) throw new RuntimeException( 'Superseded historical test lacks an active same-phase successor.' );
			}
			foreach ( $this->recursive_php_files( $this->root . '/constitutional-guard/history' ) as $file ) {
				if ( ! in_array( realpath( $file ), $registered, true ) ) throw new RuntimeException( 'Unregistered historical test: ' . basename( $file ) );
			}
			if ( in_array( 0, $phases, true ) ) throw new RuntimeException( 'A mandatory historical phase is empty.' );
			$interfaces = array();
			foreach ( $manifest['testingInterface'] ?? array() as $interface ) {
				$interface_path = $interface['path'] ?? '';
				if ( ! is_array( $interface ) || array( 'path', 'sha256' ) !== array_keys( $interface ) || ! preg_match( '/^[a-f0-9]{64}$/', $interface['sha256'] ?? '' ) || ( 'run.php' !== $interface_path && ! str_starts_with( $interface_path, 'testing-interface/' ) ) || str_contains( $interface_path, '..' ) || isset( $interfaces[ $interface_path ] ) ) throw new RuntimeException( 'Testing interface path is invalid.' );
				$file = $this->trusted_file( $interface_path, 'run.php' === $interface_path ? '' : 'testing-interface/' );
				if ( ! is_file( $file ) || hash_file( 'sha256', $file ) !== ( $interface['sha256'] ?? '' ) ) throw new RuntimeException( 'Testing interface integrity failed.' );
				$interfaces[ $interface_path ] = realpath( $file );
			}
			foreach ( $this->recursive_php_files( $this->root . '/constitutional-guard/testing-interface' ) as $file ) {
				if ( ! in_array( realpath( $file ), $interfaces, true ) ) throw new RuntimeException( 'Unregistered testing interface file.' );
			}
			if ( ! $interfaces ) throw new RuntimeException( 'Testing interface is empty.' );
			$this->manifest = $manifest;
		} );
		$lock = $this->plugin . '/composer.lock';
		if ( ! is_file( $lock ) ) {
			$this->unavailable( 'integrity', 'Composer lockfile', 'Exact development dependency lockfile is unavailable.' );
		} else {
			$relative = 'plugins/ironcreed-request-log/composer.lock';
			$this->command_gate( 'integrity', 'Composer lockfile tracked and clean', 'git -C ' . escapeshellarg( $this->root ) . ' cat-file -e ' . escapeshellarg( 'HEAD:' . $relative ) . ' && test "$(git -C ' . escapeshellarg( $this->root ) . ' ls-files -s -- ' . escapeshellarg( $relative ) . ' | cut -d" " -f1)" = 100644 && git -C ' . escapeshellarg( $this->root ) . ' diff --quiet -- ' . escapeshellarg( $relative ) . ' && git -C ' . escapeshellarg( $this->root ) . ' diff --cached --quiet -- ' . escapeshellarg( $relative ) );
			$this->command_gate( 'integrity', 'Composer lockfile consistency', 'composer validate --strict --no-check-publish ' . escapeshellarg( $this->plugin . '/composer.json' ) );
		}
	}

	private function prebuild(): void {
		$this->command_gate( 'prebuild', 'repository validation', 'python3 ' . escapeshellarg( $this->root . '/scripts/validate-repository.py' ) );
		$this->gate( 'prebuild', 'PHP syntax', function (): void {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->plugin, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( 'php' === $file->getExtension() && ! str_contains( $file->getPathname(), '/vendor/' ) ) {
					$this->must_run( PHP_BINARY . ' -l ' . escapeshellarg( $file->getPathname() ) );
				}
			}
		} );
		$this->tool_gate( 'prebuild', 'WordPress Coding Standards', $this->plugin . '/vendor/bin/phpcs', escapeshellarg( $this->plugin . '/vendor/bin/phpcs' ) . ' --standard=' . escapeshellarg( $this->plugin . '/phpcs.xml.dist' ) );
		$this->tool_gate( 'prebuild', 'PHPCompatibilityWP', $this->plugin . '/vendor/bin/phpcs', escapeshellarg( $this->plugin . '/vendor/bin/phpcs' ) . ' --standard=PHPCompatibilityWP --runtime-set testVersion 8.0- --extensions=php ' . escapeshellarg( $this->plugin . '/ironcreed-request-log.php' ) . ' ' . escapeshellarg( $this->plugin . '/uninstall.php' ) . ' ' . escapeshellarg( $this->plugin . '/includes' ) );
		$this->tool_gate( 'prebuild', 'PHPUnit current tests', $this->plugin . '/vendor/bin/phpunit', escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $this->root . '/constitutional-guard/testing-interface/current-phpunit.php' ) );
		$this->command_gate( 'prebuild', 'package source assertions', PHP_BINARY . ' ' . escapeshellarg( $this->plugin . '/tests/package-check.php' ) );
		$this->command_gate( 'prebuild', 'English and Ukrainian catalogs', 'python3 ' . escapeshellarg( $this->plugin . '/tools/translations.py' ) );
		$this->command_gate( 'prebuild', 'historical prebuild corpus', $this->historical_command( 'prebuild' ), true );
		$this->gate( 'prebuild', 'credential and fixture scan', function (): void {
			$command = 'find ' . escapeshellarg( $this->root )
				. " \\( -path '*/.git' -o -path '*/build' -o -path '*/vendor' \\) -prune -o"
				. " -type f \\( -name '*.log' -o -name '*.gz' -o -name '*.zip' \\) -print -quit";
			exec( $command, $matches, $status );
			if ( 0 !== $status || $matches ) {
				throw new RuntimeException( 'A prohibited log or archive fixture exists in source.' );
			}
		} );
		$this->integration_gate( 'prebuild', 'WordPress integration current tests', 'wordpress-current' );
		$this->integration_gate( 'prebuild', 'Multisite current tests', 'multisite-current' );
		$this->integration_gate( 'prebuild', 'Concurrent storage integration', 'concurrency' );
	}

	private function build(): bool {
		$this->zip = null;
		@unlink( $this->root . '/build/ironcreed-request-log-1.0.0.zip' );
		$this->gate( 'build', 'clean source tree', function (): void {
			$output = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' status --porcelain --untracked-files=all' ) );
			if ( '' !== $output ) throw new RuntimeException( 'The source tree is not clean.' );
		} );
		if ( 'passed' !== end( $this->results )['status'] ) return false;
		$this->gate( 'build', 'production ZIP', function (): void {
			$this->must_run( 'bash ' . escapeshellarg( $this->plugin . '/tools/build.sh' ) . ' ' . escapeshellarg( $this->root . '/build' ) );
			$file = $this->root . '/build/ironcreed-request-log-1.0.0.zip';
			if ( ! is_file( $file ) ) throw new RuntimeException( 'The build completed without its declared artifact.' );
			$this->zip = $file;
		} );
		return null !== $this->zip;
	}

	private function postbuild(): void {
		if ( null === $this->zip || ! is_file( $this->zip ) ) {
			$this->unavailable( 'postbuild', 'production artifact checks', 'Production ZIP is unavailable.' );
			return;
		}
		putenv( 'WARDEN_ZIP=' . $this->zip );
		$this->command_gate( 'postbuild', 'historical postbuild corpus', $this->historical_command( 'postbuild' ) );
		$this->gate( 'postbuild', 'reproducible production ZIP', function (): void {
			$directory = sys_get_temp_dir() . '/iron-warden-rebuild-' . getmypid();
			mkdir( $directory, 0700, true );
			$this->must_run( 'bash ' . escapeshellarg( $this->plugin . '/tools/build.sh' ) . ' ' . escapeshellarg( $directory ) );
			$rebuilt = $directory . '/ironcreed-request-log-1.0.0.zip';
			if ( hash_file( 'sha256', $this->zip ) !== hash_file( 'sha256', $rebuilt ) ) throw new RuntimeException( 'Production ZIPs differ.' );
			@unlink( $rebuilt ); @rmdir( $directory );
		} );
		$wp = trim( (string) shell_exec( 'command -v wp 2>/dev/null' ) );
		if ( $wp && 0 === $this->command_status( escapeshellarg( $wp ) . ' help plugin check' ) ) $this->command_gate( 'postbuild', 'official Plugin Check', escapeshellarg( $wp ) . ' plugin check ' . escapeshellarg( $this->zip ) );
		else $this->unavailable( 'postbuild', 'official Plugin Check', 'The WP-CLI Plugin Check command is unavailable.' );
		$this->integration_gate( 'postbuild', 'fresh-site package smoke test', 'fresh-smoke' );
		$this->integration_gate( 'postbuild', 'Multisite package lifecycle smoke test', 'multisite-smoke' );
		$this->manual_evidence( 'authenticated Hosting Ukraine smoke test' );
	}

	private function historical_command( string $phase ): string {
		$active = array_filter( $this->manifest['tests'] ?? array(), static fn( array $test ): bool => $phase === $test['phase'] && 'active' === $test['status'] );
		$commands = array_map( fn( array $test ): string => PHP_BINARY . ' ' . escapeshellarg( $this->root . '/constitutional-guard/' . $test['path'] ), $active );
		return implode( ' && ', $commands );
	}

	private function has_integrity_refusal(): bool {
		foreach ( $this->results as $result ) if ( 'integrity' === $result['phase'] && 'passed' !== $result['status'] ) return true;
		return false;
	}

	private function has_phase_refusal( string $phase ): bool {
		foreach ( $this->results as $result ) if ( $phase === $result['phase'] && 'passed' !== $result['status'] ) return true;
		return false;
	}

	private function integration_gate( string $phase, string $name, string $mode ): void {
		$runner = $this->root . '/constitutional-guard/testing-interface/integration-runner.php';
		$status = $this->command_status( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( $mode ) . ( null !== $this->zip ? ' ' . escapeshellarg( $this->zip ) : '' ) );
		if ( 2 === $status ) { $this->unavailable( $phase, $name, 'The typed integration environment is unavailable.' ); return; }
		$this->results[] = compact( 'phase', 'name' ) + array( 'status' => 0 === $status ? 'passed' : 'failed', 'detail' => 0 === $status ? '' : 'The repository-owned integration boundary failed safely.' );
	}

	private function manual_evidence( string $unused ): void {
		$file = trim( (string) getenv( 'IRON_WARDEN_MANUAL_EVIDENCE_FILE' ) );
		$real = '' !== $file ? realpath( $file ) : false;
		$inside = false !== $real && str_starts_with( $real, $this->root . DIRECTORY_SEPARATOR );
		$ignored = $inside && 0 === $this->command_status( 'git -C ' . escapeshellarg( $this->root ) . ' check-ignore -q ' . escapeshellarg( $real ) );
		$evidence = false !== $real && ( ! $inside || $ignored ) ? json_decode( (string) file_get_contents( $real ), true ) : null;
		$sha = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' rev-parse HEAD' ) );
		$zip = $this->zip ? hash_file( 'sha256', $this->zip ) : '';
		$required = array( 'authenticated Hosting Ukraine smoke test', 'accessibility review', 'localization review', 'final external-policy review' );
		$valid = array(); $invalid = false;
		if ( is_array( $evidence ) && '1.0.0' === ( $evidence['schemaVersion'] ?? '' ) && $this->exact_keys( $evidence, array( 'schemaVersion', 'evidence' ) ) && is_array( $evidence['evidence'] ) ) {
			foreach ( $evidence['evidence'] as $entry ) {
				if ( ! is_array( $entry ) || ! $this->exact_keys( $entry, array( 'gate', 'date', 'gitSha', 'zipSha256', 'status' ) ) || ! in_array( $entry['gate'], $required, true ) || isset( $valid[ $entry['gate'] ] ) || 'passed' !== $entry['status'] ) { $invalid = true; continue; }
				$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s\Z', $entry['date'], new DateTimeZone( 'UTC' ) );
				if ( false === $date || $date->format( 'Y-m-d\TH:i:s\Z' ) !== $entry['date'] || $date > new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) || $date < new DateTimeImmutable( '-30 days', new DateTimeZone( 'UTC' ) ) || $sha !== $entry['gitSha'] || $zip !== $entry['zipSha256'] ) { $invalid = true; continue; }
				$valid[ $entry['gate'] ] = true;
			}
		} else $invalid = true;
		if ( $invalid ) $valid = array();
		foreach ( $required as $gate ) isset( $valid[ $gate ] ) ? $this->results[] = array( 'phase' => 'postbuild', 'name' => $gate, 'status' => 'passed', 'detail' => 'Current external manual evidence verified.' ) : $this->manual( 'postbuild', $gate, 'Current external evidence is required.' );
		$this->gate( 'postbuild', 'clean tree after manual evidence', function (): void { if ( '' !== trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' status --porcelain --untracked-files=all' ) ) ) throw new RuntimeException( 'Manual evidence processing observed a dirty source tree.' ); } );
	}

	private function exact_keys( array $value, array $expected ): bool {
		$keys = array_keys( $value ); sort( $keys ); sort( $expected ); return $keys === $expected;
	}

	private function trusted_file( string $path, string $prefix ): string {
		$base = realpath( $this->root . '/constitutional-guard/' . rtrim( $prefix, '/' ) );
		$file = realpath( $this->root . '/constitutional-guard/' . $path );
		$declared = $this->root . '/constitutional-guard/' . $path;
		if ( false === $base || false === $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) ) throw new RuntimeException( 'Manifest path escapes its trusted directory.' );
		for ( $component = $declared; $component !== $this->root && strlen( $component ) >= strlen( $this->root ); $component = dirname( $component ) ) if ( is_link( $component ) ) throw new RuntimeException( 'Manifest path uses a symbolic link.' );
		return $file;
	}

	private function trusted_repository_file( string $path ): string {
		if ( isset( $this->dependency_paths[ $path ] ) || str_contains( $path, '..' ) || ! str_starts_with( $path, 'plugins/ironcreed-request-log/tests/' ) || ! in_array( pathinfo( $path, PATHINFO_EXTENSION ), array( 'php', 'xml' ), true ) ) throw new RuntimeException( 'Historical dependency path is invalid or duplicated.' );
		$base = realpath( $this->plugin . '/tests' ); $declared = $this->root . '/' . $path; $file = realpath( $declared );
		if ( false === $base || false === $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) ) throw new RuntimeException( 'Historical dependency escapes the repository boundary.' );
		for ( $component = $declared; $component !== $this->root && strlen( $component ) >= strlen( $this->root ); $component = dirname( $component ) ) if ( is_link( $component ) ) throw new RuntimeException( 'Historical dependency uses a symbolic link.' );
		$this->dependency_paths[ $path ] = true;
		return $file;
	}

	private function recursive_php_files( string $directory ): array {
		$files = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) if ( 'php' === $file->getExtension() ) $files[] = $file->getRealPath();
		return $files;
	}

	private function tool_gate( string $phase, string $name, string $tool, string $command ): void {
		if ( ! is_file( $tool ) ) { $this->unavailable( $phase, $name, 'Required development dependency is unavailable.' ); return; }
		$this->command_gate( $phase, $name, $command );
	}

	private function command_gate( string $phase, string $name, string $command, bool $typed_unavailable = false ): void {
		if ( $typed_unavailable ) {
			exec( $command . ' 2>&1', $output, $status );
			if ( 2 === $status ) { $this->unavailable( $phase, $name, 'The typed integration environment required by the historical corpus is unavailable.' ); return; }
			$this->gate( $phase, $name, static function () use ( $status, $output ): void {
				if ( 0 !== $status ) throw new RuntimeException( trim( implode( "\n", array_slice( $output, -5 ) ) ) ?: 'Historical command failed.' );
			} );
			return;
		}
		$this->gate( $phase, $name, fn(): bool => 0 === $this->must_run( $command ) );
	}

	private function gate( string $phase, string $name, callable $check ): void {
		try { $check(); $this->results[] = compact( 'phase', 'name' ) + array( 'status' => 'passed', 'detail' => '' ); }
		catch ( Throwable $error ) { $this->results[] = compact( 'phase', 'name' ) + array( 'status' => 'failed', 'detail' => $error->getMessage() ); }
	}

	private function unavailable( string $phase, string $name, string $detail ): void { $this->results[] = compact( 'phase', 'name', 'detail' ) + array( 'status' => 'unavailable' ); }
	private function manual( string $phase, string $name, string $detail ): void { $this->results[] = compact( 'phase', 'name', 'detail' ) + array( 'status' => 'manual evidence required' ); }

	private function must_run( string $command ): int {
		exec( $command . ' 2>&1', $output, $status );
		if ( 0 !== $status ) throw new RuntimeException( trim( implode( "\n", array_slice( $output, -5 ) ) ) ?: 'Command failed.' );
		return $status;
	}

	private function command_status( string $command ): int {
		exec( $command . ' >/dev/null 2>&1', $output, $status );
		return $status;
	}

	private function report( string $phase ): int {
		$sha = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $this->root ) . ' rev-parse HEAD' ) );
		$zip_hash = $this->zip && is_file( $this->zip ) ? hash_file( 'sha256', $this->zip ) : null;
		$pending = array_filter( $this->results, static fn( array $result ): bool => 'passed' !== $result['status'] );
		$phase_status = $pending ? 'pending' : 'passed';
		$release_status = 'all' === $phase && ! $pending ? 'passed' : 'pending';
		printf( "\nIRON WARDEN — %s\nGit SHA: %s\nPHP: %s\nZIP SHA-256: %s\n\n", gmdate( 'c' ), $sha, PHP_VERSION, $zip_hash ?? 'unavailable' );
		printf( "%-12s %-34s %-24s\n", 'Phase', 'Gate', 'Status' );
		foreach ( $this->results as $result ) printf( "%-12s %-34s %-24s%s\n", $result['phase'], $result['name'], $result['status'], $result['detail'] ? ' — ' . $result['detail'] : '' );
		$tools = array(
			'php' => PHP_VERSION,
			'composer' => trim( (string) shell_exec( 'composer --version --no-ansi 2>/dev/null' ) ),
			'phpcs' => is_file( $this->plugin . '/vendor/bin/phpcs' ) ? trim( (string) shell_exec( escapeshellarg( $this->plugin . '/vendor/bin/phpcs' ) . ' --version' ) ) : 'unavailable',
			'phpunit' => is_file( $this->plugin . '/vendor/bin/phpunit' ) ? trim( (string) shell_exec( escapeshellarg( $this->plugin . '/vendor/bin/phpunit' ) . ' --version' ) ) : 'unavailable',
			'wpCli' => trim( (string) shell_exec( 'wp --version 2>/dev/null' ) ) ?: 'unavailable',
			'wordpress' => trim( (string) shell_exec( 'wp core version 2>/dev/null' ) ) ?: 'unavailable',
			'pluginCheck' => trim( (string) shell_exec( 'wp plugin get plugin-check --field=version 2>/dev/null' ) ) ?: 'unavailable',
			'phpCompatibilityWP' => is_dir( $this->plugin . '/vendor/phpcompatibility/phpcompatibility-wp' ) ? '2.1.8' : 'unavailable',
		);
		$report = array( 'schemaVersion' => '1.0.0', 'runAt' => gmdate( 'c' ), 'phase' => $phase, 'phaseStatus' => $phase_status, 'releaseStatus' => $release_status, 'gitSha' => $sha, 'tools' => $tools, 'zipSha256' => $zip_hash, 'results' => $this->results );
		@mkdir( $this->root . '/build', 0777, true );
		file_put_contents( $this->root . '/build/warden-report.json', json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		printf( "\nPhase status: %s\nRelease status: %s\n", strtoupper( $phase_status ), strtoupper( $release_status ) );
		return $pending ? 1 : 0;
	}
}

if ( ! defined( 'IRON_WARDEN_LIBRARY' ) ) {
	exit( ( new Iron_Warden_Runner() )->run( $argv[1] ?? 'all' ) );
}
