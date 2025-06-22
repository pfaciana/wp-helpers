<?php

if ( !function_exists( 'RWP' ) ) {
	function RWP ()
	{
		return \RWP\RenderWP::get_instance();
	}

	if ( isset( $GLOBALS['wp_version'] ) ) {
		$GLOBALS['renderwp'] = RWP();
	}
}