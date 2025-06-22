<?php

namespace RWP\Settings;

use RWP\Settings\Types\ErrorLocation;

class Field extends AbstractSettings
{
	static protected string $type = 'field';
	protected Setting $setting;
	protected Page $page;
	protected Section $section;

	/**
	 * Adds a new field to a section of a settings page.
	 *
	 * Part of the Settings API. Use this to define a settings field that will show
	 * as part of a settings section inside a settings page. The fields are shown using
	 * do_settings_fields() in do_settings_sections().
	 *
	 * The $callback argument should be the name of a function that echoes out the
	 * HTML input tags for this setting field. Use get_option() to retrieve existing
	 * values to show.
	 *
	 * @param string         $id                The unique id of the field. Only used internally, not in the UI (can be auto generated).
	 * @param array{
	 *     setting: string|Setting,
	 *     page: string|Page,
	 *     section: string|Section,
	 *     title?: string,
	 *     content?: string|callable,
	 *     label_for?: string,
	 *     class?: string,
	 *     type?: string,
	 *     single?: bool,
	 *     default?: mixed,
	 *     sanitize_callback?: callable,
	 *     filter_callback?: callable,
	 *     error_location?: string|ErrorLocation,
	 *     attrs?: array,
	 *     options?: array,
	 *     inline?: string,
	 *     desc_inline?: string,
	 *     description?: string,
	 *     before_field?: string,
	 *     after_field?: string,
	 *     list?: string,
	 *     datalist?: array,
	 *     show?: bool,
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
	 * @type callable        $sanitize_callback A function to sanitize the input value before saving to the database.
	 * @type callable        $filter_callback   A function to format the value from the database.
	 * @type string          $error_location    The location where error messages should be displayed as SettingsErrorLocation or SettingsErrorLocation string value
	 * @type array           $attrs             Additional HTML attributes for the input field.
	 * @type array           $options           Options for select, radio, or checkbox inputs.
	 * @type string          $inline            Content to be displayed inline with the input.
	 * @type string          $desc_inline       Description to be displayed inline with the input.
	 * @type string          $description       Description to be displayed below the input.
	 * @type string          $before_field      Content to be displayed before the field.
	 * @type string          $after_field       Content to be displayed after the field.
	 * @type string          $list              ID of a datalist element to generate a default datalist.
	 * @type array           $datalist          Array of options for the datalist.
	 * @type bool            $show              Whether the Settings API should print the field automatically. Default true.
	 *                                          }
	 */
	public function __construct (
		protected string $id,
		protected array  $args = []
	) {
		$title               = array_key_exists( 'title', $args ) ? $args['title'] : ucwords( trim( str_replace( [ '_', '-' ], ' ', $this->id ) ) );
		$this->args['title'] = $title; // Don't unset, so we can use in radio button accessibility

		$content = array_key_exists( 'content', $args ) ? $args['content'] : fn( $cb_args ) => print $this->content( $cb_args );
		if ( !is_callable( $content ) ) {
			$content = fn() => print $content;
		}
		unset( $args['content'] );

		$this->setting = $args['setting'] instanceof Setting ? $args['setting'] : Setting::get( $args['setting'], NULL );
		unset( $args['setting'] );

		$this->page = $args['page'] instanceof Page ? $args['page'] : Page::get( $args['page'], 'general' );
		unset( $args['page'] );

		$this->section = $args['section'] instanceof Section ? $args['page'] : Section::get( $args['section'], 'default' );
		unset( $args['section'] );

		[ $field_name, $field_name_id ] = $this->get_field_names();

		$this->args['type']      ??= 'text';
		$this->args['label_for'] ??= $field_name_id;
		$this->args['single']    ??= FALSE;
		$this->args['default']   ??= NULL;

		$this->setting->updateDefault( $this->args["single"] ? NULL : $this->id, $this->args['default'] );

		if ( !empty( $this->args['sanitize_callback'] ?? NULL ) && is_callable( $this->args['sanitize_callback'] ) ) {
			\add_filter( "render/sanitize_setting/{$this->setting->getGroup()->getId()}/{$this->setting->getId()}", function ( $options ) {
				$func_args = func_get_args();
				if ( $this->args['single'] ) {
					return $this->args['sanitize_callback']( ...$func_args );
				}

				if ( !is_array( $options ) ) {
					return $options;
				}

				[ $field_name, $field_name_id ] = $this->get_field_names();
				$form_data = [
					'field_name'     => $field_name,
					'field_id'       => $field_name_id,
					'field_type'     => $this->get_field_type(),
					'single_field'   => $this->isSingle(),
					'setting_values' => $func_args[0],
				];

				$options[$this->id] = $this->args['sanitize_callback']( ...[ $options[$this->id] ?? NULL, $this->get_field_value(), $field_name, $this->id, $this->setting->getId(), $form_data ] );

				return $options;
			} );
		}

		$this->setErrorLocation();

		$this->setting->attachField( $this );

		if ( $args['show'] ?? TRUE ) {
			\add_settings_field( $this->id, $title, $content, $this->page->getId(), $this->section->getId(), $this->args );
		}
	}

