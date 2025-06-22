# Hooks

## LocalizeScripts

This is a WordPress utility class that makes it easy to pass data from WordPress to JavaScript. It acts as a bridge between your PHP backend and JavaScript frontend, allowing you to make server-side data available in your browser's JavaScript environment. When initialized with a key, it namespaces your variables under a custom window object (e.g., window.yourNamespace), or when used with an empty key, assigns directly to the global window object.

## Usage

```php
// Set up the namespace
add_action( 'renderwp_loaded', fn() => new \RWP\Hooks\LocalizeScripts( 'rwd' ) );

// Include the server-side data
add_filter( 'localize_js_rwd_header', function ( $obj, $admin ) {  
    global $pagenow;  
  
    $post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : get_queried_object_id();  
  
    $obj += [  
       'ajaxurl'       => admin_url( 'admin-ajax.php' ),  
       'apiurl'        => \RWP\Api\Endpoint::get_cached_api_url(),  
       'post_id'       => $post_id,  
       'pagenow'       => $pagenow ?? NULL,  
    ];  
  
    if ( $admin ) {  
       $obj += [  
          'postsAdminUrl'   => admin_url( 'post.php?&action=edit' ),  
          'formsAdminUrl'   => admin_url( 'admin.php?page=gf_edit_forms' ),  
          'entriesAdminUrl' => admin_url( 'admin.php?page=gf_entries&view=entry' ),  
       ];  
    }  
  
    return $obj;  
}, 10, 2 );
// This example outputs the `rwd` namespace data in the page header
```

### Aditional usage

This comes with multiple hooks that can be conditionally used by `namespace`, `location`, `isAdmin`

```php
$obj = apply_filters( "localize_js", [], $namespace, $location, $isAdmin );
$obj = apply_filters( "localize_js_{$namespace}", $obj, $location, $isAdmin );
$obj = apply_filters( "localize_js_{$namespace}_{$location}", $obj, $isAdmin );
```

`namespace` is the `$key` passed into the class constructor. Defaults to empty string, which is global `window` in JavaScript
`locations` is where the data is output in the DOM. Can be either `header` or `footer`.
`isAdmin` is if the data is for an admin page.

## How is this different from `wp_localize_script()`?

This is very similar to `wp_localize_script()` except that it is not based on an enqueued script as a dependency for output. The localizing of JavaScript objects can be done on other custom conditions you can define inside your callback which returns the data.

In other words, if your condition for localizing a script is directly dependent on a script that is enqueued, use `wp_localize_script()`. For all other conditions, use this `\RWP\Hooks\LocalizeScripts`.

* `wp_localize_script()` = localize an enqueued script only
* `\RWP\Hooks\LocalizeScripts()` = localize any script for any reason