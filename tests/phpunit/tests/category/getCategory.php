<?php

/**
 * Tests for get_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_category
 */
class Tests_Category_GetCategory extends WP_UnitTestCase {

	/**
	 * Parent category ID.
	 *
	 * @var int
	 */
	private static $parent_id;

	/**
	 * Child category object.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Creates shared category fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_id = $factory->category->create(
			array(
				'name' => 'Parent Category',
				'slug' => 'parent-category',
			)
		);

		self::$category = $factory->category->create_and_get(
			array(
				'name'        => 'Child Category',
				'slug'        => 'child-category',
				'description' => 'Category description',
				'parent'      => self::$parent_id,
			)
		);
	}

	/**
	 * Tests that get_category() returns a WP_Term object with backwards-compatible properties by default.
	 *
	 * @ticket 65819
	 */
	public function test_returns_category_object_with_compat_properties() {
		$category = get_category( self::$category->term_id );

		$this->assertInstanceOf( WP_Term::class, $category );
		$this->assertSame( 'category', $category->taxonomy );
		$this->assertSame( 'raw', $category->filter );
		$this->assertSame( self::$category->term_id, $category->cat_ID );
		$this->assertSame( self::$category->count, $category->category_count );
		$this->assertSame( 'Category description', $category->category_description );
		$this->assertSame( 'Child Category', $category->cat_name );
		$this->assertSame( 'child-category', $category->category_nicename );
		$this->assertSame( self::$parent_id, $category->category_parent );
	}

	/**
	 * Tests that get_category() accepts a term object and populates backwards-compatible properties.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_category_object() {
		$raw_object = (object) self::$category->to_array();

		$category = get_category( $raw_object );

		$this->assertInstanceOf( WP_Term::class, $category );
		$this->assertSame( self::$category->term_id, $category->cat_ID );
		$this->assertSame( 'Child Category', $category->cat_name );
		$this->assertSame( 'child-category', $category->category_nicename );
		$this->assertSame( self::$parent_id, $category->category_parent );
	}

	/**
	 * Tests that ARRAY_A output returns an associative array with backwards-compatible alias keys.
	 *
	 * @ticket 65819
	 */
	public function test_returns_associative_array_with_compat_keys() {
		$category = get_category( self::$category->term_id, ARRAY_A );

		$this->assertIsArray( $category );
		$this->assertSame( self::$category->term_id, $category['term_id'] );
		$this->assertSame( self::$category->term_id, $category['cat_ID'] );
		$this->assertSame( self::$category->count, $category['category_count'] );
		$this->assertSame( 'Category description', $category['category_description'] );
		$this->assertSame( 'Child Category', $category['cat_name'] );
		$this->assertSame( 'child-category', $category['category_nicename'] );
		$this->assertSame( self::$parent_id, $category['category_parent'] );
	}

	/**
	 * Tests that ARRAY_N output returns a numerically indexed array of term values.
	 *
	 * @ticket 65819
	 */
	public function test_returns_numeric_array() {
		$category = get_category( self::$category->term_id, ARRAY_N );

		$this->assertIsArray( $category );
		$this->assertSame( array_values( self::$category->to_array() ), $category );
	}

	/**
	 * Tests that the filter parameter sanitizes the returned category in the requested context.
	 *
	 * @ticket 65819
	 */
	public function test_applies_filter_context() {
		add_filter(
			'edit_category_name',
			static function ( $name ) {
				return $name . ' (edited)';
			}
		);

		$category = get_category( self::$category->term_id, OBJECT, 'edit' );

		$this->assertInstanceOf( WP_Term::class, $category );
		$this->assertSame( 'edit', $category->filter );
		$this->assertSame( 'Child Category (edited)', $category->name );
		$this->assertSame( 'Child Category (edited)', $category->cat_name );
	}

	/**
	 * Tests that empty category values return an invalid_term WP_Error.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_category_values
	 *
	 * @param mixed $empty_value Empty category input.
	 */
	public function test_returns_wp_error_for_empty_category( $empty_value ) {
		$result = get_category( $empty_value );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_term', $result->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_category_values() {
		return array(
			'empty string' => array( '' ),
			'zero int'     => array( 0 ),
			'zero string'  => array( '0' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}

	/**
	 * Tests that a term ID belonging to another taxonomy returns null.
	 *
	 * @ticket 65819
	 */
	public function test_returns_null_for_term_in_another_taxonomy() {
		$tag_id = self::factory()->tag->create();

		$this->assertNull( get_category( $tag_id ) );
	}
}
