<?php

/**
 * Tests for get_post_format_link().
 *
 * @group post
 * @group post-formats
 *
 * @covers ::get_post_format_link
 */
class Tests_Post_GetPostFormatLink extends WP_UnitTestCase {

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$formats = array( 'aside', 'video' );

		foreach ( $formats as $format ) {
			wp_insert_term(
				'post-format-' . $format,
				'post_format',
				array( 'slug' => 'post-format-' . $format )
			);
		}
	}

	/**
	 * Tests that get_post_format_link() returns false for a format whose term does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_get_post_format_link_non_existent_term() {
		$this->assertFalse( get_post_format_link( 'nonexistent' ) );
	}

	/**
	 * Tests that get_post_format_link() returns false for 'standard' format when no term exists.
	 *
	 * @ticket 65819
	 */
	public function test_get_post_format_link_standard_returns_false() {
		$this->assertFalse( get_post_format_link( 'standard' ) );
	}

	/**
	 * Tests that get_post_format_link() returns the correct URL with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_standard_post_formats
	 *
	 * @param string $format Post format slug.
	 */
	public function test_get_post_format_link_with_plain_permalinks( $format ) {
		$expected = home_url( '?post_format=' . $format );
		$this->assertSame( $expected, get_post_format_link( $format ) );
	}

	/**
	 * Tests that get_post_format_link() returns the correct URL with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_standard_post_formats
	 *
	 * @param string $format Post format slug.
	 */
	public function test_get_post_format_link_with_pretty_permalinks( $format ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		add_permastruct( 'post_format', 'type/%post_format%' );

		$expected = home_url( '/type/' . $format . '/' );
		$actual   = get_post_format_link( $format );

		remove_permastruct( 'post_format' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that get_post_format_link() works with custom post format terms.
	 *
	 * @ticket 65819
	 */
	public function test_get_post_format_link_custom_format_term() {
		wp_insert_term(
			'post-format-custom',
			'post_format',
			array( 'slug' => 'post-format-custom' )
		);

		$expected = home_url( '?post_format=custom' );
		$this->assertSame( $expected, get_post_format_link( 'custom' ) );
	}

	/**
	 * Tests that the 'term_link' filter can modify the post format link.
	 *
	 * @ticket 65819
	 */
	public function test_get_post_format_link_respects_term_link_filter() {
		$filter = static function ( $termlink, $term, $taxonomy ) {
			if ( 'post_format' === $taxonomy ) {
				return 'https://example.org/custom-format-link/' . $term->slug;
			}
			return $termlink;
		};

		add_filter( 'term_link', $filter, 10, 3 );

		$this->assertSame( 'https://example.org/custom-format-link/post-format-aside', get_post_format_link( 'aside' ) );

		remove_filter( 'term_link', $filter, 10 );
	}

	/**
	 * Data provider of standard post format slugs.
	 *
	 * @return array[]
	 */
	public static function data_standard_post_formats() {
		return array(
			'aside format' => array( 'aside' ),
			'video format' => array( 'video' ),
		);
	}
}
