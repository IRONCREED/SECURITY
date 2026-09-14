<?php
/** WordPress administration adapter. @package Ironcreed_Request_Log */

namespace Ironcreed\Request_Log\Admin;

use Ironcreed\Request_Log\Domain\Event;
use Ironcreed\Request_Log\Infrastructure\Event_Repository;
use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Provider;
use Ironcreed\Request_Log\Infrastructure\WP_HTTP_Client;
use RuntimeException;

/** Renders screens and processes privileged actions. */
final class Admin_Controller {
	private const SOURCES = array( 'wordpress-runtime', 'hosting-ukraine-nginx' );

	/** @var Event_Repository */
	private Event_Repository $repository;

	/** @param Event_Repository $repository Event storage. */
	public function __construct( Event_Repository $repository ) {
		$this->repository = $repository;
	}

	/** Register admin screens and handlers. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ), 30 );
		foreach ( array( 'settings', 'clear', 'connect', 'disconnect', 'test', 'fetch' ) as $action ) {
			add_action( 'admin_post_ironcreed_request_log_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/** Register the separate Connections and privacy screen. */
	public function register_settings_page(): void {
		add_submenu_page(
			'tools.php',
			__( 'Request Log Settings', 'ironcreed-request-log' ),
			__( 'Request Log Settings', 'ironcreed-request-log' ),
			'manage_ironcreed_request_log',
			'ironcreed-request-log-settings',
			array( $this, 'render_settings' )
		);
	}

