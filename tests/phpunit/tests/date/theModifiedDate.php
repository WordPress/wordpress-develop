<?php

/**
 * Tests for the_modified_date().
 *
 * @group date
 * @group datetime
 * @group post
 *
 * @covers ::the_modified_date
 */
class Tests_Date_TheModifiedDate extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test post creation date.
	 *
	 * @var string
	 */
	protected static $post_date = '2024-01-10 10:00:00';

	/**
	 * Test post modified date.
	 *
	 * @var string
	 */
	protected static $post_modified = '2024-05-18 16:45:30';

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		self::$post_id = $factory->post->create(
			array(
				'post_date' => self::$post_date,
			)
		);

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => self::$post_modified,
				'post_modified_gmt' => self::$post_modified,
			),
			array( 'ID' => self::$post_id )
		);
		clean_post_cache( self::$post_id );
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
	 * Tests that the_modified_date() displays the modified date in the default format.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_default_format() {
		$expected = get_the_modified_date();

		$this->expectOutputString( $expected );
		the_modified_date();
	}

	/**
	 * Tests that the_modified_date() respects custom date formats.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_custom_formats
	 *
	 * @param string $format   PHP date format.
	 * @param string $expected Expected formatted date output.
	 */
	public function test_the_modified_date_custom_format( $format, $expected ) {
		$this->expectOutputString( $expected );
		the_modified_date( $format );
	}

	/**
	 * Tests that the_modified_date() respects the 'date_format' option when no format is specified.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_respects_date_format_option() {
		update_option( 'date_format', 'Y/m/d' );

		$this->expectOutputString( '2024/05/18' );
		the_modified_date();
	}

	/**
	 * Tests that the_modified_date() outputs before and after markup around the date.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_with_before_and_after() {
		$this->expectOutputString( '<p class="modified">2024-05-18</p>' );
		the_modified_date( 'Y-m-d', '<p class="modified">', '</p>' );
	}

	/**
	 * Tests that the_modified_date() outputs before markup without after markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_with_only_before() {
		$this->expectOutputString( 'Modified on: 2024-05-18' );
		the_modified_date( 'Y-m-d', 'Modified on: ' );
	}

	/**
	 * Tests that the_modified_date() outputs after markup without before markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_with_only_after() {
		$this->expectOutputString( '2024-05-18 (updated)' );
		the_modified_date( 'Y-m-d', '', ' (updated)' );
	}

	/**
	 * Tests that the_modified_date() returns the date string and does not echo when display is false.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_display_false_returns_string() {
		$this->expectOutputString( '' );

		$result = the_modified_date( 'Y-m-d', '<p>', '</p>', false );
		$this->assertSame( '<p>2024-05-18</p>', $result );
	}

	/**
	 * Tests that the_modified_date() outputs nothing when no global post exists.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_without_global_post() {
		unset( $GLOBALS['post'] );

		$this->expectOutputString( '' );
		the_modified_date();
	}

	/**
	 * Tests that the_modified_date() returns an empty string when display is false and no global post exists.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_without_global_post_display_false() {
		unset( $GLOBALS['post'] );

		$this->expectOutputString( '' );

		$result = the_modified_date( '', '', '', false );
		$this->assertSame( '', $result );
	}

	/**
	 * Tests that the_modified_date() outputs before and after markup even when no global post exists.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_without_global_post_with_before_and_after() {
		unset( $GLOBALS['post'] );

		$this->expectOutputString( '<!--start--><!--end-->' );
		the_modified_date( '', '<!--start-->', '<!--end-->' );
	}

	/**
	 * Tests that the 'the_modified_date' filter hook receives all expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_filter_receives_arguments() {
		$filter = new MockAction();
		add_filter( 'the_modified_date', array( $filter, 'filter' ), 10, 4 );

		$this->expectOutputString( '<b>2024-05-18</b>' );
		the_modified_date( 'Y-m-d', '<b>', '</b>' );

		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( '<b>2024-05-18</b>', $filter_args[0][0] );
		$this->assertSame( 'Y-m-d', $filter_args[0][1] );
		$this->assertSame( '<b>', $filter_args[0][2] );
		$this->assertSame( '</b>', $filter_args[0][3] );
	}

	/**
	 * Tests that the 'the_modified_date' filter can modify the displayed date output.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_filter_modifies_output() {
		$custom_callback = static function ( $the_modified_date, $format, $before, $after ) {
			return '<time>' . $the_modified_date . '</time>';
		};

		add_filter( 'the_modified_date', $custom_callback, 10, 4 );

		$this->expectOutputString( '<time>2024-05-18</time>' );
		the_modified_date( 'Y-m-d' );

		remove_filter( 'the_modified_date', $custom_callback, 10 );
	}

	/**
	 * Tests that the_modified_date() respects modifications from the 'get_the_modified_date' filter hook.
	 *
	 * @ticket 65819
	 */
	public function test_the_modified_date_respects_get_the_modified_date_filter() {
		$custom_callback = static function ( $the_time, $format, $post ) {
			return 'Filtered Date';
		};

		add_filter( 'get_the_modified_date', $custom_callback, 10, 3 );

		$this->expectOutputString( '<time>Filtered Date</time>' );
		the_modified_date( 'Y-m-d', '<time>', '</time>' );

		remove_filter( 'get_the_modified_date', $custom_callback, 10 );
	}

	/**
	 * Data provider of custom date formats.
	 *
	 * @return array[]
	 */
	public static function data_custom_formats() {
		return array(
			'slash separated'    => array( 'd/m/Y', '18/05/2024' ),
			'full date with day' => array( 'l, F j, Y', 'Saturday, May 18, 2024' ),
		);
	}
}
