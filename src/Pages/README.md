# Pages

## AbstractAdmin

This is an abstract class for creating admin pages. It handles menu registration, URL management, and page rendering. It is extension of the `add_menu_page()` and `add_submenu_page()`, but let's you build faster by removing some common boilerplate when building more complicated pages.

### Usage

```php
class MySettingsPage extends \RWP\Pages\AbstractAdmin
{
	protected $menu_title = 'My Settings';
	protected $page_title = 'My Plugin Settings';
	protected $menu_slug = 'my-settings';
	protected $parent_slug = 'options-general.php'; // Add under Settings menu

	public function init ()
	{
		// Optional setup code, run in the constructor
	}

	public function output ()
	{
		if ( isset( $_POST['setting_one'] ) ) {
			if ( isset( $_POST['my_settings_nonce'] ) && wp_verify_nonce( $_POST['my_settings_nonce'], 'my_settings_action' ) ) {
				update_option( 'my_setting_one', sanitize_text_field( $_POST['setting_one'] ) );
				return print '<div class="updated"><p>Settings saved! - <a href="' . $this->get_admin_url() . '">Go Back</a></p></div>';
			}

			add_settings_error( 'my_settings', 'update_failed', 'Security check failed!' );
		}

		$this->displayForm();
	}

	public function displayForm ()
	{
		settings_errors( 'my_settings' );
		?>
		<div class="wrap">
			<h1>My Plugin Settings</h1>
			<form method="post">
				<?php wp_nonce_field( 'my_settings_action', 'my_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="setting_one">Setting One:</label></th>
						<td><input type="text" name="setting_one" value="<?= esc_attr( get_option( 'my_setting_one' ) ) ?>"></td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}

// Initialize the page
MySettingsPage::get_instance();
```

### Properties

Only one property is absolutely required. You must have at least either `page_title` or `menu_title`. If only one of the two is defined, the undefined one will be derived from the defined one.

If this is a submenu page, then `parent_slug` is also required.

#### User Defined

| Name          | Description                        | Default Value                         |
|---------------|------------------------------------|---------------------------------------|
| `menu_title`  | Menu/navigation text               | Copy of `page_title`                  |
| `page_title`  | Title tag of the page              | Copy of `menu_title`                  |
| `menu_slug`   | Slug name to refer to this menu by | `sanitize_title( $this->menu_title )` |
| `parent_slug` | Parent's `menu_slug`               | (Required for submenu)                |
| `capability`  | Capability required for the page   | `manage_options`                      |
| `icon`        | Dashicon or URL to the menu icon   | `dashicons-admin-generic`             |
| `position`    | Position in the menu order         | `NULL`                                |
| `priority`    | `admin_menu` hook priority         | menu `10`, submenu `100`              |

#### Generated

| Name                | Description                                                         |
|---------------------|---------------------------------------------------------------------|
| `admin_url`         | Default base url for this admin page                                |
| `current_admin_url` | `admin_url` plus any additional `$_GET` used in the current request |
| `current_url`       | Url of the current HTTP request                                     |
| `hook_suffix`       | Hook suffix returned from the `add_(sub)menu_page` function calls   |

NOTE: `admin_url`, `current_admin_url` and `current_url` are similar, and in some instances could all be the same value. However, there is some important differences. `admin` in the name means this is the default base url when building an admin page. This is important because due to rewrite rules, you may get to this admin page from different endpoints. This ensures you are using the recommended default admin url. `current` in the name means it includes all additional `$_GET` parameters used in the current request.

* Use `admin_url` when you don't want the extra `$_GET` parameters
	* For example, a `$_GET` param for the active tab
* Use `current_admin_url` when you want the full url
	* Even if extra `$_GET` parameters are present, but not needed for triggering the rewrite rules that go to this page
* Use `current_url` when you want full and exact url of the current request
	* Even if it doesn't match the base url for the rewrite rules that go to this page

### Methods

#### User Defined

| Name     | Description                                           |
|----------|-------------------------------------------------------|
| `init`   | Runs as soon as the class is instantiated (Optional)  |
| `output` | The `$callback` assigned to `add_(sub)menu_page` call |

The `init` method is shorthand for...

```php
protected function __construct ()  
{  
    // Your "init" code goes here...

    parent::__construct();  
}
```

#### Helpers

| Name                    | Args             | Description                                                                        |
|-------------------------|------------------|------------------------------------------------------------------------------------|
| `get_admin_url`         | `...$query_args` | Gets the `admin_url` property, where you can add additional `$_GET` params         | 
| `get_current_admin_url` | `...$query_args` | Gets the `current_admin_url` property, where you can add additional `$_GET` params |

`...$query_args` gets passed to the `add_query_arg()` function with the matching url passed as the url to act upon. This allows you to add or replace `$_GET` params to that url.


























