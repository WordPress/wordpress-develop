<?php
/**
 * @group taxonomy
 * @covers ::wp_has_multiple_terms
 */
class Tests_Taxonomy_WpHasMultipleTerms extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax', 'post' );
	}

	public function test_returns_false_when_no_terms_have_posts() {
		self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_returns_false_when_only_one_term_has_posts() {
		$terms   = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, $terms[0], 'wptests_tax' );

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_returns_true_when_two_terms_have_posts() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$p1    = self::factory()->post->create();
		$p2    = self::factory()->post->create();
		wp_set_object_terms( $p1, $terms[0], 'wptests_tax' );
		wp_set_object_terms( $p2, $terms[1], 'wptests_tax' );

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_cache_is_cleared_when_post_is_saved() {
		$cats = self::factory()->category->create_many( 2 );
		self::factory()->post->create( array( 'post_category' => array( $cats[0] ) ) );

		$this->assertFalse( wp_has_multiple_categories(), 'Precondition failed.' );

		self::factory()->post->create( array( 'post_category' => array( $cats[1] ) ) );

		$this->assertTrue( wp_has_multiple_categories() );
	}

	public function test_cache_is_cleared_when_terms_set_directly() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$p1    = self::factory()->post->create();
		$p2    = self::factory()->post->create();
		wp_set_object_terms( $p1, $terms[0], 'wptests_tax' );

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ), 'Precondition failed.' );

		wp_set_object_terms( $p2, $terms[1], 'wptests_tax' ); // No save_post here.

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_cache_is_cleared_when_term_is_deleted() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$p1    = self::factory()->post->create();
		$p2    = self::factory()->post->create();
		wp_set_object_terms( $p1, $terms[0], 'wptests_tax' );
		wp_set_object_terms( $p2, $terms[1], 'wptests_tax' );

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ), 'Precondition failed.' );

		wp_delete_term( $terms[1], 'wptests_tax' );

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_cache_is_cleared_when_object_terms_are_removed() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$p1    = self::factory()->post->create();
		$p2    = self::factory()->post->create();
		wp_set_object_terms( $p1, $terms[0], 'wptests_tax' );
		wp_set_object_terms( $p2, $terms[1], 'wptests_tax' );

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ), 'Precondition failed.' );

		wp_remove_object_terms( $p2, $terms[1], 'wptests_tax' );

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_cache_is_cleared_when_post_is_deleted() {
		$terms = self::factory()->term->create_many( 2, array( 'taxonomy' => 'wptests_tax' ) );
		$p1    = self::factory()->post->create();
		$p2    = self::factory()->post->create();
		wp_set_object_terms( $p1, $terms[0], 'wptests_tax' );
		wp_set_object_terms( $p2, $terms[1], 'wptests_tax' );

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ), 'Precondition failed.' );

		wp_delete_post( $p2, true ); // Permanent delete, bypasses the trash.

		$this->assertFalse( wp_has_multiple_terms( 'wptests_tax' ) );
	}

	public function test_filter_overrides_result() {
		add_filter( 'wp_has_multiple_terms', '__return_true' );

		$this->assertTrue( wp_has_multiple_terms( 'wptests_tax' ) );
	}
}
