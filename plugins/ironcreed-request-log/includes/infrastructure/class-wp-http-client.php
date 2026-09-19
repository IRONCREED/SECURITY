<?php
/**
 * WordPress HTTP adapter.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Infrastructure;

use Ironcreed\Request_Log\Application\HTTP_Client;
use RuntimeException;

/** Downloads provider responses through the safe WordPress HTTP API. */
final class WP_HTTP_Client implements HTTP_Client {
	/**
	 * {@inheritDoc}
	 *
	 * @param string $url Fixed provider HTTPS endpoint.
	 * @param array  $arguments Bounded WordPress HTTP arguments.
	 * @throws RuntimeException When the operation cannot complete safely.
	 */
	public function download( string $url, array $arguments ): array {
		$file = wp_tempnam( 'ironcreed-request-log-provider' );
		if ( ! $file ) {
			throw new RuntimeException( 'The provider request could not be prepared.' );
		}

		$arguments['stream']   = true;
		$arguments['filename'] = $file;
		$response              = wp_safe_remote_post( $url, $arguments );

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $file );
			throw new RuntimeException( 'The provider request failed.' );
		}

		$headers = wp_remote_retrieve_headers( $response );
		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => $headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary ? $headers->getAll() : (array) $headers,
			'file'    => $file,
			'size'    => (int) filesize( $file ),
		);
	}
}
