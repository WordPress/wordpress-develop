<?php

/**
 * @group bookmark
 * @covers ::get_bookmark
 */
class Tests_Bookmark_GetBookmark extends WP_UnitTestCase {
	/**
	 * Instance of the bookmark object.
	 *
	 * @var stdClass
	 */
	private static $bookmark;

	/**
	 * Create and get a bookmark for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$bookmark = $factory->bookmark->create_and_get();
		// Delete the bookmark that was cached when the factory invoked get_bookmark().
		wp_cache_delete( self::$bookmark->link_id, 'bookmark' );
	}

	/**
	 * Delete the bookmark before existing the test class.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_link( self::$bookmark->link_id );
	}

	/**
	 * Reset globals after each test.
	 */
	public function tearDown() {
		unset( $GLOBALS['link'] );
		parent::tearDown();
	}

	/**
	 * Path 1A: Given empty bookmark and global link exists.
	 *
	 * @dataProvider data_when_empty_bookmark
	 */
	public function test_should_return_global_link_in_requested_output_format( $args ) {
		$GLOBALS['link'] = self::$bookmark;
		$args            = $this->init_func_args( $args, 0 );
		$actual_bookmark = get_bookmark( ...$args );

		$expected = $this->maybe_format_expected_data( $args, $GLOBALS['link'] );

		$this->assertArrayHasKey( 'link', $GLOBALS );
		$this->assertSame( $expected, $actual_bookmark );
		// Should bypass the cache.
		$this->assertFalse( wp_cache_get( self::$bookmark->link_id, 'bookmark' ) );
	}

	/**
	 * Path 1B: Given empty bookmark and global link does not exist.
	 *
	 * @dataProvider data_when_empty_bookmark
	 */
	public function test_should_return_null( $args ) {
		$args = $this->init_func_args( $args, 0 );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		$this->assertArrayNotHasKey( 'link', $GLOBALS );
		$this->assertNull( $actual_bookmark );
		$this->assertFalse( wp_cache_get( self::$bookmark->link_id, 'bookmark' ) );
	}

	/**
	 * Path 1 data provider, i.e. when given empty bookmark.
	 */
	public function data_when_empty_bookmark() {
		return array(
			// Unhappy path.
			'with bookmark type mismatch'        => array(
				array(
					'bookmark' => '',
				),
			),
			'with invalid output'                => array(
				array(
					'bookmark' => 0,
					'output'   => 'invalid',
				),
			),
			'with bookmark type mismatch and invalid output' => array(
				array(
					'bookmark' => null,
					'output'   => 'invalid',
				),
			),
			// Happy path.
			'with defaults'                      => array(
				array(
					'bookmark' => 0,
				),
			),
			'with non-default output'            => array(
				array(
					'bookmark' => 0,
					'output'   => ARRAY_A,
				),
			),
			'with non-default filter'            => array(
				array(
					'bookmark' => 0,
					'filter'   => 'display',
				),
			),
			'with non-default output and filter' => array(
				array(
					'bookmark' => 0,
					'output'   => ARRAY_N,
					'filter'   => 'display',
				),
			),
		);
	}

	/**
	 * Path 2: Bookmark instance is given.
	 *
	 * @dataProvider data_when_instance_bookmark
	 */
	public function test_should_cache_bookmark_when_given_instance( $args ) {
		$args     = $this->init_func_args( $args );
		$bookmark = $args[0];
		$expected = $this->maybe_format_expected_data( $args, $bookmark );

		// Check the cache does not exist before the test.
		$this->assertFalse( wp_cache_get( $bookmark->link_id, 'bookmark' ) );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		$this->assertSame( $expected, $actual_bookmark );

		// Check the bookmark was cached.
		$actual_cache = wp_cache_get( $bookmark->link_id, 'bookmark' );
		$this->assertEquals( $bookmark, $actual_cache );
	}

	/**
	 * Path 2 data provider, i.e. when bookmark instance is given.
	 */
	public function data_when_instance_bookmark() {
		return array(
			// Unhappy path.
			'with incomplete bookmark data'      => array(
				array(
					'bookmark' => (object) array(
						'link_id' => '100',
					),
				),
			),
			'with invalid output'                => array(
				array(
					'output' => 'invalid',
				),
			),
			'with invalid filter'                => array(
				array(
					'filter' => 'invalid',
				),
			),
			// Happy path.
			'with defaults'                      => array(
				array(),
			),
			'with non-default output'            => array(
				array(
					'output' => ARRAY_A,
				),
			),
			'with non-default filter'            => array(
				array(
					'filter' => 'display',
				),
			),
			'with non-default output and filter' => array(
				array(
					'output' => ARRAY_N,
					'filter' => 'display',
				),
			),
		);
	}

	/**
	 * Path 3A: Uses the global link when exists and the given bookmark link ID matches the global link.
	 *
	 * @dataProvider data_when_else
	 *
	 * @param array $args Function argument list.
	 */
	public function test_should_return_global_when_else( $args ) {
		$args            = $this->init_func_args( $args, self::$bookmark->link_id );
		$GLOBALS['link'] = self::$bookmark;
		$expected        = $this->maybe_format_expected_data( $args, $GLOBALS['link'] );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		$this->assertSame( $expected, $actual_bookmark );
		$this->assertFalse( wp_cache_get( self::$bookmark->link_id, 'bookmark' ) );
	}

