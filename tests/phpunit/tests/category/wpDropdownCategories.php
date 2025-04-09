<?php
/**
 * @group taxonomy
 * @group category
 *
 * @covers ::wp_dropdown_categories
 */
class Tests_Category_WpDropdownCategories extends WP_UnitTestCase {
	/**
	 * @ticket 30306
	 */
	public function test_wp_dropdown_categories_value_field_should_default_to_term_id() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		// Get the default functionality of wp_dropdown_categories().
		$dropdown_default = wp_dropdown_categories(
			array(
				'echo'       => 0,
				'hide_empty' => 0,
			)
		);

		// Test to see if it returns the default with the category ID.
		$this->assertStringContainsString( 'value="' . $cat_id . '"', $dropdown_default );
	}

	/**
	 * @ticket 30306
	 */
	public function test_wp_dropdown_categories_value_field_term_id() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		// Get the default functionality of wp_dropdown_categories().
		$found = wp_dropdown_categories(
			array(
				'echo'        => 0,
				'hide_empty'  => 0,
				'value_field' => 'term_id',
			)
		);

		// Test to see if it returns the default with the category ID.
		$this->assertStringContainsString( 'value="' . $cat_id . '"', $found );
	}

	/**
	 * @ticket 30306
	 */
	public function test_wp_dropdown_categories_value_field_slug() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		// Get the default functionality of wp_dropdown_categories().
		$found = wp_dropdown_categories(
			array(
				'echo'        => 0,
				'hide_empty'  => 0,
				'value_field' => 'slug',
			)
		);

		// Test to see if it returns the default with the category slug.
		$this->assertStringContainsString( 'value="test_category"', $found );
	}

	/**
	 * @ticket 30306
	 */
	public function test_wp_dropdown_categories_value_field_should_fall_back_on_term_id_when_an_invalid_value_is_provided() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		// Get the default functionality of wp_dropdown_categories().
		$found = wp_dropdown_categories(
			array(
				'echo'        => 0,
				'hide_empty'  => 0,
				'value_field' => 'foo',
			)
		);

		// Test to see if it returns the default with the category slug.
		$this->assertStringContainsString( 'value="' . $cat_id . '"', $found );
	}

	/**
	 * @ticket 32330
	 */
	public function test_wp_dropdown_categories_selected_should_respect_custom_value_field() {
		$c1 = self::factory()->category->create(
			array(
				'name' => 'Test Category 1',
				'slug' => 'test_category_1',
			)
		);

		$c2 = self::factory()->category->create(
			array(
				'name' => 'Test Category 2',
				'slug' => 'test_category_2',
			)
		);

		$found = wp_dropdown_categories(
			array(
				'echo'        => 0,
				'hide_empty'  => 0,
				'value_field' => 'slug',
				'selected'    => 'test_category_2',
			)
		);

		$this->assertStringContainsString( 'value="test_category_2" selected="selected"', $found );
	}

	/**
	 * @ticket 33452
	 */
	public function test_wp_dropdown_categories_show_option_all_should_be_selected_if_no_selected_value_is_explicitly_passed_and_value_field_does_not_have_string_values() {
		$cats = self::factory()->category->create_many( 3 );

		$found = wp_dropdown_categories(
			array(
				'echo'            => 0,
				'hide_empty'      => 0,
				'show_option_all' => 'Foo',
				'value_field'     => 'slug',
			)
		);

		$this->assertStringContainsString( "value='0' selected='selected'", $found );

		foreach ( $cats as $cat ) {
			$_cat = get_term( $cat, 'category' );
			$this->assertStringNotContainsString( 'value="' . $_cat->slug . '" selected="selected"', $found );
		}
	}

	/**
	 * @ticket 33452
	 */
	public function test_wp_dropdown_categories_show_option_all_should_be_selected_if_selected_value_of_0_string_is_explicitly_passed_and_value_field_does_not_have_string_values() {
		$cats = self::factory()->category->create_many( 3 );

		$found = wp_dropdown_categories(
			array(
				'echo'            => 0,
				'hide_empty'      => 0,
				'show_option_all' => 'Foo',
				'value_field'     => 'slug',
				'selected'        => '0',
			)
		);

		$this->assertStringContainsString( "value='0' selected='selected'", $found );

		foreach ( $cats as $cat ) {
			$_cat = get_term( $cat, 'category' );
			$this->assertStringNotContainsString( 'value="' . $_cat->slug . '" selected="selected"', $found );
		}
	}

	/**
	 * @ticket 31909
	 */
	public function test_required_true_should_add_required_attribute() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		$args                = array(
			'show_option_none'  => __( 'Select one', 'text-domain' ),
			'option_none_value' => '',
			'required'          => true,
			'hide_empty'        => 0,
			'echo'              => 0,
		);
		$dropdown_categories = wp_dropdown_categories( $args );

		// Test to see if it contains the "required" attribute.
		$this->assertMatchesRegularExpression( '/<select[^>]+required/', $dropdown_categories );
	}

	/**
	 * @ticket 31909
	 */
	public function test_required_false_should_omit_required_attribute() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		$args                = array(
			'show_option_none'  => __( 'Select one', 'text-domain' ),
			'option_none_value' => '',
			'required'          => false,
			'hide_empty'        => 0,
			'echo'              => 0,
		);
		$dropdown_categories = wp_dropdown_categories( $args );

		// Test to see if it contains the "required" attribute.
		$this->assertDoesNotMatchRegularExpression( '/<select[^>]+required/', $dropdown_categories );
	}

	/**
	 * @ticket 31909
	 */
	public function test_required_should_default_to_false() {
		// Create a test category.
		$cat_id = self::factory()->category->create(
			array(
				'name' => 'Test Category',
				'slug' => 'test_category',
			)
		);

		$args                = array(
			'show_option_none'  => __( 'Select one', 'text-domain' ),
			'option_none_value' => '',
			'hide_empty'        => 0,
			'echo'              => 0,
		);
		$dropdown_categories = wp_dropdown_categories( $args );

		// Test to see if it contains the "required" attribute.
		$this->assertDoesNotMatchRegularExpression( '/<select[^>]+required/', $dropdown_categories );
	}

	/**
	 * Test that the category dropdown is generated correctly, including the level classes.
	 *
	 * @ticket 60910
	 */
	public function test_wp_dropdown_categories_hierarchical_should_have_level_classes() {
		$parent = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent,
			)
		);
		$grandchild = self::factory()->category->create(
			array(
				'name'   => 'Grandchild',
				'parent' => $child,
			)
		);

		$found = wp_dropdown_categories(
			array(
				'echo'         => 0,
				'hide_empty'   => 0,
				'hierarchical' => 1,
				'orderby'      => 'name', // Ensure consistent order for testing.
				'order'        => 'ASC',
			)
		);

		// Check for level classes at different depths.
		$this->assertStringContainsString( 'class="level-0"', $found, 'Level 0 class missing.' );
		$this->assertStringContainsString( 'class="level-1"', $found, 'Level 1 class missing.' );
		$this->assertStringContainsString( 'class="level-2"', $found, 'Level 2 class missing.' );

		// Check specific options.
		$this->assertMatchesRegularExpression( '/<option[^>]+class="level-0"[^>]*value="' . $parent . '"/', $found, 'Parent level class incorrect.' );
		$this->assertMatchesRegularExpression( '/<option[^>]+class="level-1"[^>]*value="' . $child . '"/', $found, 'Child level class incorrect.' );
		$this->assertMatchesRegularExpression( '/<option[^>]+class="level-2"[^>]*value="' . $grandchild . '"/', $found, 'Grandchild level class incorrect.' );
	}

	/**
	 * @ticket 60910
	 */
	public function test_wp_dropdown_categories_hierarchical_should_have_custom_select_class() {
		self::factory()->category->create(); // Ensure there's at least one category.

		$found = wp_dropdown_categories(
			array(
				'echo'         => 0,
				'hide_empty'   => 0,
				'hierarchical' => 1,
				'class'        => 'some-other-class', // Test appending.
			)
		);

		// Check that the select tag contains the specific hierarchical class along with any others.
		$this->assertMatchesRegularExpression( '/<select[^>]+class="[^"]*some-other-class[^"]*category-parent-hierarchical-select[^"]*"/', $found );
	}

	/**
	 * @ticket 60910
	 */
	public function test_wp_dropdown_categories_non_hierarchical_should_not_have_custom_select_class() {
		self::factory()->category->create(); // Ensure there's at least one category.

		$found = wp_dropdown_categories(
			array(
				'echo'         => 0,
				'hide_empty'   => 0,
				'hierarchical' => 0, // Explicitly non-hierarchical.
				'class'        => 'postform some-other-class',
			)
		);

		// Check that the select tag does NOT contain the specific hierarchical class.
		$this->assertStringNotContainsString( 'category-parent-hierarchical-select', $found );
		// Check that other classes are still present.
		$this->assertMatchesRegularExpression( '/<select[^>]+class="[^"]*postform[^"]*"/', $found );
		$this->assertMatchesRegularExpression( '/<select[^>]+class="[^"]*some-other-class[^"]*"/', $found );
	}
}
