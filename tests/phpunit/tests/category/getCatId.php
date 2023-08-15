<?php
/**
 * @group taxonomy
 * @group category.php
 *
 * @covers ::get_cat_ID
 */
class Tests_Category_GetCatId extends WP_UnitTestCase {

	/**
	 * Validate get_cat_ID function
	 */
	public function test_get_cat_ID() {

		// Create test category.
		$testcat = self::factory()->category->create_and_get(
			array(
				'slug' => 'testcat',
				'name' => 'Test Category 1',
			)
		);

		// Validate.
		$this->assertSame( $testcat->term_id, get_cat_ID( $testcat->name ) );
		$this->assertSame( 0, get_cat_ID( 'NO CAT' ) );
		$this->assertSame( 0, get_cat_ID( 12 ) );

	}
}
