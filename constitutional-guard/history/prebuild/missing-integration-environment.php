<?php
/** An empty environment path must never resolve to the plugin working directory. */
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
$root = Iron_Warden_Test_Driver::repository_root();
$previous = getcwd();
Iron_Warden_Test_Driver::assert( is_executable( Iron_Warden_Test_Driver::plugin_root() . '/vendor/bin/phpunit' ), 'A locked PHPUnit is required for this regression.' );
try {
    chdir( Iron_Warden_Test_Driver::plugin_root() );
    foreach ( array( 'wordpress-current', 'multisite-current' ) as $mode ) {
        foreach ( array( '', ' ', Iron_Warden_Test_Driver::plugin_root() ) as $invalid ) {
            [$status, $stdout, $stderr] = iron_warden_capture( array( PHP_BINARY, $root . '/constitutional-guard/testing-interface/integration-runner.php', $mode ), array( 'IRON_WARDEN_WORDPRESS_TESTS_DIR' => $invalid ) );
            Iron_Warden_Test_Driver::assert( 2 === $status, 'Missing or incomplete WordPress tests must be unavailable, never executed.' );
        }
    }
} finally { chdir( $previous ); }
