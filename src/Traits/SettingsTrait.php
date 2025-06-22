<?php

namespace RWP\Traits;

use RWP\Settings\Group;
use RWP\Settings\Page;
use RWP\Settings\Setting;
use RWP\Settings\Field;
use RWP\Settings\Types\ErrorLocation;

trait SettingsTrait
{
	/**
	 * Register a new setting.
	 *
	 * @param string $option_name  The name of an option to sanitize and save.
	 * @param string $option_group A settings group name. Should correspond to an allowed option key name.
	 * @param array{
	 *     page?: string|Page,
	 *     section?: string|\RWP\Settings\Section,
	 *     title?: string|callable,
	 *     content?: string|callable,
	 *     sanitize_callback?: callable,
	 *     error_location?: string|ErrorLocation,
	 *     default?: mixed,
	 *     show_in_rest?: bool|array,
	 *     type?: string,
	 *     label?: string,
	 *     description?: string,
	 *     before_section?: string,
	 *     after_section?: string,
	 *     section_class?: string
	 * }             $args         Optional. Additional arguments for registering the setting.
	 * @return Setting The newly created Setting object.
	 */
	public function registerSetting ( string $option_name, string $option_group, array $args = [] ): Setting
	{
		return new Setting( ...func_get_args() );
	}

	/**
	 * Retrieve a settings setting by its ID.
	 *
	 * @param string $id      The ID of the settings setting to retrieve.
	 * @param mixed  $default Optional. The default value to return if the group is not found.
	 * @return Setting The retrieved Group object or the default value.
	 */
	public function getSetting ( string $id, mixed $default = FALSE ): Setting
	{
		return Setting::get( ...func_get_args() );
	}

	/**
	 * Retrieve a settings group by its ID.
	 *
	 * @param string $id      The ID of the settings group to retrieve.
	 * @param mixed  $default Optional. The default value to return if the group is not found.
	 * @return Group The retrieved Group object or the default value.
	 */
	public function getSettingGroup ( string $id, mixed $default = FALSE ): Group
	{
		return Group::get( ...func_get_args() );
	}

	/**
	 * Retrieve a settings page by its ID.
	 *
	 * @param string $id      The ID of the settings page to retrieve.
	 * @param mixed  $default Optional. The default value to return if the page is not found.
	 * @return Page The retrieved Page object or the default value.
	 */
	public function getSettingsPage ( string $id, mixed $default = FALSE ): Page
	{
		return Page::get( ...func_get_args() );
	}

	/**
	 * Generate and return the HTML form for a settings group.
	 *
	 * @param string $group_id The ID of the settings group for which to generate the form.
	 * @param array{
	 *     needs_form_data?: bool,
	 *     form_attrs?: array<string, string>,
	 *     form_attr?: array<string, string>
	 * }             $args     Optional. Additional arguments for form generation.
	 * @return string The HTML markup for the settings form.
	 */
	public function getSettingsForm ( string $group_id, array $args = [] ): string
	{
		return $this->getSettingGroup( $group_id )->getForm( $args );
	}

	/**
	 * Get this Setting's value
	 *
	 * @param string|Setting $setting  The Setting or setting ID
	 * @param string|Field   $field_id Optional. The Field or field ID as an associative array key for this setting
	 * @param mixed          $default  The default value if the field doesn't exist
	 * @return mixed
	 */
	public function getOption ( string|Setting $setting, string|Field $field_id = NULL, mixed $default = NULL ): mixed
	{
		$setting = $setting instanceof Setting ? $setting : $this->getSetting( $setting );

		if ( func_num_args() < 2 ) {
			return $setting->getValue();
		}

		$field_id = $field_id instanceof Field ? $field_id->getId() : $field_id;

		return func_num_args() < 3 ? $setting->getValue( $field_id ) : $setting->getValue( $field_id, $default );
	}

	/**
	 * Get this Setting's raw value
	 *
	 * @param string|Setting $setting_id The Setting or setting ID
	 * @param string|Field   $field_id   Optional. The Field or field ID as an associative array key for this setting
	 * @param mixed          $default    The default value if the field doesn't exist
	 * @return mixed
	 */
	public function getRawOption ( string|Setting $setting_id, string|Field $field_id = NULL, mixed $default = NULL ): mixed
	{
		$default ??= $this->args['default'] ?? NULL;

		$setting_id = $setting_id instanceof Setting ? $setting_id->getId() : $setting_id;

		$value = func_num_args() > 2 ? get_option( $setting_id, $default ) : get_option( $setting_id );

		if ( !isset( $field_id ) ) {
			return $value;
		}

		$field_id = $field_id instanceof Field ? $field_id->getId() : $field_id;

		// We know this should be an associative array, so bail if it's empty array or not an array
		if ( empty( $value ) || !is_array( $value ) || !array_key_exists( $field_id, $value ) ) {
			return $default;
		}

		return $value[$field_id];
	}
}