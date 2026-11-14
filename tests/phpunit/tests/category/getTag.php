<?php

/**
 * Tests for get_tag().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_tag
 */
class Tests_Category_GetTag extends WP_UnitTestCase {

	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Tag object.
	 *
	 * @var WP_Term
	 */
	private static $tag;

	/**
	 * Creates shared tag fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tag_id = $factory->tag->create(
			array(
				'name'        => 'Test Tag',
				'slug'        => 'test-tag',
				'description' => 'Tag description',
			)
		);

		self::$tag = get_term( self::$tag_id, 'post_tag' );
	}

	/**
	 * Tests that get_tag() returns a WP_Term object by tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_tag_object_by_id() {
		$tag = get_tag( self::$tag_id );

		$this->assertInstanceOf( WP_Term::class, $tag );
		$this->assertSame( 'post_tag', $tag->taxonomy );
		$this->assertSame( 'raw', $tag->filter );
		$this->assertSame( self::$tag_id, $tag->term_id );
		$this->assertSame( 'Test Tag', $tag->name );
		$this->assertSame( 'test-tag', $tag->slug );
		$this->assertSame( 'Tag description', $tag->description );
	}

	/**
	 * Tests that get_tag() accepts a tag object.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_tag_object() {
		$tag = get_tag( self::$tag );

		$this->assertInstanceOf( WP_Term::class, $tag );
		$this->assertSame( self::$tag_id, $tag->term_id );
		$this->assertSame( 'Test Tag', $tag->name );
		$this->assertSame( 'test-tag', $tag->slug );
	}

	/**
	 * Tests that get_tag() accepts a raw database row object.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_raw_tag_object() {
		$raw_object = (object) self::$tag->to_array();

		$tag = get_tag( $raw_object );

		$this->assertInstanceOf( WP_Term::class, $tag );
		$this->assertSame( self::$tag_id, $tag->term_id );
		$this->assertSame( 'Test Tag', $tag->name );
		$this->assertSame( 'test-tag', $tag->slug );
	}

	/**
	 * Tests that ARRAY_A output returns an associative array of tag fields.
	 *
	 * @ticket 65819
	 */
	public function test_returns_associative_array() {
		$tag = get_tag( self::$tag_id, ARRAY_A );

		$this->assertIsArray( $tag );
		$this->assertSame( self::$tag_id, $tag['term_id'] );
		$this->assertSame( 'Test Tag', $tag['name'] );
		$this->assertSame( 'test-tag', $tag['slug'] );
		$this->assertSame( 'Tag description', $tag['description'] );
		$this->assertSame( 'post_tag', $tag['taxonomy'] );
	}

	/**
	 * Tests that ARRAY_N output returns a numeric array of tag field values.
	 *
	 * @ticket 65819
	 */
	public function test_returns_numeric_array() {
		$tag = get_tag( self::$tag_id, ARRAY_N );

		$this->assertIsArray( $tag );
		$this->assertSame( array_values( self::$tag->to_array() ), $tag );
	}

	/**
	 * Tests that the filter parameter sanitizes the returned tag in the requested context.
	 *
	 * @ticket 65819
	 */
	public function test_applies_filter_context() {
		add_filter(
			'edit_post_tag_name',
			static function ( $name ) {
				return $name . ' (edited)';
			}
		);

		$tag = get_tag( self::$tag_id, OBJECT, 'edit' );

		$this->assertInstanceOf( WP_Term::class, $tag );
		$this->assertSame( 'edit', $tag->filter );
		$this->assertSame( 'Test Tag (edited)', $tag->name );
	}

	/**
	 * Tests that a term ID belonging to another taxonomy returns null.
	 *
	 * @ticket 65819
	 */
	public function test_returns_null_for_term_in_another_taxonomy() {
		$category_id = self::factory()->category->create();

		$this->assertNull( get_tag( $category_id ) );
	}
}
