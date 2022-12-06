<?php
/**
 * @group taxonomy
 * @group category.php
 *
 * @covers ::get_all_category_ids
 */
class Tests_Category_GetAllCategoryIds extends WP_UnitTestCase {

	/**
	 * Validate get_all_category_ids
	 *
	 * @expectedDeprecated get_all_category_ids
	 */
	public function test_get_all_category_ids() {
		// Ccreate categories.
		self::factory()->category->create_many( 2 );

		// Create new taxonomy to ensure not included.
		register_taxonomy( 'test_tax_cat', 'post' );

		wp_insert_term( 'test1', 'test_tax_cat' );
		$cat_ids = get_all_category_ids();

		_unregister_taxonomy( 'test_tax_cat' );

		// Validate length is 1 + created due to uncategorized.
		$this->assertCount( 3, $cat_ids );
	}
}
