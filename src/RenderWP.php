<?php

namespace RWP;

class RenderWP
{
	use Traits\SettingsTrait;
	use Traits\AjaxTrait;

	/**
	 * Main renderwp Instance.
	 *
	 * Ensures only one instance of renderwp is loaded or can be loaded.
	 *
	 * @return RenderWP - Main instance.
	 */
	public static function get_instance ()
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static;
		}

		return $instance;
	}

	/**
	 * renderwp Constructor.
	 */
	protected function __construct ()
	{
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string $name
	 * @param mixed  $value
	 */
	protected function define ( $name, $value )
	{
		!defined( $name ) && define( $name, $value );
	}

	/**
	 * Define renderwp Constants.
	 */
	protected function define_constants ()
	{
		if ( file_exists( $composerJson = wp_normalize_path( dirname( __DIR__ ) ) . '/composer.json' ) ) {
			$composer = json_decode( file_get_contents( $composerJson ), TRUE );
		}

		$this->define( 'RWP_VERSION', $composer['version'] ?? '0.0.0' );
	}

	/**
	 * Set init hooks on construct
	 */
	protected function init_hooks ()
	{
		add_action( 'plugins_loaded', fn() => $this->on_plugins_loaded(), -99 );
		add_action( 'init', fn() => $this->init(), -99 );
	}

	/**
	 * Publish 'renderwp_loaded' hook
	 */
	protected function on_plugins_loaded ()
	{
		do_action( 'renderwp_loaded' );
	}

	/**
	 * Init renderwp when WordPress Initialises.
	 */
	protected function init ()
	{
		do_action( 'renderwp_init' );
	}
}