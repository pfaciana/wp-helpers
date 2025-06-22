<?php

namespace RWP\Settings;

use RWP\Settings\Types\ErrorLocation;

class Setting extends AbstractSettings
{
	static protected string $type = 'setting';
	protected Page $page;
	protected Section $section;
	/** @var Field[] */
	protected array $fields = [];
	protected array $section_arg_keys = [
		'before_section',
		'after_section',
		'section_class',
	];

	/**
	 * Registers a setting and its data.
	 *
	 * @param string         $id                The name of an option to sanitize and save.
	 * @param string|Group   $group             A settings group name. Should correspond to an allowed option key name.
	 * @param array{
	 *     page?: string|Page,
	 *     section?: string|Section,
	 *     title?: string|callable,
	 *     content?: string|callable,
	 *     sanitize_callback?: callable,
	 *     error_location?: string|ErrorLocation,
	 *     default?: mixed,
	 *     show_in_rest?: bool|array,
	 *     type?: string,
	 *     label?: string,
	 *     description?: string,
	 * }                     $args              {
	 *                                          Data used to describe the setting when registered.
	 * @type string|Page     $page              The name of the Settings Page for this setting.
	 * @type string|Section  $section           The name of the Settings Section for this setting.
	 * @type string|callable $title             The title of the Settings Section.
	 * @type string|callable $content           The content of the Settings Section.
	 * @type callable        $sanitize_callback A callback function that sanitizes the option's value.
	 * @type string          $error_location    The location for the field error messages, as a value of the SettingsErrorLocation enum.
	 * @type mixed           $default           Default value when calling `get_option()`.
	 * @type bool|array      $show_in_rest      Whether data associated with this setting should be included in the REST API.
	 * @type string          $type              The type of data associated with this setting. Values are 'string', 'boolean', 'integer', 'number', 'array', and 'object'.
	 * @type string          $label             A label of the data attached to this setting.
	 * @type string          $description       A description of the data attached to this setting.
	 *                                          }
	 */
	public function __construct (
		protected string       $id,
		protected string|Group $group,
		protected array        $args = []
	) {
		// Setting
		static::set( $this->id, $this );

		// Group
		if ( !( $group instanceof Group ) ) {
			$this->group = Group::get( $group );
		}

		// Page
		$page = array_key_exists( 'page', $args ) ? $args['page'] : $this->id . '_page';
		if ( $page instanceof Page ) {
			$this->page = $page;
		}
		elseif ( is_string( $page ) ) {
			$this->page = Page::get( $page );
		}
		unset( $args['page'] );

		// Section
		$section = array_key_exists( 'section', $args ) ? $args['section'] : $this->id . '_section';
		if ( $section instanceof Section ) {
			$this->section = $section;
		}
		elseif ( is_string( $section ) ) {
			$section_args = [];
			foreach ( $this->section_arg_keys as $section_arg_key ) {
				if ( array_key_exists( $section_arg_key, $args ) ) {
					$section_args[$section_arg_key] = $args[$section_arg_key];
					unset( $args[$section_arg_key] );
				}
			}
			$section_args['title'] = array_key_exists( 'title', $args ) ? $args['title'] : '';
			unset( $args['title'] );
			$section_args['content'] = array_key_exists( 'content', $args ) ? $args['content'] : '';
			if ( !is_callable( $section_args['content'] ) ) {
				$section_args['content'] = fn() => print $section_args['content'];
			}
			unset( $args['content'] );
			$this->section = new Section( $section, $this->page->getId(), $section_args );
		}
		unset( $args['section'] );

		$this->args['default'] ??= NULL;

		\add_filter( "render/sanitize_setting/{$this->group->getId()}/{$this->id}", [ $this, 'sanitize_callback' ] );

		$sanitize_callback               = $this->args['sanitize_callback'] ?? NULL;
		$this->args['sanitize_callback'] = function ( $value ) use ( $sanitize_callback ) {
			$func_args    = func_get_args();
			$func_args[0] = \apply_filters( "render/sanitize_setting/{$this->group->getId()}/{$this->id}", ...$func_args );

			if ( !empty( $sanitize_callback ?? NULL ) && is_callable( $sanitize_callback ) ) {
				return $sanitize_callback( ...[ ...$func_args, $this->getValue(), $this->id ] );
			}

			return $func_args[0];
		};

		$this->setErrorLocation();

		// Setting
		\register_setting( $this->group->getId(), $this->id, $this->args );
	}

