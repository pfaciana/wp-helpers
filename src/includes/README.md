# Include Helpers

## Urls

This file provides custom functions for managing WordPress admin menu items with URL-based navigation, offering an alternative to WordPress's built-in menu functions. This is for linking directly to any url (internal or external) without create a new page. They are similar to `add_menu_page()` and `add_submenu_page()` but instead of creating admin page with a menu link, this only creates the menu link.

### add_menu_link()

Adds a new top-level menu item to the WordPress admin dashboard.

```php
add_menu_link(
    string $menu_title,						// The text shown in the menu
    string $url,							// The URL the menu item links to
    string $capability = 'manage_options',	// Required user capability
    string|array $icon_url = '',			// Menu icon URL or dashicon
    int|float|null $position = null			// Menu position (optional)
)
```

#### Example Usage

```php
// Add a basic menu item
add_menu_link(
    'Menu Title',
    'https://www.example.com/', // External link
    'manage_options'
);

// Add a menu item with custom icon and position
add_menu_link(
    'My Menu',
    '/custom-page', // Internal link
    'manage_options',
    'dashicons-admin-customizer',
    50
);
```

### add_submenu_link()

Adds a submenu item under an existing top-level menu.

```php
add_submenu_link(
    string $parent_slug,					// The slug of parent menu
    string $menu_title,						// The text shown in the submenu
    string $url,							// The URL the submenu item links to
    string $capability = 'manage_options',	// Required user capability
    int? $position = null					// Submenu position (optional)
)
```

#### Example Usage

```php
// Add a basic submenu item
add_submenu_link('parent-slug', 'Submenu Item', '/custom-subpage');

// Add a submenu item with specific position
add_submenu_link('parent-slug', 'Submenu Item', '/custom-subpage', 'manage_options', 2);
```
