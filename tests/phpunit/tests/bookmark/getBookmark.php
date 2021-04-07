<?php

/**
 * @group bookmark
 * @group getBookmark
 * @covers :get_bookmark
 */
class Tests_Bookmark_GetBookmark extends WP_UnitTestCase {
	/**
	 * Capture preexisting state to restore when exiting this test class.
	 *
	 * @var array
	 */
	private static $pre_state = array();

	/**
	 * Instance of the bookmark object.
	 *
	 * @var stdClass
	 */
	private $bookmark;

	/**
	 * Setup the test environment before running the tests in this class.
	 *
	 * @param WP_UnitTest_Factory $factory Instance of the factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Capture the existing global link to restore when done.
		if ( isset( $GLOBALS['link'] ) ) {
			self::$pre_state['global_link'] = $GLOBALS['link'];
			unset( $GLOBALS['link'] );
		}
	}

	/**
	 * Restore the global state before exiting this test class.
	 */
	public static function tearDownAfterClass() {
		if ( array_key_exists( 'global_link', self::$pre_state ) ) {
			$GLOBALS['link'] = self::$pre_state['global_link'];
			unset( self::$pre_state['global_link'] );
		}

		parent::tearDownAfterClass();
	}

	public function setUp() {
		parent::setUp();

		$this->bookmark = $this->factory()->bookmark->create_and_get();
		wp_cache_delete( $this->bookmark->link_id, 'bookmark' );
	}

	/**
	 * Clean up the global and cache state after each test.
	 */
	public function tearDown() {
		unset( $GLOBALS['link'] );
		wp_cache_delete( $this->bookmark->link_id, 'bookmark' );

		parent::tearDown();
	}

	/**
	 * @dataProvider data_when_given_0_bookmark
	 */
	public function test_should_return_null( $args ) {
		$actual_bookmark = get_bookmark( ...$args );

		$this->assertArrayNotHasKey( 'link', $GLOBALS );
		$this->assertNull( $actual_bookmark );

		// Should bypass the cache.
		$this->assertFalse( wp_cache_get( $this->bookmark->link_id, 'bookmark' ) );
	}

	/**
	 * @dataProvider data_when_given_0_bookmark
	 */
	public function test_should_return_global_link_in_requested_output_format( $args ) {
		$GLOBALS['link'] = $this->bookmark;
		$actual_bookmark = get_bookmark( ...$args );

		// When given, convert the instance into the expected array format.
		if ( isset( $args[1] ) ) {
			$expected = get_object_vars( $GLOBALS['link'] );
			if ( ARRAY_N === $args[1] ) {
				$expected = array_values( $expected );
			}
		} else {
			$expected = $GLOBALS['link'];
		}

		$this->assertArrayHasKey( 'link', $GLOBALS );
		$this->assertSame( $expected, $actual_bookmark );
		// Should bypass the cache.
		$this->assertFalse( wp_cache_get( $this->bookmark->link_id, 'bookmark' ) );
	}

	public function data_when_given_0_bookmark() {
		return array(
			'with default args'                  => array( array( 0 ) ),
			'with non-default output'            => array( array( 0, ARRAY_A ) ),
			'with non-default output and filter' => array( array( 0, ARRAY_N, 'edit' ) ),
		);
	}

	/**
	 * @dataProvider data_when_given_bookmark_instance
	 */
	public function test_should_cache_bookmark_when_given_instance( $output = OBJECT, $filter = 'raw' ) {
		// Check the cache does not exist before the test.
		$this->assertFalse( wp_cache_get( $this->bookmark->link_id, 'bookmark' ) );

		get_bookmark( $this->bookmark, $output, $filter );

		// Check the bookmark was cached.
		$actual_cache = wp_cache_get( $this->bookmark->link_id, 'bookmark' );
		$this->assertEquals( $this->bookmark, $actual_cache );
	}

	/**
	 * @dataProvider data_when_given_bookmark_instance
	 */
	public function test_should_return_in_requested_output_format_when_given_instance( $output = OBJECT, $filter = 'raw' ) {
		// Convert the instance into the expected array format.
		if ( in_array( $output, array( ARRAY_A, ARRAY_N ), true ) ) {
			$expected = get_object_vars( $this->bookmark );
			if ( ARRAY_N === $output ) {
				$expected = array_values( $expected );
			}
		} else {
			$expected = $this->bookmark;
		}

		$actual_bookmark = get_bookmark( $this->bookmark, $output, $filter );

		$this->assertSame( $expected, $actual_bookmark );
	}

	public function data_when_given_bookmark_instance() {
		return array(
			'with default args'                  => array(),
			'with non-default output'            => array( ARRAY_A ),
			'with non-default output and filter' => array( ARRAY_N, 'display' ),
		);
	}
}
