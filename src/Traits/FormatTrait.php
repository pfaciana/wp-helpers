<?php

namespace RWP\Traits;

/**
 * Trait FormatTrait
 *
 * This trait provides utility methods for creating HTML tags, generating options HTML,
 * and formatting field names.
 */
trait FormatTrait
{
	/**
	 * List of HTML void tags that don't require a closing tag.
	 *
	 * @var array
	 */
	static protected $void_tags = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr', ];

	/**
	 * Create an HTML tag with attributes and content.
	 *
	 * @param string                    $tag_name The name of the HTML tag.
	 * @param array                     $attrs    An associative array of attribute names and values.
	 * @param string|array|array[]|null $content  The content to be placed inside the tag. Can be:
	 *                                            - string: Plain text or HTML to be inserted as-is.
	 *                                            - array: An array representing a nested tag, in the format: [tag_name, attrs, content, esc_html].
	 *                                            - array[]: Multiple nested tags, each in the above format.
	 *                                            - null: For empty tags or void elements.
	 * @param bool                      $esc_html Whether to escape the content HTML.
	 * @return string The generated HTML tag.
	 */
	static public function create_tag ( string $tag_name, array $attrs = [], string|array $content = NULL, bool $esc_html = FALSE ): string
	{
		if ( empty( $tag_name = tag_escape( $tag_name ) ) ) {
			return '';
		}

		$attr_string = '';

		foreach ( $attrs as $attr_name => $attr_values ) {
			if ( empty( $attr_name = sanitize_key( $attr_name ) ) || $attr_values === FALSE ) {
				continue;
			}
			if ( $attr_values === TRUE ) {
				$attr_values = $attr_name;
			}
			$attr_values = (array) $attr_values;
			foreach ( $attr_values as &$attr_value ) {
				$attr_value = $attr_name === 'class' ? sanitize_html_class( $attr_value ) : esc_attr( sanitize_text_field( $attr_value ) );
			}
			$attr_string .= ' ' . $attr_name . '="' . implode( ' ', $attr_values ) . '"';
		}

		if ( in_array( $tag_name, static::$void_tags, TRUE ) ) {
			return '<' . $tag_name . $attr_string . '>';
		}

		if ( is_array( $content ) ) {
			if ( is_array( $content[0] ) ) {
				$items   = $content;
				$content = '';
				foreach ( $items as $item ) {
					$content .= static::create_tag( ...$item );
				}
			}
			else {
				$content = static::create_tag( ...$content );
			}
		}
		else {
			$content = (string) $content;
			$content = $tag_name === 'textarea' ? esc_textarea( $content ) : ( $esc_html ? esc_html( $content ) : $content );
		}

		return '<' . $tag_name . $attr_string . '>' . $content . '</' . $tag_name . '>';
	}

	/**
	 * Generate HTML for select options.
	 *
	 * @example static::get_options_html(
	 *     [
	 *         'value1' => 'Label 1',
	 *         'value2' => 'Label 2',
	 *         'Group 1' => [
	 *             'value3' => 'Label 3',
	 *             'value4' => 'Label 4'
	 *         ]
	 *     ],
	 *     'value3'
	 * );
	 *
	 * @param array $options      An associative array of option values and labels. Can be:
	 *                            - Simple key-value pairs where the key is the option value and the value is the option label.
	 *                            - Nested arrays for option groups, where the key is the optgroup label and the value is an array of options.
	 * @param mixed $select_value The currently selected value(s). Can be a single value
	 *                            or an array of values for multi-select.
	 * @return string The generated HTML for select options.
	 */
	static public function get_options_html ( array $options = [], $select_value = NULL ): string
	{
		$html = '';

		foreach ( $options as $option_value => $text ) {
			if ( is_array( $text ) ) {
				$html .= static::create_tag( 'optgroup', [
					'label'    => $option_value,
					'selected' => is_array( $select_value ) ? in_array( $option_value, $select_value ) : $option_value == $select_value,
				], static::get_options_html( $text, $select_value ) );
			}
			else {
				$html .= static::create_tag( 'option', [
					'value'    => $option_value,
					'selected' => is_array( $select_value ) ? in_array( $option_value, $select_value ) : $option_value == $select_value,
				], $text );
			}
		}

		return $html;
	}

	/**
	 * Converts an array of values into a valid regex pattern for HTML5 input validation.
	 *
	 * @param array $options Array of values to be converted into a pattern.
	 * @return string Escaped and pipe-separated string for use in pattern attribute.
	 */
	static public function get_options_pattern ( array $options = [] ): string
	{
		if ( empty( $options ) || !is_array( $options ) ) {
			return '';
		}

		return implode( '|', array_map( fn( $option ) => esc_attr( preg_replace( '/([\\^$.*+?()[\]{}|])/', '\\\\$1', $option ) ), $options ) );
	}

	/**
	 * Generate an HTML datalist element with options.
	 *
	 * @example static::datalist(
	 *     'colors',
	 *     [
	 *         'red' => 'Red',
	 *         'blue' => 'Blue',
	 *         'green'
	 *     ]
	 * );
	 *
	 * @param string $id      The ID attribute for the datalist element.
	 * @param array  $options An associative array of option values and labels. Can be:
	 *                        - Key-value pairs where the key is the option value and the value is the option label.
	 *                        - Indexed array where the value is used for both the option value and label.
	 * @return string The generated HTML for the datalist element.
	 */
	static public function datalist ( string $id, array $options = [] ): string
	{
		$options_string = '';

		foreach ( $options as $value => $name ) {
			if ( is_int( $value ) ) {
				$value = $name;
				$name  = '';
			}
			$options_string .= static::create_tag( 'option', [ 'value' => $value, ], $name ) . "\n";
		}

		$html = static::create_tag( 'datalist', [ 'id' => $id, ], $options_string );

		return $html;
	}

	/**
	 * Generate a field name string for use in form inputs.
	 *
	 * @param string|array ...$names The components of the field name.
	 * @return string The formatted field name.
	 */
	static public function get_field_name ( ...$names )
	{
		if ( empty( $names ) ) {
			return '';
		}

		if ( count( $names ) === 1 && is_array( $names[0] ) ) {
			$names = $names[0];
		}

		$field_name = '';

		foreach ( $names as $index => $name ) {
			$field_name .= !$index ? $name : "[{$name}]";
		}

		return $field_name;
	}
}