	/**
	 * Path 3B: Pulls from cache when given existing bookmark link ID.
	 *
	 * @dataProvider data_when_else
	 *
	 * @param array $args Function argument list.
	 */
	public function test_should_return_cached_bookmark_when_given_existing_link_id( $args ) {
		// Cache the bookmark instance to setup the test.
		wp_cache_add( self::$bookmark->link_id, self::$bookmark, 'bookmark' );
		$args     = $this->init_func_args( $args, self::$bookmark->link_id );
		$expected = $this->maybe_format_expected_data( $args, self::$bookmark );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		/*
		 * For non-array output type, use assertEquals(). Why? The object pulled from the cache
		 * will have the same property values but will be a different object than the expected object.
		 */
		if ( is_object( $expected ) ) {
			$this->assertEquals( $expected, $actual_bookmark );
		} else {
			$this->assertSameSets( $expected, $actual_bookmark );
		}

		// Check the bookmark was cached.
		$actual_cache = wp_cache_get( self::$bookmark->link_id, 'bookmark' );
		$this->assertEquals( self::$bookmark, $actual_cache );
	}

	/**
	 * Path 3C: Attempts to pull non-existent bookmark from database.
	 *
	 * @dataProvider data_when_else
	 *
	 * @param array $args Function argument list.
	 */
	public function test_should_return_null_when_bookmark_not_in_database( $args ) {
		$bookmark_link_id = self::$bookmark->link_id * 100;
		$args             = $this->init_func_args( $args, $bookmark_link_id );

		// Validate it will run path 6.
		$this->assertFalse( wp_cache_get( $bookmark_link_id, 'bookmark' ) );
		$this->assertArrayNotHasKey( 'link', $GLOBALS );
		global $wpdb;
		$db_actual = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->links WHERE link_id = %d LIMIT 1", $bookmark_link_id ) );
		$this->assertNull( $db_actual );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		$this->assertNull( $actual_bookmark );
		$this->assertFalse( wp_cache_get( $bookmark_link_id, 'bookmark' ) );
	}

	/**
	 * Path 3D: Pulls existing bookmark from the database.
	 *
	 * @dataProvider data_when_else
	 *
	 * @param array $args Function argument list.
	 */
	public function test_should_return_existing_bookmark_from_database( $args ) {
		$args     = $this->init_func_args( $args, self::$bookmark->link_id );
		$expected = $this->maybe_format_expected_data( $args, self::$bookmark );

		// Validate it will run path 5.
		$this->assertFalse( wp_cache_get( self::$bookmark->link_id, 'bookmark' ) );
		$this->assertArrayNotHasKey( 'link', $GLOBALS );

		// Run the function and test results.
		$actual_bookmark = get_bookmark( ...$args );

		/*
		 * For non-array output type, use assertEquals(). Why? The object pulled from the database
		 * will have the same property values but will be a different object than the expected object.
		 */
		if ( is_object( $expected ) ) {
			$this->assertEquals( $expected, $actual_bookmark );
		} else {
			$this->assertSameSets( $expected, $actual_bookmark );
		}

		// Check the bookmark was cached.
		$actual_cache = wp_cache_get( self::$bookmark->link_id, 'bookmark' );
		$this->assertEquals( self::$bookmark, $actual_cache );
	}

	/**
	 * Path 3's data provider which covers the "else" branch, i.e. when the bookmark argument is not empty and
	 * not an object.
	 */
	public function data_when_else() {
		return array(
			// Unhappy path.
			'with invalid output'                => array(
				array(
					'output' => 'invalid',
				),
			),
			'with invalid filter'                => array(
				array(
					'filter' => 'invalid',
				),
			),
			// Happy path.
			'with defaults'                      => array(
				array(),
			),
			'with non-default output'            => array(
				array(
					'output' => ARRAY_A,
				),
			),
			'with non-default filter'            => array(
				array(
					'filter' => 'display',
				),
			),
			'with non-default output and filter' => array(
				array(
					'output' => ARRAY_N,
					'filter' => 'display',
				),
			),
		);
	}

	/**
	 * @ticket 53235
	 */
	public function test_numeric_properties_should_be_cast_to_ints() {
		$contexts = array( 'raw', 'edit', 'db', 'display', 'attribute', 'js' );

		foreach ( $contexts as $context ) {
			$bookmark = get_bookmark( self::$bookmark->link_id, OBJECT, $context );

			$this->assertIsInt( $bookmark->link_id );
			$this->assertIsInt( $bookmark->link_rating );
		}
	}

	/**
	 * Initialize the get_bookmark's function arguments to match the order of the function's signature and
	 * reduce code in the tests.
	 *
	 * @param array        $args     Function argument list.
	 * @param int|stdClass $bookmark Optional. Bookmark's cache key or instance.
	 * @return array Ordered argument list.
	 */
	private function init_func_args( array $args, $bookmark = null ) {
		// The defaults sets the order to match the function's arguments as well as setting the default values.
		$defaults = array(
			'bookmark' => self::$bookmark,
			'output'   => OBJECT,
			'filter'   => 'raw',
		);
		$args     = array_merge( $defaults, $args );

		// When given a bookmark, use it.
		if ( ! is_null( $bookmark ) ) {
			$args['bookmark'] = $bookmark;
		}

		/*
		 * Strip out the keys. Why? The splat operator (...) does not work with associative arrays,
		 * except for in PHP 8 where the keys are named arguments.
		 */
		return array_values( $args );
	}

	/**
	 * Maybe format the bookmark's expected data.
	 *
	 * @param array             $args     Function argument list.
	 * @param int|stdClass|null $bookmark Optional. Bookmark's cache key or instance.
	 * @return array|stdClass bookmark's data.
	 */
	private function maybe_format_expected_data( array $args, $bookmark = null ) {
		if ( is_null( $bookmark ) ) {
			$bookmark = self::$bookmark;
		}

		switch ( $args[1] ) {
			case ARRAY_A:
			case ARRAY_N:
				$expected = get_object_vars( $bookmark );

				if ( ARRAY_N === $args[1] ) {
					$expected = array_values( $expected );
				}

				return $expected;
			default:
				return $bookmark;
		}
	}
}
