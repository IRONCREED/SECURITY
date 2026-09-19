<?php
/**
 * Plugin lifecycle and Multisite batching.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log;

/** Installs and removes site-local plugin state. */
final class Lifecycle {
	private const BATCH_SIZE  = 100;
	private const RETRY_DELAY = 300;

	/**
	 * Activate one site or every site in a network.
	 *
	 * @param bool $network_wide Whether activation applies to the network.
	 */
	public static function activate( bool $network_wide = false ): void {
		self::guard_activation();
		if ( is_multisite() && $network_wide ) {
			self::each_site( array( self::class, 'install_site_or_fail' ) );
			update_site_option( 'ironcreed_request_log_network_active', '1' );
			return;
		}

		self::install_site_or_fail();
	}

	/**
	 * Deactivate one site or every site in a network.
	 *
	 * @param bool $network_wide Whether activation applies to the network.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			delete_site_option( 'ironcreed_request_log_network_active' );
			self::each_site( array( self::class, 'deactivate_site' ) );
			return;
		}

		self::deactivate_site();
	}

	/**
	 * Provision a new site created during network activation.
	 *
	 * @param \WP_Site $site Newly initialized WordPress site.
	 */
	public static function initialize_new_site( \WP_Site $site ): void {
		if ( '1' !== get_site_option( 'ironcreed_request_log_network_active', '0' ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		try {
			self::install_site();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Create or update one site's schema and defaults.
	 */
	public static function install_site(): bool {
		if ( ! self::migrate_schema() ) {
			return false;
		}

		add_option( 'ironcreed_request_log_enabled', '0', '', false );
		add_option( 'ironcreed_request_log_retention', 24, '', false );
		add_option( 'ironcreed_request_log_cap', 10000, '', false );

		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'view_ironcreed_request_log' );
			$role->add_cap( 'manage_ironcreed_request_log' );
		}

		if ( ! wp_next_scheduled( 'ironcreed_request_log_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ironcreed_request_log_cleanup' );
		}
		return true;
	}

	/**
	 * Apply schema upgrades during ordinary bootstrap as well as activation.
	 */
	public static function maybe_upgrade(): bool {
		if ( '2' === get_option( 'ironcreed_request_log_schema', '' ) && '1' === get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			return true;
		}
		if ( time() < (int) get_option( 'ironcreed_request_log_migration_retry_after', 0 ) ) {
			return false;
		}
		return self::migrate_schema();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Plugin-owned schema migration must inspect and change the real database, without stale cache.
	/**
	 * Idempotently create schema 2 and migrate empty Runtime fingerprints.
	 */
	private static function migrate_schema(): bool {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'ironcreed_request_log_events';
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			observed_at datetime NOT NULL,
			method varchar(12) NOT NULL,
			path varchar(2048) NOT NULL,
			query text NOT NULL,
			status smallint unsigned NOT NULL DEFAULT 0,
			duration_ms int unsigned NOT NULL DEFAULT 0,
			route_kind varchar(20) NOT NULL DEFAULT 'unknown',
			response_bytes bigint unsigned NOT NULL DEFAULT 0,
			client_ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(512) NOT NULL DEFAULT '',
			referer varchar(2048) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL,
			fingerprint char(64) NULL DEFAULT NULL,
			PRIMARY KEY (event_id),
			UNIQUE KEY source_fingerprint (source, fingerprint),
			KEY observed_at (observed_at),
			KEY source (source),
			KEY status (status),
			KEY method (method),
			KEY route_kind (route_kind)
		) ENGINE=InnoDB {$collate};";
		$changes = dbDelta( $sql );
		$engine  = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		if ( is_string( $engine ) && 'InnoDB' !== $engine ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ENGINE=InnoDB', $table ) ) ) {
				return self::storage_failure( 'engine-conversion-failed' );
			}
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		}
		if ( ! is_array( $changes ) || 'InnoDB' !== $engine || '' !== $wpdb->last_error ) {
			return self::storage_failure( 'schema-not-ready' );
		}
		if ( false === $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY fingerprint char(64) NULL DEFAULT NULL', $table ) ) ) {
			return self::storage_failure( 'fingerprint-schema-failed' );
		}
		if ( false === $wpdb->query( $wpdb->prepare( "UPDATE %i SET fingerprint = NULL WHERE source = 'wordpress-runtime' AND fingerprint = ''", $table ) ) ) {
			return self::storage_failure( 'fingerprint-migration-failed' );
		}
		$nullable = $wpdb->get_var( $wpdb->prepare( "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'fingerprint'", $table ) );
		if ( 'YES' !== $nullable ) {
			return self::storage_failure( 'fingerprint-schema-unverified' );
		}
		if ( ! update_option( 'ironcreed_request_log_schema', '2', false ) && '2' !== get_option( 'ironcreed_request_log_schema', '' ) ) {
			return self::storage_failure( 'schema-version-write-failed' );
		}
		if ( ! update_option( 'ironcreed_request_log_storage_ready', '1', false ) && '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			return self::storage_failure( 'readiness-write-failed' );
		}
		delete_option( 'ironcreed_request_log_storage_diagnostic' );
		delete_option( 'ironcreed_request_log_migration_retry_after' );
		return true;
	}

	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	/**
	 * Prepare storage or stop activation with a safe error.
	 */
	public static function install_site_or_fail(): void {
		if ( ! self::install_site() ) {
			wp_die( esc_html__( 'Request Log storage could not be prepared safely. Review the storage diagnostic and try again.', 'ironcreed-request-log' ), '', array( 'response' => 500 ) );
		}
	}

	/**
	 * Record a safe storage diagnostic and report failure.
	 *
	 * @param string $code Safe diagnostic code.
	 */
	private static function storage_failure( string $code ): bool {
		update_option( 'ironcreed_request_log_storage_ready', '0', false );
		update_option( 'ironcreed_request_log_storage_diagnostic', sanitize_key( $code ), false );
		update_option( 'ironcreed_request_log_migration_retry_after', time() + self::RETRY_DELAY, false );
		return false;
	}

	/**
	 * Clear one site's scheduled work.
	 */
	public static function deactivate_site(): void {
		wp_clear_scheduled_hook( 'ironcreed_request_log_cleanup' );
		wp_clear_scheduled_hook( 'ironcreed_request_log_import' );
	}

	/**
	 * Remove all state from every site during uninstall.
	 */
	public static function uninstall_network(): void {
		if ( is_multisite() ) {
			self::each_site( array( self::class, 'uninstall_site' ) );
			delete_site_option( 'ironcreed_request_log_network_active' );
			return;
		}

		self::uninstall_site();
	}

	/**
	 * Remove all state from one site.
	 */
	public static function uninstall_site(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'ironcreed_request_log_events';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall removes the plugin-owned table using an identifier placeholder.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

		foreach ( array( 'schema', 'storage_ready', 'storage_diagnostic', 'migration_retry_after', 'enabled', 'retention', 'cap', 'credentials', 'runtime_diagnostic', 'cleanup_diagnostic', 'import_interval', 'import_status' ) as $suffix ) {
			delete_option( 'ironcreed_request_log_' . $suffix );
		}
		self::deactivate_site();

		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->remove_cap( 'view_ironcreed_request_log' );
			$role->remove_cap( 'manage_ironcreed_request_log' );
		}
	}

