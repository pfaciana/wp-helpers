# Render WP Helpers

## WordPress Settings Page Library

### 1. Introduction

This library provides an easy-to-use, object-oriented approach to creating and managing WordPress settings pages. It simplifies the process of adding settings, creating custom fields, and handling user input in WordPress plugins or themes.

### 2. Basic Concepts

Before diving in, let's understand some key concepts of WordPress Settings:

- **Group**: A collection of related settings.
  - In the UI, a `Group` is only used to add the hidden fields into the form (to group settings)
- **Page**: A settings page in the WordPress admin area.
  - A `Page` is only used to group sections
- **Section**: A group of related fields within a settings page.
  - The same `Section` can be re-used and added to multiple/different pages
- **Setting**: An individual option that can be set by the user.
  - A `Setting` must be unique and can only be added to one group, but can have just one or multiple fields
- **Field**: The UI element (like a text input or checkbox) that allows users to interact with a setting.
  - A `Field` is a child of a `Page`/`Section` combo, the same field can be added to multiple/different combos of pages and sections

#### Visual representation of WordPress globals

The two PHP globals `$new_allowed_options` and `$wp_settings_fields`

```
$new_allowed_options
|_______group
	|_______setting[]

$wp_settings_fields
|_______page
	|_______section
		|_______field
```

> NOTE: There is no direct connection between a `Setting` and a `Field` in the WordPress code.

The responsibility of making the connection between a `Setting` and a `Field` is on the developer when a field has been `POST` back from a form via the `Group` hidden fields. The way this library simplifies the process is by forcing one `Section` per `Page`, and one `Setting` per `Section`. This small tweak has little to no effect in the user interface (UI) the end user interacts with, but allows us to greatly simplify the developer experience (DX). Now we have a representation that looks like this...

```
RWP()
|_______group
	|_______setting = page = section
	|			 |_______field
	|			 |_______field
	|_______setting = page = section
				 |_______field
				 |_______field
```

...and now we can do...

```php
# Create Form
$group = RWP()->getSettingGroup( 'some_group' );
$setting = $group->addSetting( 'some_setting', [ 'title' => 'Options' ] );
$setting->number( 'some_field', [ 'title' => 'Size', 'default' => 3, 'attrs' => ['class' => 'small-text', 'min' => 1 ] ] );
/* ... Keep adding additional Settings and Fields ... */

# Display Form
echo RWP()->getSettingsForm( 'some_group' );

# Get Setting
$option = get_option( 'some_setting' );
echo $option['some_field'] ?? 'some default value...';
# or shorthand way
echo RWP()->getOption( 'some_setting', 'some_field', 'some default value...' );
```

#### DOM representation of Setting and Field options

This highlights how some of the `Setting` and `Field` $args are related to the UI output.

This is inline with how WordPress builds its HTML in the Settings API.

```php
<?= $setting['before_section'] ?>

<h2><?= $setting['title'] ?></h2>

<?= $setting['content'] ?>

<table class="form-table" role="presentation">
	<?php foreach ( $setting['fields'] as $field ) : ?>
		<tr class="<?= $field['class'] ?>">
			<th scope="row">
				<label for="<?= $field['label_for'] ?>"><?= $field['title'] ?></label>
			</th>
			<td>
				<?= $field['before_field'] ?>

				<input <?= array_merge( [ 'value' => $field['default'], 'list' => $field['list'] ], $field['attrs'] ) ?> />
				<?= $field['inline'] ?> <span class="description"><?= $field['desc_inline'] ?></span>

				<p class="description"><?= $field['description'] ?></p>

				<datalist id="<?= $field['setting_id'] ?>_<?= $field['id'] ?>-options">
					<?php foreach ( $field['datalist'] as $choice ) : ?>
						<option value="<?= $choice ?>"></option>
					<?php endforeach; ?>
				</datalist>

				<?= $field['after_field'] ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>

<?= $setting['after_section'] ?>
```

### 3. Getting Started

To use this library, you need to have it included in your WordPress plugin or theme. Ensure that the library files are properly loaded.

The main entry point for using the library is typically through a function like `RWP()`. This function returns an instance of the main library class, which provides access to various methods for creating and managing settings.

### 4. Creating a Settings Page

