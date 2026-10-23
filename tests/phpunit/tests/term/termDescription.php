<?php

/**
 * Tests for term_description().
 *
 * @group taxonomy
 *
 * @covers ::term_description
 */
class Tests_Term_TermDescription extends WP_UnitTestCase {

	/**
	 * Category object with a description.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Tag object with a description.
	 *
	 * @var WP_Term
	 */
	private static $tag;

	/**
	 * Creates shared category and tag fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category = $factory->category->create_and_get(
			array(
				'name'        => 'Described Category',
				'slug'        => 'described-category',
				'description' => 'Category description text',
			)
		);

		self::$tag = $factory->tag->create_and_get(
			array(
				'name'        => 'Described Tag',
				'slug'        => 'described-tag',
				'description' => 'Tag description text',
			)
		);
	}

	/**
	 * Tests that term_description() returns the formatted description for a given term ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_description_for_term_id() {
		$this->assertSame(
			"<p>Category description text</p>\n",
			term_description( self::$category->term_id )
		);

		$this->assertSame(
			"<p>Tag description text</p>\n",
			term_description( self::$tag->term_id )
		);
	}

	/**
	 * Tests that term_description() defaults to the queried object on category, tag, and custom taxonomy archives.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_queried_object_on_term_archives() {
		$this->go_to( get_category_link( self::$category->term_id ) );
		$this->assertTrue( is_category() );
		$this->assertSame( "<p>Category description text</p>\n", term_description() );

		$this->go_to( get_tag_link( self::$tag->term_id ) );
		$this->assertTrue( is_tag() );
		$this->assertSame( "<p>Tag description text</p>\n", term_description() );

		register_taxonomy( 'wptests_tax', 'post' );
		$custom_term = self::factory()->term->create_and_get(
			array(
				'taxonomy'    => 'wptests_tax',
				'description' => 'Custom taxonomy description text',
			)
		);

		$this->go_to( get_term_link( $custom_term, 'wptests_tax' ) );
		$this->assertTrue( is_tax() );
		$this->assertSame( "<p>Custom taxonomy description text</p>\n", term_description() );
	}

	/**
	 * Tests that term_description() returns an empty string when the term does not exist
	 * or when no term is passed outside a term archive.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_term_or_non_term_archive() {
		$this->assertSame( '', term_description( 99999999 ) );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( '', term_description() );
	}
}