	/**
	 * Updates the default value for a specific field or the entire setting.
	 *
	 * @param string|null $field_id The ID of the field to update, or null for the entire setting.
	 * @param mixed       $default  The new default value.
	 * @return void
	 */
	public function updateDefault ( $field_id, $default )
	{
		global $wp_registered_settings;

		if ( isset( $field_id ) ) {
			$wp_registered_settings[$this->id]['default']            ??= [];
			$wp_registered_settings[$this->id]['default'][$field_id] = $default;
		}
		else {
			$wp_registered_settings[$this->id]['default'] = $default;
		}

		return;
	}

	/**
	 * Set the location for displaying error messages for this Setting.
	 *
	 * This determines where to display `settings_errors` based on the user defined `error_location` $args of this Setting.
	 * This value should be the string value representation of the SettingsErrorLocation enum
	 */
	protected function setErrorLocation ()
	{
		if ( empty( $location = $this->args["error_location"] ?? NULL ) ) {
			return;
		}

		$location = $location instanceof ErrorLocation ? $location->value : $location;

		match ( $location ) {
			ErrorLocation::ADMIN_NOTICES->value => \add_action( 'admin_notices', fn() => settings_errors( $this->id ) ),
			ErrorLocation::FORM->value => \add_action( "render/before_settings_group/{$this->group->getid()}", fn() => settings_errors( $this->id ) ),
			default => \add_action( "render/before_settings_page/{$this->group->getId()}/{$this->page->getId()}", fn() => settings_errors( $this->id ) ),
		};
	}

	/**
	 * Sanitize callback for the setting value.
	 *
	 * This method handles the sanitization of file uploads for the setting.
	 * If files are uploaded, it restructures the $_FILES data to match the
	 * expected format for the setting value.
	 *
	 * @param mixed $value The value to be sanitized.
	 * @return mixed The sanitized value. If no files are uploaded, returns the original value.
	 *                     Otherwise, returns a structured array of file upload data.
	 */
	public function sanitize_callback ( $value )
	{
		if ( !isset( $_FILES ) || !array_key_exists( $this->id, $_FILES ) ) {
			return $value;
		}

		$check = $_FILES[$this->id]['tmp_name'];

		if ( is_array( $check ) && !array_is_list( $check ) ) {
			foreach ( $_FILES[$this->id] as $file_key => $result ) {
				foreach ( $result as $field_name => $files ) {
					if ( is_array( $files ) ) {
						foreach ( $files as $index => $file ) {
							$value[$field_name][$index][$file_key] = $file;
						}
					}
					else {
						$value[$field_name][0][$file_key] = $files;
					}
				}
			}
		}
		else {
			$value = $_FILES[$this->id];
		}

		return $value;
	}

	/**
	 * Filters the setting value.
	 *
	 * @param mixed $value The value to be filtered.
	 * @return mixed The filtered value.
	 */
	public function filter_callback ( $value )
	{
		if ( empty( $fields = $this->fields ) ) {
			return $value;
		}

		foreach ( $fields as $field_id => $field ) {
			if ( $field->isSingle() ) {
				$value = $field->filterValue( $value );
				continue;
			}

			if ( !is_array( $value ) ) {
				continue;
			}

			$value[$field_id] = $field->filterValue( $value[$field_id] ?? NULL );
		}

		return $value;
	}

	/**
	 * Get the Group attached to this Setting
	 *
	 * @return Group
	 */
	public function getGroup (): Group
	{
		return $this->group;
	}

	/**
	 * Get the Page attached to this Setting
	 *
	 * @return Page
	 */
	public function getPage (): Page
	{
		return $this->page;
	}

	/**
	 * Get the Section attached to this Setting
	 *
	 * @return Section
	 */
	public function getSection (): Section
	{
		return $this->section;
	}

	/**
	 * Attach a Field to this Setting to reference when building the Form
	 *
	 * @param Field $field
	 */
	public function attachField ( Field $field )
	{
		$this->fields[$field->getId()] = $field;
	}

