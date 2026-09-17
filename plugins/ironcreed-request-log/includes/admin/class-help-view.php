<?php
/**
 * Contextual help, available without JavaScript.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Admin;

/** Renders accessible native disclosure elements and stable section links. */
final class Help_View {

	/**
	 * Render a keyboard-accessible question link.
	 *
	 * @param string $section FAQ section ID.
	 * @param string $label   Accessible translated label.
	 */
	public static function link( string $section, string $label ): void {
		$url = add_query_arg( 'section', $section, Admin_Controller::url( 'help' ) ) . '#icrl-help-' . $section;
		printf( '<a class="icrl-help-link" href="%1$s" aria-label="%2$s"><span aria-hidden="true">?</span></a>', esc_url( $url ), esc_attr( $label ) );
	}

	/**
	 * Render the FAQ and the requested open section.
	 */
	public static function render(): void {
		$sections = array(
			'purpose'   => array(
				__( 'What is Request Log for?', 'ironcreed-request-log' ),
				__( 'Inspect individual HTTP requests, their time, method, URI and response status. Filters help investigate failures and unexpected traffic. This tool does not block requests.', 'ironcreed-request-log' ),
			),
			'sources'   => array(
				__( 'How do the two sources differ?', 'ironcreed-request-log' ),
				__( 'WordPress Runtime observes requests that load WordPress. Hosting Ukraine imports the nginx access log returned by the provider, including requests that may finish before WordPress. Each source has its own tab and clear action.', 'ironcreed-request-log' ),
			),
			'token'     => array(
				__( 'Where do I get the Bearer token?', 'ironcreed-request-log' ),
				__( 'Open adm.tools → Account → API → Access data. Activate API access if needed, then choose Show token. Paste it into the password field. The token can access services delegated to your account. Check IP restrictions under Access restrictions. The hosting documentation states that it expires six months after its last use.', 'ironcreed-request-log' ),
			),
			'host-id'   => array(
				__( 'Which ID do I need?', 'ironcreed-request-log' ),
				__( 'The nginx log method needs host_id for the hosting site. The user ID in the panel header and the ID of a person who delegated access identify accounts or people. account_id identifies a hosting account; virtual_domain_id belongs to another API object. These numbers are not interchangeable. Use Find ID by domain or copy the host_id confirmed by the nginx method.', 'ironcreed-request-log' ),
			),
			'panel'     => array(
				__( 'Where is the API documentation in Hosting Ukraine?', 'ironcreed-request-log' ),
				__( 'Open API → Documentation. Under Hosting, inspect the hosting accounts, My sites and Logs methods. The site-list example requires the hosting account ID. The get_id method can resolve a hosting site by its domain with type=host. Manual request examples can include your active token: remove it before sharing screenshots or code.', 'ironcreed-request-log' ),
			),
			'multisite' => array(
				__( 'Which domain should Multisite use?', 'ironcreed-request-log' ),
				__( 'For a network served by one hosting virtual host, start with the main network site domain; the lookup form suggests it. Separately hosted mapped domains may have their own host IDs. Confirm the domain in the hosting panel. WordPress blog IDs are unrelated. Connections, schedules and records remain local to the site where you configure them.', 'ironcreed-request-log' ),
			),
			'refresh'   => array(
				__( 'How do refresh, import and retention differ?', 'ironcreed-request-log' ),
				__( 'Refresh saved records reloads the local table and makes no provider request. Fetch downloads today’s archive now. Scheduled imports download it periodically after separate consent, even while the page is closed. Retention hours controls how long records are kept. Repeated downloads deduplicate records; zero new records can be a successful result.', 'ironcreed-request-log' ),
			),
			'cron'      => array(
				__( 'Why is the scheduled import late?', 'ironcreed-request-log' ),
				__( 'WP-Cron runs due tasks when WordPress receives traffic. Low traffic, disabled WP-Cron or blocked loopback requests may delay work. A hosting system scheduler can trigger WordPress cron. Next attempt is a due time, not a real-time guarantee. Provider errors delay retries; only one import can run for this site at a time.', 'ironcreed-request-log' ),
			),
			'retention' => array(
				__( 'What do clear, disconnect and uninstall remove?', 'ironcreed-request-log' ),
				__( 'Retention removes expired records; the cap retains the newest records across both sources. Clear deletes the selected source after confirmation. Disconnect removes the token and stops future imports while keeping existing records. An import already in progress may finish. Deactivation cancels scheduled jobs and preserves data. Uninstall removes all plugin records, credentials, settings, jobs and capabilities.', 'ironcreed-request-log' ),
			),
			'errors'    => array(
				__( 'Why is the log empty or the connection failing?', 'ironcreed-request-log' ),
				__( 'Check source enablement, filters, retention and storage readiness. For Hosting Ukraine, verify the hosting site ID, token, delegated permissions and API IP restrictions. The provider may not yet have new records in today’s archive. Test validates access without importing or saving edited credentials; Save connection commits the form. Large or malformed responses fail safely.', 'ironcreed-request-log' ),
			),
			'privacy'   => array(
				__( 'What is sent to the external service?', 'ironcreed-request-log' ),
				__( 'Lookup sends the domain and Bearer token only to adm.tools. Test and import send the hosting site ID and token there. Logs may include IP addresses, URIs, response sizes, User-Agent and Referer. Imported data stays in the WordPress database under the configured retention and access controls. IRONCREED receives no logs. Activation and opening this screen make no provider request.', 'ironcreed-request-log' ),
			),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only FAQ navigation, checked against known section IDs.
		$selected = sanitize_key( wp_unslash( $_GET['section'] ?? '' ) );
		echo '<section class="icrl-card icrl-faq"><h2>' . esc_html__( 'Help and frequently asked questions', 'ironcreed-request-log' ) . '</h2>';
		foreach ( $sections as $id => $content ) {
			printf( '<details id="icrl-help-%1$s" %2$s><summary>%3$s</summary><p>%4$s</p></details>', esc_attr( $id ), $id === $selected ? 'open' : '', esc_html( $content[0] ), esc_html( $content[1] ) );
		}
		echo '<p class="icrl-actions">';
		foreach ( array(
			'https://adm.tools/user/api/'                  => __( 'Open Hosting Ukraine API', 'ironcreed-request-log' ),
			'https://www.ukraine.com.ua/wiki/account/api/' => __( 'API guide', 'ironcreed-request-log' ),
			'https://www.ukraine.com.ua/legal/tos/'        => __( 'Service terms', 'ironcreed-request-log' ),
			'https://www.ukraine.com.ua/legal/privacypolicy/' => __( 'Privacy policy', 'ironcreed-request-log' ),
			'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/' => __( 'System scheduler instructions', 'ironcreed-request-log' ),
		) as $url => $label ) {
			printf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $url ), esc_html( $label ) );
		}
		echo '</p></section>';
	}
}
