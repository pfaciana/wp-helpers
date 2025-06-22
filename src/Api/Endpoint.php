<?php

namespace RWP\Api;

/**
 * WP_JSON_API Class
 *
 * Caches the WP-JSON API endpoint URL using the WordPress Transients API.
 * The cache is updated daily using the WordPress Core `daily` schedule event.
 * The transient never expires.
 */
class Endpoint
{
	protected static $cache_key = 'wp_json_endpoint_url';
	protected static $api_url;
	protected static $api_url_default = 'wp/v2/';

	public static function get_instance ()
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static;
		}

		return $instance;
	}

	private function __construct ()
	{
		add_action( 'daily', [ __CLASS__, 'update_cache' ] );
		add_action( '_core_updated_successfully', [ __CLASS__, 'update_cache' ] );
		static::$api_url = static::get_cached_api_url();
	}

	/**
	 * Gets the wp-json contents.
	 *
	 * @return array|bool The wp-json contents or false if not found
	 */
	protected static function get_wp_json_contents ()
	{
		$response = wp_remote_get( home_url( '/wp-json/' ), [ 'sslverify' => ( wp_get_environment_type() === 'production' ) ] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return FALSE;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), TRUE );

		if ( empty( $data ) || !is_array( $data ) ) {
			return FALSE;
		}

		return $data;
	}

	/**
	 * Gets the latest API endpoint.
	 *
	 * @param array $data The wp-json data
	 * @return string|bool The latest API endpoint or false if not found
	 */
	protected static function get_latest_api_endpoint ( $data )
	{
		if ( !isset( $data['namespaces'] ) || !is_array( $data['namespaces'] ) ) {
			return FALSE;
		}

		$versions = array_filter( $data['namespaces'], function ( $namespace ) {
			return preg_match( '/^wp\/v(\d+)/', $namespace, $matches );
		} );

		if ( empty( $versions ) ) {
			return FALSE;
		}

		$versions = array_map( function ( $version ) {
			preg_match( '/^wp\/v(\d+)/', $version, $matches );

			return intval( $matches[1] );
		}, $versions );

		return 'wp/v' . max( $versions ) . '/';
	}


	/**
	 * Updates the cache.
	 *
	 * @return string|bool The cached API endpoint URL or false if the cache could not be updated
	 */
	public static function update_cache ()
	{
		if ( empty( $data = static::get_wp_json_contents() ) ) {
			return FALSE;
		}

		if ( empty( $endpoint = static::get_latest_api_endpoint( $data ) ) ) {
			return FALSE;
		}

		$api_url = home_url( '/wp-json/' . $endpoint );

		set_transient( static::$cache_key, $api_url );

		return ( static::$api_url = $api_url );
	}

	/**
	 * Gets the cached API endpoint URL.
	 *
	 * @return string The cached API endpoint URL.
	 */
	public static function get_cached_api_url ()
	{
		if ( !empty( static::$api_url ) ) {
			return static::$api_url;
		}

		if ( empty( $api_url = get_transient( static::$cache_key ) ) ) {
			if ( empty( static::update_cache() ) ) {
				return home_url( '/wp-json/' . static::$api_url_default );
			}
		}
		else {
			static::$api_url = $api_url;
		}

		return static::$api_url;
	}

	/**
	 * Prevents the class from being cloned.
	 */
	private function __clone () { }
}
