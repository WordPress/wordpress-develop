<?php

/**
 * Tests for the wp_shortlink_wp_head() function.
 *
 * @group link
 * @covers ::wp_shortlink_wp_head
 */
class Tests_Link_WpShortlinkWpHead extends WP_UnitTestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Shortlink Head Post',
			)
		);
	}

	/**
	 * Tests that wp_shortlink_wp_head() outputs nothing on non-singular requests.
	 *
	 * @ticket 65819
	 */
	public function test_wp_shortlink_wp_head_outputs_nothing_when_not_singular() {
		$this->go_to( home_url( '/' ) );

		$this->expectOutputString( '' );
		wp_shortlink_wp_head();
	}

	/**
	 * Tests that wp_shortlink_wp_head() outputs the shortlink element on a singular post.
	 *
	 * @ticket 65819
	 */
	public function test_wp_shortlink_wp_head_outputs_link_for_singular_post() {
		$this->go_to( get_permalink( self::$post_id ) );

		$shortlink = wp_get_shortlink( self::$post_id );
		$expected  = "<link rel='shortlink' href='" . esc_url( $shortlink ) . "' />\n";

		$this->expectOutputString( $expected );
		wp_shortlink_wp_head();
	}

	/**
	 * Tests that wp_shortlink_wp_head() respects custom shortlinks from the 'pre_get_shortlink' filter.
	 *
	 * @ticket 65819
	 */
	public function test_wp_shortlink_wp_head_respects_pre_get_shortlink_filter() {
		$this->go_to( get_permalink( self::$post_id ) );

		$custom_shortlink = 'https://wp.me/custom123?one=1&two=2';
		$filter           = static function () use ( $custom_shortlink ) {
			return $custom_shortlink;
		};

		add_filter( 'pre_get_shortlink', $filter );

		$expected = "<link rel='shortlink' href='https://wp.me/custom123?one=1&#038;two=2' />\n";

		$this->expectOutputString( $expected );
		wp_shortlink_wp_head();

		remove_filter( 'pre_get_shortlink', $filter );
	}

	/**
	 * Tests that wp_shortlink_wp_head() outputs nothing when the shortlink is empty.
	 *
	 * @ticket 65819
	 */
	public function test_wp_shortlink_wp_head_outputs_nothing_when_shortlink_is_empty() {
		$this->go_to( get_permalink( self::$post_id ) );

		$filter = static function () {
			return '';
		};

		add_filter( 'get_shortlink', $filter );

		$this->expectOutputString( '' );
		wp_shortlink_wp_head();

		remove_filter( 'get_shortlink', $filter );
	}
}
