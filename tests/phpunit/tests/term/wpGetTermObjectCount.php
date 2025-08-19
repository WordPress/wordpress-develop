<?php

/**
 * Class Tests_Term_wpGetTermObjectCount
 *
 * @group taxonomy
 * @ticket 38280
 */
class Tests_Term_wpGetTermObjectCount extends WP_UnitTestCase {

	/**
	 * Test when a taxonomy is invalid.
	 */
	public function test_wp_get_term_object_count_invalid_taxonomy() {
		$actual = wp_get_term_object_count( 0, 'does-not-exist', 'post' );

		$this->assertWPError( $actual );
		$this->assertSame( 'invalid_taxonomy', $actual->get_error_code() );
	}

	/**
	 * Test when an object type is not in a taxonomy.
	 */
	public function test_wp_get_term_object_count_object_not_in_taxonomy() {
		$this->assertWPError( wp_get_term_object_count( 0, 'category', 'page' ) );
	}

	/**
	 * Test when an invalid term is passed.
	 */
	public function test_wp_get_term_object_count_invalid_term() {
		$actual = wp_get_term_object_count( 0, 'category', 'post' );

		$this->assertWPError( $actual );
		$this->assertSame( 'invalid_term', $actual->get_error_code() );
	}

	/**
	 * Test when a taxonomy belongs to a single object type. No term meta should be
	 * stored and the count property should be used.
	 */
	public function test_wp_get_term_object_count_single_object_type() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_type'     => 'post',
				'post_category' => array( $term_id ),
			)
		);

		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'category', 'post' ) );

		$term_object = get_term( $term_id, 'category' );
		$this->assertEquals( 1, $term_object->count );

		wp_remove_object_terms( $post_id, array( $term_id ), 'category' );

		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'category', 'post' ) );

		$term_object = get_term( $term_id, 'category' );
		$this->assertEquals( 0, $term_object->count );
	}

	/**
	 * Test when a taxonomy belongs to more than one object, and at least one object is not a post type.
	 */
	public function test_wp_get_term_object_count_non_post_type() {
		register_taxonomy(
			'wptests_tax',
			array(
				'user',
				'foo',
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$foo_id = 99999;

		wp_set_object_terms( $foo_id, $term_id, 'wptests_tax' );

		// 'foo' object doesn't have an update count callback, so the value is always 0.
		$count = wp_get_term_object_count( $term_id, 'wptests_tax', 'foo' );
		$this->assertSame( 0, $count );
	}

	/**
	 * Test when a taxonomy belongs to multiple object types.
	 */
	public function test_wp_get_term_object_count_multiple_object_types() {
		register_post_type( 'wptests_cpt' );
		register_taxonomy(
			'wptests_tax',
			array(
				'post',
				'wptests_cpt',
			)
		);

		$term_id        = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$post_id        = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$custom_post_id = self::factory()->post->create( array( 'post_type' => 'wptests_cpt' ) );

		// When a term has no relationships, no meta should be stored.
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'wptests_cpt' ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_wptests_cpt', true ) );

		wp_set_object_terms( $post_id, array( $term_id ), 'wptests_tax' );

		// Term has relationships, meta should be stored caching types counted and counts for each type > 0.
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'wptests_cpt' ) );
		$this->assertEquals( array( 'post', 'wptests_cpt' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_wptests_cpt', true ) );

		wp_set_object_terms( $custom_post_id, array( $term_id ), 'wptests_tax' );

		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'wptests_cpt' ) );
		$this->assertEquals( array( 'post', 'wptests_cpt' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_wptests_cpt', true ) );

		// Total count should be stored in the term's count property.
		$term_object = get_term( $term_id, 'wptests_tax' );
		$this->assertEquals( 2, $term_object->count );

		wp_remove_object_terms( $custom_post_id, array( $term_id ), 'wptests_tax' );

		// Object count cache should be removed.
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'wptests_cpt' ) );
		$this->assertEquals( array( 'post', 'wptests_cpt' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_wptests_cpt', true ) );

		wp_remove_object_terms( $post_id, array( $term_id ), 'wptests_tax' );

		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'wptests_cpt' ) );
		$this->assertEquals( array( 'post', 'wptests_cpt' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_wptests_cpt', true ) );
	}

	/**
	 * Term count must be generated on the fly (as for "legacy" terms).
	 */
	public function test_count_should_be_generated_for_legacy_terms() {
		register_post_type( 'wptests_cpt' );
		register_taxonomy(
			'wptests_tax',
			array(
				'post',
				'wptests_cpt',
			)
		);

		$t = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$p = self::factory()->post->create( array( 'post_type' => 'post' ) );

		wp_set_object_terms( $p, array( $t ), 'wptests_tax' );

		// Mimic "legacy" terms, which will not have the proper counts.
		delete_term_meta( $t, '_wp_object_count_post' );
		delete_term_meta( $t, '_wp_counted_object_types' );

		$found = wp_get_term_object_count( $t, 'wptests_tax', 'post' );
		$this->assertSame( 1, $found );
	}

	/**
	 * Test when a taxonomy belongs to multiple object types, one of which is attachments.
	 */
	public function test_wp_get_term_object_count_multiple_object_types_attachment() {
		register_taxonomy(
			'wptests_tax',
			array(
				'post',
				'attachment',
			)
		);

		$term_id       = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$post_id       = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post_id );

		// When a term has no relationships, no meta should be stored.
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'attachment' ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_attachment', true ) );

		wp_set_object_terms( $post_id, array( $term_id ), 'wptests_tax' );

		// Term has relationships, meta should be stored caching types counted and counts for each type > 0.
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'attachment' ) );
		$this->assertEquals( array( 'post', 'attachment' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_attachment', true ) );

		wp_set_object_terms( $attachment_id, array( $term_id ), 'wptests_tax' );

		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'attachment' ) );
		$this->assertEquals( array( 'post', 'attachment' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_attachment', true ) );

		// Total count should be stored in the term's count property.
		$term_object = get_term( $term_id, 'wptests_tax' );
		$this->assertEquals( 2, $term_object->count );

		wp_remove_object_terms( $post_id, array( $term_id ), 'wptests_tax' );

		// Object count cache should be removed.
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 1, wp_get_term_object_count( $term_id, 'wptests_tax', 'attachment' ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEquals( 1, get_term_meta( $term_id, '_wp_object_count_attachment', true ) );

		wp_remove_object_terms( $attachment_id, array( $term_id ), 'wptests_tax' );

		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'post' ) );
		$this->assertEquals( 0, wp_get_term_object_count( $term_id, 'wptests_tax', 'attachment' ) );
		$this->assertEquals( array( 'post', 'attachment' ), get_term_meta( $term_id, '_wp_counted_object_types', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_post', true ) );
		$this->assertEmpty( get_term_meta( $term_id, '_wp_object_count_attachment', true ) );
	}
}
