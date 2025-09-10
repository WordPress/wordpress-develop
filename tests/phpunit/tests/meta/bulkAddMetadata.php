<?php

/**
 * @group meta
 *
 * @covers bulk_add_metadata
 */
class Tests_Meta_BulkAddMetadata extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	/**
	 * @ticket 59269
	 */
	public function test_all_meta_fields_should_be_added() {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$meta    = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);

		$latest_mid              = (int) $wpdb->get_var( "SELECT MAX( meta_id ) FROM {$wpdb->postmeta}" );
		$add_post_metadata_calls = did_filter( 'add_post_metadata' );
		$add_post_meta_calls     = did_action( 'add_post_meta' );
		$added_post_meta_calls   = did_action( 'added_post_meta' );

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

	/**
	 * @ticket 59269
	 */
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
		$meta    = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);

		$latest_mid              = (int) $wpdb->get_var( "SELECT MAX( meta_id ) FROM {$wpdb->postmeta}" );
		$add_post_metadata_calls = did_filter( 'add_post_metadata' );
		$add_post_meta_calls     = did_action( 'add_post_meta' );
		$added_post_meta_calls   = did_action( 'added_post_meta' );

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

	/**
	 * @ticket 59269
	 */
	public function test_slashed_data_should_be_handled_correctly() {
		$post_id = self::factory()->post->create();
		$meta    = array(
			'key1' => addslashes( self::SLASH_1 ),
			'key2' => addslashes( self::SLASH_2 ),
			'key3' => addslashes( self::SLASH_3 ),
			'key4' => addslashes( self::SLASH_4 ),
			'key5' => addslashes( self::SLASH_5 ),
			'key6' => addslashes( self::SLASH_6 ),
			'key7' => addslashes( self::SLASH_7 ),
		);

		$result = bulk_add_metadata( 'post', $post_id, $meta );

		$actual_vals = array(
			'key1' => get_post_meta( $post_id, 'key1', true ),
			'key2' => get_post_meta( $post_id, 'key2', true ),
			'key3' => get_post_meta( $post_id, 'key3', true ),
			'key4' => get_post_meta( $post_id, 'key4', true ),
			'key5' => get_post_meta( $post_id, 'key5', true ),
			'key6' => get_post_meta( $post_id, 'key6', true ),
			'key7' => get_post_meta( $post_id, 'key7', true ),
		);

		$expected_vals = array(
			'key1' => self::SLASH_1,
			'key2' => self::SLASH_2,
			'key3' => self::SLASH_3,
			'key4' => self::SLASH_4,
			'key5' => self::SLASH_5,
			'key6' => self::SLASH_6,
			'key7' => self::SLASH_7,
		);

		$this->assertCount( 7, $result );
		$this->assertSame( $expected_vals, $actual_vals );
	}
}
