<?php
use PHPUnit\Framework\TestCase;

/** Orchestration fault injection; live WordPress state remains an integration gate. */
final class PackageSmokeTest extends TestCase {
	public static function setUpBeforeClass(): void { require_once dirname( __DIR__, 5 ) . '/constitutional-guard/testing-interface/package-smoke.php'; }
	public function test_success_cleans_resources_before_returning(): void {
		foreach ( array( 'fresh-smoke', 'multisite-smoke' ) as $mode ) {
			$fake = new Package_Smoke_Boundary( $mode );
			self::assertSame( 0, $this->run_smoke( $fake ) ); self::assertFalse( $fake->installed ); self::assertFalse( $fake->site_created );
			self::assertContains( array( 'plugin', 'install', '/current-run.zip' ), $fake->commands );
			self::assertContains( array( 'plugin', 'uninstall', 'ironcreed-request-log' ), $fake->commands );
			foreach ( $fake->commands as $command ) if ( array_slice( $command, 0, 2 ) === array( 'plugin', 'uninstall' ) ) self::assertNotContains( '--network', $command );
			if ( 'multisite-smoke' === $mode ) {
				self::assertSame( 'site-ids', end( $fake->commands )[2] );
				self::assertContains( array( 'plugin', 'deactivate', 'ironcreed-request-log', '--network' ), $fake->commands );
			}
		}
	}
	public function test_every_failed_operation_after_install_attempt_is_cleaned(): void {
		foreach ( array( 'fresh-smoke', 'multisite-smoke' ) as $mode ) {
			$baseline = new Package_Smoke_Boundary( $mode ); self::assertSame( 0, $this->run_smoke( $baseline ) );
			foreach ( $baseline->commands as $index => $command ) {
				if ( $index < 4 ) continue;
				$fake = new Package_Smoke_Boundary( $mode ); $fake->fail_once = $index;
				self::assertSame( 1, $this->run_smoke( $fake ), json_encode( $command ) );
				self::assertFalse( $fake->installed, json_encode( $command ) ); self::assertFalse( $fake->site_created, json_encode( $command ) );
				self::assertNotEmpty( $fake->diagnostics );
			}
		}
	}
	public function test_malformed_site_creation_output_still_cleans_owned_site(): void {
		$fake = new Package_Smoke_Boundary( 'multisite-smoke' ); $fake->malformed_id = true;
		self::assertSame( 1, $this->run_smoke( $fake ) ); self::assertFalse( $fake->installed ); self::assertFalse( $fake->site_created );
		self::assertContains( 'delete-test-site', array_column( $fake->commands, 2 ) );
	}
	public function test_cleanup_failure_cannot_pass_or_hide_operation_failure(): void {
		$fake = new Package_Smoke_Boundary( 'multisite-smoke' ); $fake->malformed_id = true; $fake->fail_cleanup = true;
		self::assertSame( 1, $this->run_smoke( $fake ) ); self::assertCount( 2, $fake->diagnostics ); self::assertTrue( $fake->site_created );
		self::assertSame( 'Package lifecycle failed safely.', $fake->diagnostics[0] ); self::assertSame( 'Package lifecycle cleanup failed safely.', $fake->diagnostics[1] );
	}
	public function test_deactivation_persistent_change_is_a_failure(): void {
		foreach ( array( 'fresh-smoke', 'multisite-smoke' ) as $mode ) {
			$fake = new Package_Smoke_Boundary( $mode ); $fake->changed_digest = true;
			self::assertSame( 1, $this->run_smoke( $fake ) ); self::assertFalse( $fake->installed ); self::assertFalse( $fake->site_created );
		}
	}
	public function test_wrong_topology_is_unavailable_before_install(): void {
		$fake = new Package_Smoke_Boundary( 'fresh-smoke' ); $fake->wrong_topology = true;
		self::assertSame( 2, $this->run_smoke( $fake ) ); self::assertFalse( $fake->installed ); self::assertCount( 2, $fake->commands );
	}
	private function run_smoke( Package_Smoke_Boundary $fake ): int {
		return iron_warden_package_smoke( $fake->mode, '/current-run.zip', '/wp-state.php', $fake, static function ( string $message ) use ( $fake ): void { $fake->diagnostics[] = $message; } );
	}
}

final class Package_Smoke_Boundary {
	public string $mode; public array $commands = array(); public array $diagnostics = array();
	public bool $installed = false; public bool $site_created = false; public bool $malformed_id = false; public bool $changed_digest = false; public bool $wrong_topology = false; public bool $fail_cleanup = false;
	public int $fail_once = -1; private int $digests = 0;
	public function __construct( string $mode ) { $this->mode = $mode; }
	public function __invoke( array $command ): array {
		$index = count( $this->commands ); $this->commands[] = $command; $out = '';
		if ( 'plugin' === $command[0] ) {
			if ( 'install' === $command[1] ) $this->installed = true;
			if ( 'uninstall' === $command[1] ) $this->installed = false;
		} elseif ( 'eval-file' === $command[0] ) {
			$operation = $command[2];
			if ( 'topology' === $operation ) $out = ( 'multisite-smoke' === $this->mode || $this->wrong_topology ) ? 'multisite' : 'single';
			if ( 'site-ids' === $operation ) $out = $this->site_created ? '[1,2,3]' : '[1,2]';
			if ( 'create-test-site' === $operation ) { $this->site_created = true; $out = $this->malformed_id ? 'invalid' : '3'; }
			if ( 'delete-test-site' === $operation ) { if ( $this->fail_cleanup ) return array( 1, '', '' ); $this->site_created = false; }
			if ( 'persistent-digest' === $operation ) $out = str_repeat( $this->changed_digest && $this->digests++ ? 'b' : 'a', 64 );
		}
		// Fail after mutation to cover partial installation and site creation.
		return array( $index === $this->fail_once ? 1 : 0, $out, '' );
	}
}
