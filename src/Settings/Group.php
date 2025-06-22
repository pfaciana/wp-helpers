<?php

namespace RWP\Settings;

use RWP\Settings\Types\ErrorLocation;

class Group extends AbstractSettings
{
	static protected string $type = 'group';
	protected array $pages = [];
	protected array $sections = [];
	protected array $settings = [];

	/**
	 * Constructor for the RWP\Settings\Group class.
	 *
	 * @param string $id The unique id of the Group
	 */
	public function __construct (
		protected string $id,
	) {
		static::set( $this->id, $this );
	}

	/**
	 * Get a Page instance by id
	 *
	 * @param string $id      The unique id of the Group
	 * @param mixed  $default The default value if Group is not found. Defaults to FALSE
	 * @return Group
	 */
	static public function get ( string $id, mixed $default = FALSE )
	{
		if ( !array_key_exists( static::$type, static::$items ) || !array_key_exists( $id, static::$items[static::$type] ) ) {
			return new static( $id );
		}

		return static::$items[static::$type][$id];
	}

	/**
	 * Add a Setting to the Group
	 *
	 * @param string         $id
	 * @param array{
	 *    page?: string|Page,
	 *    section?: string|Section,
	 *    title?: string|callable,
	 *    content?: string|callable,
	 *    sanitize_callback?: callable,
	 *    error_location?: string|ErrorLocation,
	 *    default?: mixed,
	 *    show_in_rest?: bool|array,
	 *    type?: string,
	 *    label?: string,
	 *    description?: string,
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
	 * @return Setting
	 */
	public function addSetting ( string $id, array $args = [] )
	{
		$this->settings[] = $setting = new Setting( $id, $this->id, $args );
		$this->pages[]    = $setting->getPage();
		$this->sections[] = $setting->getSection();

		return $setting;
	}

	/**
	 * Get the Pages attached to this Group
	 *
	 * @return Page[]
	 */
	public function getPages (): array
	{
		return $this->pages;
	}

	/**
	 * Get the Sections attached to this Group
	 *
	 * @return Section[]
	 */
	public function setSections (): array
	{
		return $this->sections;
	}

	/**
	 * Get the Settings attached to this Group
	 *
	 * @return Setting[]
	 */
	public function getSettings (): array
	{
		return $this->settings;
	}

	/**
	 * Get the HTML Form output for this Group
	 *
	 * @param array{
	 *     attrs?: array
	 *  }          $args                         {
	 *                                           Optional. Extra arguments that get passed to the action hoos.
	 *                                           Additional custom items can be added, they will get passed on to the callback function.
	 * @type array $attrs                        Additional HTML attributes for the form element
	 *                                           }
	 * @return string HTML form
	 */
	public function getForm ( array $args = [] ): string
	{
		$needs_form_data = FALSE;
		foreach ( $this->settings as $setting ) {
			if ( !empty( $setting->getFields( fn( $field ) => $field->get_field_type() === 'file' ) ) ) {
				$needs_form_data = TRUE;
				break;
			}
		}

		ob_start();

		echo static::create_tag( 'form', wp_parse_args( [
			'action'        => admin_url( 'options.php' ),
			'data-group-id' => $this->id,
			'method'        => 'post',
			'enctype'       => $needs_form_data ? 'multipart/form-data' : FALSE,
		], $args['attrs'] ?? $args['attr'] ?? [] ), ( function ( array $args = [] ) {
			ob_start();
			\do_action( 'render/before_settings_group' . $this->id, $args );
			\do_action( "render/before_settings_group/{$this->id}", $args );
			settings_fields( $this->id );
			foreach ( $this->pages as $page ) {
				\do_action( "render/before_settings_page/{$this->id}", $page->getId(), $args );
				\do_action( "render/before_settings_page/{$this->id}/{$page->getId()}", $args );
				\do_settings_sections( $page->getId() );
				\do_action( "render/after_settings_page/{$this->id}/{$page->getId()}", $args );
				\do_action( "render/after_settings_page/{$this->id}", $page->getId(), $args );
			}
			\do_action( "render/after_settings_group/{$this->id}", $args );
			\do_action( 'render/after_settings_group', $this->id, $args );
			submit_button();
			?>
			<script>
				jQuery(function($) {
					const $form = $('form[data-group-id="<?= $this->id ?>"]')
					$form.find('input[data-togglebox][type="checkbox"]').on('change', function() {
						var $this = $(this)
						if ($this.is(':checked')) {
							$form.find(`input[data-togglebox][type="checkbox"][name="${$this.attr('name')}"]`).not($this).prop('checked', false)
						}
					})
				})
			</script>
			<?php
			return ob_get_clean();
		} )( $args ) );

		return ob_get_clean();
	}
}
