<?php
/**
 * Test heartbeat_autosave().
 *
 * @group admin
 * @group heartbeat
 *
 * @covers ::heartbeat_autosave
 */
class Tests_Admin_Includes_Misc_HeartbeatAutosave extends WP_UnitTestCase {

	/**
	 * Tests that heartbeat_autosave() correctly handles missing autosave data.
	 *
	 * @ticket 65201
	 */
	public function test_heartbeat_autosave_no_data() {
		$response = array( 'existing' => 'data' );
		$data     = array();

		$result = heartbeat_autosave( $response, $data );

		$this->assertSame( $response, $result, 'The response should remain unchanged when no autosave data is provided.' );
	}

	/**
	 * Tests that heartbeat_autosave() correctly handles successful autosave.
	 *
	 * @ticket 65201
	 */
	public function test_heartbeat_autosave_success() {
		$post_id = self::factory()->post->create();
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$response = array();
		$data     = array(
			'wp_autosave' => array(
				'post_id'      => $post_id,
				'post_type'    => 'post',
				'_wpnonce'     => wp_create_nonce( 'update-post_' . $post_id ),
				'post_content' => 'Autosaved content',
			),
		);

		$result = heartbeat_autosave( $response, $data );

		$this->assertArrayHasKey( 'wp_autosave', $result );
		$this->assertTrue( $result['wp_autosave']['success'] );
		$this->assertStringContainsString( 'Draft saved at', $result['wp_autosave']['message'] );
	}

	/**
	 * Tests that heartbeat_autosave() correctly handles autosave errors.
	 *
	 * @ticket 65201
	 */
	public function test_heartbeat_autosave_error() {
		$post_id = self::factory()->post->create();
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$response = array();
		$data     = array(
			'wp_autosave' => array(
				'post_id'  => $post_id,
				'_wpnonce' => 'invalid-nonce',
			),
		);

		$result = heartbeat_autosave( $response, $data );

		$this->assertArrayHasKey( 'wp_autosave', $result );
		$this->assertFalse( $result['wp_autosave']['success'] );
		$this->assertSame( 'Error while saving.', $result['wp_autosave']['message'] );
	}

	/**
	 * Tests that heartbeat_autosave() handles empty return from wp_autosave().
	 *
	 * @ticket 65201
	 */
	public function test_heartbeat_autosave_empty_result() {
		$post_id = self::factory()->post->create();
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$response = array();
		$data     = array(
			'wp_autosave' => array(
				'post_id'      => $post_id,
				'post_type'    => 'post',
				'_wpnonce'     => wp_create_nonce( 'update-post_' . $post_id ),
			),
		);

		// Trigger an empty return by making edit_post return 0.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$result = heartbeat_autosave( $response, $data );

		$this->assertArrayHasKey( 'wp_autosave', $result );
		$this->assertFalse( $result['wp_autosave']['success'] );
		$this->assertSame( 'Content, title, and excerpt are empty.', $result['wp_autosave']['message'] );
	}
}
