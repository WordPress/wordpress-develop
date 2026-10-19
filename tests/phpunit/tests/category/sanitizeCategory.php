<?php

/**
 * Tests for sanitize_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::sanitize_category
 */
class Tests_Category_SanitizeCategory extends WP_UnitTestCase {

	/**
	 * Tests that sanitize_category() sanitizes a category object, casts integer fields
	 * to non-negative integers, and sets the filter property to the default 'display' context.
	 *
	 * @ticket 65819
	 */
	public function test_sanitizes_category_object_with_default_context() {
		$category = (object) array(
			'term_id'          => '42',
			'name'             => 'Category Name',
			'description'      => 'Category Description',
			'slug'             => 'category-name',
			'count'            => '7',
			'parent'           => '-3',
			'term_group'       => '2',
			'term_taxonomy_id' => '55',
			'object_id'        => '100',
		);

		$result = sanitize_category( $category );

		$this->assertIsObject( $result );
		$this->assertSame( 42, $result->term_id );
		$this->assertSame( 7, $result->count );
		$this->assertSame( 0, $result->parent );
		$this->assertSame( 2, $result->term_group );
		$this->assertSame( 55, $result->term_taxonomy_id );
		$this->assertSame( 100, $result->object_id );
		$this->assertSame( 'display', $result->filter );
	}

	/**
	 * Tests that sanitize_category() sanitizes a category array, casts integer fields
	 * to non-negative integers, and sets the 'filter' key to the given context.
	 *
	 * @ticket 65819
	 */
	public function test_sanitizes_category_array() {
		$category = array(
			'term_id'          => '15',
			'name'             => '<b>Category</b>',
			'description'      => '<p>Description</p>',
			'slug'             => 'category',
			'count'            => '-5',
			'parent'           => '3',
			'term_group'       => '-1',
			'term_taxonomy_id' => '19',
			'object_id'        => '8',
		);

		$result = sanitize_category( $category, 'edit' );

		$this->assertIsArray( $result );
		$this->assertSame( 15, $result['term_id'] );
		$this->assertSame( esc_attr( '<b>Category</b>' ), $result['name'] );
		$this->assertSame( esc_html( '<p>Description</p>' ), $result['description'] );
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( 3, $result['parent'] );
		$this->assertSame( 0, $result['term_group'] );
		$this->assertSame( 19, $result['term_taxonomy_id'] );
		$this->assertSame( 8, $result['object_id'] );
		$this->assertSame( 'edit', $result['filter'] );
	}

	/**
	 * Tests that sanitize_category() passes the category ID and 'category' taxonomy
	 * to the context filters, defaulting term_id to 0 when omitted.
	 *
	 * @ticket 65819
	 */
	public function test_passes_category_id_and_taxonomy_to_filters() {
		$term_filter     = new MockAction();
		$category_filter = new MockAction();

		add_filter( 'edit_term_name', array( $term_filter, 'filter' ), 10, 3 );
		add_filter( 'edit_category_name', array( $category_filter, 'filter' ), 10, 2 );

		sanitize_category(
			array(
				'term_id' => 25,
				'name'    => 'News',
			),
			'edit'
		);

		// Also test when term_id is omitted (defaults to 0).
		sanitize_category(
			(object) array(
				'name' => 'Updates',
			),
			'edit'
		);

		$this->assertSame(
			array(
				array( 'News', 25, 'category' ),
				array( 'Updates', 0, 'category' ),
			),
			$term_filter->get_args()
		);
		$this->assertSame(
			array(
				array( 'News', 25 ),
				array( 'Updates', 0 ),
			),
			$category_filter->get_args()
		);
	}

	/**
	 * Tests that sanitize_category() does not add fields that are not set on the input.
	 *
	 * @ticket 65819
	 */
	public function test_does_not_add_unset_fields() {
		$object_result = sanitize_category( (object) array( 'name' => 'Only Name' ), 'raw' );
		$this->assertSame(
			array(
				'name'   => 'Only Name',
				'filter' => 'raw',
			),
			get_object_vars( $object_result )
		);

		$array_result = sanitize_category( array( 'name' => 'Only Name' ), 'raw' );
		$this->assertSame(
			array(
				'name'   => 'Only Name',
				'filter' => 'raw',
			),
			$array_result
		);
	}
}
