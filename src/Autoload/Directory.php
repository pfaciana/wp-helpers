<?php

namespace RWP\Autoload;

/**
 * Directory autoloader and file processor class
 */
class Directory
{
	/**
	 * Process files in a directory recursively with callback
	 *
	 * @param string   $relativeDir Relative directory path
	 * @param string   $baseDir     Base directory path
	 * @param callable $callback    Callback function to process each file
	 * @param string   $ext         File extension to filter by
	 */
	protected static function processFiles ( $relativeDir, $baseDir, callable $callback, $ext = 'php' )
	{
		$dir = rtrim( $baseDir, '/' ) . '/' . trim( $relativeDir, '/' );

		if ( strpbrk( $dir, '*?' ) !== FALSE ) {
			static::processGlobPatterns( $dir, $callback, $ext );
		}
		else {
			static::processDirectoryFiles( $dir, $relativeDir, $baseDir, $callback, $ext );
		}
	}

	/**
	 * Process files using glob patterns
	 *
	 * @param string   $dir      Directory path with glob patterns
	 * @param callable $callback Callback function to process each file
	 * @param string   $ext      File extension to filter by
	 */
	protected static function processGlobPatterns ( $dir, callable $callback, $ext )
	{
		$patterns = static::expandConsecutiveAsterisks( $dir );
		$files    = [];

		foreach ( $patterns as $pattern ) {
			$patternFiles = glob( $pattern );
			if ( $patternFiles ) {
				$files = array_merge( $files, $patternFiles );
			}
		}

		// Remove duplicates and process files
		$files = array_unique( $files );

		foreach ( $files as $file ) {
			if ( is_file( $file ) && pathinfo( $file, PATHINFO_EXTENSION ) === $ext ) {
				$callback( $file );
			}
		}
	}

	/**
	 * Process directory files recursively
	 *
	 * @param string   $dir         Full directory path
	 * @param string   $relativeDir Relative directory path
	 * @param string   $baseDir     Base directory path
	 * @param callable $callback    Callback function to process each file
	 * @param string   $ext         File extension to filter by
	 */
	protected static function processDirectoryFiles ( $dir, $relativeDir, $baseDir, callable $callback, $ext )
	{
		if ( !is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), [ '.', '..' ] );

		foreach ( $files as $file ) {
			if ( is_dir( "$dir/$file" ) ) {
				static::processFiles( "$relativeDir/$file", $baseDir, $callback, $ext );
			}
			elseif ( pathinfo( "$dir/$file", PATHINFO_EXTENSION ) === $ext ) {
				$callback( "$dir/$file" );
			}
		}
	}

	/**
	 * Set autoloader configuration
	 *
	 * @param array $config Configuration array with base directories and namespaces
	 */
	public static function set ( $config = [] )
	{
		foreach ( $config as $baseDir => $namespaces ) {
			foreach ( $namespaces as $namespace => $relativeDirs ) {
				foreach ( (array) $relativeDirs as $relativeDir ) {
					if ( empty( $namespace ) || is_int( $namespace ) ) {
						static::require( $relativeDir, $baseDir );
					}
					else {
						static::load( $relativeDir, $baseDir, $namespace );
					}
				}
			}
		}
	}

	/**
	 * Require files from a directory
	 *
	 * @param string $relativeDir Relative directory path
	 * @param string $baseDir     Base directory path
	 * @param string $ext         File extension to filter by
	 */
	public static function require ( $relativeDir, $baseDir, $ext = 'php' )
	{
		static::processFiles( $relativeDir, realpath( $baseDir ), function ( $file ) {
			require $file;
		}, $ext );
	}

	/**
	 * Load and auto-instantiate classes from a directory
	 *
	 * @param string $relativeDir Relative directory path
	 * @param string $baseDir     Base directory path
	 * @param string $namespace   Namespace prefix
	 * @param string $ext         File extension to filter by
	 */
	public static function load ( $relativeDir, $baseDir, $namespace, $ext = 'php' )
	{
		static::processFiles( $relativeDir, $baseDir = realpath( $baseDir ), function ( $file ) use ( $baseDir, $namespace ) {
			$relativePath = str_replace( $baseDir, '', $file );
			$classPath    = str_replace( '/', '\\', $relativePath );
			$className    = $namespace . '\\' . trim( $classPath, '\\' );

			// Remove the file extension from the class name
			$className = '\\' . substr( $className, 0, strrpos( $className, '.' ) );

			if ( class_exists( $className ) && method_exists( $className, 'get_instance' ) ) {
				call_user_func( [ $className, 'get_instance' ] );
			}
		}, $ext );
	}

	/**
	 * Expand consecutive asterisks into multiple glob patterns
	 *
	 * @param string $dir Directory path with potential consecutive asterisks
	 * @return array Array of expanded glob patterns
	 */
	public static function expandConsecutiveAsterisks ( $dir )
	{
		// Find all consecutive asterisk groups
		preg_match_all( '/\*{2,}/', $dir, $matches, PREG_OFFSET_CAPTURE );

		if ( empty( $matches[0] ) ) {
			// No consecutive asterisks found, return original
			return [ $dir ];
		}

		// Extract groups with their counts
		$groups = [];
		foreach ( $matches[0] as $match ) {
			$groups[] = strlen( $match[0] ); // Just store the count
		}

		// Calculate total number of pattern combinations
		$combinations = 1;
		foreach ( $groups as $count ) {
			$combinations *= $count;
		}

		$patterns = [];

		// Generate all possible pattern combinations
		for ( $i = 0; $i < $combinations; $i++ ) {
			$pattern = $dir;
			$temp    = $i;

			// For each group, determine what depth to use
			foreach ( $groups as $groupCount ) {
				$depth = ( $temp % $groupCount ) + 1;
				$temp  = intval( $temp / $groupCount );

				// Create the replacement pattern
				$replacement = str_repeat( '*/', $depth - 1 ) . '*';

				// Find and replace the first occurrence of consecutive asterisks
				$pattern = preg_replace( '/\*{2,}/', $replacement, $pattern, 1 );
			}

			$patterns[] = $pattern;
		}

		// Remove duplicates and return
		return array_unique( $patterns );
	}
}
