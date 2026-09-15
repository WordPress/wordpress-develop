<?php

/**
 * @group admin
 *
 * @covers ::wp_heartbeat_set_suspension
 * @covers ::wp_check_post_list_table_changes
 * @covers ::_wp_get_post_list_table_last_changed
 * @covers ::_wp_set_post_list_table_last_changed
 * @covers ::_wp_set_post_list_table_last_changed_for_post_meta
 * @covers ::_wp_set_post_list_table_last_changed_for_terms
 */
class Tests_Admin_Includes_Misc_WpHeartbeatSetSuspension_Test extends WP_UnitTestCase {

	/**
	 * Original value of $pagenow.
	 *
	 * @var string
	 */
	private $orig_pagenow;

	public function set_up() {
		global $pagenow;

		parent::set_up();

		_wp_finalize_post_list_table_last_changed();
		$this->orig_pagenow = $pagenow;
	}

	public function tear_down() {
		global $pagenow;

		$pagenow = $this->orig_pagenow;
		_wp_finalize_post_list_table_last_changed();

		parent::tear_down();
	}

	/**
	 * Returns Heartbeat data for a post type and change token.
	 *
	 * @param string $post_type   Post type.
	 * @param string $last_changed Last changed token.
	 * @return array Heartbeat data.
	 */
	private function get_post_list_heartbeat_data( $post_type, $last_changed ) {
		return array(
			'wp-check-post-list' => array(
				'post_type'    => $post_type,
				'last_changed' => $last_changed,
			),
		);
	}

	/**
	 * Tests that wp_heartbeat_set_suspension() disables suspension on post screens.
	 *
	 * @dataProvider data_wp_heartbeat_set_suspension
	 *
	 * @ticket 65200
	 *
	 * @param string $pagenow_value The value for the $pagenow global.
	 * @param string $expected      The expected value of 'suspension' in settings.
	 */
	public function test_wp_heartbeat_set_suspension( $pagenow_value, $expected ) {
		global $pagenow;

		$pagenow = $pagenow_value;

		$settings = array( 'suspension' => 'initial' );
		$result   = wp_heartbeat_set_suspension( $settings );

		$this->assertSame( $expected, $result['suspension'], "Suspension should be '{$expected}' when \$pagenow is {$pagenow_value}." );
	}

	/**
	 * Tests post list Heartbeat comparisons and validation.
	 *
	 * @ticket 65461
	 */
	public function test_post_list_heartbeat_comparison() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$last_changed = _wp_get_post_list_table_last_changed( 'post' );

		$response = wp_check_post_list_table_changes( array(), $this->get_post_list_heartbeat_data( 'post', $last_changed ) );
		$this->assertArrayNotHasKey( 'wp-refresh-post-list', $response );

		self::factory()->post->create();
		$response = wp_check_post_list_table_changes( array(), $this->get_post_list_heartbeat_data( 'post', $last_changed ) );
		$this->assertTrue( $response['wp-refresh-post-list'] );

