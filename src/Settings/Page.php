<?php

namespace RWP\Settings;

class Page extends AbstractSettings
{
	static protected string $type = 'page';

	/**
	 * Constructor for the RWP\Settings\Page class.
	 *
	 * @param string $id The unique id of the Page
	 */
	public function __construct (
		public string $id,
	) {
		static::set( $this->id, $this );
	}

	/**
	 * Get a Page instance by id
	 *
	 * @param string $id      The unique id of the Page
	 * @param mixed  $default The default value if the Page is not found. Defaults to FALSE
	 * @return Page
	 */
	static public function get ( string $id, mixed $default = FALSE )
	{
		if ( !array_key_exists( static::$type, static::$items ) || !array_key_exists( $id, static::$items[static::$type] ) ) {
			return new static( $id );
		}

		return static::$items[static::$type][$id];
	}

	/**
	 * Initializes the settings section
	 *
	 * @param string $id             The unique id of the section. Only used internally, not in the UI (can be auto generated).
	 * @param array{
	 *    title?: string|callable,
	 *    content?: string|callable,
	 *    before_section?: string,
	 *    after_section?: string,
	 *    section_class?: string
	 * }             $args           {
	 *                               Additional custom items can be added, they will get passed on to the callback function.
	 * @type mixed   $title          Content for the section `<h2>` tag
	 * @type mixed   $content        Content between the `<h2>` tag and the fields `<table>` tag. It's passed the entire section array
	 * @type string  $before_section HTML content to prepend to the section's HTML output.
	 *                               Receives the section's class name as `%s`. Default empty.
	 * @type string  $after_section  HTML content to append to the section's HTML output. Default empty.
	 * @type string  $section_class  The class name to use for the section. Default empty.
	 *                               }
	 */
	public function addSection ( string $id, array $args = [], )
	{
		return new Section( $id, $this->id, $args );
	}
}
