<?php

/**
 * Tests for the rel_canonical() function.
 *
 * @group link
 * @covers ::rel_canonical
 */
class Tests_Link_RelCanonical extends WP_UnitTestCase {

	/**
	 * Post ID for tests.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test fixtures before class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Canonical Post',
			)
		);
	}

	/**
	 * Tests that rel_canonical() outputs nothing when not on a singular view.
	 *
	 * @ticket 65819
	 */
	public function test_rel_canonical_outputs_nothing_when_not_singular() {
		$this->go_to( home_url( '/' ) );

		$this->expectOutputString( '' );
		rel_canonical();
	}

	/**
	 * Tests that rel_canonical() outputs link element for a singular post.
	 *
	 * @ticket 65819
	 */
	public function test_rel_canonical_outputs_link_for_singular_post() {
		$this->go_to( get_permalink( self::$post_id ) );

		$expected = '<link rel="canonical" href="' . esc_url( wp_get_canonical_url( self::$post_id ) ) . '" />' . "\n";

		$this->expectOutputString( $expected );
		rel_canonical();
	}

	/**
	 * Tests that rel_canonical() respects a modified URL from the 'get_canonical_url' filter.
	 *
	 * @ticket 65819
	 */
	public function test_rel_canonical_respects_filter() {
		$this->go_to( get_permalink( self::$post_id ) );

		$custom_url = 'https://custom.example.org/canonical-post/?one=1&two=2';
		$filter     = static function () use ( $custom_url ) {
			return $custom_url;
		};

		add_filter( 'get_canonical_url', $filter );

		$expected = '<link rel="canonical" href="https://custom.example.org/canonical-post/?one=1&#038;two=2" />' . "\n";

		$this->expectOutputString( $expected );
		rel_canonical();

		remove_filter( 'get_canonical_url', $filter );
	}

	/**
	 * Tests that rel_canonical() outputs nothing when the canonical URL is empty.
	 *
	 * @ticket 65819
	 */
	public function test_rel_canonical_outputs_nothing_when_canonical_url_is_empty() {
		$this->go_to( get_permalink( self::$post_id ) );

		$filter = static function () {
			return '';
		};

		add_filter( 'get_canonical_url', $filter );

		$this->expectOutputString( '' );
		rel_canonical();

		remove_filter( 'get_canonical_url', $filter );
	}
}