	/**
	 * Set the location for displaying error messages for this Field.
	 *
	 * This determines where to display `settings_errors` based on the user defined `error_location` $args of this Field.
	 * This value should be the string value representation of the SettingsErrorLocation enum
	 */
	protected function setErrorLocation ()
	{
		if ( empty( $location = $this->args["error_location"] ?? NULL ) ) {
			return;
		}

		$field_name = $this->get_field_names()[0];

		$location = $location instanceof ErrorLocation ? $location->value : $location;

		match ( $location ) {
			ErrorLocation::ADMIN_NOTICES->value => \add_action( 'admin_notices', fn() => settings_errors( $field_name ) ),
			ErrorLocation::FORM->value => \add_action( "render/before_settings_group/{$this->setting->getGroup()->getid()}", fn() => settings_errors( $field_name ) ),
			ErrorLocation::SECTION->value => \add_action( "render/before_settings_page/{$this->setting->getGroup()->getId()}/{$this->page->getId()}", fn() => settings_errors( $field_name ) ),
			default => \add_action( "render/before_settings_field/{$this->setting->getGroup()->getId()}/{$this->page->getId()}/{$this->id}", fn() => print settings_errors( $field_name ) . '<br>' ),
		};
	}

	/**
	 * Checks if the field is a single field or part of a group.
	 *
	 * @return bool True if the field is single, false otherwise.
	 */
	public function isSingle ()
	{
		return $this->args['single'] ?? FALSE;
	}

	/**
	 * Filters the value of the field using a custom callback if provided.
	 *
	 * @param mixed $value The value to be filtered.
	 * @return mixed The filtered value.
	 */
	public function filterValue ( $value )
	{
		if ( !empty( $this->args['filter_callback'] ?? NULL ) && is_callable( $this->args['filter_callback'] ) ) {
			[ $field_name, $field_name_id ] = $this->get_field_names();
			$form_data = [
				'field_name'   => $field_name,
				'field_id'     => $field_name_id,
				'field_type'   => $this->get_field_type(),
				'single_field' => $this->isSingle(),
			];

			return $this->args['filter_callback']( $value, $this->id, $this->setting->getId(), $form_data );
		}

		return $value;
	}

	/**
	 * Get this Field's value
	 *
	 * @param mixed $default The default value if the field doesn't exist
	 * @return mixed
	 */
	public function getValue ( mixed $default = NULL ): mixed
	{
		$default ??= $this->args['default'] ?? NULL;

		return !func_num_args() ? $this->setting->getValue( $this->id ) : $this->setting->getValue( $this->id, $default );
	}

	/**
	 * Generate the content for the settings field.
	 *
	 * This method outputs the HTML for the field, including any associated
	 * labels, descriptions, and inline content. It also handles the rendering
	 * of different input types based on the provided arguments.
	 *
	 * @param array{
	 *     type?: string,
	 *     before_field?: string,
	 *     inline?: string,
	 *     desc_inline?: string,
	 *     description?: string,
	 *     after_field?: string
	 * }            $args         Optional. An associative array of arguments to customize the field output.
	 *
	 * @type string $type         The type of input field (e.g., 'text', 'checkbox', etc.).
	 * @type string $before_field HTML to display before the field.
	 * @type string $inline       Content to display inline with the input.
	 * @type string $desc_inline  Description to display inline with the input.
	 * @type string $description  Description to display below the input.
	 * @type string $after_field  HTML to display after the field.
	 * @return string The generated HTML content for the field.
	 */
	protected function content ( array $args = [] ): string
	{
		ob_start();

		$args['attrs'] ??= $args['attr'] ?? [];

		if ( empty( $args['type'] ?? NULL ) ) {
			$args['type'] = 'text';
		}

		do_action( "render/before_settings_field/{$this->setting->getGroup()->getId()}/{$this->page->getId()}/{$this->id}" );

		if ( $args['before_field'] ?? FALSE ) {
			echo $args['before_field'];
		}

		echo $this->{$args['type']}( $args ) ?: '';

		if ( $args['inline'] ?? FALSE ) {
			echo $args['inline'];
		}

		if ( $args['desc_inline'] ?? FALSE ) {
			echo static::create_tag( 'span', [
				'class' => 'description',
			], $args['desc_inline'] );
		}

		if ( $args['description'] ?? FALSE ) {
			echo static::create_tag( 'p', [
				'class' => 'description',
				'id'    => "{$this->id}-description",
			], $args['description'] );
		}

		if ( $args['after_field'] ?? FALSE ) {
			echo $args['after_field'];
		}

		return ob_get_clean();
	}

