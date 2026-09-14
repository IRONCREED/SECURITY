<?php
/** Load plugin classes. */

namespace Ironcreed\Request_Log;

defined( 'ABSPATH' ) || exit;

$ironcreed_request_log_files = array(
	'domain/class-uri-normalizer.php',
	'domain/class-event.php',
	'application/interface-http-client.php',
	'application/interface-provider.php',
	'infrastructure/class-wp-http-client.php',
	'infrastructure/class-hosting-ukraine-provider.php',
	'infrastructure/class-event-repository.php',
	'infrastructure/class-lifecycle.php',
	'suite/class-suite-menu.php',
	'admin/class-admin-controller.php',
	'class-plugin.php',
);
foreach ( $ironcreed_request_log_files as $ironcreed_request_log_file ) {
	require_once __DIR__ . '/' . $ironcreed_request_log_file;
}
unset( $ironcreed_request_log_files, $ironcreed_request_log_file );
