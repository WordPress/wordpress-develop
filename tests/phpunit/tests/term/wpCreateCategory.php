<?php

/**
 * @group taxonomy
 * @covers ::wp_create_category
 */
class Tests_Term_WpCreateCategory extends WP_UnitTestCase {

	/**
	 * Tests that a new category is created.
	 */
	public function test_create_category_when_not_existing() {
		$category_name = 'Foo';
		$category_id   = wp_create_category( $category_name );
		$this->assertGreaterThan( 0, $category_id, 'Expected category to be created.' );
		$term = get_term( $category_id );
		$this->assertSame( 'Foo', $term->name, 'Expected category name to match.' );
	}

	/**
	 * Tests error case.
	 */
	public function test_create_with_error() {
		$this->assertSame( 0, wp_create_category( '' ), 'Expected error.' );
	}

	/**
	 * Tests that an existing category is identified.
	 */
	public function test_create_category_when_already_exists() {
		$category_name = 'Foo';
		$category_id   = self::factory()->category->create( array( 'name' => $category_name ) );
		$this->assertSame( $category_id, wp_create_category( $category_name ), 'Expected existing category to be identified.' );
	}

	/**
	 * Tests that the existing category is identified when a child of a parent.
	 */
	public function test_create_category_with_parent_when_already_exists() {
		$category_name      = 'Foo';
		$parent_category_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$category_id        = self::factory()->category->create(
			array(
				'name'   => $category_name,
				'parent' => $parent_category_id,
			)
		);
		$this->assertSame( $category_id, wp_create_category( $category_name, $parent_category_id ), 'Expected existing category to be identified.' );
	}

	/**
	 * Tests that new root category is created when another of the same name exists as a child.
	 */
	public function test_create_root_category_when_exists_as_child_of_parent() {
		$category_name      = 'Foo';
		$parent_category_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$category_id        = self::factory()->category->create(
			array(
				'name'   => $category_name,
				'parent' => $parent_category_id,
			)
		);

		$new_category_id = wp_create_category( $category_name );
		$this->assertNotSame( $category_id, $new_category_id, 'Expected a new category to have been created.' );
		$this->assertGreaterThan( 0, $new_category_id, 'Expected category to have been created.' );
	}

	/**
	 * Tests that new child category is created when another of the same name exists at the root.
	 */
	public function test_create_child_category_when_exists_as_root() {
		$category_name      = 'Foo';
		$parent_category_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$category_id        = self::factory()->category->create( array( 'name' => $category_name ) );

		$new_category_id = wp_create_category( $category_name, $parent_category_id );
		$this->assertNotSame( $category_id, $new_category_id, 'Expected a new category to have been created.' );
		$this->assertGreaterThan( 0, $new_category_id, 'Expected category to have been created.' );
	}
}
