<?php

/**
 * Tests for tag_description().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::tag_description
 */
class Tests_Category_TagDescription extends WP_UnitTestCase {

	/**
	 * Tag object with a description.
	 *
	 * @var WP_Term
	 */
	private static $tag;

	/**
	 * Tag object without a description.
	 *
	 * @var WP_Term
	 */
	private static $empty_tag;

	/**
	 * Category object with a description.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Creates shared category and tag fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tag = $factory->tag->create_and_get(
			array(
				'name'        => 'Described Tag',
				'slug'        => 'described-tag',
				'description' => 'Tag description text',
			)
		);

		self::$empty_tag = $factory->tag->create_and_get(
			array(
				'name'        => 'Empty Tag',
				'slug'        => 'empty-tag',
				'description' => '',
			)
		);

		self::$category = $factory->category->create_and_get(
			array(
				'name'        => 'Described Category',
				'slug'        => 'described-category',
				'description' => 'Category description text',
			)
		);
	}

	/**
	 * Tests that tag_description() returns the formatted description for a given tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_description_for_tag_id() {
		$this->assertSame(
			"<p>Tag description text</p>\n",
			tag_description( self::$tag->term_id )
		);
	}

	/**
	 * Tests that tag_description() returns the formatted description for a WP_Term object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_description_for_term_object() {
		$this->assertSame(
			"<p>Tag description text</p>\n",
			tag_description( self::$tag )
		);
	}

	/**
	 * Tests that tag_description() defaults to the queried object on a tag archive.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_queried_object_on_tag_archive() {
		$this->go_to( get_tag_link( self::$tag->term_id ) );
		$this->assertTrue( is_tag() );
		$this->assertSame(
			"<p>Tag description text</p>\n",
			tag_description()
		);
	}

	/**
	 * Tests that tag_description() returns an empty string when the tag does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_non_existent_tag() {
		$this->assertSame( '', tag_description( 99999999 ) );
	}

	/**
	 * Tests that tag_description() returns an empty string when the tag has no description.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_tag_without_description() {
		$this->assertSame( '', tag_description( self::$empty_tag->term_id ) );
	}

	/**
	 * Tests that tag_description() returns an empty string when called without arguments outside a term archive.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_called_without_arguments_outside_archive() {
		$this->go_to( home_url() );
		$this->assertFalse( is_tag() );
		$this->assertSame( '', tag_description() );
	}

	/**
	 * Tests that tag_description() applies the 'term_description' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_term_description_filter() {
		$filter = static function ( $description ) {
			return '<p>Filtered description</p>';
		};

		add_filter( 'term_description', $filter );

		$result = tag_description( self::$tag->term_id );

		remove_filter( 'term_description', $filter );

		$this->assertSame( '<p>Filtered description</p>', $result );
	}

	/**
	 * Tests that tag_description() delegates to term_description() and returns descriptions for terms from other taxonomies.
	 *
	 * @ticket 65819
	 */
	public function test_returns_description_for_term_from_another_taxonomy() {
		$this->assertSame(
			"<p>Category description text</p>\n",
			tag_description( self::$category->term_id )
		);
	}
}