To create a settings page, you'll typically use the `add_menu_page()` WordPress function within an `admin_menu` action hook. Here's an example:

```php
add_action('admin_menu', function() {
	add_menu_page(
	    'Plugin Settings',
	    'Plugin Settings',
	    'manage_options',
	    'your-plugin-settings',
	    function() {
	        echo '<h1>Settings</h1>';
	        echo RWP()->getSettingsForm('your_settings_group');
	    },
	    'dashicons-admin-generic'
	);
});
```

This creates a new menu item in the WordPress admin sidebar and displays your settings form when clicked.

### 5. Adding Settings to a Group

Settings are added within the `admin_init` hook. Here's a basic example:

```php
add_action('admin_init', function() {
	$rwp = RWP();

	$group = $rwp->getSettingGroup('your_settings_group');

	$setting = $group->addSetting('your_setting_id', [
		'title'   => 'Your Section Title'
		'content' => 'Configure the settings for this plugin.',
	]);

	$setting->text('your_field_id', [
		'title'   => 'Your Field Title',
		'default' => 'default_value',
	]);
});
```

This creates a new setting with a text field.

> `$rwp->getSettingGroup()` will create a new group if that group doesn't exist <br>
> This is the preferred way to create a new group

### 6. Field Types

The library supports a wide range of field types. Here's a list with examples and unique options for each type:

#### Text

```php
$setting->text( 'text_field', [
	'title' => 'Text Field',
	'default' => 'Default text',
	'attrs' => [ 'placeholder' => 'Enter text here' ],
]);
```

#### Radio

```php
$setting->radio( 'radio_field', [
	'options' => [
		'F j, Y'       => ' September 25, 2024 &nbsp; <code>F j, Y</code>',
		'Y-m-d'        => ' 2024-09-25 &nbsp; <code>Y-m-d</code>',
		'm/d/Y'        => ' 09/25/2024 &nbsp; <code>m/d/Y</code>',
		'd/m/Y'        => ' 25/09/2024 &nbsp; <code>d/m/Y</code>',
	],
	'default' => 'F j, Y',
]);
```

#### Checkbox

```php
$setting->checkbox( 'checkbox_field', [
	'title' => 'Checkbox Field',
	'options' => [
	    'F j, Y'       => ' September 25, 2024 &nbsp; <code>F j, Y</code>',
	    'Y-m-d'        => ' 2024-09-25 &nbsp; <code>Y-m-d</code>',
	    'm/d/Y'        => ' 09/25/2024 &nbsp; <code>m/d/Y</code>',
	    'd/m/Y'        => ' 25/09/2024 &nbsp; <code>d/m/Y</code>',
	    '\c\u\s\t\o\m' => [
	        'Custom: &nbsp; ' => [
	            $setting->text( 'checkbox_field_custom', [
	                'attrs'   => [ 'class' => 'small-text', ],
	                'default' => 'F j, Y',
	            ], FALSE )->html(), # This discussed later
	        ],
	    ],
	],
	'default' => [ 'Y-m-d', 'm/d/Y' ],
]);
```

#### Toggle

```php
# Toggle as a single on/off switch
$setting->toggle( 'toggle_field', [
	'default'           => 'on',
	'options'           => [ 'on' => ' On/Off', ],
] );

# Toggle as a radio/checkbox hybrid
$setting->toggle( 'toggle_checkbox_field', [
	'options' => [
		'F j, Y'       => ' September 25, 2024 &nbsp; <code>F j, Y</code>',
		'Y-m-d'        => ' 2024-09-25 &nbsp; <code>Y-m-d</code>',
		'm/d/Y'        => ' 09/25/2024 &nbsp; <code>m/d/Y</code>',
		'd/m/Y'        => ' 25/09/2024 &nbsp; <code>d/m/Y</code>',
		'\c\u\s\t\o\m' => [
			'Custom: &nbsp; ' => $setting->text( 'toggle_checkbox_field_custom', [
				'attrs' => [ 'class' => 'small-text', ],
			], FALSE )->html(),
		],
	],
	'default' => 'F j, Y',
]);
```