	/**
	 * Get the content for the settings field.
	 *
	 * This method returns the HTML for the field. It also handles the rendering
	 * of different input types based on the provided arguments.
	 *
	 * @return string
	 */
	public function html ()
	{
		return $this->content( $this->args );
	}

	/**
	 * Get the type of the field.
	 *
	 * This method retrieves the type of the field as defined in the arguments.
	 *
	 * @return string The type of the field (e.g., 'text', 'checkbox', etc.).
	 */
	public function get_field_type ()
	{
		return $this->args['type'];
	}

	/**
	 * Get the field names for the setting.
	 *
	 * This method generates the field name and field ID based on the setting ID
	 * and the field ID. It handles both single and multiple field scenarios.
	 *
	 * @return array An array containing the field name and field ID.
	 */
	public function get_field_names ()
	{
		$setting_id = $this->setting->getId();
		$field_id   = $this->id;

		if ( $this->args['single'] ?? FALSE ) {
			$field_name    = static::get_field_name( $setting_id );
			$field_name_id = $setting_id;
		}
		else {
			$field_name    = static::get_field_name( $setting_id, $field_id );
			$field_name_id = implode( '_', [ $setting_id, $field_id ] );
		}

		return [ $field_name, $field_name_id ];
	}

	/**
	 * Get the field value.
	 *
	 * This method retrieves the value of the field from the options array.
	 * It handles both single and multiple field scenarios.
	 *
	 * @return mixed The value of the field.
	 */
	public function get_field_value ()
	{
		$setting_id = $this->setting->getId();
		$field_id   = $this->id;

		$options = get_option( $setting_id, NULL );
		if ( $this->args['single'] ?? FALSE ) {
			$value = $options ?? $this->args['default'];
		}
		else {
			if ( is_array( $options ) && array_key_exists( $field_id, $options ) ) {
				$value = $options[$field_id];
			}
			else {
				$value = $this->args['default'] ?? ( is_scalar( $options ) ? $options : '' );
			}
		}

		return $value;
	}

	/**
	 * Generate the HTML for the input field.
	 *
	 * This method creates the HTML for the input field based on the provided
	 * arguments, including attributes and the field type.
	 *
	 * @param array $args Optional. An associative array of arguments to customize the input field.
	 * @return string The generated HTML for the input field.
	 */
	public function input ( array $args = [] )
	{
		[ $field_name, $field_name_id ] = $this->get_field_names();
		$value = $this->get_field_value();

		if ( ( $args['list'] ?? FALSE ) || ( $args['datalist'] ?? FALSE ) ) {
			$args['attrs']['list'] = is_string( $args['list'] ?? FALSE ) ? $args['list'] : "{$field_name_id}-options";
		}

		$tag_name = match ( $args['type'] ?? 'text' ) {
			'textarea' => 'textarea',
			default => 'input',
		};

		ob_start();

		if ( !empty( $args['attrs']['pattern'] ?? NULL ) && is_array( $args['attrs']['pattern'] ) ) {
			$args['attrs']['pattern'] = static::get_options_pattern( $args['attrs']['pattern'] );
		}

		$is_multiple = $args['type'] === 'checkbox' || ( $args['attrs']['multiple'] ?? FALSE );
		echo static::create_tag( $tag_name, wp_parse_args( $args['attrs'], [
			'type'             => $args['type'],
			'name'             => $is_multiple ? "{$field_name}[]" : $field_name,
			'id'               => $field_name_id,
			'aria-describedby' => ( $args['description'] ?? FALSE ) ? $field_name_id . '-description' : FALSE,
			'class'            => $args['attrs']['class'] ?? ( $args['type'] === 'hidden' ? FALSE : 'regular-text' ),
			'value'            => $tag_name !== 'textarea' ? $value : FALSE,
		] ), $tag_name === 'textarea' ? $value : NULL );

		if ( $args['datalist'] ?? FALSE ) {
			echo static::datalist( "{$field_name_id}-options", $args['datalist'] );
		}

		return ob_get_clean();
	}

