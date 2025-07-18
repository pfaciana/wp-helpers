# Autoload

## Directory

This finds and loads PHP files in your code automatically. It can either `require` files or initialize singletons that have a `get_instance()` method. It supports both directory paths and glob patterns for flexible file matching.

### Usage

It accepts a `$config` array. That `$config` array is an associative array with the base directory to search as the key, with the value as another associative array of namespaces as the keys and the an array of relative directories as the values.

If the namespace key is either empty or an integer, then the array of values are directories or glob patterns where the files will be `require`d , otherwise, the files with classes in them will be initialized as singletons with the `get_instance()` method. If a file does not have a `public` `get_instance()` method, it will be skipped.

#### Example

```php
add_action( 'renderwp_loaded', function () {  
    $config = [  
       __DIR__ . '/src' => [  
          NULL    => [ 'includes', 'helpers' ],  
          'NameSpaceA' => [ 'Dir1', 'Dir2', 'Dir3' ],  
          'NameSpaceZ' => [ 'DirX', 'DirY', 'DirZ' ],  
       ],  
    ];  
    \RWP\Autoload\Directory::set( $config );  
}, -9e9 );
```

In this example, the key that's `NULL` will recursively finds all the php files in the `__DIR__ . '/src/includes'` and `__DIR__ . '/src/helpers'` directories and `require` them. For example, if `includes/SomeFile.php` and `helpers/SomeFile.php` exist, then the code runs this...

```php
require __DIR__ . '/src/includes/SomeFile.php';
// any other files ...
require __DIR__ . '/src/helpers/SomeFile.php';
```

For the other keys that are namespaces (non-NULL/non-integers), it runs something like this...

```php
\NameSpaceA\Dir1\SomeFile::get_instance()
// any other classes ...
\NameSpaceZ\DirZ\SomeFile::get_instance()
```

### Custom Glob Pattern Support

The Directory class includes custom glob pattern support for flexible file matching. This extends standard PHP glob functionality with special handling for consecutive asterisks:

- **Standard wildcards**: Use `*` and `?` (handled by PHP's built-in `glob()` function)
- **Custom consecutive asterisks**: Use `**`, `***`, etc. for multi-depth directory matching
  - `glob()` only supports one directory (no `**` syntax). So we've added custom support for multiple levels of directories.
  - `**` internally expands to multiple patterns: `*` and `*/*`
  - `***` expands to: `*`, `*/*`, and `*/*/*`
  - Each additional asterisk adds another depth level
  - This allows you to search multiple levels of nested dir while working with the `glob()` function

#### How Consecutive Asterisks Work

When you use consecutive asterisks, the code automatically expands them into multiple glob patterns:

```php
// Input special glob pattern
'api/**/handlers'
// Gets expanded internally to these glob patterns:
[
    'api/*/handlers',      // 1 level deep
    'api/*/*/handlers'     // 2 levels deep
]

// Input special glob pattern  
'modules/***/init'
// Gets expanded internally to these glob patterns:
[
    'modules/*/init',        // 1 level deep
    'modules/*/*/init',      // 2 levels deep  
    'modules/*/*/*/init'     // 3 levels deep
]

// Input special glob pattern
'/a/**/b/**/c'
// Gets expanded internally to these glob patterns:
[   
    '/a/*/b/*/c'                 // Both sets of ** are 1 level deep
    '/a/*/*/b/*/c'               // First set of ** is 1 level deep, second is 2 levels deep 
    '/a/*/b/*/*/c'               // First set of ** is 2 levels deep, second is 1 level deep
    '/a/*/*/b/*/*/c'             // Both sets of ** are 2 levels deep
]
```

### Why not just use composer's autoloader?

It's because you cannot include entire directories with composer or initialize singletons. You can autoload specific files, but not all the files in a directory. So every time you add or remove a file in a project that you need autoloaded, you have to remember to update composer. Also, composer hashes based on the file's relative pathname. Which means if you are in WordPress instance and you have two plugins that include the same packagist repo which includes a specific file, then composer only loads one of those files. Sometimes this is intended, but other times it is not. This make loading the file(s) intentional and explicit.
