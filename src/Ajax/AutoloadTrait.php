<?php

namespace RWP\Ajax;

/**
 * @property bool $priv   = FALSE // Can non-logged-in users access these AJAX endpoints?
 * @property bool $noPriv = TRUE  // Can logged-in users access these AJAX endpoints?
 */
trait AutoloadTrait
{
	public static function get_instance ()
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static;
		}

		return $instance;
	}

	protected function __construct ()
	{
		$reflection = new \ReflectionClass( $this );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		foreach ( $methods as $method ) {
			$method_name = $method->getName();
			if ( strpos( $method_name, '__' ) !== 0 ) {
				if ( !property_exists( $this, 'priv' ) || $this->priv ) {
					add_action( 'wp_ajax_' . $method_name, [ $this, $method_name ] );
				}
				if ( property_exists( $this, 'noPriv' ) && $this->noPriv ) {
					add_action( 'wp_ajax_nopriv_' . $method_name, [ $this, $method_name ] );
				}
			}
		}
	}
}