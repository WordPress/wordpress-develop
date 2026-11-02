<?php

/**
 * Tests for the_permalink().
 *
 * @group link
 *
 * @covers ::the_permalink
 */
class Tests_Link_ThePermalink extends WP_UnitTestCase {

	/**
	 * Shared post ID.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * Creates shared post fixture.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_name'   => 'test-post',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets up global post data before each test.
	 */
	public function set_up() {
		parent::set_up();

		$GLOBALS['post'] = get_post( self::$post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Cleans up global post data after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Tests that the_permalink() echoes the permalink for the global post by default.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_permalink_for_global_post() {
		$output = get_echo( 'the_permalink' );

		$this->assertSame( get_permalink( self::$post_id ), $output );
		$this->assertStringContainsString( '?p=' . self::$post_id, $output );
	}

	/**
	 * Tests that the_permalink() accepts an explicit post ID.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_explicit_post_id() {
		$other_post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Other Post',
				'post_status' => 'publish',
			)
		);

		$output = get_echo( 'the_permalink', array( $other_post_id ) );

		$this->assertSame( get_permalink( $other_post_id ), $output );
		$this->assertStringContainsString( '?p=' . $other_post_id, $output );
	}

	/**
	 * Tests that the_permalink() accepts an explicit WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_explicit_post_object() {
		$other_post = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Other Post Object',
				'post_status' => 'publish',
			)
		);

		$output = get_echo( 'the_permalink', array( $other_post ) );

		$this->assertSame( get_permalink( $other_post ), $output );
		$this->assertStringContainsString( '?p=' . $other_post->ID, $output );
	}

	/**
	 * Tests that the_permalink() echoes pretty permalinks when enabled.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_pretty_permalink_when_enabled() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$output   = get_echo( 'the_permalink' );
		$expected = get_permalink( self::$post_id );

		$this->set_permalink_structure( '' );

		$this->assertSame( $expected, $output );
		$this->assertStringContainsString( '/test-post/', $output );
	}

	/**
	 * Tests that the_permalink() reflects the 'the_permalink' filter and receives the post argument.
	 *
	 * @ticket 65819
	 */
	public function test_applies_the_permalink_filter() {
		$passed_args = array();

		$filter = static function ( $permalink, $post ) use ( &$passed_args ) {
			$passed_args = array(
				'permalink' => $permalink,
				'post'      => $post,
			);
			return 'https://example.org/custom-permalink/';
		};

		add_filter( 'the_permalink', $filter, 10, 2 );

		$output = get_echo( 'the_permalink', array( self::$post_id ) );

		remove_filter( 'the_permalink', $filter, 10 );

		$this->assertSame( 'https://example.org/custom-permalink/', $output );
		$this->assertSame( get_permalink( self::$post_id ), $passed_args['permalink'] );
		$this->assertSame( self::$post_id, $passed_args['post'] );
	}

	/**
	 * Tests that the_permalink() echoes an empty string when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_empty_string_for_non_existent_post() {
		$output = get_echo( 'the_permalink', array( 99999999 ) );

		$this->assertSame( '', $output );
	}
}
