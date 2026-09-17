<?php
/** Fixed WP-CLI assertions; disposable installations only, no environment shell text. */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$mode = (string) ( $args[0] ?? '' );
$slug = 'ironcreed-request-log/ironcreed-request-log.php';
$directory = WP_PLUGIN_DIR . '/ironcreed-request-log';
$fail = static function (): void { throw new RuntimeException( 'Request Log state assertion failed.' ); };
$site_ids = static function (): array {
	$ids = is_multisite() ? get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) : array( get_current_blog_id() );
	$ids = array_map( 'intval', $ids ); sort( $ids ); return $ids;
};
$each_site = static function ( callable $check ) use ( $site_ids ): void {
	foreach ( $site_ids() as $id ) { switch_to_blog( $id ); try { $check( $id ); } finally { restore_current_blog(); } }
};
$options = array( 'schema', 'storage_ready', 'storage_diagnostic', 'migration_retry_after', 'enabled', 'retention', 'cap', 'credentials', 'runtime_diagnostic', 'cleanup_diagnostic', 'import_interval', 'import_status' );
$caps = array( 'view_ironcreed_request_log', 'manage_ironcreed_request_log' );
$site_ready = static function ( bool $active ) use ( $fail, $caps ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'ironcreed_request_log_events';
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	$nullable = $wpdb->get_var( $wpdb->prepare( "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'fingerprint'", $table ) );
	if ( 'InnoDB' !== $engine || 'YES' !== $nullable || '2' !== get_option( 'ironcreed_request_log_schema' ) || '1' !== get_option( 'ironcreed_request_log_storage_ready' ) || $wpdb->last_error ) $fail();
	if ( '0' !== get_option( 'ironcreed_request_log_enabled' ) || 24 !== (int) get_option( 'ironcreed_request_log_retention' ) || 10000 !== (int) get_option( 'ironcreed_request_log_cap' ) || $active !== (bool) wp_next_scheduled( 'ironcreed_request_log_cleanup' ) ) $fail();
	if ( wp_next_scheduled( 'ironcreed_request_log_import' ) ) $fail();
	$role = get_role( 'administrator' );
	foreach ( $caps as $cap ) if ( ! $role || ! $role->has_cap( $cap ) ) $fail();
};
$site_removed = static function () use ( $fail, $options, $caps ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'ironcreed_request_log_events';
	$found = $wpdb->get_var( $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	if ( null !== $found || $wpdb->last_error ) $fail();
	foreach ( $options as $suffix ) if ( false !== get_option( 'ironcreed_request_log_' . $suffix, false ) ) $fail();
	$role = get_role( 'administrator' );
	if ( wp_next_scheduled( 'ironcreed_request_log_cleanup' ) || wp_next_scheduled( 'ironcreed_request_log_import' ) ) $fail();
	foreach ( $caps as $cap ) if ( $role && $role->has_cap( $cap ) ) $fail();
};
try {
	if ( 'topology' === $mode ) { WP_CLI::line( is_multisite() ? 'multisite' : 'single' ); return; }
	if ( 'site-ids' === $mode ) { WP_CLI::line( wp_json_encode( $site_ids() ) ); return; }
	if ( 'installed' === $mode ) {
		if ( ! is_dir( $directory ) || ! is_file( $directory . '/ironcreed-request-log.php' ) || ! isset( get_plugins()[ $slug ] ) ) $fail();
	} elseif ( in_array( $mode, array( 'create-test-site', 'delete-test-site' ), true ) ) {
		$token = (string) ( $args[1] ?? '' );
		if ( ! is_multisite() || ! preg_match( '/^warden-[a-f0-9]{24}$/', $token ) ) $fail();
		$network = get_network();
		$domain = is_subdomain_install() ? $token . '.' . $network->domain : $network->domain;
		$path = is_subdomain_install() ? $network->path : trailingslashit( $network->path ) . $token . '/';
		$matching = get_sites( array( 'domain' => $domain, 'path' => $path, 'network_id' => (int) $network->id, 'number' => 0 ) );
		if ( 'create-test-site' === $mode ) {
			if ( $matching ) $fail();
			$id = wp_insert_site( array( 'domain' => $domain, 'path' => $path, 'network_id' => (int) $network->id ) );
			if ( is_wp_error( $id ) || (int) $id < 1 ) $fail();
			WP_CLI::line( (string) $id ); return;
		}
		$original = json_decode( (string) ( $args[2] ?? '' ), true );
		if ( ! is_array( $original ) || ! $original || array_filter( $original, static fn( $id ): bool => ! is_int( $id ) || $id < 1 ) ) $fail();
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		foreach ( $matching as $site ) {
			$id = (int) $site->blog_id;
			if ( in_array( $id, $original, true ) || is_main_site( $id ) ) $fail();
			wpmu_delete_blog( $id, true );
			if ( get_site( $id ) ) $fail();
		}
	} elseif ( in_array( $mode, array( 'fresh-active', 'fresh-inactive', 'network-active', 'network-inactive' ), true ) ) {
		$network = str_starts_with( $mode, 'network-' ); $active = str_ends_with( $mode, '-active' );
		if ( $network !== is_multisite() || ! is_file( $directory . '/ironcreed-request-log.php' ) ) $fail();
		if ( $network && ( $active !== is_plugin_active_for_network( $slug ) || ( $active ? '1' !== get_site_option( 'ironcreed_request_log_network_active' ) : false !== get_site_option( 'ironcreed_request_log_network_active', false ) ) ) ) $fail();
		if ( isset( $args[1] ) && ! in_array( (int) $args[1], $site_ids(), true ) ) $fail();
		$each_site( static function () use ( $slug, $active, $fail, $site_ready ): void { if ( $active !== is_plugin_active( $slug ) ) $fail(); $site_ready( $active ); } );
	} elseif ( in_array( $mode, array( 'fresh-absent', 'fresh-removed', 'network-absent', 'network-removed' ), true ) ) {
		if ( str_starts_with( $mode, 'network-' ) !== is_multisite() || is_dir( $directory ) || isset( get_plugins()[ $slug ] ) || is_plugin_active_for_network( $slug ) || false !== get_site_option( 'ironcreed_request_log_network_active', false ) ) $fail();
		$each_site( static function () use ( $slug, $fail, $site_removed ): void { if ( is_plugin_active( $slug ) ) $fail(); $site_removed(); } );
	} elseif ( 'seed-persistent-state' === $mode ) {
		$each_site( static function () use ( $fail ): void {
			global $wpdb;
			foreach ( array( 'credentials' => array( 'synthetic' => 'warden-fixture' ), 'storage_diagnostic' => 'synthetic-smoke', 'runtime_diagnostic' => 'synthetic-smoke', 'cleanup_diagnostic' => 'synthetic-smoke', 'migration_retry_after' => 0, 'import_interval' => 300, 'import_status' => array( 'state' => 'success', 'count' => 2 ) ) as $suffix => $value ) {
				update_option( 'ironcreed_request_log_' . $suffix, $value, false );
				if ( get_option( 'ironcreed_request_log_' . $suffix ) != $value ) $fail();
			}
			if ( ! wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'ironcreed_request_log_import' ) || ! wp_next_scheduled( 'ironcreed_request_log_import' ) ) $fail();
			$row = array( 'observed_at' => '2020-01-01 00:00:00', 'method' => 'GET', 'path' => '/warden-fixture', 'query' => '', 'status' => 200, 'duration_ms' => 1, 'route_kind' => 'front-end', 'source' => 'wordpress-runtime', 'fingerprint' => null, 'response_bytes' => 0, 'client_ip' => '', 'user_agent' => '', 'referer' => '' );
			if ( 1 !== $wpdb->insert( $wpdb->prefix . 'ironcreed_request_log_events', $row ) ) $fail();
		} );
	} elseif ( 'persistent-digest' === $mode ) {
		$snapshot = array();
		$each_site( static function ( int $id ) use ( &$snapshot, $options, $caps, $fail ): void {
			global $wpdb;
			$rows = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'ironcreed_request_log_events` ORDER BY event_id', ARRAY_A );
			if ( ! $rows || $wpdb->last_error ) $fail();
			$values = array(); foreach ( $options as $suffix ) $values[ $suffix ] = get_option( 'ironcreed_request_log_' . $suffix, null );
			$role = get_role( 'administrator' ); $capabilities = array(); foreach ( $caps as $cap ) $capabilities[ $cap ] = $role && $role->has_cap( $cap );
			$snapshot[ $id ] = array( 'rows' => $rows, 'options' => $values, 'capabilities' => $capabilities );
		} );
		WP_CLI::line( hash( 'sha256', wp_json_encode( $snapshot ) ) ); return;
	} else $fail();
} catch ( Throwable $error ) { WP_CLI::error( 'Request Log state assertion failed.' ); }
WP_CLI::success( 'Request Log state verified.' );
