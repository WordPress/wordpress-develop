<?php

/**
 * @group taxonomy
 */
class Tests_Taxonomy_IsTaxonomyViewable extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		register_post_type( 'wptests_pt' );
		register_taxonomy( 'wptests_tax_viewable', 'wptests_pt', array( 'publicly_queryable' => true ) );
		register_taxonomy( 'wptests_tax_non_viewable', 'wptests_pt', array( 'publicly_queryable' => false ) );
	}

	/**
	 * @ticket 44466
	 */
	public function test_is_taxonomy_viewable_for_querable_taxonomy() {
		$this->assertTrue( is_taxonomy_viewable( 'wptests_tax_viewable' ) );
	}

	/**
	 * @ticket 44466
	 */
	public function test_is_taxonomy_viewable_for_non_querable_taxonomy() {
		$this->assertFalse( is_taxonomy_viewable( 'wptests_tax_non_viewable' ) );
	}

	/**
	 * @ticket 44466
	 */
	public function test_is_taxonomy_viewable_for_non_existing_taxonomy() {
		$this->assertFalse( is_taxonomy_viewable( 'wptests_tax_non_existing' ) );
	}

	/**
	 * @ticket 44466
	 */
	public function test_is_taxonomy_viewable_with_object_given() {
		$taxonomy = get_taxonomy( 'wptests_tax_viewable' );

		$this->assertTrue( is_taxonomy_viewable( $taxonomy ) );
	}
}
