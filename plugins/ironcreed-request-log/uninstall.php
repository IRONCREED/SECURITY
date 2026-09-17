<?php
/**
 * Uninstall IRONCREED Request Log.
 *
 * @package Ironcreed_Request_Log
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/bootstrap.php';

\Ironcreed\Request_Log\Lifecycle::uninstall_network();
