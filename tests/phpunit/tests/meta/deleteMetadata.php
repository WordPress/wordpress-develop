<?php

/**
 * @group meta
 */
class Tests_Meta_DeleteMetadata extends WP_UnitTestCase {
	public function test_all_metas_for_key_should_be_deleted_when_no_meta_value_is_provided() {
		$vals = array( '0', '1', '2' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo' );
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( array(), $m );
	}

	public function test_with_meta_value() {
		$vals = array( '0', '1', '2' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo', '1' );
		$m        = get_metadata( 'post', 12345, 'foo', false );
		$expected = array_diff( $vals, array( '1' ) );

		$this->assertSameSets( $expected, $m );
	}

	/**
	 * @ticket 32224
	 */
	public function test_with_falsey_meta_value_should_not_delete_all_meta() {
		$vals = array( '0', '1', '2' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo', '0' );
		$m        = get_metadata( 'post', 12345, 'foo', false );
		$expected = array_diff( $vals, array( '0' ) );

		$this->assertSameSets( $expected, $m );
	}

	/**
	 * @ticket 32224
	 *
	 * This is a backwards compatibility quirk.
	 */
	public function test_meta_value_should_be_ignored_when_empty_string() {
		$vals = array( '0', '1', '2', '' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo', '' );
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( array(), $m );
	}

	/**
	 * @ticket 32224
	 */
	public function test_meta_value_should_be_ignored_when_null() {
		$vals = array( '0', '1', '2', '' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo', null );
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( array(), $m );
	}

	/**
	 * @ticket 32224
	 */
	public function test_meta_value_should_be_ignored_when_false() {
		$vals = array( '0', '1', '2', '' );
		foreach ( $vals as $val ) {
			add_metadata( 'post', 12345, 'foo', $val );
		}
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( $vals, $m );

		delete_metadata( 'post', 12345, 'foo', false );
		$m = get_metadata( 'post', 12345, 'foo', false );
		$this->assertSameSets( array(), $m );
	}

	/**
	 * @ticket 35797
	 */
	public function test_delete_all_should_only_invalidate_cache_for_objects_matching_meta_value() {
		$p1 = 1234;
		$p2 = 5678;

		add_metadata( 'post', $p1, 'foo', 'value1' );
		add_metadata( 'post', $p2, 'foo', 'value2' );

		// Prime caches.
		update_meta_cache( 'post', array( $p1, $p2 ) );

		$deleted = delete_metadata( 'post', 5, 'foo', 'value1', true );

		$p1_cache = wp_cache_get( $p1, 'post_meta' );
		$this->assertFalse( $p1_cache );

		// Should not have been touched.
		$p2_cache = wp_cache_get( $p2, 'post_meta' );
		$this->assertNotEmpty( $p2_cache );
	}

	/**
	 * @ticket 35797
	 */
	public function test_delete_all_should_invalidate_cache_for_all_objects_with_meta_key_when_meta_value_is_not_provided() {
		$p1 = 1234;
		$p2 = 5678;

		add_metadata( 'post', $p1, 'foo', 'value1' );
		add_metadata( 'post', $p2, 'foo', 'value2' );

		// Prime caches.
		update_meta_cache( 'post', array( $p1, $p2 ) );

		$deleted = delete_metadata( 'post', 5, 'foo', false, true );

		$p1_cache = wp_cache_get( $p1, 'post_meta' );
		$this->assertFalse( $p1_cache );

		$p2_cache = wp_cache_get( $p2, 'post_meta' );
		$this->assertFalse( $p2_cache );
	}

	/**
	 * @ticket 43561
	 */
	public function test_object_id_is_int_inside_delete_post_meta() {
		$post_id = self::factory()->post->create();
		$meta_id = add_metadata( 'post', $post_id, 'my_key', 'my_value' );
		add_action( 'delete_post_meta', array( $this, 'action_check_object_id_is_int' ), 10, 2 );
		delete_metadata_by_mid( 'post', $meta_id );
	}

	public function action_check_object_id_is_int( $meta_type, $object_id ) {
		$this->assertSame(
			'integer',
			gettype( $object_id )
		);
	}

	/**
	 * @ticket 65976
	 *
	 * @dataProvider data_actions_should_receive_delete_all
	 *
	 * @param bool $delete_all Whether to delete the matching metadata entries for all objects.
	 */
	public function test_actions_should_receive_delete_all( $delete_all ) {
		add_metadata( 'post', 123, 'test_key', 'value' );

		$delete_action  = new MockAction();
		$deleted_action = new MockAction();

		add_action( 'delete_post_meta', array( $delete_action, 'action' ), 10, 5 );
		add_action( 'deleted_post_meta', array( $deleted_action, 'action' ), 10, 5 );

		delete_metadata( 'post', 123, 'test_key', '', $delete_all );

		$delete_args  = $delete_action->get_args();
		$deleted_args = $deleted_action->get_args();

		$this->assertSame( $delete_all, $delete_args[0][4], 'The delete_post_meta action should receive the value of $delete_all.' );
		$this->assertSame( $delete_all, $deleted_args[0][4], 'The deleted_post_meta action should receive the value of $delete_all.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_actions_should_receive_delete_all() {
		return array(
			'deleting for a single object' => array( false ),
			'deleting for all objects'     => array( true ),
		);
	}

	/**
	 * @ticket 65976
	 */
	public function test_actions_should_receive_delete_all_false_when_deleting_by_mid() {
		$meta_id = add_metadata( 'post', 123, 'test_key', 'value' );

		$delete_action  = new MockAction();
		$deleted_action = new MockAction();

		add_action( 'delete_post_meta', array( $delete_action, 'action' ), 10, 5 );
		add_action( 'deleted_post_meta', array( $deleted_action, 'action' ), 10, 5 );

		delete_metadata_by_mid( 'post', $meta_id );

		$delete_args  = $delete_action->get_args();
		$deleted_args = $deleted_action->get_args();

		$this->assertFalse( $delete_args[0][4], 'The delete_post_meta action should receive false for $delete_all when deleting by meta ID.' );
		$this->assertFalse( $deleted_args[0][4], 'The deleted_post_meta action should receive false for $delete_all when deleting by meta ID.' );
	}
}
