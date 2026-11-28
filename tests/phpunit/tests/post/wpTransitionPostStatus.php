<?php

/**
 * Tests for wp_transition_post_status().
 *
 * @group post
 *
 * @covers ::wp_transition_post_status
 */
class Tests_Post_WpTransitionPostStatus extends WP_UnitTestCase {

	/**
	 * Post that is used in the transitions.
	 *
	 * @var WP_Post
	 */
	protected static $post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post = get_post( $factory->post->create( array( 'post_status' => 'draft' ) ) );
	}

	/**
	 * Records the actions of a transition.
	 *
	 * @param string[] $hooks Names of the actions to record.
	 * @param array    $calls Reference to the list of the calls.
	 */
	private function record_actions( array $hooks, array &$calls ) {
		foreach ( $hooks as $hook ) {
			add_action(
				$hook,
				static function () use ( &$calls, $hook ) {
					$calls[] = array_merge( array( $hook ), func_get_args() );
				},
				10,
				3
			);
		}
	}

	/**
	 * Tests that the actions fire in order, with their arguments, for a change of a known status.
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_actions_of_a_status_change() {
		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'draft_to_publish', 'publish_post' ), $calls );

		wp_transition_post_status( 'publish', 'draft', self::$post );

		$this->assertSame(
			array(
				array( 'transition_post_status', 'publish', 'draft', self::$post ),
				array( 'draft_to_publish', self::$post ),
				array( 'publish_post', self::$post->ID, self::$post, 'draft' ),
			),
			$calls
		);
	}

	/**
	 * Tests that the actions also fire when the status does not change.
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_actions_when_the_status_does_not_change() {
		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'publish_to_publish', 'publish_post' ), $calls );

		wp_transition_post_status( 'publish', 'publish', self::$post );

		$this->assertSame(
			array( 'transition_post_status', 'publish_to_publish', 'publish_post' ),
			wp_list_pluck( $calls, 0 )
		);
	}

	/**
	 * Tests that a post that is new has the transition action of the status "new".
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_transition_action_for_a_new_post() {
		$calls = array();
		$this->record_actions( array( 'new_to_draft', 'draft_post' ), $calls );

		wp_transition_post_status( 'draft', 'new', self::$post );

		$this->assertSame(
			array(
				array( 'new_to_draft', self::$post ),
				array( 'draft_post', self::$post->ID, self::$post, 'new' ),
			),
			$calls
		);
	}

	/**
	 * Tests that the transition action fires for the statuses that are registered.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_registered_statuses
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function test_should_fire_the_transition_action_for_registered_statuses( $new_status, $old_status ) {
		$calls = array();
		$this->record_actions( array( "{$old_status}_to_{$new_status}" ), $calls );

		wp_transition_post_status( $new_status, $old_status, self::$post );

		$this->assertCount( 1, $calls );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_registered_statuses() {
		return array(
			'draft to pending'    => array( 'pending', 'draft' ),
			'pending to publish'  => array( 'publish', 'pending' ),
			'publish to private'  => array( 'private', 'publish' ),
			'publish to trash'    => array( 'trash', 'publish' ),
			'trash to draft'      => array( 'draft', 'trash' ),
			'auto-draft to draft' => array( 'draft', 'auto-draft' ),
			'future to publish'   => array( 'publish', 'future' ),
		);
	}

	/**
	 * Tests that only the first action fires when the new status is not registered.
	 *
	 * @ticket 66495
	 */
	public function test_should_only_fire_the_first_action_for_a_new_status_that_is_not_registered() {
		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'draft_to_made-up', 'made-up_post' ), $calls );

		wp_transition_post_status( 'made-up', 'draft', self::$post );

		$this->assertSame( array( 'transition_post_status' ), wp_list_pluck( $calls, 0 ) );
	}

	/**
	 * Tests that the transition action does not fire when the old status is not registered, and is not "new".
	 *
	 * @ticket 66495
	 */
	public function test_should_not_fire_the_transition_action_for_an_old_status_that_is_not_registered() {
		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'made-up_to_publish', 'publish_post' ), $calls );

		wp_transition_post_status( 'publish', 'made-up', self::$post );

		$this->assertSame( array( 'transition_post_status', 'publish_post' ), wp_list_pluck( $calls, 0 ) );
	}

	/**
	 * Tests that the action of the status and the post type does not fire when the post type is not registered.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_fire_the_post_type_action_for_a_post_type_that_is_not_registered() {
		$post            = clone self::$post;
		$post->post_type = 'made-up-type';

		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'draft_to_publish', 'publish_made-up-type' ), $calls );

		wp_transition_post_status( 'publish', 'draft', $post );

		$this->assertSame( array( 'transition_post_status', 'draft_to_publish' ), wp_list_pluck( $calls, 0 ) );
	}

	/**
	 * Tests that the post type is part of the name of the last action.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string $post_type Post type.
	 */
	public function test_should_include_the_post_type_in_the_name_of_the_last_action( $post_type ) {
		$post            = clone self::$post;
		$post->post_type = $post_type;

		$calls = array();
		$this->record_actions( array( "publish_{$post_type}" ), $calls );

		wp_transition_post_status( 'publish', 'draft', $post );

		$this->assertSame( array( array( "publish_{$post_type}", $post->ID, $post, 'draft' ) ), $calls );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_post_types() {
		return array(
			'post'       => array( 'post' ),
			'page'       => array( 'page' ),
			'attachment' => array( 'attachment' ),
		);
	}

	/**
	 * Tests that the function is used when a post is published.
	 *
	 * @ticket 66495
	 */
	public function test_should_be_used_when_a_post_is_published() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$calls = array();
		$this->record_actions( array( 'transition_post_status', 'draft_to_publish' ), $calls );

		wp_publish_post( $post_id );

		$transitions = array_values( wp_list_filter( $calls, array( 0 => 'draft_to_publish' ) ) );

		$this->assertCount( 1, $transitions, 'The draft_to_publish action should fire once.' );
		$this->assertSame( $post_id, $transitions[0][1]->ID );
		$this->assertSame( array( 'transition_post_status', 'publish', 'draft' ), array_slice( $calls[0], 0, 3 ), 'The first call should be the change from draft to publish.' );
	}
}
