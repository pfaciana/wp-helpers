<?php
/**
 * Taxonomy REST API Enhancement for Intuitive Custom Post Order (HICPO)
 *
 * This class extends WordPress REST API to include custom ordering information
 * for taxonomies that have been made sortable through the HICPO plugin.
 *
 * The HICPO plugin (https://hijiriworld.com/web/plugins/intuitive-custom-post-order/)
 * allows administrators to manually sort taxonomies using drag-and-drop in the WordPress admin.
 * This class ensures that custom ordering is available via the REST API for modern applications.
 */

namespace RWP\Api;

class Taxonomy
{
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
		if ( !empty( $hicpo_options = get_option( 'hicpo_options' ) ) ) {
			$taxonomies = array_key_exists( 'tags', $hicpo_options ) && !empty( $hicpo_options['tags'] ) ? $hicpo_options['tags'] : [];
			foreach ( $taxonomies as $taxonomy ) {
				add_filter( "rest_prepare_{$taxonomy}", [ __CLASS__, 'add_term_order' ], 10, 3 );
			}
		}
	}

	/**
	 * Adds the term_order value to the response data.
	 *
	 * @param \WP_REST_Response $response The REST API response object
	 * @param \WP_Term          $term     The taxonomy term object
	 * @param \WP_REST_Request  $request  The original REST API request
	 *
	 * @return \WP_REST_Response Modified response including term_order
	 */
	public static function add_term_order ( $response, $term, $request )
	{
		if ( !property_exists( $term, 'term_order' ) ) {
			return $response;
		}

		$response->data['term_order'] = +$term->term_order;

		return $response;
	}

	/**
	 * Prevents the class from being cloned.
	 */
	private function __clone ()
	{
		throw new \RuntimeException( 'Cloning is not allowed for this class' );
	}
}