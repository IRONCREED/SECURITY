<?php
/** Strict result boundary for the mutable current suite. */
require_once __DIR__ . '/process.php';
require_once __DIR__ . '/phpunit-result.php';
$plugin = dirname( __DIR__, 2 ) . '/plugins/ironcreed-request-log';
$binary = $plugin . '/vendor/bin/phpunit';
if ( ! is_file( $binary ) || ! is_executable( $binary ) ) exit( 2 );
list( $status, $stdout, $stderr ) = iron_warden_capture( array( $binary, '-c', $plugin . '/phpunit.xml.dist', '--do-not-cache-result' ) );
exit( iron_warden_phpunit_passed( $status, $stdout . $stderr ) ? 0 : 1 );
