<?php
/** Typed repository-owned integration boundary. */

$mode = $argv[1] ?? '';
$zip  = $argv[2] ?? '';
$root = dirname( __DIR__, 2 );
require_once __DIR__ . '/phpunit-result.php';
require_once __DIR__ . '/process.php';

function warden_wp_cli( string $root ): string {
	$value = getenv( 'IRON_WARDEN_WP_CLI_EXECUTABLE' ); $real = is_string( $value ) ? realpath( $value ) : false;
	if ( false === $real || ! is_file( $real ) || ! is_executable( $real ) ) return '';
	list( $status, $stdout ) = iron_warden_capture( array( $real, '--version' ) );
	return 0 === $status && str_starts_with( trim( $stdout ), 'WP-CLI ' ) ? $real : '';
}

if ( in_array( $mode, array( 'wordpress-current', 'multisite-current' ), true ) ) {
	$phpunit = realpath( $root . '/plugins/ironcreed-request-log/vendor/bin/phpunit' );
	$tests   = realpath( (string) getenv( 'IRON_WARDEN_WORDPRESS_TESTS_DIR' ) );
	if ( false === $phpunit || ! is_executable( $phpunit ) || false === $tests || ! is_file( $tests . '/includes/bootstrap.php' ) ) exit( 2 );
	$environment = array( 'WP_TESTS_DIR' => $tests, 'WP_MULTISITE' => 'multisite-current' === $mode ? '1' : '0' );
	list( $status, $stdout, $stderr ) = iron_warden_capture( array( $phpunit, '-c', $root . '/plugins/ironcreed-request-log/tests/integration/phpunit.xml', '--group', $mode ), $environment );
	$result = $stdout . $stderr;
	if ( ! iron_warden_phpunit_passed( $status, $result ) ) exit( 1 );
	exit( 0 );
}

if ( 'concurrency' === $mode ) {
	$runner = $root . '/constitutional-guard/testing-interface/mysql-repository-runner.php';
	list( $status ) = iron_warden_capture( array( PHP_BINARY, $runner ) );
	exit( $status );
}

if ( in_array( $mode, array( 'fresh-smoke', 'multisite-smoke' ), true ) ) {
	$wp = warden_wp_cli( $root ); $path = realpath( (string) getenv( 'IRON_WARDEN_WORDPRESS_PATH' ) );
	if ( '1' !== getenv( 'IRON_WARDEN_DISPOSABLE_WORDPRESS' ) || '' === $wp || false === $path || ! is_dir( $path ) || ! is_file( $zip ) ) exit( 2 );
	require_once __DIR__ . '/package-smoke.php';
	$base = array( $wp, '--path=' . $path, '--no-color' );
	$capture = static fn( array $arguments ): array => iron_warden_capture( array_merge( $base, $arguments ) );
	$status = iron_warden_package_smoke( $mode, $zip, __DIR__ . '/wp-state.php', $capture );
	exit( $status );
}
exit( 2 );
