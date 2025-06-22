# API

## Endpoint

This automatically finds and caches the latest REST API endpoint URL.

### Usage

```php
$api_url = \RWP\Api\Endpoint::get_cached_api_url();
// Example response: https://www.example.com/wp-json/wp/v2/
```

## Taxonomy

This code makes sure that when you fetch categories or tags from WordPress's API, they come back in the same custom order that administrators set up in the WordPress dashboard, rather than just the default alphabetical order.

To enable this feature, run...

```php
\RWP\Api\Taxonomy::get_instance();
```

> NOTE: this only runs if the `Intuitive Custom Post Order` plugin (https://wordpress.org/plugins/intuitive-custom-post-order/) is active.