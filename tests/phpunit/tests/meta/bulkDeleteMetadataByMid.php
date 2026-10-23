<?php

/**
 * @group meta
 *
 * @covers ::bulk_delete_metadata_by_mid
 */
class Tests_Meta_BulkDeleteMetadataByMid extends WP_UnitTestCase {

	/**
	 * @ticket 37702
	 */
	public function test_should_delete_given_meta_ids_only() {
		$mid1 = add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'post', 12345, 'baz', 'qux' );
		$mid3 = add_metadata( 'post', 54321, 'foo', 'bar' );
		$keep = add_metadata( 'post', 12345, 'keep', 'me' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2, $mid3 ) ) );

		$this->assertFalse( get_metadata_by_mid( 'post', $mid1 ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid2 ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid3 ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_delete_user_meta() {
		$mid1 = add_metadata( 'user', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'user', 12345, 'baz', 'qux' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'user', array( $mid1, $mid2 ) ) );

		$this->assertFalse( get_metadata_by_mid( 'user', $mid1 ) );
		$this->assertFalse( get_metadata_by_mid( 'user', $mid2 ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @dataProvider data_should_return_false_for_invalid_input
	 *
	 * @param string $meta_type Meta type.
	 * @param mixed  $meta_ids  Meta IDs.
	 */
	public function test_should_return_false_for_invalid_input( $meta_type, $meta_ids ) {
		$this->assertFalse( bulk_delete_metadata_by_mid( $meta_type, $meta_ids ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_return_false_for_invalid_input() {
		return array(
			'empty meta type'      => array( '', array( 1 ) ),
			'unknown meta type'    => array( 'foo', array( 1 ) ),
			'not an array'         => array( 'post', 1 ),
			'empty array'          => array( 'post', array() ),
			'invalid ids'          => array( 'post', array( 0, -1, 1.5, 'abc', null ) ),
			'non-existent meta id' => array( 'post', array( PHP_INT_MAX ) ),
		);
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_skip_invalid_ids_and_delete_valid_ones() {
		$mid = add_metadata( 'post', 12345, 'foo', 'bar' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( 0, 'abc', (string) $mid, $mid ) ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_use_one_select_and_one_delete_query() {
		global $wpdb;

		$mids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$mids[] = add_metadata( 'post', 12345, "foo_$i", 'bar' );
		}

		$num_queries = $wpdb->num_queries;
		bulk_delete_metadata_by_mid( 'post', $mids );

		$this->assertSame( 2, $wpdb->num_queries - $num_queries );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_clear_object_meta_cache() {
		add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid  = add_metadata( 'post', 54321, 'foo', 'bar' );
		$mids = array( $mid, add_metadata( 'post', 12345, 'baz', 'qux' ) );

		// Prime the caches.
		get_metadata( 'post', 12345 );
		get_metadata( 'post', 54321 );

		bulk_delete_metadata_by_mid( 'post', $mids );

		$this->assertSame( array( 'foo' => array( 'bar' ) ), get_metadata( 'post', 12345 ) );
		$this->assertSame( array(), get_metadata( 'post', 54321 ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_fire_actions_for_each_entry() {
		$mid1 = add_metadata( 'post', 12345, 'foo', array( 'bar' ) );
		$mid2 = add_metadata( 'post', 54321, 'baz', 'qux' );

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_post_meta', array( $delete, 'action' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $deleted, 'action' ), 10, 4 );

		bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2 ) );

		$expected = array(
			array( array( $mid1 ), 12345, 'foo', array( 'bar' ) ),
			array( array( $mid2 ), 54321, 'baz', 'qux' ),
		);

		$this->assertSame( $expected, $delete->get_args(), 'delete_post_meta args do not match.' );
		$this->assertSame( $expected, $deleted->get_args(), 'deleted_post_meta args do not match.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_fire_old_style_actions_for_each_entry() {
		$mid1 = add_metadata( 'comment', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'comment', 12345, 'baz', 'qux' );

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_commentmeta', array( $delete, 'action' ) );
		add_action( 'deleted_commentmeta', array( $deleted, 'action' ) );

		bulk_delete_metadata_by_mid( 'comment', array( $mid1, $mid2 ) );

		$expected = array( array( $mid1 ), array( $mid2 ) );

		$this->assertSame( $expected, $delete->get_args(), 'delete_commentmeta args do not match.' );
		$this->assertSame( $expected, $deleted->get_args(), 'deleted_commentmeta args do not match.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_short_circuit_filter_should_run_for_each_id() {
		$mid1 = add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'post', 12345, 'baz', 'qux' );

		$filter = new MockAction();
		add_filter( 'delete_post_metadata_by_mid', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'delete_post_metadata_by_mid',
			static function ( $check, $meta_id ) use ( $mid1 ) {
				return $mid1 === $meta_id ? true : $check;
			},
			10,
			2
		);

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2 ) ) );

		$this->assertSame( array( array( null, $mid1 ), array( null, $mid2 ) ), $filter->get_args(), 'Filter args do not match.' );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid1 ), 'Short-circuited meta should not be deleted.' );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid2 ), 'Other meta should be deleted.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_return_true_when_all_ids_are_short_circuited() {
		$mid = add_metadata( 'post', 12345, 'foo', 'bar' );

		add_filter( 'delete_post_metadata_by_mid', '__return_true' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid ) ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_post
	 */
	public function test_wp_delete_post_should_delete_all_post_meta() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'foo', 'bar' );
		add_post_meta( $post_id, 'baz', 'qux' );

		wp_delete_post( $post_id, true );

		$this->assertSame( array(), get_post_meta( $post_id ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_comment
	 */
	public function test_wp_delete_comment_should_delete_all_comment_meta() {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, 'foo', 'bar' );
		add_comment_meta( $comment_id, 'baz', 'qux' );

		wp_delete_comment( $comment_id, true );

		$this->assertSame( array(), get_comment_meta( $comment_id ) );
	}
}
