<?php
/**
 * @group taxonomy
 * @group category.php
 *
 * @covers ::_make_cat_compat
 */
class Tests_Category_MakeCatCompat extends WP_UnitTestCase {

	/**
	 * Validate _make_cat_compat function
	 */
	public function test__make_cat_compat() {

		// Create test categories and array representations.
		$testcat_array            = array(
			'slug'        => 'testmcc',
			'name'        => 'Test MCC',
			'description' => 'Category Test',
		);
		$testcat                  = self::factory()->category->create_and_get( $testcat_array );
		$testcat_array['term_id'] = $testcat->term_id;

		$testcat2_array            = array(
			'slug'        => 'testmcc',
			'name'        => 'Test MCC',
			'description' => 'Category Test',
			'parent'      => $testcat->term_id,
		);
		$testcat2                  = self::factory()->category->create_and_get( $testcat2_array );
		$testcat2_array['term_id'] = $testcat2->term_id;

		// Unset properties to enable validation of object.
		unset( $testcat->cat_ID );
		unset( $testcat->category_count );
		unset( $testcat->category_description );
		unset( $testcat->cat_name );
		unset( $testcat->category_nicename );
		unset( $testcat->category_parent );

		unset( $testcat2->cat_ID );
		unset( $testcat2->category_count );
		unset( $testcat2->category_description );
		unset( $testcat2->cat_name );
		unset( $testcat2->category_nicename );
		unset( $testcat2->category_parent );

		// Make compatible.
		_make_cat_compat( $testcat );
		_make_cat_compat( $testcat2 );
		_make_cat_compat( $testcat_array );
		_make_cat_compat( $testcat2_array );

		// Validate compatibility object.
		$this->assertSame( $testcat->cat_ID, $testcat->term_id );
		$this->assertSame( $testcat->category_count, $testcat->count );
		$this->assertSame( $testcat->category_description, $testcat->description );
		$this->assertSame( $testcat->cat_name, $testcat->name );
		$this->assertSame( $testcat->category_nicename, $testcat->slug );
		$this->assertSame( $testcat->category_parent, $testcat->parent );

		// Validate compatibility object with parent.
		$this->assertSame( $testcat->cat_ID, $testcat->term_id );
		$this->assertSame( $testcat->category_count, $testcat->count );
		$this->assertSame( $testcat->category_description, $testcat->description );
		$this->assertSame( $testcat->cat_name, $testcat->name );
		$this->assertSame( $testcat->category_nicename, $testcat->slug );
		$this->assertSame( $testcat->category_parent, $testcat->parent );

		// Validate compatibility array.
		$this->assertSame( $testcat_array['cat_ID'], $testcat_array['term_id'] );
		$this->assertSame( $testcat_array['category_count'], $testcat_array['count'] );
		$this->assertSame( $testcat_array['category_description'], $testcat_array['description'] );
		$this->assertSame( $testcat_array['cat_name'], $testcat_array['name'] );
		$this->assertSame( $testcat_array['category_nicename'], $testcat_array['slug'] );
		$this->assertSame( $testcat_array['category_parent'], $testcat_array['parent'] );

		// Validate compatibility array with parent.
		$this->assertSame( $testcat_array['cat_ID'], $testcat_array['term_id'] );
		$this->assertSame( $testcat_array['category_count'], $testcat_array['count'] );
		$this->assertSame( $testcat_array['category_description'], $testcat_array['description'] );
		$this->assertSame( $testcat_array['cat_name'], $testcat_array['name'] );
		$this->assertSame( $testcat_array['category_nicename'], $testcat_array['slug'] );
		$this->assertSame( $testcat_array['category_parent'], $testcat_array['parent'] );
	}
}
