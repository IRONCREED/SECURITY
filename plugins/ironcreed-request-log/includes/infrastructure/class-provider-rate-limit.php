<?php
/**
 * Safe provider throttle information.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Infrastructure;

/** Carries only a bounded delay; response bodies and credentials are omitted. */
final class Provider_Rate_Limit extends \RuntimeException {
	/**
	 * Bounded provider retry delay.
	 *
	 * @var int
	 */
	private int $delay;
	/**
	 * Initialize the adapter dependencies.
	 *
	 * @param int $delay Retry delay in seconds.
	 */
	public function __construct( int $delay ) {
		parent::__construct( 'The provider rate limit was reached.' );
		$this->delay = max( 60, min( 86400, $delay ) );
	}
	/**
	 * Return the bounded provider retry delay.
	 *
	 * @return int Bounded retry delay.
	 */
	public function retry_after(): int {
		return $this->delay;
	}
}
