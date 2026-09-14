<?php
/** Deadlock-safe repository-owned process execution. */
function iron_warden_capture( array $command, array $environment = array() ): array {
	$spec = array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) );
	$process = proc_open( $command, $spec, $pipes, null, array_replace( getenv(), $environment ) );
	if ( ! is_resource( $process ) ) return array( 1, '', '' );
	foreach ( array( 1, 2 ) as $descriptor ) stream_set_blocking( $pipes[ $descriptor ], false );
	$output = array( 1 => '', 2 => '' );
	while ( true ) {
		$read = array();
		foreach ( array( 1, 2 ) as $descriptor ) if ( is_resource( $pipes[ $descriptor ] ) && ! feof( $pipes[ $descriptor ] ) ) $read[] = $pipes[ $descriptor ];
		if ( ! $read ) break;
		$write = null; $except = null;
		if ( false === stream_select( $read, $write, $except, 5 ) ) break;
		foreach ( $read as $stream ) {
			$descriptor = $stream === $pipes[1] ? 1 : 2;
			$chunk = fread( $stream, 8192 );
			if ( false !== $chunk ) $output[ $descriptor ] .= $chunk;
		}
	}
	foreach ( array( 1, 2 ) as $descriptor ) fclose( $pipes[ $descriptor ] );
	return array( proc_close( $process ), $output[1], $output[2] );
}
