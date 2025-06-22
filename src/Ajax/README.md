# Ajax

## AutoloadTrait

The AutoloadTrait is a helper tool that makes handling AJAX in WordPress much easier. Instead of manually setting up each AJAX endpoint, it automatically converts public methods (in a class that uses this trait) into WordPress AJAX endpoints. You can control whether these endpoints are accessible to logged-in users and/or non-logged-in users. It's a time-saver that handles all the technical setup automatically so you can focus on writing your actual functionality!

### Basic Example

Server-side code:

```php
class MyCustomClass {
	use \RWP\Ajax\AutoloadTrait;

	// Creat a public method where its name is the ajax action hook_name
	public function hello_world() {
		wp_send_json('Hello from AJAX!');
	}
}

// You must init your class somewhere in your code
MyCustomClass::get_instance();
```

Client-side code:

```javascript
jQuery.ajax({
	url: ajaxurl,
	type: 'POST',
	data: {
		action: 'hello_world',
	},
	success: function(response) {
		console.log(response) // "Hello from AJAX!"
	},
})
```

### Explanation

By default, all endpoints are "privileged" only (only for logged-in users).

```php
protected $noPriv = FALSE; // Can non-logged-in users access these AJAX endpoints?
protected $priv = TRUE;    // Can logged-in users access these AJAX endpoints?
```

You can override these properties in your custom class that uses the `\RWP\Ajax\AutoloadTrait` trait

All `public` methods that do not start with `__` (magic methods like `__construct` or `__invoke`, etc) are automatically registered as ajax endpoints.

The name of the method (for example `hello_world`) must match the ajax `action` param. As seen in the example above.

Your custom class must be a Singleton to prevent the same hook action being registered more than once. This is handled automatically by the `\RWP\Ajax\AutoloadTrait` trait. You just need to init your custom class like `MyCustomClass::get_instance()` somewhere in your code.

## RWP() Helper

If you don't want to create a singleton class, you can use the `RWP()` helper method, `ajax`, to register `priv` and `nopriv` in one go.

```php
RWP()->ajax( 'hello_world', function () {
	wp_send_json('Hello from AJAX!');
} )
```