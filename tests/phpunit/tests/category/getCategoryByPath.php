<?php
/**
 * @group taxonomy
 * @group category.php
 *
 * @covers ::get_category_by_path
 */
class Tests_Category_GetCategoryByPath extends WP_UnitTestCase {

	/**
	 * Validate get_category_by_path function
	 */
	public function test_get_category_by_path() {

		// Create test categories.
		$root_id           = self::factory()->category->create(
			array(
				'slug' => 'root',
			)
		);
		$root_cat_id       = self::factory()->category->create(
			array(
				'slug'   => 'cat',
				'parent' => $root_id,
			)
		);
		$root_cat_cat_id   = self::factory()->category->create(
			array(
				'slug'   => 'cat', // Note this is modified on create.
				'parent' => $root_cat_id,
			)
		);
		$root_path_id      = self::factory()->category->create(
			array(
				'slug'   => 'path',
				'parent' => $root_id,
			)
		);
		$root_path_cat_id  = self::factory()->category->create(
			array(
				'slug'   => 'cat', // Note this is modified on create.
				'parent' => $root_path_id,
			)
		);
		$root_level_id     = self::factory()->category->create(
			array(
				'slug'   => 'level-1',
				'parent' => $root_id,
			)
		);
		$root_level_cat_id = self::factory()->category->create(
			array(
				'slug'   => 'cat', // Note this is modified on create.
				'parent' => $root_level_id,
			)
		);

		// Validate full match.
		$ret_cat = get_category_by_path( '/root/level-1', true );
		$this->assertSame( $root_level_id, $ret_cat->term_id );
		$this->assertNull( get_category_by_path( 'level-1', true ) );
		$this->assertNull( get_category_by_path( 'nocat/nocat/', true ) );

		// Validate partial match.
		$ret_cat = get_category_by_path( 'level-1', false );
		$this->assertSame( $root_level_id, $ret_cat->term_id );
		$ret_cat = get_category_by_path( 'root/cat/level-1', false );
		$this->assertSame( $root_level_id, $ret_cat->term_id );
		$ret_cat = get_category_by_path( 'root$2Fcat%20%2Flevel-1', false );
		$this->assertSame( $root_level_id, $ret_cat->term_id );
		$this->assertNull( get_category_by_path( 'nocat/nocat/', false ) );
	}
}
