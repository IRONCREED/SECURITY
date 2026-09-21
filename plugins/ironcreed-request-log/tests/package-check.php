<?php
$root = dirname( __DIR__ );
$required = array( 'ironcreed-request-log.php', 'uninstall.php', 'readme.txt', 'license.txt', 'changelog.txt', 'docs/HOSTING-UKRAINE-API-CONTRACT.md', 'assets/admin.css', 'assets/admin.js', 'languages/ironcreed-request-log.pot', 'languages/ironcreed-request-log-uk.po', 'languages/ironcreed-request-log-uk.mo' );
foreach ( $required as $file ) { if ( ! is_file( $root . '/' . $file ) ) { fwrite( STDERR, "Missing {$file}\n" ); exit( 1 ); } }
$runtime_roots = array( 'ironcreed-request-log.php', 'uninstall.php', 'readme.txt', 'license.txt', 'changelog.txt', 'includes', 'docs/HOSTING-UKRAINE-API-CONTRACT.md', 'assets/admin.css', 'assets/admin.js', 'languages/ironcreed-request-log.pot', 'languages/ironcreed-request-log-uk.po', 'languages/ironcreed-request-log-uk.mo' );
foreach ( $runtime_roots as $runtime_root ) {
	$path = $root . '/' . $runtime_root;
	$files = is_dir( $path ) ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) : array( new SplFileInfo( $path ) );
	foreach ( $files as $file ) { if ( ! $file->isFile() ) continue; $contents=file_get_contents($file->getPathname()); if ( preg_match('/Bearer\s+(?!<token>|test-token-not-a-secret)[A-Za-z0-9._-]{12,}/',$contents) ) { fwrite(STDERR,"Possible credential in {$file}\n"); exit(1); } }
}
$composition = file_get_contents( $root . '/includes/class-plugin.php' );
if ( false !== strpos( $composition, 'load_plugin_textdomain(' ) ) {
	fwrite( STDERR, "WordPress.org packages must rely on directory language packs.\n" );
	exit( 1 );
}
echo "Package assertions passed.\n";
