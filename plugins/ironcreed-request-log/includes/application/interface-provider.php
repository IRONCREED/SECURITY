<?php
/**
 * Provider port.
 *
 * @package Ironcreed_Request_Log
 */

namespace Ironcreed\Request_Log\Application;

/** Produces normalized events from a provider. */
interface Provider {
	/**
	 * Fetch today's events as a lazy iterable.
	 *
	 * @param int    $host_id Hosting account identifier.
	 * @param string $token   Bearer credential.
	 * @return iterable<int,array<string,mixed>>
	 */
	public function fetch_today( int $host_id, string $token ): iterable;
}
