<?php

/**
 * @group taxonomy
 * @covers ::get_tag_link
 */
class Tests_Term_GetTagLink extends WP_UnitTestCase {
	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	public static $tag_id;

	/**
	 * Test taxonomy term ID.
	 *
	 * @var int
	 */
	public static $term_id;

	/**
	 * Set up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tag_id = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'test-tag',
			)
		);

		register_taxonomy( 'wptests_tax', 'post' );
		self::$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => 'test-term',
			)
		);
	}

	/**
	 * Set up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		// Required as taxonomies are reset between tests.
		register_taxonomy( 'wptests_tax', 'post' );
	}

	public function test_success() {
		$tag_id = self::$tag_id;

		$found    = get_tag_link( $tag_id );
		$expected = home_url( '?tag=test-tag' );

		$this->assertSame( $expected, $found );
	}

	/**
	 * @ticket 42771
	 */
	public function test_should_return_link_for_term_from_another_taxonomy_on_primed_cache() {
		$term_id = self::$term_id;

		$term = get_term( $term_id );

		$found    = get_tag_link( $term_id );
		$expected = home_url( '?wptests_tax=test-term' );

		$this->assertSame( $expected, $found );
	}

	/**
	 * @ticket 42771
	 */
	public function test_should_return_link_for_term_from_another_taxonomy_on_empty_cache() {
		$term_id = self::$term_id;

		clean_term_cache( $term_id );

		$found    = get_tag_link( $term_id );
		$expected = home_url( '?wptests_tax=test-term' );

		$this->assertSame( $expected, $found );
	}
}
