<?php

/**
 * @group taxonomy
 */
class Tests_Term_WpUniqueTermSlug extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax1', 'post', array( 'hierarchical' => false ) );
		register_taxonomy( 'wptests_tax2', 'post', array( 'hierarchical' => true ) );
	}

	public function test_unique_slug_should_be_unchanged() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);

		$actual = wp_unique_term_slug( 'bar', $term );
		$this->assertSame( 'bar', $actual );
	}

	public function test_nonunique_slug_in_different_taxonomy_should_be_unchanged() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax1' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar', $actual );
	}

	public function test_nonunique_slug_in_same_nonhierarchical_taxonomy_should_be_changed() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'bar',
				'slug'     => 'bar',
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax1' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-2', $actual );
	}

	public function test_nonunique_slug_in_same_hierarchical_taxonomy_with_same_parent_should_be_suffixed_with_parent_slug() {
		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'parent-term',
			)
		);

		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
				'parent'   => $parent,
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'foo',
				'slug'     => 'foo',
				'parent'   => $parent,
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax2' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-parent-term', $actual );
	}

	public function test_nonunique_slug_in_same_hierarchical_taxonomy_at_different_level_of_hierarchy_should_be_suffixed_with_number() {
		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'parent-term',
			)
		);

		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
				'parent'   => $parent,
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax2' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-2', $actual );
	}

	/**
	 * @ticket 46431
	 */
	public function test_duplicate_parent_suffixed_slug_should_get_numeric_suffix() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Animal',
				'slug'     => 'animal',
			)
		);

		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Dog',
				'slug'     => 'dog',
			)
		);

		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Cat',
				'slug'     => 'dog-animal',
				'parent'   => $t1,
			)
		);

		$t4 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Giraffe',
				'slug'     => 'giraffe',
				'parent'   => $t1,
			)
		);

		$term = get_term( $t4 );

		$slug = wp_unique_term_slug( 'dog', $term );

		$this->assertSame( 'dog-animal-2', $slug );
	}

	/**
	 * The `slug` column in the terms table holds 200 characters.
	 *
	 * @ticket 46010
	 */
	public function test_parent_suffixed_slug_should_be_truncated_to_the_column_length() {
		// This name percent-encodes to a 116 character slug, so appending it to itself overflows.
		$name = 'Категория на продукта';
		$slug = sanitize_title( $name );

		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => $name,
				'slug'     => $slug,
			)
		);

		$child = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Child',
				'slug'     => 'child',
				'parent'   => $parent,
			)
		);

		$actual = wp_unique_term_slug( $slug, $child );

		$this->assertLessThanOrEqual( 200, strlen( $actual ), 'The slug does not fit the column.' );
		$this->assertSame(
			0,
			preg_match( '/%(?![0-9a-fA-F]{2})/', $actual ),
			'The slug contains a truncated percent-encoded sequence.'
		);
		$this->assertTrue(
			wp_is_valid_utf8( urldecode( $actual ) ),
			'The slug does not decode to valid UTF-8.'
		);
	}

	/**
	 * @ticket 46010
	 */
	public function test_numeric_suffixed_slug_should_be_truncated_to_the_column_length() {
		$slug = str_repeat( 'a', 200 );

		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'Existing',
				'slug'     => $slug,
			)
		);

		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'New',
				'slug'     => 'new',
			)
		);

		$this->assertSame( str_repeat( 'a', 198 ) . '-2', wp_unique_term_slug( $slug, $term ) );
	}

	/**
	 * A two digit suffix needs one more character of headroom than a one digit suffix.
	 *
	 * @ticket 46010
	 */
	public function test_multi_digit_numeric_suffix_should_be_truncated_to_the_column_length() {
		$slug = str_repeat( 'a', 198 );

		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'Existing',
				'slug'     => $slug,
			)
		);

		// Occupy every one digit suffix, forcing the loop on to "-10".
		for ( $num = 2; $num <= 9; $num++ ) {
			self::factory()->term->create(
				array(
					'taxonomy' => 'wptests_tax1',
					'name'     => "Existing $num",
					'slug'     => $slug . "-$num",
				)
			);
		}

		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'New',
				'slug'     => 'new',
			)
		);

		$actual = wp_unique_term_slug( $slug, $term );

		$this->assertSame( str_repeat( 'a', 197 ) . '-10', $actual );
		$this->assertNull( term_exists( $actual ), 'The returned slug is already in use.' );
	}
}
