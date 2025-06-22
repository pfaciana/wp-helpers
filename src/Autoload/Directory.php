<?php

namespace RWP\Autoload;

class Directory
{
	protected static function processFiles ( $relativeDir, $baseDir, callable $callback, $ext = 'php' )
	{
		if ( !is_dir( $dir = rtrim( $baseDir, '/' ) . '/' . trim( $relativeDir, '/' ) ) ) {
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

	public static function require ( $relativeDir, $baseDir, $ext = 'php' )
	{
		static::processFiles( $relativeDir, realpath( $baseDir ), function ( $file ) {
			require $file;
		}, $ext );
	}

	public static function load ( $relativeDir, $baseDir, $namespace, $ext = 'php' )
	{
		static::processFiles( $relativeDir, $baseDir = realpath( $baseDir ), function ( $file ) use ( $baseDir, $namespace ) {
			$file      = str_replace( $baseDir, '', $file );
			$classPath = str_replace( '/', '\\', $file );
			$className = $namespace . '\\' . trim( $classPath, '\\' );

			// Remove the file extension from the class name
			$className = '\\' . substr( $className, 0, strrpos( $className, '.' ) );

			if ( class_exists( $className ) && method_exists( $className, 'get_instance' ) ) {
				call_user_func( [ $className, 'get_instance' ] );
			}
		}, $ext );
	}
}
