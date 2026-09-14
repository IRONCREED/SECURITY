<?php
/**
 * Plugin Name:       IRONCREED Request Log
 * Description:       Privacy-aware request logging for WordPress Runtime and opt-in Hosting Ukraine nginx logs.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            IRONCREED
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ironcreed-request-log
 * Product ID:        ironcreed/request-log
 *
 * @package Ironcreed_Request_Log
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $GLOBALS['ironcreed_request_log_instance'] ) ) {
	$GLOBALS['ironcreed_request_log_conflict'] = array(
		$GLOBALS['ironcreed_request_log_instance'],
		plugin_basename( __FILE__ ),
	);
	register_activation_hook(
		__FILE__,
		static function (): void {
			wp_die( esc_html__( 'Another active copy of IRONCREED Request Log has the same Product ID. Keep only one copy active.', 'ironcreed-request-log' ) );
		}
	);
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$copies = $GLOBALS['ironcreed_request_log_conflict'] ?? array();
			$message = sprintf(
				/* translators: 1: first plugin basename, 2: conflicting plugin basename. */
				__( 'Conflicting IRONCREED Request Log copies loaded: %1$s and %2$s. Keep one copy active.', 'ironcreed-request-log' ),
				$copies[0] ?? '',
				$copies[1] ?? ''
			);
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	);
	return;
}

$GLOBALS['ironcreed_request_log_instance'] = plugin_basename( __FILE__ );

define( 'IRONCREED_REQUEST_LOG_VERSION', '1.0.0' );
define( 'IRONCREED_REQUEST_LOG_FILE', __FILE__ );
define( 'IRONCREED_REQUEST_LOG_DIR', __DIR__ );
define( 'IRONCREED_REQUEST_LOG_PRODUCT_ID', 'ironcreed/request-log' );

require_once __DIR__ . '/includes/bootstrap.php';

register_activation_hook( __FILE__, array( \Ironcreed\Request_Log\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Ironcreed\Request_Log\Lifecycle::class, 'deactivate' ) );

\Ironcreed\Request_Log\Plugin::instance()->register();
