<?php
/**
 * Plugin composition root.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log;

use Ironcreed\Request_Log\Admin\Admin_Controller;
use Ironcreed\Request_Log\Domain\Event;
use Ironcreed\Request_Log\Domain\URI_Normalizer;
use Ironcreed\Request_Log\Infrastructure\Event_Repository;
use Ironcreed\Request_Log\Infrastructure\Provider_Import;
use Ironcreed\Request_Log\Suite\Suite_Menu;

/** Composes WordPress adapters without adding cross-plugin dependencies. */
final class Plugin {
	/**
	 * Process-local plugin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Monotonic request start time.
	 *
	 * @var float Monotonic request start.
	 */
	private float $started;

	/**
	 * Return the process-local plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the plugin's hooks.
	 */
	public function register(): void {
		global $wpdb;
		$storage_ready = Lifecycle::maybe_upgrade();
		$repository    = new Event_Repository( $wpdb );
		$admin         = new Admin_Controller( $repository );

		( new Suite_Menu( array( $admin, 'render_log' ) ) )->register();
		$admin->register();
		add_action( 'init', array( Provider_Import::class, 'restore_schedule' ) );
		add_action( Provider_Import::HOOK, array( new Provider_Import( $wpdb ), 'scheduled' ) );

		add_action( 'admin_init', array( $this, 'register_privacy_content' ) );
		add_action( 'wp_initialize_site', array( Lifecycle::class, 'initialize_new_site' ), 20 );
		if ( ! $storage_ready ) {
			return;
		}
		add_action( 'ironcreed_request_log_cleanup', array( $this, 'cleanup' ) );

		if ( '1' === get_option( 'ironcreed_request_log_enabled', '0' ) && ! self::is_plugin_request() ) {
			$this->started = hrtime( true ) / 1e9;
			add_action( 'shutdown', array( $this, 'observe' ), PHP_INT_MAX );
		}
	}

	/**
	 * Remove expired records and enforce the storage cap.
	 */
	public function cleanup(): void {
		global $wpdb;
		try {
			( new Event_Repository( $wpdb ) )->cleanup();
		} catch ( \Throwable $error ) {
			update_option( 'ironcreed_request_log_cleanup_diagnostic', 'storage-failure', false );
		}
	}

	/**
	 * Persist one request after WordPress finishes generating its response.
	 */
	public function observe(): void {
		global $wpdb;
		$uri = URI_Normalizer::normalize(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URI_Normalizer bounds, validates UTF-8 and redacts encoded query keys; text sanitization would destroy percent escapes.
			wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ),
			(array) apply_filters( 'ironcreed_request_log_sensitive_query_keys', array() )
		);
		$event = Event::validate(
			array(
				'method'      => sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ),
				'path'        => $uri['path'],
				'query'       => $uri['query'],
				'status'      => http_response_code(),
				'duration_ms' => (int) ( ( hrtime( true ) / 1e9 - $this->started ) * 1000 ),
				'route_kind'  => self::classify_route(),
				'source'      => 'wordpress-runtime',
			)
		);

		try {
			( new Event_Repository( $wpdb ) )->insert_runtime( $event );
		} catch ( \Throwable $error ) {
			update_option( 'ironcreed_request_log_runtime_diagnostic', 'storage-failure', false );
		}
	}

	/**
	 * Classify the current WordPress execution route.
	 */
	public static function classify_route(): string {
		$path = self::request_path();
		if ( wp_doing_ajax() || '/wp-admin/admin-ajax.php' === $path ) {
			return 'ajax';
		}
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xml-rpc';
		}
		if ( '/wp-login.php' === $path ) {
			return 'login';
		}
		if ( is_admin() || str_starts_with( $path, '/wp-admin/' ) ) {
			return 'admin';
		}

		return 'front-end';
	}

	/**
	 * Register suggested Privacy Policy Guide content.
	 */
	public function register_privacy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'WordPress Runtime logging and the Hosting Ukraine connection are disabled by default. Runtime records stay in this site database and contain UTC time, method, redacted URI, status, duration, route type, and source. They omit IP address, User-Agent, Referer, bodies, cookies, and authorization data.', 'ironcreed-request-log' ) . '</p>';
		$content .= '<p>' . esc_html__( 'An administrator may resolve a domain through Hosting Ukraine, test a connection or fetch today’s nginx access log. Domain lookup sends the Bearer token and type=host to adm.tools, receives the accessible host-service list, and matches the domain locally without storing that list. Test and import send the saved Bearer token and matched host ID to adm.tools and may receive IP addresses, URIs, response status and size, User-Agent, and Referer. Imported records remain local, follow the configured retention, and can be cleared by source. Disconnect deletes credentials; uninstall deletes credentials, settings, schedules, capabilities, and records. IRONCREED and other services receive no fetched logs.', 'ironcreed-request-log' ) . '</p>';
		wp_add_privacy_policy_content( __( 'IRONCREED Request Log', 'ironcreed-request-log' ), wp_kses_post( wpautop( $content ) ) );
	}

	/**
	 * Exclude this plugin's own administrative actions and viewer.
	 */
	private static function is_plugin_request(): bool {
		$path = self::request_path();
		if ( is_admin() && in_array( $path, array( wp_parse_url( admin_url( 'tools.php' ) )['path'], wp_parse_url( admin_url( 'admin.php' ) )['path'] ), true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route classification reads only; authorization belongs to the handler.
			$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
			return in_array( $page, array( 'ironcreed-request-log', 'ironcreed-request-log-settings', 'ironcreed-security' ), true );
		}

		if ( is_admin() && in_array( $path, array( wp_parse_url( admin_url( 'admin-post.php' ) )['path'], wp_parse_url( admin_url( 'admin-ajax.php' ) )['path'] ), true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route classification only; handlers verify their own nonces.
			$action  = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
			$allowed = array( 'settings', 'clear', 'connect', 'disconnect', 'test', 'fetch', 'schedule' );
			$allowed = array_map( static fn( string $name ): string => 'ironcreed_request_log_' . $name, $allowed );
			return in_array( $action, $allowed, true );
		}

		return false;
	}

	/**
	 * Return only the normalized URL path; query parameters never classify a route.
	 */
	private static function request_path(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Dedicated URI normalization preserves encoded paths and strips invalid control characters.
		$uri = URI_Normalizer::normalize( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
		return $uri['path'];
	}
}
