<?php
/** Installed-package orchestration; every owned resource is cleaned before returning. */
function iron_warden_package_smoke( string $mode, string $zip, string $state, callable $capture, ?callable $diagnostic = null ): int {
	$diagnostic = $diagnostic ?? static function ( string $message ): void { fwrite( STDERR, $message . "\n" ); };
	$network = 'multisite-smoke' === $mode;
	$prefix = $network ? 'network' : 'fresh';
	$slug = 'ironcreed-request-log';
	$installed = false; $removed = false; $site = ''; $original_sites = ''; $failed = false; $cleanup_failed = false;
	$must = static function ( array $arguments ) use ( $capture ): string {
		list( $status, $stdout ) = $capture( $arguments );
		if ( 0 !== $status ) throw new RuntimeException( 'Package lifecycle operation failed.' );
		return trim( $stdout );
	};
	$deactivate = array( 'plugin', 'deactivate', $slug );
	$activate = array( 'plugin', 'activate', $slug );
	if ( $network ) { $deactivate[] = '--network'; $activate[] = '--network'; }
	try {
		list( $status ) = $capture( array( 'core', 'is-installed' ) );
		if ( 0 !== $status ) return 2;
		list( $status, $topology ) = $capture( array( 'eval-file', $state, 'topology' ) );
		if ( 0 !== $status || ( $network ? 'multisite' : 'single' ) !== trim( $topology ) ) return 2;
		$must( array( 'eval-file', $state, $prefix . '-absent' ) );
		$original_sites = $must( array( 'eval-file', $state, 'site-ids' ) );
		$ids = json_decode( $original_sites, true );
		if ( ! is_array( $ids ) || ! $ids || array_filter( $ids, static fn( $id ): bool => ! is_int( $id ) || $id < 1 ) ) return 2;
		// Mark ownership before installation, including a partially failed install.
		$installed = true;
		$must( array( 'plugin', 'install', $zip ) );
		$must( array( 'eval-file', $state, 'installed' ) );
		$must( $activate );
		$must( array( 'eval-file', $state, $prefix . '-active' ) );
		if ( $network ) {
			// Keep an ownership token even when site creation returns malformed output.
			$site = 'warden-' . bin2hex( random_bytes( 12 ) );
			$id = $must( array( 'eval-file', $state, 'create-test-site', $site ) );
			if ( ! ctype_digit( $id ) || (int) $id < 1 ) throw new RuntimeException( 'Synthetic site creation failed.' );
			$must( array( 'eval-file', $state, 'network-active', $id ) );
		}
		$must( array( 'eval-file', $state, 'seed-persistent-state' ) );
		$before = $must( array( 'eval-file', $state, 'persistent-digest' ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $before ) ) throw new RuntimeException( 'Persistent state assertion failed.' );
		$must( $deactivate );
		$must( array( 'eval-file', $state, $prefix . '-inactive' ) );
		$after = $must( array( 'eval-file', $state, 'persistent-digest' ) );
		if ( ! hash_equals( $before, $after ) ) throw new RuntimeException( 'Deactivation changed persistent state.' );
		$must( array( 'plugin', 'uninstall', $slug ) );
		$must( array( 'eval-file', $state, $prefix . '-removed' ) );
		$removed = true;
	} catch ( Throwable $error ) {
		$failed = true;
	} finally {
		if ( $installed && ! $removed ) {
			foreach ( array( $deactivate, array( 'plugin', 'uninstall', $slug ), array( 'eval-file', $state, $prefix . '-removed' ) ) as $command ) {
				try { $must( $command ); } catch ( Throwable $error ) { $cleanup_failed = true; }
			}
		}
		if ( '' !== $site ) {
			try {
				$must( array( 'eval-file', $state, 'delete-test-site', $site, $original_sites ) );
				if ( $original_sites !== $must( array( 'eval-file', $state, 'site-ids' ) ) ) throw new RuntimeException( 'Site cleanup assertion failed.' );
			} catch ( Throwable $error ) { $cleanup_failed = true; }
		}
	}
	if ( $failed ) $diagnostic( 'Package lifecycle failed safely.' );
	if ( $cleanup_failed ) $diagnostic( 'Package lifecycle cleanup failed safely.' );
	return $failed || $cleanup_failed ? 1 : 0;
}
