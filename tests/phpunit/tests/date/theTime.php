<?php

/**
 * Tests for the_time().
 *
 * @group date
 * @group datetime
 * @group post
 *
 * @covers ::the_time
 */
class Tests_Date_TheTime extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test post date.
	 *
	 * @var string
	 */
	protected static $post_date = '2024-05-18 14:30:45';

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_date' => self::$post_date,
			)
		);
	}

	/**
	 * Sets up global post before each test.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
	}

	/**
	 * Tears down global post after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that the_time() displays the post time in default format.
	 *
	 * @ticket 65819
	 */
	public function test_the_time_default_format() {
		$expected = get_post_time( get_option( 'time_format' ), false, self::$post_id, true );

		$this->expectOutputString( $expected );
		the_time();
	}

	/**
	 * Tests that the_time() respects custom date/time formats.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_custom_formats
	 *
	 * @param string $format   PHP date format string.
	 * @param string $expected Expected output.
	 */
	public function test_the_time_custom_format( $format, $expected ) {
		$this->expectOutputString( $expected );
		the_time( $format );
	}

	/**
	 * Tests that the_time() respects the 'time_format' option when no format argument is passed.
	 *
	 * @ticket 65819
	 */
	public function test_the_time_respects_time_format_option() {
		update_option( 'time_format', 'H:i' );

		$this->expectOutputString( '14:30' );
		the_time();
	}

	/**
	 * Tests that the_time() outputs nothing when no global post exists.
	 *
	 * @ticket 65819
	 */
	public function test_the_time_without_global_post() {
		unset( $GLOBALS['post'] );

		$this->expectOutputString( '' );
		the_time();
	}

	/**
	 * Tests that the 'the_time' filter receives the formatted time and controls output.
	 *
	 * @ticket 65819
	 */
	public function test_the_time_filter_receives_arguments() {
		$filter_args     = array();
		$custom_callback = static function ( $formatted_time, $format ) use ( &$filter_args ) {
			$filter_args[] = func_get_args();
			return 'Time: ' . $formatted_time;
		};

		add_filter( 'the_time', $custom_callback, 10, 2 );

		$this->expectOutputString( 'Time: 14:30:45' );
		the_time( 'H:i:s' );

		$this->assertSame( array( array( '14:30:45', 'H:i:s' ) ), $filter_args );

		remove_filter( 'the_time', $custom_callback, 10 );
	}

	/**
	 * Tests that the_time() respects modifications from the 'get_the_time' filter hook.
	 *
	 * @ticket 65819
	 */
	public function test_the_time_respects_get_the_time_filter() {
		$custom_callback = static function ( $the_time, $format, $post ) {
			return 'Filtered Time';
		};

		add_filter( 'get_the_time', $custom_callback, 10, 3 );

		$this->expectOutputString( 'Filtered Time' );
		the_time();

		remove_filter( 'get_the_time', $custom_callback, 10 );
	}

	/**
	 * Data provider of custom date and time formats.
	 *
	 * @return array[]
	 */
	public static function data_custom_formats() {
		return array(
			'12-hour time with minutes' => array( 'g:i a', '2:30 pm' ),
			'date and time'             => array( 'Y-m-d H:i', '2024-05-18 14:30' ),
			'day of the week'           => array( 'l', 'Saturday' ),
		);
	}
}
