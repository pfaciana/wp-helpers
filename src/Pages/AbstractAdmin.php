<?php

namespace RWP\Pages;

/**
 * Abstract class for creating WordPress admin pages
 *
 * Handles menu registration, URL management, and page rendering by extending
 * WordPress's add_menu_page() and add_submenu_page() functionality.
 */
abstract class AbstractAdmin
{
	/** @var string Menu item text shown in navigation */
	protected $menu_title = '';

	/** @var string Page title shown in browser tab/title */
	protected $page_title = '';

	/** @var string Unique slug to identify this menu */
	protected $menu_slug = '';

	/** @var string Required capability to access this page */
	protected $capability = 'manage_options';

	/** @var string Dashicon name or URL to menu icon */
	protected $icon = '';

	/** @var int|null Position in menu order */
	protected $position = NULL;

	/** @var string Parent menu slug for submenu pages */
	protected $parent_slug = '';

	/** @var string Base parent slug without query args */
	protected $parent_slug_base = '';

	/** @var string Full current request URL */
	protected $current_url = '';

	/** @var string Base admin URL for this page */
	protected $admin_url = '';

	/** @var string Current admin URL with query parameters */
	protected $current_admin_url = '';

	/** @var string WordPress hook suffix for this page */
	protected $hook_suffix = '';

	/** @var int|null Priority for admin_menu action */
	protected $priority = NULL;

	/**
	 * Get singleton instance of the admin page
	 *
	 * @return static Instance of the admin page class
	 */
	public static function get_instance (): static
	{
		static $instance;

		if ( !( $instance instanceof static ) && static::class !== self::class ) {
			$instance = new static;
		}

		return $instance;
	}

	/**
	 * Initialize the admin page
	 *
	 * @throws \Exception If page_title or menu_title are empty
	 */
	protected function __construct ()
	{
		$this->init();

		if ( empty( $this->menu_title ) ) {
			$this->menu_title = $this->page_title;
		}

		if ( empty( $this->page_title ) ) {
			$this->page_title = $this->menu_title;
		}

		if ( empty( $this->menu_slug ) ) {
			$this->menu_slug = sanitize_title( $this->menu_title );
		}

		$this->admin_url = $this->get_admin_url();

		$this->current_url = ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '' ) . $_SERVER['REQUEST_URI'];

		$this->priority = $this->priority ?? ( $this->parent_slug ? 100 : 10 );

		add_action( 'admin_menu', function () {
			if ( empty( $this->page_title ) || empty( $this->menu_title ) ) {
				throw new \Exception( 'Error: Both page_title and menu_title must not be empty.' );
			}

			if ( empty( $this->parent_slug ) ) {
				$this->hook_suffix = add_menu_page( $this->page_title, $this->menu_title, $this->capability, $this->menu_slug, [ $this, 'output' ], $this->icon, $this->position );
			}
			else {
				$this->hook_suffix = add_submenu_page( $this->parent_slug, $this->page_title, $this->menu_title, $this->capability, $this->menu_slug, [ $this, 'output' ] );
			}
		}, $this->priority );
	}

	/**
	 * Generate the admin menu page URL
	 *
	 * @return string Admin menu page URL
	 */
	protected function menu_page_url (): string
	{
		$this->parent_slug_base = $this->parent_slug ? explode( '?', $this->parent_slug )[0] : '';

		if ( str_ends_with( $this->parent_slug_base, '.php' ) ) {
			$url = admin_url( add_query_arg( 'page', $this->menu_slug, $this->parent_slug ) );
		}
		else {
			$url = admin_url( 'admin.php?page=' . $this->menu_slug );
		}

		return $url;
	}

	/**
	 * Get the base admin URL with optional query parameters
	 *
	 * @param mixed ...$query_args Query arguments to append
	 * @return string Admin URL with optional query args
	 */
	public function get_admin_url ( ...$query_args ): string
	{
		if ( empty( $this->admin_url ) ) {
			$this->admin_url = $this->menu_page_url();
		}

		if ( !empty( $query_args ) ) {
			$query_args[] = $this->admin_url;

			return add_query_arg( ...$query_args );
		}

		return $this->admin_url;
	}

	/**
	 * Get current admin URL including existing query parameters
	 *
	 * @param mixed ...$query_args Additional query arguments to append
	 * @return string Current admin URL with query args
	 */
	public function get_current_admin_url ( ...$query_args ): string
	{
		if ( empty( $this->current_admin_url ) ) {
			$this->current_admin_url = add_query_arg( $_GET ?? [], $this->get_admin_url() );
		}

		if ( !empty( $query_args ) ) {
			$query_args[] = $this->current_admin_url;

			return add_query_arg( ...$query_args );
		}

		return $this->current_admin_url;
	}

	/**
	 * Initialize the admin page
	 *
	 * Optional method that runs during construction
	 *
	 * @return void
	 */
	protected function init () { }

	/**
	 * Output the admin page content
	 *
	 * Must be implemented by child classes to render the admin page
	 *
	 * @return void
	 */
	abstract public function output ();
}