	/**
	 * Reject activation when any active path declares this Product ID.
	 */
	private static function guard_activation(): void {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$current = plugin_basename( IRONCREED_REQUEST_LOG_FILE );
		foreach ( array_unique( $plugins ) as $plugin ) {
			if ( $plugin === $current ) {
				continue;
			}

			$file = WP_PLUGIN_DIR . '/' . $plugin;
			if ( ! is_file( $file ) ) {
				continue;
			}
			$data = get_file_data( $file, array( 'product_id' => 'Product ID' ) );
			if ( IRONCREED_REQUEST_LOG_PRODUCT_ID === ( $data['product_id'] ?? '' ) ) {
				wp_die( esc_html__( 'Another active copy of IRONCREED Request Log has the same Product ID. Keep only one copy active.', 'ironcreed-request-log' ) );
			}
		}
	}

	/**
	 * Visit every site in bounded ID batches.
	 *
	 * @param callable $callback Site-local operation.
	 */
	private static function each_site( callable $callback ): void {
		$offset = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => self::BATCH_SIZE,
					'offset' => $offset,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					call_user_func( $callback );
				} finally {
					restore_current_blog();
				}
			}
			$offset    += self::BATCH_SIZE;
			$site_count = count( $site_ids );
		} while ( self::BATCH_SIZE === $site_count );
	}
}
