<?php
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
$root = Iron_Warden_Test_Driver::repository_root();
$directory = sys_get_temp_dir() . '/iron-warden-stale-' . getmypid();
mkdir( $directory, 0700, true );
$zip = $directory . '/ironcreed-request-log-1.0.0.zip';
file_put_contents( $directory . '/stale.txt', 'synthetic stale entry' );
Iron_Warden_Test_Driver::command( 'cd ' . escapeshellarg( $directory ) . ' && zip -q ' . escapeshellarg( $zip ) . ' stale.txt' );
$status = Iron_Warden_Test_Driver::command( 'bash ' . escapeshellarg( $root . '/plugins/ironcreed-request-log/tools/build.sh' ) . ' ' . escapeshellarg( $directory ), $output );
Iron_Warden_Test_Driver::assert( 0 === $status, 'Production rebuild failed.' );
Iron_Warden_Test_Driver::command( 'unzip -Z1 ' . escapeshellarg( $zip ), $contents );
Iron_Warden_Test_Driver::assert( ! str_contains( $contents, 'stale.txt' ), 'Production rebuild retained a stale ZIP entry.' );
@unlink( $directory . '/stale.txt' );
@unlink( $zip );
@rmdir( $directory );
