<?php

/**
 * Test WP_Theme_JSON_Resolver::read_json_file().
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group theme
 *
 * @covers WP_Theme_JSON_Resolver::read_json_file
 */
class Tests_Theme_wpThemeJsonResolver_readJsonFile extends WP_UnitTestCase {

	/**
	 * WP_Theme_JSON_Resolver::$theme_json_file_cache property.
	 *
	 * @var ReflectionProperty
	 */
	private static $property_theme_json_file_cache;

	/**
	 * Original value of the WP_Theme_JSON_Resolver::$theme_json_file_cache property.
	 *
	 * @var array
	 */
	private static $property_theme_json_file_cache_orig_value;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		static::$property_theme_json_file_cache = new ReflectionProperty( WP_Theme_JSON_Resolver::class, 'theme_json_file_cache' );
		if ( PHP_VERSION_ID < 80100 ) {
			static::$property_theme_json_file_cache->setAccessible( true );
		}
		static::$property_theme_json_file_cache_orig_value = static::$property_theme_json_file_cache->getValue();
	}

	public static function tear_down_after_class() {
		static::$property_theme_json_file_cache->setValue( null, static::$property_theme_json_file_cache_orig_value );
		parent::tear_down_after_class();
	}

	public function tear_down() {
		// Reset data between tests.
		static::$property_theme_json_file_cache->setValue( null, array() );
		parent::tear_down();
	}

	/**
	 * @ticket 64620
	 */
	public function test_read_json_file() {
		$read_json_file = new ReflectionMethod( WP_Theme_JSON_Resolver::class, 'read_json_file' );
		if ( PHP_VERSION_ID < 80100 ) {
			$read_json_file->setAccessible( true );
		}

		// Test reading a valid JSON file.
		$valid_file = DIR_TESTDATA . '/themedir1/block-theme/theme.json';
		$result     = $read_json_file->invoke( null, $valid_file );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( 3, $result['version'] );

		// Test that the result is cached.
		$cache = static::$property_theme_json_file_cache->getValue();
		$this->assertArrayHasKey( $valid_file, $cache );
		$this->assertSame( $result, $cache[ $valid_file ] );

		// Test cache hit: modify cache and verify read_json_file returns cached value.
		$cache[ $valid_file ] = array(
			'version' => 3,
			'cached'  => true,
		);
		static::$property_theme_json_file_cache->setValue( null, $cache );
		$result = $read_json_file->invoke( null, $valid_file );
		$this->assertSame( 3, $result['version'] );
		$this->assertTrue( $result['cached'] );

		// Test non-existent file.
		$non_existent_file = DIR_TESTDATA . '/non-existent.json';
		$result            = $read_json_file->invoke( null, $non_existent_file );
		$this->assertSame( array(), $result );

		// Test unreadable file.
		if ( function_exists( 'posix_getpwuid' ) && 'root' !== posix_getpwuid( posix_geteuid() )['name'] ) {
			$unreadable_file = DIR_TESTDATA . '/unreadable.json';
			touch( $unreadable_file );
			chmod( $unreadable_file, 0000 );
			$result = @$read_json_file->invoke( null, $unreadable_file );
			$this->assertSame( array(), $result );
			unlink( $unreadable_file );
		}

		// Test invalid JSON.
		$invalid_json_file = tempnam( sys_get_temp_dir(), 'invalid-json' );
		file_put_contents( $invalid_json_file, '{ invalid json }' );
		$result = @$read_json_file->invoke( null, $invalid_json_file );
		$this->assertSame( array(), $result );
		unlink( $invalid_json_file );
	}
}
