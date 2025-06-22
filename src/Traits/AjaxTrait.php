<?php

namespace RWP\Traits;

trait AjaxTrait
{
	protected static int $AJAX_PRIV = 1;
	protected static int $AJAX_NO_PRIV = 2;
	protected static int $AJAX_BOTH = 3;

	public function ajax ( string $method_name, callable $method_callback, int $privileges = 3 )
	{
		if ( $privileges & static::$AJAX_PRIV ) {
			add_action( 'wp_ajax_' . $method_name, $method_callback );
		}

		if ( $privileges & static::$AJAX_NO_PRIV ) {
			add_action( 'wp_ajax_nopriv_' . $method_name, $method_callback );
		}
	}
}