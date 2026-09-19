<?php
/**
 * WordPress administration adapter.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Admin;

use Ironcreed\Request_Log\Domain\Event;
use Ironcreed\Request_Log\Infrastructure\Event_Repository;
use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Provider;
use Ironcreed\Request_Log\Infrastructure\WP_HTTP_Client;
use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Discovery;
use Ironcreed\Request_Log\Infrastructure\Provider_Import;
use RuntimeException;

/** Renders screens and processes privileged actions. */
final class Admin_Controller {
	private const SOURCES = array( 'wordpress-runtime', 'hosting-ukraine-nginx' );

	/**
	 * Event persistence adapter.
	 *
	 * @var Event_Repository
	 */
	private Event_Repository $repository;

	/**
	 * Initialize the adapter dependencies.
	 *
	 * @param Event_Repository $repository Event storage.
	 */
	public function __construct( Event_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register admin screens and handlers.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'redirect_legacy_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		foreach ( array( 'settings', 'clear', 'connect', 'disconnect', 'test', 'fetch', 'schedule' ) as $action ) {
			add_action( 'admin_post_ironcreed_request_log_' . $action, array( $this, 'handle_' . $action ) );
		}
	}


	/**
	 * Redirect old bookmarks without registering another menu item.
	 */
	public function redirect_legacy_settings(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bookmark redirect.
		if ( 'ironcreed-request-log-settings' === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) {
			$this->require_manage();
			wp_safe_redirect( self::url( 'settings' ) );
			exit;
		}
	}

	/**
	 * Load local assets only on this plugin's screens.
	 */
	public function assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selects local presentation assets only.
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( ! in_array( $page, array( 'ironcreed-request-log', 'ironcreed-security' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'ironcreed-request-log', plugins_url( 'assets/admin.css', IRONCREED_REQUEST_LOG_FILE ), array(), IRONCREED_REQUEST_LOG_VERSION );
		wp_enqueue_script( 'ironcreed-request-log', plugins_url( 'assets/admin.js', IRONCREED_REQUEST_LOG_FILE ), array(), IRONCREED_REQUEST_LOG_VERSION, true );
	}

	/**
	 * Build a stable URL that also works with Suite menu grouping.
	 *
	 * @param string $tab Known tab ID.
	 * @return string Administration URL.
	 */
	public static function url( string $tab = 'runtime' ): string {
		return add_query_arg(
			array(
				'page' => 'ironcreed-request-log',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the single screen, enforcing capabilities before reading data.
	 */
	public function render_log(): void {
		$this->require_view();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, allowlisted below.
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? $_GET['source'] ?? 'runtime' ) );
		$tab = in_array( $tab, array( 'runtime', 'hosting', 'settings', 'help' ), true ) ? $tab : 'runtime';
		if ( 'settings' === $tab ) {
			$this->require_manage();
		}
		echo '<div class="wrap ironcreed-request-log"><h1>' . esc_html__( 'IRONCREED Request Log', 'ironcreed-request-log' ) . '</h1>';
		$this->render_notice();
		$this->render_tabs( $tab );
		if ( 'settings' === $tab ) {
			Settings_View::render( $this->credentials() );
		} elseif ( 'help' === $tab ) {
			Help_View::render();
		} else {
			$this->render_events( $tab );
		}
		echo '</div>';
	}

	/**
	 * Render the event tabs.
	 *
	 * @param string $tab Selected source tab.
	 */
	private function render_events( string $tab ): void {
		$source = 'hosting' === $tab ? 'hosting-ukraine-nginx' : 'wordpress-runtime';
		$this->render_boundary( $source );
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Request Log storage is unavailable.', 'ironcreed-request-log' ) . '</p></div>';
			return;
		}
		if ( 'hosting' === $tab ) {
			Settings_View::status();
			if ( current_user_can( 'manage_ironcreed_request_log' ) && $this->credentials() ) {
				Settings_View::action_button( 'fetch', __( 'Fetch today’s logs', 'ironcreed-request-log' ), 'primary' );
			}
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Bounded read-only pagination.
		$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$requested = absint( $_GET['per_page'] ?? 20 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$per_page = in_array( $requested, array( 20, 50, 100 ), true ) ? $requested : 20;
		$filters  = $this->filters();
		$result   = $this->repository->page( $source, $page, $per_page, $filters );
		$this->render_filters( $source, $filters, $per_page );
		echo '<p><label for="ironcreed-refresh">' . esc_html__( 'Refresh saved records', 'ironcreed-request-log' ) . '</label> <select id="ironcreed-refresh"><option value="0">' . esc_html__( 'Off', 'ironcreed-request-log' ) . '</option>';
		foreach ( array( 15, 30, 60 ) as $seconds ) {
			/* translators: %d: table refresh interval in seconds. */
			echo '<option value="' . esc_attr( $seconds ) . '">' . esc_html( sprintf( __( '%d sec', 'ironcreed-request-log' ), $seconds ) ) . '</option>';
		}
		echo '</select> ';
		Help_View::link( 'refresh', __( 'About refresh and import', 'ironcreed-request-log' ) );
		echo '</p>';
		$this->render_table( $result['items'], $source );
		$this->render_pagination( $source, $filters, $per_page, $page, max( 1, (int) ceil( $result['total'] / $per_page ) ) );
		$this->render_clear_form( $source );
	}

	/**
	 * Save source and retention settings.
	 */
	public function handle_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_settings' );
		update_option( 'ironcreed_request_log_enabled', isset( $_POST['enabled'] ) ? '1' : '0', false );
		update_option( 'ironcreed_request_log_retention', max( 1, min( 720, absint( $_POST['retention'] ?? 24 ) ) ), false );
		update_option( 'ironcreed_request_log_cap', max( 100, min( 100000, absint( $_POST['cap'] ?? 10000 ) ) ), false );
		$this->redirect_with_notice( 'success', __( 'Request Log settings were saved.', 'ironcreed-request-log' ), true );
	}


	/**
	 * Save a connection after an optional explicit domain lookup.
	 *
	 * @throws RuntimeException When the operation cannot complete safely.
	 */
	public function handle_connect(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_connection' );
		try {
			$credentials = $this->connection_input();
			Provider_Import::configure( 0 );
			update_option( 'ironcreed_request_log_credentials', $credentials, false );
			if ( $this->credentials() !== $credentials ) {
				throw new RuntimeException( 'The connection could not be persisted.' );
			}
			delete_option( 'ironcreed_request_log_import_status' );
			$this->redirect_with_notice( 'success', __( 'Connection saved. Scheduled imports remain off until you enable them.', 'ironcreed-request-log' ), true );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The connection could not be saved. Check the domain or hosting site ID, token and delegated access.', 'ironcreed-request-log' ), true );
		}
	}

	/**
	 * Read fields after the caller verified the connection nonce.
	 *
	 * @throws RuntimeException When the operation cannot complete safely.
	 */
	private function connection_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Both callers verify the connection nonce before entering this helper.
		$existing = $this->credentials();
		if ( isset( $_POST['token'] ) && ! is_string( $_POST['token'] ) ) {
			throw new RuntimeException( 'Invalid connection credential.' );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque secret is validated below without silently changing its bytes.
		$token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '';
		$token = '' === $token && $existing ? $existing['token'] : $token;
		if ( ! Hosting_Ukraine_Provider::valid_token( $token ) ) {
			throw new RuntimeException( 'Invalid connection credential.' );
		}
		$mode = sanitize_key( wp_unslash( $_POST['connection_mode'] ?? 'manual' ) );
		if ( ! in_array( $mode, array( 'lookup', 'manual' ), true ) ) {
			throw new RuntimeException( 'Invalid connection method.' );
		}
		if ( 'lookup' === $mode ) {
			$domain  = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
			$host_id = ( new Hosting_Ukraine_Discovery( new WP_HTTP_Client() ) )->find( $domain, $token );
		} else {
			$host_id = filter_var( sanitize_text_field( wp_unslash( $_POST['host_id'] ?? '' ) ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( false === $host_id || $host_id < 1 ) {
			throw new RuntimeException( 'Invalid hosting site ID.' );
		}
		return array(
			'host_id' => $host_id,
			'token'   => $token,
		);
	}

	/**
	 * Save explicit scheduling consent without an immediate provider call.
	 */
	public function handle_schedule(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_schedule' );
		$interval = isset( $_POST['scheduled_consent'] ) ? absint( $_POST['interval'] ?? 300 ) : 0;
		try {
			Provider_Import::configure( $interval );
			$this->redirect_with_notice( 'success', __( 'The import schedule was saved.', 'ironcreed-request-log' ), true );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The import could not be scheduled. Save a valid connection and try again.', 'ironcreed-request-log' ), true );
		}
	}

	/**
	 * Delete provider credentials while retaining imported records.
	 */
	public function handle_disconnect(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_disconnect' );
		Provider_Import::configure( 0 );
		delete_option( 'ironcreed_request_log_credentials' );
		delete_option( 'ironcreed_request_log_import_status' );
		$this->redirect_with_notice( 'success', __( 'The Hosting Ukraine connection was removed. Imported records remain until clearing or retention expiry.', 'ironcreed-request-log' ), true );
	}

	/**
	 * Test the configured provider and report a persistent safe result.
	 */
	public function handle_test(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_connection' );
		try {
			$credentials = $this->connection_input();
			$events      = ( new Hosting_Ukraine_Provider( new WP_HTTP_Client() ) )->fetch_today( $credentials['host_id'], $credentials['token'] );
			foreach ( $events as $unused_event ) {
				break;
			}
			unset( $events );
			$this->redirect_with_notice( 'success', __( 'The Hosting Ukraine connection test succeeded.', 'ironcreed-request-log' ), true );
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The Hosting Ukraine connection test failed safely. Verify the credentials and try again.', 'ironcreed-request-log' ), true );
		}
	}

	/**
	 * Fetch, normalize, deduplicate, and import today's provider events.
	 */
	public function handle_fetch(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_fetch' );
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			$this->redirect_with_notice( 'error', __( 'Storage is unavailable; no provider request was made.', 'ironcreed-request-log' ), false, 'hosting-ukraine-nginx' );
		}
		try {
			global $wpdb;
			$count = ( new Provider_Import( $wpdb ) )->run();
			$this->redirect_with_notice(
				'success',
				sprintf(
				/* translators: %d: number of newly imported records. */
					__( 'Hosting Ukraine fetch completed. %d new records were imported.', 'ironcreed-request-log' ),
					$count
				),
				false,
				'hosting-ukraine-nginx'
			);
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The Hosting Ukraine fetch failed safely. Verify the connection and try again.', 'ironcreed-request-log' ), false, 'hosting-ukraine-nginx' );
		}
	}

	/**
	 * Clear exactly one allowlisted source.
	 */
	public function handle_clear(): void {
		$this->require_manage();
		check_admin_referer( 'ironcreed_request_log_clear' );
		if ( '1' !== get_option( 'ironcreed_request_log_storage_ready', '0' ) ) {
			$this->redirect_with_notice( 'error', __( 'Storage is unavailable; no records were cleared.', 'ironcreed-request-log' ) );
		}
		if ( 'yes' !== sanitize_key( wp_unslash( $_POST['confirm_clear'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Explicit confirmation is required to clear request records.', 'ironcreed-request-log' ), '', array( 'response' => 400 ) );
		}
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		if ( ! self::valid_clear_source( $source ) ) {
			wp_die( esc_html__( 'The requested log source is invalid.', 'ironcreed-request-log' ), '', array( 'response' => 400 ) );
		}

		try {
			$count = $this->repository->clear_source( $source );
			$this->redirect_with_notice(
				'success',
				sprintf(
				/* translators: %d: number of deleted records. */
					__( '%d records were cleared from the selected source.', 'ironcreed-request-log' ),
					$count
				),
				false,
				$source
			);
		} catch ( RuntimeException $error ) {
			$this->redirect_with_notice( 'error', __( 'The selected request records could not be cleared safely.', 'ironcreed-request-log' ), false, $source );
		}
	}

	/**
	 * Report whether a source may be cleared.
	 *
	 * @param string $source Allowlisted log source.
	 */
	public static function valid_clear_source( string $source ): bool {
		return in_array( $source, self::SOURCES, true );
	}


	/**
	 * Retrieve valid credentials without returning them to the browser.
	 */
	private function credentials(): array {
		return Provider_Import::credentials();
	}

	/**
	 * Require the viewing capability.
	 */
	private function require_view(): void {
		if ( ! current_user_can( 'view_ironcreed_request_log' ) ) {
			wp_die( esc_html__( 'You cannot view this request log.', 'ironcreed-request-log' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Require the management capability.
	 */
	private function require_manage(): void {
		if ( ! current_user_can( 'manage_ironcreed_request_log' ) ) {
			wp_die( esc_html__( 'You cannot manage this request log.', 'ironcreed-request-log' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Persist a safe notice for the current user and redirect.
	 *
	 * @param string $type Notice severity.
	 * @param string $message Translated notice text.
	 * @param bool   $settings Whether to return to settings.
	 * @param string $source Allowlisted log source.
	 */
	private function redirect_with_notice( string $type, string $message, bool $settings = false, string $source = 'wordpress-runtime' ): void {
		$key = 'ironcreed_request_log_notice_' . get_current_user_id();
		set_transient(
			$key,
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		$tab = 'hosting-ukraine-nginx' === $source ? 'hosting' : 'runtime';
		$url = $settings
			? self::url( 'settings' )
			: self::url( $tab );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render and consume a redirect-persistent user notice.
	 */
	private function render_notice(): void {
		$key    = 'ironcreed_request_log_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $notice ) || ! in_array( $notice['type'] ?? '', array( 'success', 'error' ), true ) ) {
			return;
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ?? '' ) );
	}

	/**
	 * Read allowlisted URL filters.
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sanitized read-only filters.
		$method = strtoupper( sanitize_key( wp_unslash( $_GET['method'] ?? '' ) ) );
		return array(
			'path'         => sanitize_text_field( wp_unslash( $_GET['path_filter'] ?? '' ) ),
			'method'       => in_array( $method, Event::METHODS, true ) ? $method : '',
			'status_class' => in_array( absint( $_GET['status_class'] ?? 0 ), array( 1, 2, 3, 4, 5 ), true ) ? absint( $_GET['status_class'] ) : 0,
		);
	}

	// phpcs:enable WordPress.Security.NonceVerification.Recommended


	/**
	 * Render four tabs.
	 *
	 * @param string $current Active tab.
	 */
	private function render_tabs( string $current ): void {
		$tabs = array(
			'runtime'  => __( 'WordPress Runtime', 'ironcreed-request-log' ),
			'hosting'  => __( 'Hosting Ukraine', 'ironcreed-request-log' ),
			'settings' => __( 'Settings', 'ironcreed-request-log' ),
			'help'     => __( 'Help', 'ironcreed-request-log' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Request Log sections', 'ironcreed-request-log' ) . '">';
		foreach ( $tabs as $tab => $label ) {
			if ( 'settings' === $tab && ! current_user_can( 'manage_ironcreed_request_log' ) ) {
				continue;
			}
			printf( '<a class="nav-tab %1$s" href="%2$s" %3$s>%4$s</a>', $current === $tab ? 'nav-tab-active' : '', esc_url( self::url( $tab ) ), $current === $tab ? 'aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}

	/**
	 * Render the permanent source boundary.
	 *
	 * @param string $source Allowlisted log source.
	 */
	private function render_boundary( string $source ): void {
		$message = 'wordpress-runtime' === $source
			? __( 'This source sees only requests that reach and load WordPress. CDN, WAF, web-server, full-page-cache, and static-file responses remain outside its boundary.', 'ironcreed-request-log' )
			: __( 'This source shows entries returned from Hosting Ukraine nginx access logs. Coverage remains limited to the provider log and period returned by its API.', 'ironcreed-request-log' );
		printf( '<div class="notice notice-info inline"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Render source-preserving filters and page-size selection.
	 *
	 * @param string $source Allowlisted log source.
	 * @param array  $filters Validated request filters.
	 * @param int    $per_page Bounded page size.
	 */
	private function render_filters( string $source, array $filters, int $per_page ): void {
		$tab = 'wordpress-runtime' === $source ? 'runtime' : 'hosting';
		?>
		<form method="get">
			<input type="hidden" name="page" value="ironcreed-request-log"><input type="hidden" name="source" value="<?php echo esc_attr( $tab ); ?>">
			<label><?php esc_html_e( 'Path contains', 'ironcreed-request-log' ); ?> <input name="path_filter" value="<?php echo esc_attr( $filters['path'] ); ?>"></label>
			<label><?php esc_html_e( 'Method', 'ironcreed-request-log' ); ?> <input name="method" value="<?php echo esc_attr( $filters['method'] ); ?>"></label>
			<label><?php esc_html_e( 'Status class', 'ironcreed-request-log' ); ?> <select name="status_class"><option value="0"><?php esc_html_e( 'Any', 'ironcreed-request-log' ); ?></option>
			<?php
			foreach ( array( 1, 2, 3, 4, 5 ) as $class ) :
				?>
				<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $filters['status_class'], $class ); ?>><?php echo esc_html( $class . 'xx' ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Rows per page', 'ironcreed-request-log' ); ?> <select name="per_page">
			<?php
			foreach ( array( 20, 50, 100 ) as $size ) :
				?>
				<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option><?php endforeach; ?></select></label>
			<?php submit_button( __( 'Filter', 'ironcreed-request-log' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render escaped events.
	 *
	 * @param array  $events Normalized events to consume.
	 * @param string $source Allowlisted log source.
	 */
	private function render_table( array $events, string $source ): void {
		?>
		<div class="icrl-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Observed UTC', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Method', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Request', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Status', 'ironcreed-request-log' ); ?></th><th><?php esc_html_e( 'Details', 'ironcreed-request-log' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $events ) :
			?>
			<tr><td colspan="5"><?php esc_html_e( 'No records are available for this source.', 'ironcreed-request-log' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( $events as $event ) :
			?>
			<?php /* translators: %d: duration in milliseconds or response size in bytes. */ ?><tr><td><?php echo esc_html( $event['observed_at'] ); ?></td><td><?php echo esc_html( $event['method'] ); ?></td><td><code><?php echo esc_html( $event['path'] . ( $event['query'] ? '?' . $event['query'] : '' ) ); ?></code></td><td><?php echo esc_html( $event['status'] ? $event['status'] : '—' ); ?></td><td><?php echo esc_html( 'wordpress-runtime' === $source ? $event['route_kind'] . ', ' . sprintf( __( '%d ms', 'ironcreed-request-log' ), $event['duration_ms'] ) : $event['client_ip'] . ' · ' . sprintf( __( '%d bytes', 'ironcreed-request-log' ), $event['response_bytes'] ) ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	/**
	 * Render pagination while retaining source and filters.
	 *
	 * @param string $source Allowlisted log source.
	 * @param array  $filters Validated request filters.
	 * @param int    $per_page Bounded page size.
	 * @param int    $page One-based page number.
	 * @param int    $total_pages Total available pages.
	 */
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
			admin_url( 'admin.php' )
		);
		$base = str_replace( '999999999', '%#%', $base );
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => $base,
					'current' => $page,
					'total'   => $total_pages,
					'type'    => 'list',
				)
			)
		);
	}

	/**
	 * Render explicit per-source clear action.
	 *
	 * @param string $source Allowlisted log source.
	 */
	private function render_clear_form( string $source ): void {
		if ( ! current_user_can( 'manage_ironcreed_request_log' ) ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="ironcreed_request_log_clear"><input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>"><?php wp_nonce_field( 'ironcreed_request_log_clear' ); ?><label><input type="checkbox" name="confirm_clear" value="yes" required> <?php esc_html_e( 'I understand that these records will be permanently deleted.', 'ironcreed-request-log' ); ?></label><?php submit_button( __( 'Clear this source', 'ironcreed-request-log' ), 'delete', '', false ); ?></form>
		<?php
	}
}
