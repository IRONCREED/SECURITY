<?php
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
$zip = getenv( 'WARDEN_ZIP' );
Iron_Warden_Test_Driver::assert( is_string( $zip ) && is_file( $zip ), 'Production ZIP is unavailable.' );
$status = Iron_Warden_Test_Driver::command( 'unzip -Z1 ' . escapeshellarg( $zip ), $contents );
Iron_Warden_Test_Driver::assert( 0 === $status, 'Production ZIP cannot be listed.' );
Iron_Warden_Test_Driver::assert( ! preg_match( '~/(tests|tools|vendor|constitutional-guard)/|/(composer\.(json|lock)|RELEASE-CANDIDATE\.md)$~', $contents ), 'Production ZIP contains development files.' );
Iron_Warden_Test_Driver::assert( str_contains( $contents, 'ironcreed-request-log/ironcreed-request-log.php' ), 'Production ZIP lacks the plugin bootstrap.' );
