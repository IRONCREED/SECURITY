<?php
/** Pure PHPUnit summary validator used by typed integration gates. */
function iron_warden_phpunit_passed( int $status, string $output ): bool {
	if ( 0 !== $status ) return false;
	if ( preg_match( '/(?:No tests executed|FAILURES!|ERRORS!)/i', $output ) ) return false;
	$pattern = '/^OK \(([1-9][0-9]*) tests?, ([1-9][0-9]*) assertions?\)$/m';
	if ( 1 !== preg_match_all( $pattern, rtrim( $output ), $matches, PREG_OFFSET_CAPTURE ) ) return false;
	$summary = $matches[0][0][0];
	$offset = $matches[0][0][1];
	return '' === trim( substr( rtrim( $output ), $offset + strlen( $summary ) ) );
}
