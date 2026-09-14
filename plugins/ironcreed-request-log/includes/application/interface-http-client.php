<?php
/** HTTP client port. @package Ironcreed_Request_Log */

namespace Ironcreed\Request_Log\Application;

/** Provides bounded downloads without exposing a response body in errors. */
interface HTTP_Client {
	/**
	 * Download a response to a temporary file.
	 *
	 * @param string $url       Fixed provider URL.
	 * @param array  $arguments WordPress HTTP arguments.
	 * @return array{code:int,headers:array,file:string,size:int}
	 */
	public function download( string $url, array $arguments ): array;
}
