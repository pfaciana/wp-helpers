# Autoload

## Directory

This finds and loads PHP files in your code automatically. It can either `require` files or initialize singletons that have a `get_instance()` method.

### Usage

It accepts a `$config` array. That `$config` array is an associative array with the base directory to search as the key, with the value as another associative array of namespaces as the keys and the an array of relative directories as the values.

If the namespace key is either empty or an integer, then the array of values are directories where the files will be `require`d , otherwise, the files with classes in them will be initialized as singletons with the `get_instance()` method. If a file does not have a `public` `get_instance()` method, it will be skipped.

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

### Why not just use composer's autoloader?

It's because you cannot include entire directories with composer or initialize singletons. You can autoload specific files, but not all the files in a directory. So every time you add or remove a file in a project that you need autoloaded, you have to remember to update composer. Also, composer hashes based on the file's relative pathname. Which means if you are in WordPress instance and you have two plugins that include the same packagist repo which includes a specific file, then composer only loads one of those files. Sometimes this is intended, but other times it is not. This make loading the file(s) intentional and explicit.