> `toggle` can be used visually like `checkboxes`, but they act like `radio` buttons. <br>
> `toggle` can be unchecked, whereas `radio` buttons cannot. <br>
> `toggle` can have zero or one value, while `checkbox` can have many values (as an array).

#### Select

```php
# Simple Options
$setting->select( 'select_field', [
	'options' => [
		'subscriber'    => 'Subscriber',
		'contributor'   => 'Contributor',
		'author'        => 'Author',
		'editor'        => 'Editor',
		'administrator' => 'Administrator',
	],
	'default' => 'editor',
] );

# With OptGroup
$setting->select( 'select_optgroup_field', [
	'options'           => [
		'Installed' => [
			'site-default' => 'Site Default',
			'en_US'        => 'English (United States)',
		],
		'Available' => [
			'en_AU' => 'English (Australia)',
			'en_CA' => 'English (Canada)',
			'en_NZ' => 'English (New Zealand)',
			'en_ZA' => 'English (South Africa)',
			'en_GB' => 'English (UK)',
		],
	],
] );
```

#### Textarea

```php
$setting->textarea( 'textarea_field', [
	'description' => 'Share a little biographical information to fill out your profile. This may be shown publicly.',
	'attrs' => [ 'class' => [ 'regular-text', 'code' ], 'spellcheck' => 'false', 'rows' => 5, 'cols' => 50 ],
]);
```

#### Color

```php
$setting->color('color_field', [
	'datalist' => [ '#FF0000', '#FFFFFF', '#0000FF', ], # This discussed later
]);
```

#### File

```php
$setting->file('file_field', [
	'attrs' => [ 'multiple' => TRUE, 'accept' => '.pdf,.doc,.docx' ]
]);
```

#### Hidden

```php
$setting->hidden('hidden_field', [
	'value' => 'hidden_value'
]);
```

#### Number

```php
$setting->number('number_field', [
	'default' => 1,
	'attrs' => ['min' => 0, 'max' => 100, 'step' => 1],
]);
```

#### Password

```php
$setting->password('password_field', [
	'title' => 'Password Field',
]);
```

#### Range

```php
$setting->range('range_field', [
	'default' => 50,
	'attrs' => ['min' => 0, 'max' => 100, 'step' => 1]
]);
```

#### Email

```php
$setting->email('email_field', [
	[ 'attrs' => [ 'placeholder' => 'example@example.com', 'minlength' => 3, 'maxlength' => 64 ] ]
]);
```

#### URL

```php
$setting->url('url_field', [
	'attrs' => [ 'required' => TRUE, 'pattern' => "https://.*" ],
]);
```

#### Date

```php
$setting->date('date_field', [
	'attrs' => [ 'min' => '2017-04-01', 'max' => '2017-04-30' ],
	'default' => date('Y-m-d'),
]);
```

#### Time

```php
$setting->time('time_field', [
	'datalist' => [ '14:00', '15:00', '16:00', '17:00', ],
	'attrs' => [ 'min' => '12:00', 'max' => '17:59' ],
	'default' => date('H:i'),
]);
  ```

#### Datetime

```php
$setting->datetime('datetime_field', [
	'attrs' => [ 'min' => '2024-06-01T08:30', 'max' => '2024-06-30T16:30' ],
	'default' => date('Y-m-d\TH:i'),
]);
```

#### Month

```php
$setting->month('month_field', [
	'attrs' => [ 'min' => '1900-01', 'max' => '2016-12' ],
	'default' => date('Y-m'),
]);
```

#### Week

```php
$setting->week('week_field', [
	'attrs' => [ 'min' => '1900-W01', 'max' => '2017-W52' ],
	'default' => date('Y-\WW'),
]);
```

#### Tel

```php
$setting->tel('tel_field', [
	'attrs' => [ 'pattern' => '[0-9]{3}-[0-9]{3}-[0-9]{4}' ],
	'default' => '123-456-7890',
]);
```

#### Search

```php
$setting->search('search_field', [
	'attrs' => [ 'placeholder' => 'Search...', 'aria-label' => 'Search through site content' ],
]);
```

#### Additional Attributes

> NOTE: If you provide a `datalist` array in the `$args` array, <br>
> it will automatically create a `<datalist>` tag for you and connect the field to it by ID with the `list` attribute. <br>
> If the `<datalist>` tag already exists in the DOM, you can use the `list` $arg to connect to it.

