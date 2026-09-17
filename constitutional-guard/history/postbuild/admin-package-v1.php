<?php
/** Production archive must contain only the complete runtime allowlist. */
require_once dirname( __DIR__, 2 ) . '/testing-interface/driver.php';
$zip = getenv( 'WARDEN_ZIP' );
Iron_Warden_Test_Driver::assert( is_string( $zip ) && is_file( $zip ), 'Production ZIP is unavailable.' );
[$status, $stdout, $stderr] = iron_warden_capture( array( 'unzip', '-Z1', $zip ) );
Iron_Warden_Test_Driver::assert( 0 === $status, 'The archive cannot be inspected.' );
$actual = explode( "\n", trim( $stdout ) );
$relative = array( 'ironcreed-request-log.php', 'uninstall.php', 'readme.txt', 'license.txt', 'changelog.txt', 'docs/HOSTING-UKRAINE-API-CONTRACT.md', 'assets/admin.css', 'assets/admin.js', 'languages/ironcreed-request-log.pot', 'languages/ironcreed-request-log-uk.po', 'languages/ironcreed-request-log-uk.mo' );
$root = Iron_Warden_Test_Driver::plugin_root();
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
    Iron_Warden_Test_Driver::assert( $file->isFile() && ! $file->isLink() && 'php' === $file->getExtension(), 'Unexpected runtime source file.' );
    $relative[] = substr( $file->getPathname(), strlen( $root ) + 1 );
}
$expected = array_map( static fn( $file ) => 'ironcreed-request-log/' . $file, $relative );
sort( $expected ); sort( $actual );
Iron_Warden_Test_Driver::assert( $actual === $expected, 'Incomplete runtime archive or unexpected development files.' );
foreach ( $relative as $file ) {
    [$status, $contents, $stderr] = iron_warden_capture( array( 'unzip', '-p', $zip, 'ironcreed-request-log/' . $file ) );
    Iron_Warden_Test_Driver::assert( 0 === $status && hash_file( 'sha256', $root . '/' . $file ) === hash( 'sha256', $contents ), 'Archive bytes differ from verified source.' );
}