	/**
	 *
	 * @return Field[]
	 */
	public function getFields ( callable $filter = NULL ): array
	{
		if ( !is_callable( $filter ) ) {
			return $this->fields;
		}

		$fields = [];

		foreach ( $this->fields as $field ) {
			if ( $filter( $field ) ) {
				$fields[$field->getId()] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Adds a new field for this Setting
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 *  } $args
	 */
	public function addField ( string|array $id = NULL, array $args = [] ): Field
	{
		$args['setting'] ??= $this->id;
		$args['page']    ??= $this->page->getId();
		$args['section'] ??= $this->section->getId();

		$args["show"] ??= TRUE;

		return new Field( $id, $args );
	}

	/**
	 * Adds a new solo field to this setting.
	 *
	 * @see Field::__construct()
	 *
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
	 *     datalist?: array
	 *  } $args
	 */
	public function addSoloField ( array $args = [] ): Field
	{
		$args['single']  ??= TRUE;
		$args['default'] ??= $this->args['default'];

		return $this->addField( $this->id, $args );
	}

	/**
	 * Adds a new field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  }            $id
	 * @param string $type
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
	 *     datalist?: array
	 *  }            $args
	 */
	protected function createField ( string|array|null $id = NULL, string $type, array|bool $args = [], bool $show = TRUE ): Field
	{
		if ( is_array( $id ??= [] ) ) {
			if ( 'production' !== wp_get_environment_type() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				if ( array_key_exists( 'default', $id ) ) {
					$action = $type === 'hidden' ? 'admin_notices' : "render/before_settings_field/{$this->group->getId()}/{$this->page->getId()}/{$this->id}";
					\add_action( $action, function () use ( $type ) {
						echo wp_get_admin_notice( "<b>Warning!</b> Solo fields should have the default value on the Setting's args, not the Field's arg." . ( $type === 'hidden' ? ' (hidden field)' : '' ), [
							'type'               => 'warning',
							'additional_classes' => [ 'notice-alt' ],
						] ), '<br>';
					} );
				}
			}

			return $this->addSoloField( wp_parse_args( $id, [ 'type' => $type, 'show' => ( is_bool( $args ) ? $args : TRUE ) ] ) );
		}

		return $this->addField( $id, wp_parse_args( $args, [ 'type' => $type, 'show' => $show ] ) );
	}

	/**
	 * Adds a new checkbox field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 * } $id
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
	 *     datalist?: array
	 * } $args
	 */
	public function checkbox ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new color field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function color ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new date field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 * } $id
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
	 *     datalist?: array
	 * } $args
	 */
	public function date ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new datetime field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function datetime ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new email field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function email ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new file field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function file ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new hidden field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function hidden ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		if ( is_array( $id ??= [] ) ) {
			$id['class'] = trim( ( $id['class'] ?? '' ) . ' hide-all' );
		}
		else {
			$args['class'] = trim( ( $args['class'] ?? '' ) . ' hide-all' );
		}

		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new month field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function month ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new number field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function number ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new password field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function password ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new radio field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function radio ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		$args['label_for'] = FALSE;

		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new range field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function range ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new search field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function search ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new select field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function select ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new telephone field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function tel ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new text field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function text ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new textarea field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function textarea ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new time field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function time ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new toggle field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function toggle ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new url field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function url ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Adds a new week field to this setting.
	 *
	 * @see Field::__construct()
	 *
	 * @param ?string|array{
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
	 *     datalist?: array
	 *  } $id
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
	 *     datalist?: array
	 * }  $args
	 */
	public function week ( string|array $id = NULL, array|bool $args = [], bool $show = TRUE ): Field
	{
		return $this->createField( $id, __FUNCTION__, $args, $show );
	}

	/**
	 * Get this Setting's value
	 *
	 * @param mixed $field_id Optional. The field ID as an associative array key for this setting
	 * @param mixed $default  The default value if the field doesn't exist
	 * @return mixed
	 */
	public function getValue ( mixed $field_id = NULL, mixed $default = NULL ): mixed
	{
		$default ??= $this->args['default'] ?? NULL;

		$value = func_num_args() > 1 ? get_option( $this->id, $default ) : get_option( $this->id );
		$value = $this->filter_callback( $value );

		if ( !isset( $field_id ) ) {
			return $value;
		}

		// We know this should be an associative array, so bail if it's empty array or not an array
		if ( empty( $value ) || !is_array( $value ) || !array_key_exists( $field_id, $value ) ) {
			return $default;
		}

		return $value[$field_id];
	}

	/**
	 * Get this Setting's raw value
	 *
	 * @param mixed $field_id Optional. The field ID as an associative array key for this setting
	 * @param mixed $default  The default value if the field doesn't exist
	 * @return mixed
	 */
	public function getRawValue ( mixed $field_id = NULL, mixed $default = NULL ): mixed
	{
		$default ??= $this->args['default'] ?? NULL;

		$value = func_num_args() > 1 ? get_option( $this->id, $default ) : get_option( $this->id );

		if ( !isset( $field_id ) ) {
			return $value;
		}

		// We know this should be an associative array, so bail if it's empty array or not an array
		if ( empty( $value ) || !is_array( $value ) || !array_key_exists( $field_id, $value ) ) {
			return $default;
		}

		return $value[$field_id];
	}
}