> NOTE: You can pass `datalist` an associative array, where... <br>
> the `key` will be the option value <br>
> the `value` will be the option label.

> NOTE: You can pass `attrs['pattern']` an array, and it will convert that into a `pattern` string that
> forces one of those values to be required.

```php
// if you pass...
$setting->text('text_field', [ 'attrs' => [ 'pattern' => ['a', 'b', 'c'] ] ]);
```

```html
<!-- it will be converted to... -->
<input type="text" name="text_field" pattern="a|b|c" />
```

> NOTE: It will escape regex characters automatically using WordPress' `esc_attr` function

```php
// if you pass...
$setting->text('text_field', [ 'attrs' => [ 'pattern' => [ 'a', 'Option | ( ) + [ ]', 'z', ] ] ]);
```

```html
<!-- it will be converted to... -->
<input type="text" name="text_field" pattern="a|Option \| \( \) \+ \[ \]|z" />
```

If you don't want to repeat yourself, you can use a temporary variables like this:

```php
$setting->text( 'pie-choice', [
	'datalist' => ( $flavors = [ 'Apple', 'Banana', 'Cherry', ] ),
	'attrs'    => [ 'pattern' => $flavors, ],
] );
// or with labels
$setting->text( 'pie-choice', [
	'datalist' => ( $flavors = [ 'a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', ] ),
	'attrs'    => [ 'pattern' => array_keys( $flavors ), ],
] );
```

### 7. Single Field Setting

By default, a `Setting` is set up to have multiple fields, and thus, its value is an array. Each `Field` becomes a key in the values array.

However, if you don't want a `Setting` to be an array (and not have multiple fields), you can use the `single` option.

This can be enabled by skipping the field `$id` argument on the fields, for example:

```php
// Multiple fields
$setting->text( 'text_field', [ 'attrs' => [ 'placeholder' => 'Enter text here' ], 'default' => 'Default text' ]);
// Single field
$setting->text( [ 'attrs' => [ 'placeholder' => 'Enter text here' ] ]);
```

The field `$id` is omitted and the `$args` array is now the first argument.

> WARNING: A Single Field Setting should not have a `default` $arg <br>
> Instead, use the `default` value on its parent `Setting`. <br>
> (This is due to how WordPress optimizes saving $options into the database.)

```php
// Default value on the Setting, not the Field. Correct!
$group->addSetting( 'single_field_setting', [ 'default' => 'Default text on the Setting' ] )->text();
```

A `Setting` can always have a `default` $arg, but often times it's put on the `Field` instead for readability.
However, in the event of a Single Field Setting, the `default` $arg should not be used on the `Field` itself.

### 8. Direct Field output

If you don't want WordPress to automatically output the html of a field to the respective Settings API section, then you can pass `FALSE` as the last argument. It builds the field as normal, but does not tell WordPress to display it. This allows you to output the field yourself in a place of your choosing. A common use case is when you want to nest a second field inside another field like how WordPress does its `Date Format` and `Time Format` settings. Be sure to call the `html()` method on the field to get the html, so you can echo it to the screen.

```php
echo $setting->text( 'radio_field_custom', [ 'attrs' => [ 'class' => 'small-text' ] ], FALSE )->html()
```

### 9. Field Error Handling and Sanitization and Formatting

You can add custom sanitization and error handling to your fields:

```php
// Store the field in a variable to use getErrorId() helper method
$emailField = $setting->text('email_field', [
	// ... other args ...
	'sanitize_callback' => function ( $value, $orig_value, $field_name, $field_id, $setting_id, $form_data ) use ($emailField) {
	    if (!is_email($value)) {
	        // Option 1: Use the field_name parameter directly
	        add_settings_error( $field_name, 'invalid_email', 'Please enter a valid email address.', 'error' );

	        // Option 2: Use the getErrorId() helper method (recommended)
	        // add_settings_error( $emailField->getErrorId(), 'invalid_email', 'Please enter a valid email address.', 'error' );

	        return $orig_value;
	    }
	    return $value;
	},
	'error_location' => 'admin_notices', // OR \RWP\Settings\Type\ErrorLocation::ADMIN_NOTICES
]);

// For settings, you can also use the getErrorId() helper method
$setting = $group->addSetting('my_setting', [
	'sanitize_callback' => function ( $value, $orig_value, $setting_id ) use ($setting) {
	    if ($value < 10) {
	        // Option 1: Use the setting_id parameter directly
	        add_settings_error( $setting_id, 'too_low', 'Value is too low.', 'error' );

	        // Option 2: Use the getErrorId() helper method (recommended)
	        // add_settings_error( $setting->getErrorId(), 'too_low', 'Value is too low.', 'error' );

	        return $orig_value;
	    }
	    return $value;
	},
]);
```

