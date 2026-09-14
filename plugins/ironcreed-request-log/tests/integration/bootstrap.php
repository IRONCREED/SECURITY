<?php
$tests = getenv( 'WP_TESTS_DIR' );
if ( ! is_string( $tests ) || ! is_file( $tests . '/includes/functions.php' ) ) throw new RuntimeException( 'Official WordPress tests are unavailable.' );
require_once $tests . '/includes/functions.php';
tests_add_filter( 'muplugins_loaded', static function (): void { require dirname( __DIR__, 2 ) . '/ironcreed-request-log.php'; } );
require $tests . '/includes/bootstrap.php';
