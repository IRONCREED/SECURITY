<?php
/** Stable repository testing boundary for IRON WARDEN historical tests. */

require_once __DIR__ . '/process.php';
require_once __DIR__ . '/phpunit-result.php';

final class Iron_Warden_Test_Driver {
	public static function repository_root(): string {
		return dirname( __DIR__, 2 );
	}

	public static function plugin_root(): string {
		return self::repository_root() . '/plugins/ironcreed-request-log';
	}

	public static function source( string $relative ): string {
		$contents = file_get_contents( self::repository_root() . '/' . ltrim( $relative, '/' ) );
		if ( false === $contents ) {
			throw new RuntimeException( 'Required source file is unavailable: ' . $relative );
		}
		return $contents;
	}

	public static function assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	public static function command( string $command, ?string &$output = null ): int {
		exec( $command . ' 2>&1', $lines, $status );
		$output = implode( "\n", $lines );
		return $status;
	}

	public static function apply_wordpress_filter( string $hook, mixed $value, mixed ...$arguments ): mixed {
		if ( ! function_exists( 'apply_filters' ) ) throw new RuntimeException( 'WordPress hooks are unavailable.' );
		return apply_filters( $hook, $value, ...$arguments );
	}

	public static function dispatch_wordpress_action( string $hook, mixed ...$arguments ): void {
		if ( ! function_exists( 'do_action' ) ) throw new RuntimeException( 'WordPress hooks are unavailable.' );
		do_action( $hook, ...$arguments );
	}

	public static function run_phpunit( string $filter ): void {
		self::phpunit( self::plugin_root() . '/phpunit.xml.dist', array( '--filter', $filter ) );
	}

	public static function run_historical_suite( string $suite ): void {
		if ( ! in_array( $suite, array( 'provider', 'runtime', 'storage', 'warden', 'provider-v4', 'runtime-v3', 'storage-v4', 'warden-v4', 'admin-refresh-v1' ), true ) ) throw new RuntimeException( 'Unknown historical suite.' );
		self::phpunit( self::plugin_root() . '/tests/historical/' . $suite . '/phpunit.xml' );
	}

	private static function phpunit( string $configuration, array $arguments = array() ): void {
		$binary = self::plugin_root() . '/vendor/bin/phpunit';
		if ( ! is_file( $binary ) || ! is_executable( $binary ) ) throw new RuntimeException( 'PHPUnit is unavailable for a historical behavior test.' );
		list( $status, $stdout, $stderr ) = iron_warden_capture( array_merge( array( $binary, '-c', $configuration, '--do-not-cache-result' ), $arguments ) );
		if ( ! iron_warden_phpunit_passed( $status, $stdout . $stderr ) ) throw new RuntimeException( 'Historical PHPUnit result failed validation.' );
	}

	public static function run_environment_boundary( string $variable ): void {
		$map = array( 'IRON_WARDEN_MULTISITE_TEST_COMMAND' => 'multisite-current' );
		if ( ! isset( $map[ $variable ] ) ) throw new RuntimeException( 'Unknown integration boundary.' );
		$status = self::command( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( self::repository_root() . '/constitutional-guard/testing-interface/integration-runner.php' ) . ' ' . escapeshellarg( $map[ $variable ] ), $output );
		if ( 0 !== $status ) throw new RuntimeException( $output ?: 'WordPress testing boundary failed.' );
	}
}