		$response = wp_check_post_list_table_changes( array(), $this->get_post_list_heartbeat_data( 'attachment', 'stale' ) );
		$this->assertArrayNotHasKey( 'wp-refresh-post-list', $response );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = wp_check_post_list_table_changes( array(), $this->get_post_list_heartbeat_data( 'post', $last_changed ) );
		$this->assertArrayNotHasKey( 'wp-refresh-post-list', $response );
	}

	/**
	 * Tests post lifecycle token updates.
	 *
	 * @ticket 65461
	 */
	public function test_post_list_lifecycle_tokens() {
		$post_token = _wp_get_post_list_table_last_changed( 'post' );
		$page_token = _wp_get_post_list_table_last_changed( 'page' );
		$post_id    = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertNotSame( $post_token, _wp_get_post_list_table_last_changed( 'post' ) );
		$this->assertSame( $page_token, _wp_get_post_list_table_last_changed( 'page' ) );

		_wp_finalize_post_list_table_last_changed();
		$first_token = _wp_get_post_list_table_last_changed( 'post' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'First update',
			)
		);
		_wp_finalize_post_list_table_last_changed();
		$second_token = _wp_get_post_list_table_last_changed( 'post' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Second update',
			)
		);
		_wp_finalize_post_list_table_last_changed();
		$third_token = _wp_get_post_list_table_last_changed( 'post' );

		$this->assertNotSame( $first_token, $second_token );
		$this->assertNotSame( $second_token, $third_token );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		_wp_finalize_post_list_table_last_changed();
		$this->assertNotSame( $third_token, _wp_get_post_list_table_last_changed( 'post' ) );
		$this->assertNotSame( $page_token, _wp_get_post_list_table_last_changed( 'page' ) );

		$post_token = _wp_get_post_list_table_last_changed( 'post' );
		$page_token = _wp_get_post_list_table_last_changed( 'page' );
		wp_insert_post(
			array(
				'post_parent' => $post_id,
				'post_status' => 'inherit',
				'post_type'   => 'revision',
			)
		);
		wp_insert_post(
			array(
				'post_status' => 'auto-draft',
				'post_type'   => 'post',
			)
		);
		_wp_finalize_post_list_table_last_changed();
		$this->assertSame( $post_token, _wp_get_post_list_table_last_changed( 'post' ) );
		$this->assertSame( $page_token, _wp_get_post_list_table_last_changed( 'page' ) );

		wp_delete_post( $post_id, true );
		_wp_finalize_post_list_table_last_changed();
		$this->assertNotSame( $page_token, _wp_get_post_list_table_last_changed( 'page' ) );
	}

	/**
	 * Tests related-data invalidation and bounded writes.
	 *
	 * @ticket 65461
	 */
	public function test_post_list_related_data_and_write_coalescing() {
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create();
		_wp_finalize_post_list_table_last_changed();
		$last_changed = _wp_get_post_list_table_last_changed( 'post' );

		add_post_meta( $post_id, 'list_value', 'one' );
		$this->assertSame( $last_changed, _wp_get_post_list_table_last_changed( 'post' ) );
		_wp_finalize_post_list_table_last_changed();
		$meta_token = _wp_get_post_list_table_last_changed( 'post' );
		$this->assertNotSame( $last_changed, $meta_token );

		wp_add_object_terms( $post_id, $term_id, 'category' );
		$this->assertSame( $meta_token, _wp_get_post_list_table_last_changed( 'post' ) );
		_wp_finalize_post_list_table_last_changed();
		$this->assertNotSame( $meta_token, _wp_get_post_list_table_last_changed( 'post' ) );

		$writes   = 0;
		$callback = static function ( $option ) use ( &$writes ) {
			if ( '_wp_post_list_table_last_changed_post' === $option ) {
				++$writes;
			}
		};
		add_action( 'updated_option', $callback );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'First update',
			)
		);
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Second update',
			)
		);
		$writes_before_shutdown = $writes;
		_wp_finalize_post_list_table_last_changed();
		remove_action( 'updated_option', $callback );

		$this->assertSame( 1, $writes_before_shutdown );
		$this->assertSame( 2, $writes );
	}

	/**
	 * Tests that a failed immediate token update is retried at shutdown.
	 *
	 * @ticket 65461
	 */
	public function test_post_list_failed_token_update_is_retried() {
		$post_id = self::factory()->post->create();
		_wp_finalize_post_list_table_last_changed();
		$last_changed = _wp_get_post_list_table_last_changed( 'post' );
		$filter       = static function ( $value, $old_value ) {
			return $old_value;
		};

		add_filter( 'pre_update_option__wp_post_list_table_last_changed_post', $filter, 10, 2 );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated title',
			)
		);
		remove_filter( 'pre_update_option__wp_post_list_table_last_changed_post', $filter );
		$this->assertSame( $last_changed, _wp_get_post_list_table_last_changed( 'post' ) );

		_wp_finalize_post_list_table_last_changed();
		$this->assertNotSame( $last_changed, _wp_get_post_list_table_last_changed( 'post' ) );
	}

	/**
	 * Data provider for test_wp_heartbeat_set_suspension().
	 *
	 * @return array<string, array{
	 *     pagenow_value: string,
	 *     expected:      string,
	 * }>
	 */
	public function data_wp_heartbeat_set_suspension(): array {
		return array(
			'post.php'     => array(
				'pagenow_value' => 'post.php',
				'expected'      => 'disable',
			),
			'post-new.php' => array(
				'pagenow_value' => 'post-new.php',
				'expected'      => 'disable',
			),
			'index.php'    => array(
				'pagenow_value' => 'index.php',
				'expected'      => 'initial',
			),
			'edit.php'     => array(
				'pagenow_value' => 'edit.php',
				'expected'      => 'initial',
			),
		);
	}
}
