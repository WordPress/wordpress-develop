<?php

/**
 * Tests for the `allow_multiple_terms` taxonomy argument.
 *
 * @group taxonomy
 *
 * @ticket 64991
 */
class Tests_Taxonomy_AllowMultipleTerms extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		register_taxonomy(
			'wptests_single_h',
			'post',
			array(
				'hierarchical'         => true,
				'allow_multiple_terms' => false,
			)
		);

		register_taxonomy(
			'wptests_single_flat',
			'post',
			array(
				'hierarchical'         => false,
				'allow_multiple_terms' => false,
			)
		);
	}

	public function tear_down() {
		unregister_taxonomy( 'wptests_single_h' );
		unregister_taxonomy( 'wptests_single_flat' );
		parent::tear_down();
	}

	/**
	 * @covers WP_Taxonomy::set_props
	 */
	public function test_allow_multiple_terms_defaults_to_true() {
		register_taxonomy( 'wptests_default', 'post' );
		$taxonomy = get_taxonomy( 'wptests_default' );
		unregister_taxonomy( 'wptests_default' );

		$this->assertTrue( $taxonomy->allow_multiple_terms );
		$this->assertSame( 'post_tags_meta_box', $taxonomy->meta_box_cb );
	}

	/**
	 * @covers WP_Taxonomy::set_props
	 *
	 * @dataProvider data_single_term_taxonomies
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public function test_single_term_taxonomy_uses_single_term_callbacks( $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );

		$this->assertFalse( $tax->allow_multiple_terms );
		$this->assertSame( 'post_single_term_meta_box', $tax->meta_box_cb );
		$this->assertSame( 'taxonomy_meta_box_sanitize_cb_single_term', $tax->meta_box_sanitize_cb );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_single_term_taxonomies() {
		return array(
			'hierarchical'     => array( 'wptests_single_h' ),
			'non-hierarchical' => array( 'wptests_single_flat' ),
		);
	}

	/**
	 * @covers WP_Taxonomy::set_props
	 */
	public function test_custom_meta_box_cb_is_not_overridden() {
		register_taxonomy(
			'wptests_custom_cb',
			'post',
			array(
				'allow_multiple_terms' => false,
				'meta_box_cb'          => 'post_categories_meta_box',
			)
		);
		$taxonomy = get_taxonomy( 'wptests_custom_cb' );
		unregister_taxonomy( 'wptests_custom_cb' );

		$this->assertSame( 'post_categories_meta_box', $taxonomy->meta_box_cb );
		$this->assertSame( 'taxonomy_meta_box_sanitize_cb_checkboxes', $taxonomy->meta_box_sanitize_cb );
	}

	/**
	 * @covers ::wp_set_object_terms
	 */
	public function test_wp_set_object_terms_keeps_only_last_term() {
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_single_h' ) );

		wp_set_object_terms( self::$post_id, $terms, 'wptests_single_h' );

		$this->assertSame(
			array( $terms[2] ),
			wp_get_object_terms( self::$post_id, 'wptests_single_h', array( 'fields' => 'ids' ) )
		);
	}

	/**
	 * @covers ::wp_set_object_terms
	 */
	public function test_wp_set_object_terms_append_replaces_existing_term() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_single_h' ) );

		wp_set_object_terms( self::$post_id, $terms[0], 'wptests_single_h' );
		wp_set_object_terms( self::$post_id, $terms[1], 'wptests_single_h', true );

		$this->assertSame(
			array( $terms[1] ),
			wp_get_object_terms( self::$post_id, 'wptests_single_h', array( 'fields' => 'ids' ) )
		);
	}

	/**
	 * @covers ::wp_set_object_terms
	 */
	public function test_wp_set_object_terms_is_unchanged_for_multi_term_taxonomies() {
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'category' ) );

		wp_set_object_terms( self::$post_id, $terms, 'category' );

		$this->assertSameSets(
			$terms,
			wp_get_object_terms( self::$post_id, 'category', array( 'fields' => 'ids' ) )
		);
	}

	/**
	 * @covers ::taxonomy_meta_box_sanitize_cb_single_term
	 *
	 * @dataProvider data_sanitize_cb_single_term
	 *
	 * @param mixed $input    Raw submitted value.
	 * @param int[] $expected Expected sanitized value.
	 */
	public function test_sanitize_cb_single_term( $input, $expected ) {
		$this->assertSame( $expected, taxonomy_meta_box_sanitize_cb_single_term( 'wptests_single_h', $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_sanitize_cb_single_term() {
		return array(
			'sentinel only'            => array( array( '0' ), array() ),
			'sentinel and a term'      => array( array( '0', '12' ), array( 12 ) ),
			'multiple terms, last one' => array( array( '0', '12', '15' ), array( 15 ) ),
			'scalar value'             => array( '7', array( 7 ) ),
			'non-numeric junk'         => array( array( 'abc', '-3' ), array() ),
		);
	}

	/**
	 * Quick Edit preselects from the inline data, so it must expose the current
	 * term ID for single-term taxonomies, including after an inline save.
	 *
	 * @covers ::get_inline_data
	 *
	 * @dataProvider data_single_term_taxonomies
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public function test_get_inline_data_exposes_single_term_id( $taxonomy ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$term_id = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		wp_set_object_terms( self::$post_id, $term_id, $taxonomy );

		$expected = '<div class="post_single_term" id="' . $taxonomy . '_' . self::$post_id . '">' . $term_id . '</div>';

		$output = get_echo( 'get_inline_data', array( get_post( self::$post_id ) ) );

		$this->assertStringContainsString( $expected, $output );
		$this->assertStringNotContainsString( 'class="post_category" id="' . $taxonomy . '_', $output );
		$this->assertStringNotContainsString( 'class="tags_input" id="' . $taxonomy . '_', $output );
	}

	/**
	 * @covers ::get_inline_data
	 */
	public function test_get_inline_data_exposes_zero_when_no_term_is_set() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = get_echo( 'get_inline_data', array( get_post( self::$post_id ) ) );

		$this->assertStringContainsString(
			'<div class="post_single_term" id="wptests_single_h_' . self::$post_id . '">0</div>',
			$output
		);
	}
}
