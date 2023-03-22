<?php
/**
 * @group taxonomy
 * @group category.php
 *
 * @covers ::get_cat_name
 */
class Tests_Category_GetCatName extends WP_UnitTestCase {

	/**
	 * Validate get_cat_name function
	 */
	public function test_get_cat_name() {

		// Create test category.
		$testcat = self::factory()->category->create_and_get(
			array(
				'slug' => 'testcat',
				'name' => 'Test Category 1',
			)
		);

		// Validate.
		$this->assertSame( $testcat->name, get_cat_name( $testcat->term_id ) );
		$this->assertSame( '', get_cat_name( -1 ) );
		$this->assertSame( '', get_cat_name( $testcat->term_id + 100 ) );

	}
}