	/** Render the source-separated event viewer. */
	public function render_log(): void {
		$this->require_view();
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Request Log', 'ironcreed-request-log' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Request Log storage is unavailable.', 'ironcreed-request-log' ) . '</p></div></div>';
			return;
		}
		$source     = $this->requested_source();
		$page       = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$requested  = absint( $_GET['per_page'] ?? 20 );
		$per_page   = in_array( $requested, array( 20, 50, 100 ), true ) ? $requested : 20;
		$filters    = $this->filters();
		$result     = $this->repository->page( $source, $page, $per_page, $filters );
		$total_pages = max( 1, (int) ceil( $result['total'] / $per_page ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'IRONCREED Request Log', 'ironcreed-request-log' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php $this->render_tabs( $source ); ?>
			<?php $this->render_boundary( $source ); ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=ironcreed-request-log-settings' ) ); ?>"><?php esc_html_e( 'Connections and retention settings', 'ironcreed-request-log' ); ?></a></p>
			<?php $this->render_filters( $source, $filters, $per_page ); ?>
			<?php $this->render_table( $result['items'], $source ); ?>
			<?php $this->render_pagination( $source, $filters, $per_page, $page, $total_pages ); ?>
			<?php $this->render_clear_form( $source ); ?>
		</div>
		<?php
	}

	/** Render the separate settings and connection screen. */
	public function render_settings(): void {
		$this->require_manage();
		$credentials = $this->credentials();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Request Log Settings', 'ironcreed-request-log' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Request Log storage is unavailable. Data collection and provider import remain disabled until the schema is ready.', 'ironcreed-request-log' ); ?></p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Sources', 'ironcreed-request-log' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="ironcreed_request_log_settings">
				<?php wp_nonce_field( 'ironcreed_request_log_settings' ); ?>
				<label><input type="checkbox" name="enabled" value="1" <?php checked( '1', get_option( 'ironcreed_request_log_enabled', '0' ) ); ?>> <?php esc_html_e( 'Enable WordPress Runtime logging', 'ironcreed-request-log' ); ?></label>
				<p class="description"><?php esc_html_e( 'This source records only requests that load WordPress.', 'ironcreed-request-log' ); ?></p>
				<h2><?php esc_html_e( 'Privacy and retention', 'ironcreed-request-log' ); ?></h2>
				<label><?php esc_html_e( 'Retention hours', 'ironcreed-request-log' ); ?> <input type="number" min="1" max="720" name="retention" value="<?php echo esc_attr( get_option( 'ironcreed_request_log_retention', 24 ) ); ?>"></label>
				<label><?php esc_html_e( 'Hard cap', 'ironcreed-request-log' ); ?> <input type="number" min="100" max="100000" name="cap" value="<?php echo esc_attr( get_option( 'ironcreed_request_log_cap', 10000 ) ); ?>"></label>
				<?php submit_button( __( 'Save settings', 'ironcreed-request-log' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Connections', 'ironcreed-request-log' ); ?></h2>
			<label for="ironcreed-provider"><?php esc_html_e( 'Add connection', 'ironcreed-request-log' ); ?></label>
			<select id="ironcreed-provider" disabled><option><?php esc_html_e( 'Hosting Ukraine API', 'ironcreed-request-log' ); ?></option></select>
			<p><?php esc_html_e( 'A manual test or fetch sends the host ID and saved Bearer token to adm.tools and receives a potentially personal nginx access log. The token is stored in a separate non-autoload WordPress option. Imported records remain after disconnect until retention or clearing.', 'ironcreed-request-log' ); ?></p>
			<?php $this->render_connection_form( $credentials ); ?>
		</div>
		<?php
	}

	/** Save source and retention settings. */
	public function handle_settings(): void {
		$this->authorize( 'settings' );
		update_option( 'ironcreed_request_log_enabled', isset( $_POST['enabled'] ) ? '1' : '0', false );
		update_option( 'ironcreed_request_log_retention', max( 1, min( 720, absint( $_POST['retention'] ?? 24 ) ) ), false );
		update_option( 'ironcreed_request_log_cap', max( 100, min( 100000, absint( $_POST['cap'] ?? 10000 ) ) ), false );
		$this->redirect_with_notice( 'success', __( 'Request Log settings were saved.', 'ironcreed-request-log' ), true );
	}

	/** Create or update a valid provider connection. */
	public function handle_connect(): void {
		$this->authorize( 'connect' );
		$existing = $this->credentials();
		$host_id  = absint( $_POST['host_id'] ?? 0 );
		$token    = (string) wp_unslash( $_POST['token'] ?? '' );

		if ( '' === $token && $existing ) {
			$token = (string) $existing['token'];
		}
		if ( $host_id < 1 || ! Hosting_Ukraine_Provider::valid_token( $token ) ) {
			$this->redirect_with_notice( 'error', __( 'Enter a valid host ID and Bearer token.', 'ironcreed-request-log' ), true );
		}

		update_option( 'ironcreed_request_log_credentials', array( 'host_id' => $host_id, 'token' => $token ), false );
		$this->redirect_with_notice( 'success', __( 'The Hosting Ukraine connection was saved. No network request was made.', 'ironcreed-request-log' ), true );
	}

	/** Delete provider credentials while retaining imported records. */
	public function handle_disconnect(): void {
		$this->authorize( 'disconnect' );
		delete_option( 'ironcreed_request_log_credentials' );
		$this->redirect_with_notice( 'success', __( 'The Hosting Ukraine connection was removed. Imported records remain until clearing or retention expiry.', 'ironcreed-request-log' ), true );
	}

	/** Test the configured provider and report a persistent safe result. */
	public function handle_test(): void {
		$this->authorize( 'test' );
		try {
			$events = $this->provider_events();
			foreach ( $events as $unused_event ) {
				break;
			}
			unset( $events );
			$this->redirect_with_notice( 'success', __( 'The Hosting Ukraine connection test succeeded.', 'ironcreed-request-log' ), true );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The Hosting Ukraine connection test failed safely. Verify the credentials and try again.', 'ironcreed-request-log' ), true );
		}
	}

	/** Fetch, normalize, deduplicate, and import today's provider events. */
	public function handle_fetch(): void {
		$this->authorize( 'fetch' );
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) $this->redirect_with_notice( 'error', __( 'Storage is unavailable; no provider request was made.', 'ironcreed-request-log' ) );
		try {
			$count = $this->repository->import( $this->provider_events() );
			$this->redirect_with_notice( 'success', sprintf( __( 'Hosting Ukraine fetch completed. %d new records were imported.', 'ironcreed-request-log' ), $count ) );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The Hosting Ukraine fetch failed safely. Verify the connection and try again.', 'ironcreed-request-log' ) );
		}
	}

	/** Clear exactly one allowlisted source. */
	public function handle_clear(): void {
		$this->authorize( 'clear' );
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) $this->redirect_with_notice( 'error', __( 'Storage is unavailable; no records were cleared.', 'ironcreed-request-log' ) );
		if ( 'yes' !== sanitize_key( wp_unslash( $_POST['confirm_clear'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Explicit confirmation is required to clear request records.', 'ironcreed-request-log' ), '', array( 'response' => 400 ) );
		}
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		if ( ! self::valid_clear_source( $source ) ) {
			wp_die( esc_html__( 'The requested log source is invalid.', 'ironcreed-request-log' ), '', array( 'response' => 400 ) );
		}

		try {
			$count = $this->repository->clear_source( $source );
			$this->redirect_with_notice( 'success', sprintf( __( '%d records were cleared from the selected source.', 'ironcreed-request-log' ), $count ), false, $source );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The selected request records could not be cleared safely.', 'ironcreed-request-log' ), false, $source );
		}
	}

	/** Report whether a source may be cleared. */
	public static function valid_clear_source( string $source ): bool {
		return in_array( $source, self::SOURCES, true );
	}

	/** Return normalized provider events using saved credentials. */
	private function provider_events(): iterable {
		$credentials = $this->credentials();
		if ( ! $credentials ) {
			throw new RuntimeException( 'Connection details are unavailable.' );
		}

		$provider = new Hosting_Ukraine_Provider( new WP_HTTP_Client() );
		return $provider->fetch_today( (int) $credentials['host_id'], (string) $credentials['token'] );
	}

	/** Retrieve only structurally valid credentials. */
	private function credentials(): array {
		$value = get_option( 'ironcreed_request_log_credentials', array() );
		if ( ! is_array( $value ) || (int) ( $value['host_id'] ?? 0 ) < 1 || ! Hosting_Ukraine_Provider::valid_token( (string) ( $value['token'] ?? '' ) ) ) {
			return array();
		}

		return $value;
	}

	/** Enforce management capability before nonce verification. */
	private function authorize( string $action ): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_' . $action );
	}

	/** Require the viewing capability. */
	private function require_view(): void {
		if ( ! current_user_can( 'view_ironcreed_request_log' ) ) {
			wp_die( esc_html__( 'You cannot view this request log.', 'ironcreed-request-log' ), '', array( 'response' => 403 ) );
		}
	}

	/** Require the management capability. */
	private function require_manage(): void {
		if ( ! current_user_can( 'manage_ironcreed_request_log' ) ) {
			wp_die( esc_html__( 'You cannot manage this request log.', 'ironcreed-request-log' ), '', array( 'response' => 403 ) );
		}
	}

	/** Persist a safe notice for the current user and redirect. */
	private function redirect_with_notice( string $type, string $message, bool $settings = false, string $source = 'wordpress-runtime' ): void {
		$key = 'ironcreed_request_log_notice_' . get_current_user_id();
		set_transient( $key, array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		$tab = 'hosting-ukraine-nginx' === $source ? 'hosting' : 'runtime';
		$url = $settings
			? admin_url( 'tools.php?page=ironcreed-request-log-settings' )
			: add_query_arg( array( 'page' => 'ironcreed-request-log', 'source' => $tab ), admin_url( 'tools.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** Render and consume a redirect-persistent user notice. */
	private function render_notice(): void {
		$key    = 'ironcreed_request_log_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $notice ) || ! in_array( $notice['type'] ?? '', array( 'success', 'error' ), true ) ) {
			return;
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ?? '' ) );
	}

	/** Resolve the selected source from the public tab value. */
	private function requested_source(): string {
		return 'hosting' === sanitize_key( wp_unslash( $_GET['source'] ?? '' ) ) ? 'hosting-ukraine-nginx' : 'wordpress-runtime';
	}

	/** Read allowlisted URL filters. */
	private function filters(): array {
		$method = strtoupper( sanitize_key( wp_unslash( $_GET['method'] ?? '' ) ) );
		return array(
			'path'         => sanitize_text_field( wp_unslash( $_GET['path_filter'] ?? '' ) ),
			'method'       => in_array( $method, Event::METHODS, true ) ? $method : '',
			'status_class' => in_array( absint( $_GET['status_class'] ?? 0 ), array( 1, 2, 3, 4, 5 ), true ) ? absint( $_GET['status_class'] ) : 0,
		);
	}

	/** Render source tabs. */
	private function render_tabs( string $source ): void {
		?>
		<nav class="nav-tab-wrapper">
			<a class="nav-tab <?php echo 'wordpress-runtime' === $source ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=ironcreed-request-log&source=runtime' ) ); ?>"><?php esc_html_e( 'WordPress Runtime', 'ironcreed-request-log' ); ?></a>
			<a class="nav-tab <?php echo 'hosting-ukraine-nginx' === $source ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'tools.php?page=ironcreed-request-log&source=hosting' ) ); ?>"><?php esc_html_e( 'Hosting Ukraine', 'ironcreed-request-log' ); ?></a>
		</nav>
		<?php
	}

	/** Render the permanent source boundary. */
	private function render_boundary( string $source ): void {
		$message = 'wordpress-runtime' === $source
			? __( 'This source sees only requests that reach and load WordPress. CDN, WAF, web-server, full-page-cache, and static-file responses remain outside its boundary.', 'ironcreed-request-log' )
			: __( 'This source shows entries returned from Hosting Ukraine nginx access logs. Coverage remains limited to the provider log and period returned by its API.', 'ironcreed-request-log' );
		printf( '<div class="notice notice-info inline"><p>%s</p></div>', esc_html( $message ) );
	}

	/** Render source-preserving filters and page-size selection. */
	private function render_filters( string $source, array $filters, int $per_page ): void {
		$tab = 'wordpress-runtime' === $source ? 'runtime' : 'hosting';
		?>
		<form method="get">
			<input type="hidden" name="page" value="ironcreed-request-log"><input type="hidden" name="source" value="<?php echo esc_attr( $tab ); ?>">
			<label><?php esc_html_e( 'Path contains', 'ironcreed-request-log' ); ?> <input name="path_filter" value="<?php echo esc_attr( $filters['path'] ); ?>"></label>
			<label><?php esc_html_e( 'Method', 'ironcreed-request-log' ); ?> <input name="method" value="<?php echo esc_attr( $filters['method'] ); ?>"></label>
			<label><?php esc_html_e( 'Status class', 'ironcreed-request-log' ); ?> <select name="status_class"><option value="0"><?php esc_html_e( 'Any', 'ironcreed-request-log' ); ?></option><?php foreach ( array( 1, 2, 3, 4, 5 ) as $class ) : ?><option value="<?php echo esc_attr( $class ); ?>" <?php selected( $filters['status_class'], $class ); ?>><?php echo esc_html( $class . 'xx' ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Rows per page', 'ironcreed-request-log' ); ?> <select name="per_page"><?php foreach ( array( 20, 50, 100 ) as $size ) : ?><option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option><?php endforeach; ?></select></label>
			<?php submit_button( __( 'Filter', 'ironcreed-request-log' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	/** Render escaped events. */
	private function render_table( array $events, string $source ): void {
		?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Observed UTC', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Method', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Request', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Status', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Details', 'ironcreed-request-log' ); ?></th></tr></thead><tbody>
		<?php if ( ! $events ) : ?><tr><td colspan="5"><?php esc_html_e( 'No records are available for this source.', 'ironcreed-request-log' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $events as $event ) : ?><tr><td><?php echo esc_html( $event['observed_at'] ); ?></td><td><?php echo esc_html( $event['method'] ); ?></td><td><code><?php echo esc_html( $event['path'] . ( $event['query'] ? '?' . $event['query'] : '' ) ); ?></code></td><td><?php echo esc_html( $event['status'] ?: '—' ); ?></td><td><?php echo esc_html( 'wordpress-runtime' === $source ? $event['route_kind'] . ', ' . $event['duration_ms'] . ' ms' : $event['client_ip'] . ' · ' . $event['response_bytes'] . ' bytes' ); ?></td></tr><?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/** Render pagination while retaining source and filters. */
	private function render_pagination( string $source, array $filters, int $per_page, int $page, int $total_pages ): void {
		$base = add_query_arg(
			array(
				'page'         => 'ironcreed-request-log',
				'source'       => 'wordpress-runtime' === $source ? 'runtime' : 'hosting',
				'path_filter'  => $filters['path'],
				'method'       => $filters['method'],
				'status_class' => $filters['status_class'],
				'per_page'     => $per_page,
				'paged'        => 999999999,
			),
			admin_url( 'tools.php' )
		);
		$base = str_replace( '999999999', '%#%', $base );
		echo wp_kses_post( paginate_links( array( 'base' => $base, 'current' => $page, 'total' => $total_pages, 'type' => 'list' ) ) );
	}

	/** Render explicit per-source clear action. */
	private function render_clear_form( string $source ): void {
		if ( ! current_user_can( 'manage_ironcreed_request_log' ) ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="ironcreed_request_log_clear"><input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>"><?php wp_nonce_field( 'ironcreed_request_log_clear' ); ?><label><input type="checkbox" name="confirm_clear" value="yes" required> <?php esc_html_e( 'I understand that these records will be permanently deleted.', 'ironcreed-request-log' ); ?></label><?php submit_button( __( 'Clear this source', 'ironcreed-request-log' ), 'delete', '', false ); ?></form>
		<?php
	}

	/** Render connection editor without returning a saved token to HTML. */
	private function render_connection_form( array $credentials ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="ironcreed_request_log_connect"><?php wp_nonce_field( 'ironcreed_request_log_connect' ); ?>
			<label><?php esc_html_e( 'Host ID', 'ironcreed-request-log' ); ?> <input type="number" min="1" name="host_id" value="<?php echo esc_attr( $credentials['host_id'] ?? '' ); ?>"></label>
			<label><?php esc_html_e( 'Bearer token', 'ironcreed-request-log' ); ?> <input type="password" autocomplete="new-password" name="token" value="" aria-describedby="ironcreed-token-help"></label>
			<p id="ironcreed-token-help" class="description"><?php echo esc_html( $credentials ? __( 'Leave empty to retain the saved token while editing this connection.', 'ironcreed-request-log' ) : __( 'A token is required for a new connection.', 'ironcreed-request-log' ) ); ?></p>
			<?php submit_button( __( 'Save connection', 'ironcreed-request-log' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( $credentials ) : ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="ironcreed_request_log_test"><?php wp_nonce_field( 'ironcreed_request_log_test' ); ?><?php submit_button( __( 'Test connection', 'ironcreed-request-log' ), 'secondary', '', false ); ?></form>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="ironcreed_request_log_fetch"><?php wp_nonce_field( 'ironcreed_request_log_fetch' ); ?><?php submit_button( __( 'Fetch today’s logs', 'ironcreed-request-log' ), 'primary', '', false ); ?></form>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="ironcreed_request_log_disconnect"><?php wp_nonce_field( 'ironcreed_request_log_disconnect' ); ?><?php submit_button( __( 'Disconnect', 'ironcreed-request-log' ), 'delete', '', false ); ?></form>
		<?php endif; ?>
		<?php
	}
}
