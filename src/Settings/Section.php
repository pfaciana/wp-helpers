<?php

namespace RWP\Settings;

use RWP\Settings\Types\ErrorLocation;

class Section extends AbstractSettings
{
	static protected string $type = 'section';

	/**
	 * Constructor for the SettingsSection class.
	 *
	 * Initializes the settings section by calling add_settings_section and
	 * stores the necessary properties for adding fields later.
	 *
	 * @param string $id             The unique id of the section. Only used internally, not in the UI (can be auto generated).
	 * @param string $page           The unique id of the settings area on which to show this section.
	 *                               Built-in pages include 'general', 'writing', 'reading', 'discussion', 'media' and 'permalink'
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
	public function __construct (
		protected string $id,
		protected string $page,
		protected array  $args = []
	) {

		$title = array_key_exists( 'title', $args ) ? $args['title'] : '';
		unset( $args['title'] );

		$content = array_key_exists( 'content', $args ) ? $args['content'] : '';
		if ( !is_callable( $content ) ) {
			$content = fn() => print $content;
		}
		unset( $args['content'] );

		static::set( $this->id, $this );
		\add_settings_section( $this->id, $title, $content, $this->page, $this->args );
	}

	/**
	 * Get a Section instance by id
	 *
	 * @param string $id      The unique id of the Section
	 * @param mixed  $default The default value if the Section is not found. Defaults to FALSE
	 * @return Section
	 */
	static public function get ( string $id, mixed $default = FALSE )
	{
		if ( !array_key_exists( static::$type, static::$items ) || !array_key_exists( $id, static::$items[static::$type] ) ) {
			return new static( $id, $default );
		}

		return static::$items[static::$type][$id];
	}

	/**
	 * Adds a new settings field to the section.
	 *
	 * This method utilizes the stored $page and $section ID to streamline
	 * the process of adding settings fields.
	 *
	 * @param string         $id                The unique id of the field. Only used internally, not in the UI (can be auto generated).
	 * @param array{
	 *    setting: string|Setting,
	 *    page: string|Page,
	 *    section: string|Section,
	 *    title?: string,
	 *    content?: string|callable,
	 *    label_for?: string,
	 *    class?: string,
	 *    type?: string,
	 *    single?: bool,
	 *    default?: mixed,
	 *    sanitize_callback?: callable,
	 *    error_location?: string|ErrorLocation,
	 *    attrs?: array,
	 *    options?: array,
	 *    inline?: string,
	 *    desc_inline?: string,
	 *    description?: string,
	 *    before_field?: string,
	 *    after_field?: string,
	 *    list?: string,
	 *    datalist?: array
	 * }                     $args              {
	 *                                          Optional. Extra arguments that get passed to the callback function.
	 *                                          Additional custom items can be added, they will get passed on to the callback function.
	 * @type string|Setting  $setting           The unique id of the Settings Setting this field is attached to, or a Setting object.
	 * @type string|Page     $page              The unique id of the settings area on which to show the section, or a Page object.
	 *                                          Built-in pages include 'general', 'writing', 'reading', 'discussion', 'media' and 'permalink'
	 * @type string|Section  $section           Optional. The unique id of the settings page on which to show the field, or a Section object. Default 'default'.
	 * @type string          $title             Content for the `<th>` tag
	 * @type string|callable $content           Content for the `<td>` tag. The function should echo its output.
	 * @type string          $label_for         When supplied, the setting title will be wrapped in a `<label>` element,
	 *                                          should match the form element `id` attribute.
	 * @type string          $class             CSS Class for the `<tr>` tag.
	 * @type string          $type              The type of the input field (e.g., 'text', 'checkbox', 'radio', etc.).
	 * @type bool            $single            Whether this treated as a solo field or one field of many within a Setting
	 * @type mixed           $default           The default value for the field.
	 * @type callable        $sanitize_callback A function to sanitize the input value.
	 * @type string          $error_location    The location where error messages should be displayed as SettingsErrorLocation string
	 * @type array           $attrs             Additional HTML attributes for the input field.
	 * @type array           $options           Options for select, radio, or checkbox inputs.
	 * @type string          $inline            Content to be displayed inline with the input.
	 * @type string          $desc_inline       Description to be displayed inline with the input.
	 * @type string          $description       Description to be displayed below the input.
	 * @type string          $before_field      Content to be displayed before the field.
	 * @type string          $after_field       Content to be displayed after the field.
	 * @type string          $list              ID of a datalist element to generate a default datalist.
	 * @type array           $datalist          Array of options for the datalist.
	 *                                          }
	 */
	public function addField ( $id, $args = [] )
	{
		$args['setting'] ??= NULL;
		$args['page']    ??= $this->page;
		$args['section'] ??= $this->id;

		return new Field( $id, $args );
	}
}
