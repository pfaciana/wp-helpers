<?php

use RWP\Autoload\Directory;

describe( 'Directory::expandConsecutiveAsterisks', function () {
	it( 'returns original pattern when no consecutive asterisks are found', function () {
		$result = Directory::expandConsecutiveAsterisks( '/path/*' );
		expect( $result )->toBe( [ '/path/*' ] );

		$result = Directory::expandConsecutiveAsterisks( '/path/*/file.txt' );
		expect( $result )->toBe( [ '/path/*/file.txt' ] );

		$result = Directory::expandConsecutiveAsterisks( '/normal/path' );
		expect( $result )->toBe( [ '/normal/path' ] );

		$result = Directory::expandConsecutiveAsterisks( '*' );
		expect( $result )->toBe( [ '*' ] );
	} );

	it( 'expands double asterisks correctly', function () {
		$result = Directory::expandConsecutiveAsterisks( '**' );
		expect( $result )->toBe( [ '*', '*/*' ] );

		$result = Directory::expandConsecutiveAsterisks( '**/' );
		expect( $result )->toBe( [ '*/', '*/*/' ] );

		$result = Directory::expandConsecutiveAsterisks( '/path/**' );
		expect( $result )->toBe( [ '/path/*', '/path/*/*' ] );

		$result = Directory::expandConsecutiveAsterisks( '/**/' );
		expect( $result )->toBe( [ '/*/', '/*/*/' ] );
	} );

	it( 'expands triple asterisks correctly', function () {
		$result = Directory::expandConsecutiveAsterisks( '/some/path/***/dir' );
		expect( $result )->toHaveCount( 3 );
		expect( $result )->toContain( '/some/path/*/dir' );
		expect( $result )->toContain( '/some/path/*/*/dir' );
		expect( $result )->toContain( '/some/path/*/*/*/dir' );

		$result = Directory::expandConsecutiveAsterisks( '/***/' );
		expect( $result )->toHaveCount( 3 );
		expect( $result )->toContain( '/*/' );
		expect( $result )->toContain( '/*/*/' );
		expect( $result )->toContain( '/*/*/*/' );
	} );

	it( 'expands quadruple asterisks correctly', function () {
		$result = Directory::expandConsecutiveAsterisks( '/some/****/deeply/nested' );
		expect( $result )->toHaveCount( 4 );
		expect( $result )->toContain( '/some/*/deeply/nested' );
		expect( $result )->toContain( '/some/*/*/deeply/nested' );
		expect( $result )->toContain( '/some/*/*/*/deeply/nested' );
		expect( $result )->toContain( '/some/*/*/*/*/deeply/nested' );

		$result = Directory::expandConsecutiveAsterisks( '/****/' );
		expect( $result )->toHaveCount( 4 );
		expect( $result )->toContain( '/*/' );
		expect( $result )->toContain( '/*/*/' );
		expect( $result )->toContain( '/*/*/*/' );
		expect( $result )->toContain( '/*/*/*/*/' );
	} );

	it( 'handles multiple consecutive asterisk groups', function () {
		$result = Directory::expandConsecutiveAsterisks( '/some/dir/**/dir/**' );
		expect( $result )->toHaveCount( 4 );
		expect( $result )->toContain( '/some/dir/*/dir/*' );
		expect( $result )->toContain( '/some/dir/*/*/dir/*' );
		expect( $result )->toContain( '/some/dir/*/dir/*/*' );
		expect( $result )->toContain( '/some/dir/*/*/dir/*/*' );

		$result = Directory::expandConsecutiveAsterisks( '/a/**/b/**/c' );
		expect( $result )->toHaveCount( 4 );
		expect( $result )->toContain( '/a/*/b/*/c' );
		expect( $result )->toContain( '/a/*/*/b/*/c' );
		expect( $result )->toContain( '/a/*/b/*/*/c' );
		expect( $result )->toContain( '/a/*/*/b/*/*/c' );
	} );

	it( 'handles complex multiple groups with different asterisk counts', function () {
		$result = Directory::expandConsecutiveAsterisks( '/test/***/middle/**/end' );
		expect( $result )->toHaveCount( 6 );
		expect( $result )->toContain( '/test/*/middle/*/end' );
		expect( $result )->toContain( '/test/*/*/middle/*/end' );
		expect( $result )->toContain( '/test/*/*/*/middle/*/end' );
		expect( $result )->toContain( '/test/*/middle/*/*/end' );
		expect( $result )->toContain( '/test/*/*/middle/*/*/end' );
		expect( $result )->toContain( '/test/*/*/*/middle/*/*/end' );
	} );

	it( 'handles the problematic debug case', function () {
		$result = Directory::expandConsecutiveAsterisks( '/**/**/***' );
		expect( $result )->toHaveCount( 5 );
		expect( $result )->toContain( '/*/*/*' );
		expect( $result )->toContain( '/*/*/*/*' );
		expect( $result )->toContain( '/*/*/*/*/*' );
		expect( $result )->toContain( '/*/*/*/*/*/*' );
		expect( $result )->toContain( '/*/*/*/*/*/*/*' );
	} );

	it( 'removes duplicate patterns', function () {
		// This test ensures that when different combinations produce the same pattern,
		// duplicates are removed
		$result       = Directory::expandConsecutiveAsterisks( '/**/**/***' );
		$uniqueResult = array_unique( $result );
		expect( count( $result ) )->toBe( count( $uniqueResult ) );
	} );
} );