Error Location is where the `settings_error` will get displayed.

It should be the string value representation of the ErrorLocation enum (`admin_notices`, `form`, `section`, or `field`)

You can use the enum itself (`'error_location' => \RWP\Settings\Type\ErrorLocation::ADMIN_NOTICES`) and it will get converted to the string for you.

### 10. Getting the `Setting` and `Field` values

```php
$options = get_option( 'elv_settings' );

$a_setting = RWP()->getRawOption( 'elv_settings' );
$a_field   = RWP()->getRawOption( 'elv_settings', 'exclude_directories' );

$a_setting_formatted = RWP()->getOption( 'elv_settings' );
$a_field_formatted   = RWP()->getOption( 'elv_settings', 'exclude_directories' );

$setting = RWP()->getSetting( 'elv_settings' );

$b_setting           = $setting->getRawValue();
$b_setting_formatted = $setting->getValue();

$b_field           = $setting->getRawValue( 'exclude_directories' );
$b_field_formatted = $setting->getValue( 'exclude_directories' );
```

## Reference for available options

### `Field`

Options that get passed onto the `do_settings_fields()` function

| Key       | Type               | Description                  | Default                |
|-----------|--------------------|------------------------------|------------------------|
| `class`   | `string`           | CSS Class for the `<tr>` tag |                        |
| `title`   | `string`           | Content for the `<th>` tag   | Auto-generated from ID |
| `content` | `string\|callable` | Content for the `<td>` tag   | Auto-generated         |

Options that add additional content around the `do_settings_fields()` function output

| Key            | Type     | Description                         |
|----------------|----------|-------------------------------------|
| `before_field` | `string` | Content displayed before field      |
| `inline`       | `string` | Raw text directly after the field   |
| `desc_inline`  | `string` | `span.description` after `inline`   |
| `description`  | `string` | `p.description` after `desc_inline` |
| `after_field`  | `string` | Content displayed after field       |

Options applied directly to the `Field`

| Key                 | Type                    | Description                                                 | Default |
|---------------------|-------------------------|-------------------------------------------------------------|---------|
| `default`           | `mixed`                 | Default value for the `Field`                               | `null`  |
| `attrs`             | `array`                 | Additional HTML attributes for input                        | `[]`    |
| `options`           | `array`                 | Options for select, radio, or checkbox inputs               |         |
| `datalist`          | `array`                 | Options for datalist                                        |         |
| `sanitize_callback` | `callable`              | Callback to sanitize and validate the `Field` before saving |         |
| `filter_callback`   | `callable`              | Callback to format the value from the database              |         |
| `error_location`    | `string\|ErrorLocation` | Where to display error messages                             | `field` |
| `single`            | `bool`                  | Whether field is solo or part of many                       | `false` |
| `show`              | `bool`                  | Whether to auto-print the field                             | `true`  |

Signatures

* `sanitize_callback( $new_value, $old_value, string $field_id, string $setting_id, array $form_data )`
* `filter_callback( $value, string $field_id, string $setting_id, array $form_data )`

### `Setting`

| Key                 | Type                    | Description                                                   | Default   |
|---------------------|-------------------------|---------------------------------------------------------------|-----------|
| `title`             | `string\|callable`      | Title of the Settings Section                                 |           |
| `content`           | `string\|callable`      | Content directly below the `title`                            |           |
| `sanitize_callback` | `callable`              | Callback to sanitize and validate the `Setting` before saving |           |
| `error_location`    | `string\|ErrorLocation` | Where to display error messages                               | `section` |
| `default`           | `mixed`                 | Default value for the `Setting`                               | `null`    |
