<?php
/**
 * Settings presentation.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Admin;

use Ironcreed\Request_Log\Infrastructure\Hosting_Ukraine_Discovery;
use Ironcreed\Request_Log\Infrastructure\Provider_Import;

/** Native controls for explicit connection and scheduling actions. */
final class Settings_View {

	/**
	 * Render authorized settings.
	 *
	 * @param array $credentials Site-local credentials.
	 */
	public static function render( array $credentials ): void {
		$domain = Hosting_Ukraine_Discovery::domain( is_multisite() ? get_home_url( get_main_site_id() ) : home_url() );
		$mode   = $credentials ? 'manual' : 'lookup';
		?>
		<section class="icrl-card">
			<h2><?php esc_html_e( 'Sources and retention', 'ironcreed-request-log' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="ironcreed_request_log_settings">
				<?php wp_nonce_field( 'ironcreed_request_log_settings' ); ?>
				<p><label><input type="checkbox" name="enabled" value="1" <?php checked( '1', get_option( 'ironcreed_request_log_enabled', '0' ) ); ?>> <?php esc_html_e( 'Enable WordPress Runtime logging', 'ironcreed-request-log' ); ?></label> <?php Help_View::link( 'sources', __( 'About log sources', 'ironcreed-request-log' ) ); ?></p>
				<div class="icrl-fields">
					<label for="icrl-retention"><?php esc_html_e( 'Retention hours', 'ironcreed-request-log' ); ?><input id="icrl-retention" type="number" min="1" max="720" name="retention" value="<?php echo esc_attr( get_option( 'ironcreed_request_log_retention', 24 ) ); ?>"></label>
					<label for="icrl-cap"><?php esc_html_e( 'Maximum stored records', 'ironcreed-request-log' ); ?><input id="icrl-cap" type="number" min="100" max="100000" name="cap" value="<?php echo esc_attr( get_option( 'ironcreed_request_log_cap', 10000 ) ); ?>"></label>
					<?php Help_View::link( 'retention', __( 'About retention and clearing', 'ironcreed-request-log' ) ); ?>
				</div>
				<?php submit_button( __( 'Save settings', 'ironcreed-request-log' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>
		<section class="icrl-card">
			<h2><?php esc_html_e( 'Connection', 'ironcreed-request-log' ); ?></h2>
			<label for="icrl-provider"><?php esc_html_e( 'Provider adapter', 'ironcreed-request-log' ); ?></label>
			<select id="icrl-provider"><option value="hosting-ukraine"><?php esc_html_e( 'Hosting Ukraine API', 'ironcreed-request-log' ); ?></option></select>
			<p class="description"><?php esc_html_e( 'Lookup sends your token and type=host to adm.tools, receives the host services available to that token, and matches the entered domain locally. Test and import send the hosting site ID and token and download logs that may contain personal data. The saved token stays hidden.', 'ironcreed-request-log' ); ?> <?php Help_View::link( 'privacy', __( 'External service and privacy', 'ironcreed-request-log' ) ); ?></p>
			<form class="icrl-connection" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( 'ironcreed_request_log_connection' ); ?>
				<fieldset class="icrl-modes">
					<legend><?php esc_html_e( 'Connection method', 'ironcreed-request-log' ); ?></legend>
					<label><input type="radio" name="connection_mode" value="lookup" <?php checked( 'lookup', $mode ); ?>> <?php esc_html_e( 'Find ID by domain', 'ironcreed-request-log' ); ?></label>
					<label><input type="radio" name="connection_mode" value="manual" <?php checked( 'manual', $mode ); ?>> <?php esc_html_e( 'Enter ID manually', 'ironcreed-request-log' ); ?></label>
				</fieldset>
				<div class="icrl-mode" data-mode="lookup">
					<p><?php esc_html_e( 'Enter the hosting site domain. We will resolve its ID when you connect or test.', 'ironcreed-request-log' ); ?></p>
					<label for="icrl-domain"><?php echo esc_html( is_multisite() ? __( 'Main network site domain', 'ironcreed-request-log' ) : __( 'Site domain', 'ironcreed-request-log' ) ); ?></label>
					<input id="icrl-domain" type="text" name="domain" value="<?php echo esc_attr( $domain ); ?>" autocomplete="url" placeholder="example.com">
					<?php Help_View::link( 'multisite', __( 'Choosing a domain in Multisite', 'ironcreed-request-log' ) ); ?>
				</div>
				<div class="icrl-mode" data-mode="manual">
					<p><?php esc_html_e( 'Use the hosting site ID (host_id) accepted by the nginx log method. Account and panel user IDs identify different objects.', 'ironcreed-request-log' ); ?></p>
					<label for="icrl-host"><?php esc_html_e( 'Hosting site ID', 'ironcreed-request-log' ); ?></label>
					<input id="icrl-host" type="number" min="1" name="host_id" value="<?php echo esc_attr( $credentials['host_id'] ?? '' ); ?>">
					<?php Help_View::link( 'host-id', __( 'Where to find the hosting site ID', 'ironcreed-request-log' ) ); ?>
				</div>
				<div class="icrl-token-row">
					<label for="icrl-token"><?php esc_html_e( 'Bearer token', 'ironcreed-request-log' ); ?></label>
					<input id="icrl-token" type="password" name="token" value="" autocomplete="new-password" aria-describedby="icrl-token-note">
					<button type="submit" class="button" name="action" value="ironcreed_request_log_test"><?php esc_html_e( 'Test connection', 'ironcreed-request-log' ); ?></button>
					<?php Help_View::link( 'token', __( 'Where to find the token', 'ironcreed-request-log' ) ); ?>
				</div>
				<p id="icrl-token-note" class="description"><?php echo esc_html( $credentials ? __( 'Token saved. Leave empty to keep it, or enter a replacement.', 'ironcreed-request-log' ) : __( 'Enter a token for this connection. Testing does not save it.', 'ironcreed-request-log' ) ); ?></p>
				<div class="icrl-actions">
					<button type="submit" class="button button-primary" name="action" value="ironcreed_request_log_connect"><?php esc_html_e( 'Save connection', 'ironcreed-request-log' ); ?></button>
				</div>
			</form>
			<?php if ( $credentials ) : ?>
				<p>
				<?php
				/* translators: %d: saved hosting site identifier. */
				printf( esc_html__( 'Connected hosting site ID: %d', 'ironcreed-request-log' ), (int) $credentials['host_id'] );
				?>
				</p>
				<div class="icrl-actions">
					<?php self::action_button( 'fetch', __( 'Fetch today’s logs', 'ironcreed-request-log' ), 'secondary' ); ?>
					<?php self::action_button( 'disconnect', __( 'Disconnect', 'ironcreed-request-log' ), 'delete' ); ?>
				</div>
			<?php endif; ?>
		</section>
		<section class="icrl-card">
			<h2><?php esc_html_e( 'Scheduled imports', 'ironcreed-request-log' ); ?> <?php Help_View::link( 'refresh', __( 'About refresh and import', 'ironcreed-request-log' ) ); ?></h2>
			<?php self::status(); ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="ironcreed_request_log_schedule">
				<?php wp_nonce_field( 'ironcreed_request_log_schedule' ); ?>
				<?php $interval = (int) get_option( 'ironcreed_request_log_import_interval', 0 ); ?>
				<p><label><input type="checkbox" name="scheduled_consent" value="1" <?php checked( true, $interval > 0 ); ?> <?php disabled( ! $credentials ); ?>> <?php esc_html_e( 'Allow periodic downloads from Hosting Ukraine, including when this page is closed.', 'ironcreed-request-log' ); ?></label></p>
				<div class="icrl-actions">
					<label for="icrl-interval"><?php esc_html_e( 'Import interval', 'ironcreed-request-log' ); ?></label>
					<select id="icrl-interval" name="interval">
						<?php foreach ( Provider_Import::INTERVALS as $seconds ) : ?>
							<option value="<?php echo esc_attr( $seconds ); ?>" <?php selected( $interval > 0 ? $interval : 300, $seconds ); ?>>
							<?php
							/* translators: %d: interval in minutes. */
							printf( esc_html__( '%d min', 'ironcreed-request-log' ), (int) ( $seconds / 60 ) );
							?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Save schedule', 'ironcreed-request-log' ), 'secondary', 'submit', false ); ?>
				</div>
			</form>
		</section>
		<?php
	}

	/**
	 * Render safe scheduling status without exposing credentials or provider responses.
	 */
	public static function status(): void {
		$status   = (array) get_option( 'ironcreed_request_log_import_status', array() );
		$interval = (int) get_option( 'ironcreed_request_log_import_interval', 0 );
		$next     = wp_next_scheduled( Provider_Import::HOOK );
		$states   = array(
			'running' => __( 'Import in progress', 'ironcreed-request-log' ),
			'success' => __( 'Last import succeeded', 'ironcreed-request-log' ),
			'failed'  => __( 'Last import failed; check the connection and retry.', 'ironcreed-request-log' ),
		);
		echo '<div class="icrl-status" role="status"><p>' . esc_html( $interval ? __( 'Automatic import enabled', 'ironcreed-request-log' ) : __( 'Manual import only', 'ironcreed-request-log' ) ) . '</p>';
		if ( isset( $states[ $status['state'] ?? '' ] ) ) {
			echo '<p>' . esc_html( $states[ $status['state'] ] ) . '</p>';
		}
		foreach ( array(
			'attempt_at' => __( 'Last attempt', 'ironcreed-request-log' ),
			'success_at' => __( 'Last successful import', 'ironcreed-request-log' ),
		) as $key => $label ) {
			if ( ! empty( $status[ $key ] ) ) {
				echo '<p>' . esc_html( $label . ': ' . wp_date( 'Y-m-d H:i:s T', (int) $status[ $key ] ) ) . '</p>';
			}
		}
		if ( isset( $status['count'] ) ) {
			/* translators: %d: number of records added by the last successful import. */
			echo '<p>' . esc_html( sprintf( __( 'New records in the last successful import: %d', 'ironcreed-request-log' ), (int) $status['count'] ) ) . '</p>';
		}
		if ( $interval && $next ) {
			echo '<p>' . esc_html__( 'Next attempt', 'ironcreed-request-log' ) . ': ' . esc_html( wp_date( 'Y-m-d H:i:s T', $next ) ) . '</p>';
		}
		if ( $interval && ( ! $next || $next < time() - 60 || ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) ) {
			echo '<p>' . esc_html__( 'WP-Cron needs site traffic or a system scheduler. If attempts are overdue, check the cron setup.', 'ironcreed-request-log' ) . ' ';
			Help_View::link( 'cron', __( 'WP-Cron help', 'ironcreed-request-log' ) );
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render an independent non-nested POST form.
	 *
	 * @param string $action Handler suffix.
	 * @param string $label  Translated label.
	 * @param string $style  Native button style.
	 */
	public static function action_button( string $action, string $label, string $style ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="icrl-action">
			<input type="hidden" name="action" value="<?php echo esc_attr( 'ironcreed_request_log_' . $action ); ?>">
			<?php wp_nonce_field( 'ironcreed_request_log_' . $action ); ?>
			<?php submit_button( $label, $style, 'submit', false ); ?>
		</form>
		<?php
	}
}
