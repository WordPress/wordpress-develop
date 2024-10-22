<?php

/**
 * @group meta
 */
class Tests_Meta extends WP_UnitTestCase {
	protected $updated_mids = array();

	/**
	 * @var \WP_User
	 */
	private $author;

	private $meta_id;
	private $delete_meta_id;
	protected $error_query_regexp;

	public function set_up() {
		parent::set_up();
		$this->author             = new WP_User( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->meta_id            = add_metadata( 'user', $this->author->ID, 'meta_key', 'meta_value' );
		$this->delete_meta_id     = add_metadata( 'user', $this->author->ID, 'delete_meta_key', 'delete_meta_value' );
		$this->error_query_regexp = null;
	}

	public function meta_sanitize_cb( $meta_value, $meta_key, $meta_type ) {
		return 'sanitized';
	}

	public function test_sanitize_meta() {
		$meta = sanitize_meta( 'some_meta', 'unsanitized', 'post' );
		$this->assertSame( 'unsanitized', $meta );

		register_meta( 'post', 'some_meta', array( $this, 'meta_sanitize_cb' ) );
		$meta = sanitize_meta( 'some_meta', 'unsanitized', 'post' );
		$this->assertSame( 'sanitized', $meta );
	}

	public function test_delete_metadata_by_mid() {
		// Let's try and delete a non-existing ID, non existing meta.
		$this->assertFalse( delete_metadata_by_mid( 'user', 0 ) );
		$this->assertFalse( delete_metadata_by_mid( 'non_existing_meta', $this->delete_meta_id ) );

		// Now let's delete the real meta data.
		$this->assertTrue( delete_metadata_by_mid( 'user', $this->delete_meta_id ) );

		// And make sure it's been deleted.
		$this->assertFalse( get_metadata_by_mid( 'user', $this->delete_meta_id ) );

		// Make sure the caches are cleared.
		$this->assertFalse( (bool) get_user_meta( $this->author->ID, 'delete_meta_key' ) );
	}

	public function test_update_metadata_by_mid() {
		// Setup.
		$meta = get_metadata_by_mid( 'user', $this->meta_id );

		// Update the meta value.
		$this->assertTrue( update_metadata_by_mid( 'user', $this->meta_id, 'meta_new_value' ) );
		$meta = get_metadata_by_mid( 'user', $this->meta_id );
		$this->assertSame( 'meta_new_value', $meta->meta_value );

		// Update the meta value.
		$this->assertTrue( update_metadata_by_mid( 'user', $this->meta_id, 'meta_new_value', 'meta_new_key' ) );
		$meta = get_metadata_by_mid( 'user', $this->meta_id );
		$this->assertSame( 'meta_new_key', $meta->meta_key );

		// Update the key and value.
		$this->assertTrue( update_metadata_by_mid( 'user', $this->meta_id, 'meta_value', 'meta_key' ) );
		$meta = get_metadata_by_mid( 'user', $this->meta_id );
		$this->assertSame( 'meta_key', $meta->meta_key );
		$this->assertSame( 'meta_value', $meta->meta_value );

		// Update the value that has to be serialized.
		$this->assertTrue( update_metadata_by_mid( 'user', $this->meta_id, array( 'first', 'second' ) ) );
		$meta = get_metadata_by_mid( 'user', $this->meta_id );
		$this->assertSame( array( 'first', 'second' ), $meta->meta_value );

		// Let's try some invalid meta data.
		$this->assertFalse( update_metadata_by_mid( 'user', 0, 'meta_value' ) );
		$this->assertFalse( update_metadata_by_mid( 'user', $this->meta_id, 'meta_value', array( 'invalid', 'key' ) ) );

		// Let's see if caches get cleared after updates.
		$meta  = get_metadata_by_mid( 'user', $this->meta_id );
		$first = get_user_meta( $meta->user_id, $meta->meta_key );
		$this->assertTrue( update_metadata_by_mid( 'user', $this->meta_id, 'other_meta_value' ) );
		$second = get_user_meta( $meta->user_id, $meta->meta_key );
		$this->assertFalse( $first === $second );
	}

	/**
	 * @ticket 11683
	 */
	public function test_update_metadata_hooks_for_multiple_updated_rows() {
		add_metadata( 'post', 1, 'test_key', 'value_1' );
		add_metadata( 'post', 1, 'test_key', 'value_2' );
		add_action( 'update_post_meta', array( $this, 'updated_meta' ) );
		add_action( 'update_postmeta', array( $this, 'updated_meta' ) );
		add_action( 'updated_post_meta', array( $this, 'updated_meta' ) );
		add_action( 'updated_postmeta', array( $this, 'updated_meta' ) );

		update_metadata( 'post', 1, 'test_key', 'value_3' );

		remove_action( 'update_post_meta', array( $this, 'updated_meta' ) );
		remove_action( 'update_postmeta', array( $this, 'updated_meta' ) );
		remove_action( 'updated_post_meta', array( $this, 'updated_meta' ) );
		remove_action( 'updated_postmeta', array( $this, 'updated_meta' ) );

		$found              = $this->updated_mids;
		$this->updated_mids = array();

		foreach ( $found as $action => $mids ) {
			$this->assertCount( 2, $mids );
		}
	}

	public function test_metadata_exists() {
		$this->assertFalse( metadata_exists( 'user', $this->author->ID, 'foobarbaz' ) );
		$this->assertTrue( metadata_exists( 'user', $this->author->ID, 'meta_key' ) );
		$this->assertFalse( metadata_exists( 'user', 1234567890, 'foobarbaz' ) );
		$this->assertFalse( metadata_exists( 'user', 1234567890, 'meta_key' ) );
	}

	/**
	 * @ticket 22746
	 */
	public function test_metadata_exists_with_filter() {
		// Let's see if it returns the correct value when adding a filter.
		add_filter( 'get_user_metadata', '__return_zero' );
		$this->assertFalse( metadata_exists( 'user', $this->author->ID, 'meta_key' ) ); // Existing meta key.
		$this->assertFalse( metadata_exists( 'user', 1234567890, 'meta_key' ) );
		remove_filter( 'get_user_metadata', '__return_zero' );
	}

	/**
	 * @ticket 18158
	 */
	public function test_user_metadata_not_exists() {
		$u = get_users(
			array(
				'meta_query' => array(
					array(
						'key'     => 'meta_key',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$this->assertCount( 1, $u );

		// User found is not locally defined author (it's the admin).
		$this->assertNotEquals( $this->author->user_login, $u[0]->user_login );

		// Test EXISTS and NOT EXISTS together, no users should be found.
		$this->assertCount(
			0,
			get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => 'meta_key',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'delete_meta_key',
							'compare' => 'EXISTS',
						),
					),
				)
			)
		);

		$this->assertCount(
			2,
			get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => 'non_existing_meta',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);

		delete_metadata( 'user', $this->author->ID, 'meta_key' );

		$this->assertCount(
			2,
			get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => 'meta_key',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);
	}

	public function test_metadata_slashes() {
		$key       = __FUNCTION__;
		$value     = 'Test\\singleslash';
		$expected  = 'Testsingleslash';
		$value2    = 'Test\\\\doubleslash';
		$expected2 = 'Test\\doubleslash';
		$this->assertFalse( metadata_exists( 'user', $this->author->ID, $key ) );
		$this->assertFalse( delete_metadata( 'user', $this->author->ID, $key ) );
		$this->assertSame( '', get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertIsInt( add_metadata( 'user', $this->author->ID, $key, $value ) );
		$this->assertSame( $expected, get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertTrue( delete_metadata( 'user', $this->author->ID, $key ) );
		$this->assertSame( '', get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertIsInt( update_metadata( 'user', $this->author->ID, $key, $value ) );
		$this->assertSame( $expected, get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertTrue( update_metadata( 'user', $this->author->ID, $key, 'blah' ) );
		$this->assertSame( 'blah', get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertTrue( delete_metadata( 'user', $this->author->ID, $key ) );
		$this->assertSame( '', get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertFalse( metadata_exists( 'user', $this->author->ID, $key ) );

		// Test overslashing.
		$this->assertIsInt( add_metadata( 'user', $this->author->ID, $key, $value2 ) );
		$this->assertSame( $expected2, get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertTrue( delete_metadata( 'user', $this->author->ID, $key ) );
		$this->assertSame( '', get_metadata( 'user', $this->author->ID, $key, true ) );
		$this->assertIsInt( update_metadata( 'user', $this->author->ID, $key, $value2 ) );
		$this->assertSame( $expected2, get_metadata( 'user', $this->author->ID, $key, true ) );
	}

	/**
	 * @ticket 16814
	 */
	public function test_meta_type_cast() {
		$post_id1 = self::factory()->post->create();
		add_post_meta( $post_id1, 'num_as_longtext', 123 );
		add_post_meta( $post_id1, 'num_as_longtext_desc', 10 );
		$post_id2 = self::factory()->post->create();
		add_post_meta( $post_id2, 'num_as_longtext', 99 );
		add_post_meta( $post_id2, 'num_as_longtext_desc', 100 );

		$posts = new WP_Query(
			array(
				'fields'       => 'ids',
				'post_type'    => 'any',
				'meta_key'     => 'num_as_longtext',
				'meta_value'   => '0',
				'meta_compare' => '>',
				'meta_type'    => 'UNSIGNED',
				'orderby'      => 'meta_value',
				'order'        => 'ASC',
			)
		);

		$this->assertSame( array( $post_id2, $post_id1 ), $posts->posts );
		$this->assertSame( 2, substr_count( $posts->request, 'CAST(' ) );

		// Make sure the newer meta_query syntax behaves in a consistent way.
		$posts = new WP_Query(
			array(
				'fields'     => 'ids',
				'post_type'  => 'any',
				'meta_query' => array(
					array(
						'key'     => 'num_as_longtext',
						'value'   => '0',
						'compare' => '>',
						'type'    => 'UNSIGNED',
					),
				),
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
			)
		);

		$this->assertSame( array( $post_id2, $post_id1 ), $posts->posts );
		$this->assertSame( 2, substr_count( $posts->request, 'CAST(' ) );

		// The legacy `meta_key` value should take precedence.
		$posts = new WP_Query(
			array(
				'fields'       => 'ids',
				'post_type'    => 'any',
				'meta_key'     => 'num_as_longtext',
				'meta_compare' => '>',
				'meta_type'    => 'UNSIGNED',
				'meta_query'   => array(
					array(
						'key'     => 'num_as_longtext_desc',
						'value'   => '0',
						'compare' => '>',
						'type'    => 'UNSIGNED',
					),
				),
				'orderby'      => 'meta_value',
				'order'        => 'ASC',
			)
		);

		$this->assertSame( array( $post_id2, $post_id1 ), $posts->posts );
		$this->assertSame( 2, substr_count( $posts->request, 'CAST(' ) );
	}

	public function test_meta_cache_order_asc() {
		$post_id = self::factory()->post->create();
		$colors  = array( 'red', 'blue', 'yellow', 'green' );
		foreach ( $colors as $color ) {
			add_post_meta( $post_id, 'color', $color );
		}

		foreach ( range( 1, 10 ) as $i ) {
			$meta = get_post_meta( $post_id, 'color' );
			$this->assertSame( $meta, $colors );

			if ( 0 === $i % 2 ) {
				wp_cache_delete( $post_id, 'post_meta' );
			}
		}
	}

	/**
	 * @ticket 28315
	 */
	public function test_non_numeric_object_id() {
		$this->assertFalse( add_metadata( 'user', array( 1 ), 'meta_key', 'meta_value' ) );
		$this->assertFalse( update_metadata( 'user', array( 1 ), 'meta_key', 'meta_new_value' ) );
		$this->assertFalse( delete_metadata( 'user', array( 1 ), 'meta_key' ) );
		$this->assertFalse( get_metadata( 'user', array( 1 ) ) );
		$this->assertFalse( metadata_exists( 'user', array( 1 ), 'meta_key' ) );
	}

	/**
	 * @ticket 28315
	 */
	public function test_non_numeric_meta_id() {
		$this->assertFalse( get_metadata_by_mid( 'user', array( 1 ) ) );
		$this->assertFalse( update_metadata_by_mid( 'user', array( 1 ), 'meta_new_value' ) );
		$this->assertFalse( delete_metadata_by_mid( 'user', array( 1 ) ) );
	}

	/**
	 * @ticket 37746
	 */
	public function test_negative_meta_id() {
		$negative_mid = $this->meta_id * -1;

		$this->assertLessThan( 0, $negative_mid );
		$this->assertFalse( get_metadata_by_mid( 'user', $negative_mid ) );
		$this->assertFalse( update_metadata_by_mid( 'user', $negative_mid, 'meta_new_value' ) );
		$this->assertFalse( delete_metadata_by_mid( 'user', $negative_mid ) );
	}

	/**
	 * @ticket 37746
	 */
	public function test_floating_meta_id() {
		$floating_mid = $this->meta_id + 0.1337;

		$this->assertTrue( floor( $floating_mid ) !== $floating_mid );
		$this->assertFalse( get_metadata_by_mid( 'user', $floating_mid ) );
		$this->assertFalse( update_metadata_by_mid( 'user', $floating_mid, 'meta_new_value' ) );
		$this->assertFalse( delete_metadata_by_mid( 'user', $floating_mid ) );
	}

	/**
	 * @ticket 37746
	 */
	public function test_string_point_zero_meta_id() {
		$meta_id = add_metadata( 'user', $this->author->ID, 'meta_key', 'meta_value_2' );

		$string_mid = "{$meta_id}.0";

		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- intentional implicit casting check
		$this->assertTrue( floor( $string_mid ) == $string_mid );
		$this->assertNotFalse( get_metadata_by_mid( 'user', $string_mid ) );
		$this->assertNotFalse( update_metadata_by_mid( 'user', $string_mid, 'meta_new_value_2' ) );
		$this->assertNotFalse( delete_metadata_by_mid( 'user', $string_mid ) );
	}

	/**
	 * @ticket 15030
	 */
	public function test_get_metadata_with_empty_key_array_value() {
		$data  = array( 1, 2 );
		$value = serialize( $data );
		add_metadata( 'user', $this->author->ID, 'foo', $data );
		$found = get_metadata( 'user', $this->author->ID );

		$this->assertSame( array( $value ), $found['foo'] );
	}

	/**
	 * @ticket 15030
	 */
	public function test_get_metadata_with_empty_key_object_value() {
		$data      = new stdClass();
		$data->foo = 'bar';
		$value     = serialize( $data );
		add_metadata( 'user', $this->author->ID, 'foo', $data );
		$found = get_metadata( 'user', $this->author->ID );

		$this->assertSame( array( $value ), $found['foo'] );
	}

	/**
	 * @ticket 15030
	 */
	public function test_get_metadata_with_empty_key_nested_array_value() {
		$data  = array(
			array( 1, 2 ),
			array( 3, 4 ),
		);
		$value = serialize( $data );
		add_metadata( 'user', $this->author->ID, 'foo', $data );
		$found = get_metadata( 'user', $this->author->ID );

		$this->assertSame( array( $value ), $found['foo'] );
	}

	/**
	 * @dataProvider data_get_metadata_with_non_existent_object_id
	 */
	public function test_get_metadata_with_non_existent_object_id( $expected, $args ) {
		$this->assertSame( $expected, get_metadata( 'user', ...$args ) );
	}

	public function data_get_metadata_with_non_existent_object_id() {
		return array(
			'should return empty array for default `$meta_key` and `$single` values' => array(
				'expected' => array(),
				'args'     => array( PHP_INT_MAX ),
			),
			'should return empty array for default `$single` value' => array(
				'expected' => array(),
				'args'     => array( PHP_INT_MAX, 'meta_key' ),
			),
			'should return empty array when `$single` is `false`' => array(
				'expected' => array(),
				'args'     => array( PHP_INT_MAX, 'meta_key', false ),
			),
			'should return empty string when `$single` is `true`' => array(
				'expected' => '',
				'args'     => array( PHP_INT_MAX, 'meta_key', true ),
			),
		);
	}

	/** Helpers */

	public function updated_meta( $meta_id ) {
		$this->updated_mids[ current_action() ][] = $meta_id;
	}

	/**
	 * @ticket 60618
	 *
	 * @covers ::update_metadata
	 */
	public function test_update_metadata_should_return_wp_error_on_database_error() {
		global $wpdb;
		$wpdb->suppress_errors = true;

		// Attempt to add new metadata, but intentionally cause the query to fail using a filter.
		$this->error_query_regexp = '/^INSERT.*bar_meta_value/i';
		add_filter( 'query', array( $this, 'error_query' ) );
		$result = update_metadata( 'user', $this->author->ID, 'foo_meta_key', 'bar_meta_value', '', true );
		remove_filter( 'query', array( $this, 'error_query' ) );

		$this->assertWPError( $result, 'Expected result to be a WP_Error due to a database error.' );

		// Attempt to add new metadata; the operation should succeed.
		$result = update_metadata( 'user', $this->author->ID, 'foo_meta_key', 'bar_meta_value', '', true );

		$this->assertIsInt( $result, 'Expected result to be an integer after successfully adding metadata.' );
		$this->assertGreaterThan( 0, $result, 'Expected result to be greater than zero, representing a valid ID.' );

		// Attempt to update existing metadata, but intentionally cause the query to fail using a filter.
		$this->error_query_regexp = '/^UPDATE.*new_meta_value/i';
		add_filter( 'query', array( $this, 'error_query' ) );
		$result = update_metadata( 'user', $this->author->ID, 'foo_meta_key', 'new_meta_value', '', true );
		$this->assertWPError( $result, 'Expected result to be a WP_Error when the update query fails.' );
		remove_filter( 'query', array( $this, 'error_query' ) );

		// Attempt to update existing metadata; the operation should succeed.
		$result = update_metadata( 'user', $this->author->ID, 'foo_meta_key', 'new_meta_value', '', true );
		$this->assertTrue( $result, 'Expected result to be true after successfully updating metadata.' );

		$wpdb->suppress_errors = false;
	}

	/**
	 * Internal function used to disable a query which
	 * will trigger a wpdb error for testing purposes.
	 *
	 * @param string $query The query to modify.
	 */
	public function error_query( $query ) {
		if ( 1 === preg_match( $this->error_query_regexp, $query ) ) {
			$query = '],';
		}

		return $query;
	}
}
