<?php

/**
 * Test WP_Theme_JSON_Resolver::read_json_file().
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
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

	/**
	 * WP_Theme_JSON_Resolver::read_json_file() method.
	 *
	 * @var ReflectionMethod
	 */
	private static $method_read_json_file;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		static::$property_theme_json_file_cache = new ReflectionProperty( WP_Theme_JSON_Resolver::class, 'theme_json_file_cache' );
		if ( PHP_VERSION_ID < 80100 ) {
			static::$property_theme_json_file_cache->setAccessible( true );
		}
		static::$property_theme_json_file_cache_orig_value = static::$property_theme_json_file_cache->getValue();

		static::$method_read_json_file = new ReflectionMethod( WP_Theme_JSON_Resolver::class, 'read_json_file' );
		if ( PHP_VERSION_ID < 80100 ) {
			static::$method_read_json_file->setAccessible( true );
		}
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
	 * Calls the protected WP_Theme_JSON_Resolver::read_json_file() method.
	 *
	 * @param string $file_path Path to file.
	 * @return array
	 */
	private static function read_json_file( $file_path ) {
		return static::$method_read_json_file->invoke( null, $file_path );
	}

	/**
	 * Tests reading a valid JSON file.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_with_valid_file() {
		$valid_file = DIR_TESTDATA . '/themedir1/block-theme/theme.json';
		$result     = self::read_json_file( $valid_file );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( 3, $result['version'] );
	}

	/**
	 * Tests that the result is cached after reading a file.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_caches_result() {
		$valid_file = DIR_TESTDATA . '/themedir1/block-theme/theme.json';
		$result     = self::read_json_file( $valid_file );

		$cache = static::$property_theme_json_file_cache->getValue();
		$this->assertArrayHasKey( $valid_file, $cache );
		$this->assertSame( $result, $cache[ $valid_file ] );
	}

	/**
	 * Tests that the cached value is returned when available.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_returns_cached_value() {
		$valid_file = DIR_TESTDATA . '/themedir1/block-theme/theme.json';

		// Seed cache with custom value.
		$cache = array(
			$valid_file => array(
				'version' => 3,
				'cached'  => true,
			),
		);
		static::$property_theme_json_file_cache->setValue( null, $cache );

		$result = self::read_json_file( $valid_file );
		$this->assertSame( 3, $result['version'] );
		$this->assertTrue( $result['cached'] );
	}

	/**
	 * Tests reading a non-existent file.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_with_non_existent_file() {
		$this->expectNotice();

		$non_existent_file = DIR_TESTDATA . '/non-existent.json';
		// we get an error, not an empty array
		self::read_json_file( $non_existent_file );
	}

	/**
	 * Tests reading an unreadable file.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_with_unreadable_file() {
		if ( ! function_exists( 'posix_getpwuid' ) || ! function_exists( 'posix_geteuid' ) ) {
			$this->markTestSkipped( 'The POSIX extension is not available.' );
		}

		$pwuid = posix_getpwuid( posix_geteuid() );
		if ( is_array( $pwuid ) && isset( $pwuid['name'] ) && 'root' === $pwuid['name'] ) {
			$this->markTestSkipped( 'Cannot test unreadable file permissions when running as root.' );
		}

		$this->expectNotice();

		$unreadable_file = tempnam( sys_get_temp_dir(), 'unreadable-json' );
		chmod( $unreadable_file, 0000 );
		try {
			// we get an error, not an empty array
			self::read_json_file( $unreadable_file );
		} finally {
			chmod( $unreadable_file, 0644 );
			unlink( $unreadable_file );
		}
	}

	/**
	 * Tests reading a file with invalid JSON.
	 *
	 * @ticket 64620
	 */
	public function test_read_json_file_with_invalid_json() {
		$this->expectNotice();

		$invalid_json_file = tempnam( sys_get_temp_dir(), 'invalid-json' );
		file_put_contents( $invalid_json_file, '{ invalid json }' );
		try {
			$this->assertSame( array(), self::read_json_file( $invalid_json_file ) );
		} finally {
			unlink( $invalid_json_file );
		}
	}
}