	/**
	 * Generate the HTML for a multi-input field.
	 *
	 * This method creates the HTML for a multi-input field, which can be used
	 * for fields like checkboxes, radio buttons, etc.
	 *
	 * @param array $args Optional. An associative array of arguments to customize the multi-input field.
	 * @return string The generated HTML for the multi-input field.
	 */
	public function multiInput ( array $args = [] ): string
	{
		[ $field_name, $field_name_id ] = $this->get_field_names();
		$value = $this->get_field_value();

		$type = match ( $args['type'] ?? 'checkbox' ) {
			'toggle' => 'checkbox',
			default => $args['type'],
		};

		$items = [];

		ob_start();

		foreach ( $args['options'] as $option_value => $option_name ) {
			$tag_args = FALSE;
			if ( is_array( $option_name ) ) {
				$tag_args    = $option_name[$key = array_key_first( $option_name )];
				$option_name = $key;
			}
			$is_multiple = $args['type'] === 'checkbox' || ( $args['attrs']['multiple'] ?? FALSE );
			$item        = static::create_tag( 'input', wp_parse_args( $args['attrs'] ?? [], [
				'type'    => $type,
				'name'    => $is_multiple ? "{$field_name}[]" : $field_name,
				'value'   => $option_value,
				'checked' => is_array( $value ) ? in_array( $option_value, $value ) : $option_value == $value,
			] ) );
			$label       = static::create_tag( 'label', [], $item . $option_name );
			if ( !empty( $tag_args ?? FALSE ) ) {
				foreach ( (array) $tag_args as $tag_item ) {
					$label .= $tag_item;
				}
			}
			$items[] = $label;
		}

		$legend = static::create_tag( 'legend', [
			'class' => 'screen-reader-text',
		], "<span> {$args['title']} </span>" );

		echo static::create_tag( 'fieldset', [], $legend . implode( '<br>', $items ) );

		return ob_get_clean();
	}

	public function checkbox ( array $args = [] ): string
	{
		return $this->multiInput( ...func_get_args() );
	}

	public function color ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function date ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function datetime ( array $args = [] ): string
	{
		$args['type'] = 'datetime-local';

		return $this->input( $args );
	}

	public function email ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function file ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function hidden ( array $args = [] ): string
	{
		foreach ( [ 'value' ] as $key ) {
			if ( isset( $args[$key] ) ) {
				$args['attrs'][$key] = $args[$key];
			}
		}

		return $this->input( ...func_get_args() );
	}

	public function month ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function number ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function password ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function radio ( array $args = [] ): string
	{
		return $this->multiInput( ...func_get_args() );
	}

	public function range ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function search ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function select ( array $args = [] ): string
	{
		[ $field_name, $field_name_id ] = $this->get_field_names();
		$value = $this->get_field_value();

		ob_start();

		$is_multiple = $args['type'] === 'checkbox' || ( $args['attrs']['multiple'] ?? FALSE );
		echo static::create_tag( 'select', wp_parse_args( $args['attrs'] ?? [], [
			'name'             => $is_multiple ? "{$field_name}[]" : $field_name,
			'id'               => $field_name_id,
			'aria-describedby' => ( $args['description'] ?? FALSE ) ? $field_name_id . '-description' : FALSE,
			'class'            => $args['attrs']['class'] ?? 'regular-text',
		] ), static::get_options_html( $args['options'], $value ) );

		return ob_get_clean();
	}

	public function tel ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function text ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function textarea ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function toggle ( array $args = [] ): string
	{
		$args['attrs']['data-togglebox'] ??= 1;

		return $this->multiInput( ...func_get_args() );
	}

	public function time ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function url ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}

	public function week ( array $args = [] ): string
	{
		return $this->input( ...func_get_args() );
	}
}
