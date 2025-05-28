<?php

/**
 * @group meta
 */
class Tests_Meta_BulkAddMetadata extends WP_UnitTestCase {

	public function test_all_meta_fields_should_be_added() {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$meta = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);
		$latest_mid = (int) $wpdb->get_var( "SELECT MAX( meta_id ) FROM {$wpdb->postmeta}" );
		$add_post_metadata_calls = did_filter( 'add_post_metadata' );
		$add_post_meta_calls = did_action( 'add_post_meta' );
		$added_post_meta_calls = did_action( 'added_post_meta' );

		$result = bulk_add_metadata( 'post', $post_id, $meta );

		$actual_vals = array(
			'key1' => get_post_meta( $post_id, 'key1', true ),
			'key2' => get_post_meta( $post_id, 'key2', true ),
			'key3' => get_post_meta( $post_id, 'key3', true ),
		);
		$expected_vals = $meta;
		$expected_mids = array(
			'key1' => ( $latest_mid + 1 ),
			'key2' => ( $latest_mid + 2 ),
			'key3' => ( $latest_mid + 3 ),
		);

		$this->assertSame( $expected_vals, $actual_vals );
		$this->assertSame( $expected_mids, $result );
		$this->assertSame( $add_post_metadata_calls + 3, did_filter( 'add_post_metadata' ) );
		$this->assertSame( $add_post_meta_calls + 3, did_action( 'add_post_meta' ) );
		$this->assertSame( $added_post_meta_calls + 3, did_action( 'added_post_meta' ) );
	}

	public function test_correct_mids_should_be_returned_when_filter_is_in_place() {
		global $wpdb;

		add_filter(
			'add_post_metadata',
			static function ( $check, $object_id, $meta_key ) {
				return ( 'key2' === $meta_key ) ? 123456 : $check;
			},
			10,
			3
		);

		$post_id = self::factory()->post->create();
		$meta = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);
		$latest_mid = (int) $wpdb->get_var( "SELECT MAX( meta_id ) FROM {$wpdb->postmeta}" );
		$add_post_metadata_calls = did_filter( 'add_post_metadata' );
		$add_post_meta_calls = did_action( 'add_post_meta' );
		$added_post_meta_calls = did_action( 'added_post_meta' );

		$result = bulk_add_metadata( 'post', $post_id, $meta );

		$expected_vals = array(
			'key1' => '1',
			'key2' => '',
			'key3' => '3',
		);
		$actual_vals = array(
			'key1' => get_post_meta( $post_id, 'key1', true ),
			'key2' => get_post_meta( $post_id, 'key2', true ),
			'key3' => get_post_meta( $post_id, 'key3', true ),
		);
		$expected_mids = array(
			'key2' => 123456,
			'key1' => ( $latest_mid + 1 ),
			'key3' => ( $latest_mid + 2 ),
		);

		$this->assertSame( $expected_vals, $actual_vals );
		$this->assertSame( $expected_mids, $result );
		$this->assertSame( $add_post_metadata_calls + 3, did_filter( 'add_post_metadata' ) );
		$this->assertSame( $add_post_meta_calls + 2, did_action( 'add_post_meta' ) );
		$this->assertSame( $added_post_meta_calls + 2, did_action( 'added_post_meta' ) );
	}

}
