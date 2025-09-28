<?php

/**
 * @group post
 */
class Tests_Post_GetPostStatus extends WP_UnitTestCase {

	/**
	 * Array of post IDs.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Create shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$post_statuses = array( 'publish', 'future', 'draft', 'auto-draft', 'trash', 'private', 'delete', 'pending' );
		foreach ( $post_statuses as $post_status ) {
			$date          = '';
			$actual_status = $post_status;
			if ( 'future' === $post_status ) {
				$date = date_format( date_create( '+1 year' ), 'Y-m-d H:i:s' );
			} elseif ( in_array( $post_status, array( 'trash', 'delete' ), true ) ) {
				$actual_status = 'publish';
			}

			self::$post_ids[ $post_status ] = $factory->post->create(
				array(
					'post_status' => $actual_status,
					'post_date'   => $date,
					'post_name'   => "$post_status-post",
				)
			);

			// Attachments without parent or media.
			self::$post_ids[ "$post_status-attachment-no-parent" ] = $factory->attachment->create_object(
				array(
					'post_status' => $actual_status,
					'post_name'   => "$post_status-attachment-no-parent",
					'post_date'   => $date,
				)
			);

			// Attachments without media.
			self::$post_ids[ "$post_status-attachment" ] = $factory->attachment->create_object(
				array(
					'post_parent' => self::$post_ids[ $post_status ],
					'post_status' => 'inherit',
					'post_name'   => "$post_status-attachment",
					'post_date'   => $date,
				)
			);
		}

		// Attachment with incorrect parent ID.
		self::$post_ids['badly-parented-attachment'] = $factory->attachment->create_object(
			array(
				'post_parent' => PHP_INT_MAX, // Impossibly large number.
				'post_status' => 'inherit',
				'post_name'   => "$post_status-attachment",
				'post_date'   => $date,
			)
		);

		// Password protected post
		self::$post_ids['password-protected'] = $factory->post->create_object(
			array(
				'post_status'   => 'publish',
				'post_name'     => 'password-protected',
				'post_date'     => $date,
				'post_content'  => 'This is a password protected post.',
				'post_password' => wp_generate_password(),
			)
		);

		// Customization draft post
		self::$post_ids['customization-draft'] = $factory->post->create_object(
			array(
				'post_status'  => 'draft',
				'post_name'    => 'customization-draft',
				'post_date'    => $date,
				'post_content' => 'This is a customization draft post.',
				'meta_input'   => array(
					'_customize_changeset_uuid' => wp_generate_uuid4(),
				),
			)
		);

		// Trashed customization draft post
		self::$post_ids['trashed-customization-draft'] = $factory->post->create_object(
			array(
				'post_status'  => 'trash',
				'post_name'    => 'trashed-customization-draft',
				'post_date'    => $date,
				'post_content' => 'This is a trashed customization draft post.',
				'meta_input'   => array(
					'_customize_changeset_uuid' => wp_generate_uuid4(),
				),
			)
		);

		// Sticky post
		self::$post_ids['sticky'] = $factory->post->create_object(
			array(
				'post_status'  => 'publish',
				'post_name'    => 'sticky-post',
				'post_content' => 'This is a sticky post.',
				'post_date'    => $date,
			)
		);

		stick_post( self::$post_ids['sticky'] );

		// Page Show on front
		self::$post_ids['page-show-on-front'] = $factory->post->create_object(
			array(
				'post_status'  => 'publish',
				'post_name'    => 'page-show-on-front',
				'post_content' => 'This is the page set to show on front.',
				'post_date'    => $date,
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$post_ids['page-show-on-front'] );

		// Page for posts
		self::$post_ids['page-for-posts'] = $factory->post->create_object(
			array(
				'post_status'  => 'publish',
				'post_name'    => 'page-for-posts',
				'post_content' => 'This is the page for posts.',
				'post_date'    => $date,
			)
		);
		update_option( 'page_for_posts', self::$post_ids['page-for-posts'] );

		// Page for privacy policy
		self::$post_ids['page-for-privacy-policy'] = $factory->post->create_object(
			array(
				'post_status'  => 'publish',
				'post_name'    => 'page-for-privacy-policy',
				'post_content' => 'This is the page for privacy policy.',
				'post_date'    => $date,
			)
		);
		update_option( 'wp_page_for_privacy_policy', self::$post_ids['page-for-privacy-policy'] );

		// Trash the trash post and attachment.
		wp_trash_post( self::$post_ids['trash'] );
		wp_trash_post( self::$post_ids['trash-attachment-no-parent'] );

		// Force delete parent and unattached post objects.
		wp_delete_post( self::$post_ids['delete'], true );
		wp_delete_post( self::$post_ids['delete-attachment-no-parent'], true );
	}

	/**
	 * Ensure `get_post_status()` resolves correctly for posts and attachments.
	 *
	 * @ticket 52326
	 * @dataProvider data_get_post_status_resolves
	 *
	 * @param string $post_key The post key in self::$post_ids.
	 * @param string $expected The expected get_post_status() return value.
	 */
	public function test_get_post_status_resolves( $post_key, $expected ) {
		$this->assertSame( $expected, get_post_status( self::$post_ids[ $post_key ] ) );
	}

	/**
	 * Data provider for test_get_post_status_resolves().
	 *
	 * @return array[] {
	 *     @type string $post_key The post key in self::$post_ids.
	 *     @type string $expected The expected get_post_status() return value.
	 * }
	 */
	public function data_get_post_status_resolves() {
		return array(
			array( 'publish', 'publish' ),
			array( 'future', 'future' ),
			array( 'draft', 'draft' ),
			array( 'auto-draft', 'auto-draft' ),
			array( 'trash', 'trash' ),
			array( 'private', 'private' ),
			array( 'delete', false ),

			// Attachment with `inherit` status from parent.
			array( 'publish-attachment', 'publish' ),
			array( 'future-attachment', 'future' ),
			array( 'draft-attachment', 'draft' ),
			array( 'auto-draft-attachment', 'auto-draft' ),
			array( 'trash-attachment', 'publish' ),
			array( 'private-attachment', 'private' ),
			array( 'delete-attachment', 'publish' ),

			// Attachment with native status (rather than inheriting from parent).
			array( 'publish-attachment-no-parent', 'publish' ),
			array( 'future-attachment-no-parent', 'publish' ), // Attachments can't have future status.
			array( 'draft-attachment-no-parent', 'publish' ),  // Attachments can't have draft status.
			array( 'auto-draft-attachment-no-parent', 'auto-draft' ),
			array( 'trash-attachment-no-parent', 'trash' ),
			array( 'private-attachment-no-parent', 'private' ),
			array( 'delete-attachment-no-parent', false ),

			// Attachment attempting to inherit from an invalid parent number.
			array( 'badly-parented-attachment', 'publish' ),
		);
	}

	/**
	 * Ensure post status resolves after trashing parent posts.
	 *
	 * @ticket 52326
	 * @dataProvider data_get_post_status_after_trashing
	 *
	 * @param string $post_to_test  The post key in self::$post_ids.
	 * @param string $post_to_trash The post key to trash then delete in self::$post_ids.
	 * @param string $expected      The expected result after trashing the post.
	 */
	public function test_get_post_status_after_trashing( $post_to_test, $post_to_trash, $expected ) {
		wp_trash_post( self::$post_ids[ $post_to_trash ] );
		$this->assertSame( $expected, get_post_status( self::$post_ids[ $post_to_test ] ) );

		// Now delete the post, expect publish.
		wp_delete_post( self::$post_ids[ $post_to_trash ], true );
		$this->assertSame( 'publish', get_post_status( self::$post_ids[ $post_to_test ] ) );
	}

	/**
	 * Data provider for test_get_post_status_after_trashing().
	 * @return array[] {
	 *     @type string $post_to_test  The post key in self::$post_ids.
	 *     @type string $post_to_trash The post key to trash then delete in self::$post_ids.
	 *     @type string $expected      The expected result after trashing the post.
	 * }
	 */
	public function data_get_post_status_after_trashing() {
		return array(
			array( 'publish-attachment', 'publish', 'publish' ),
			array( 'future-attachment', 'future', 'future' ),
			array( 'draft-attachment', 'draft', 'draft' ),
			array( 'auto-draft-attachment', 'auto-draft', 'auto-draft' ),
			array( 'private-attachment', 'private', 'private' ),
			array( 'delete-attachment', 'publish', 'publish' ),
		);
	}

	/**
	 * Ensure the `get_post_states` function don't return the current filtered post status in its result array.
	 *
	 * @ticket 64026
	 *
	 * @dataProvider data_filtered_post_status_shouldnt_be_included_in_post_state_array
	 *
	 * @param string $post_state The post state to test.
	 */
	public function test_filtered_post_status_shouldnt_be_included_in_post_state_array( $post_state ) {
		$_REQUEST['post_status'] = $post_state;
		$post                    = get_post( self::$post_ids[ $post_state ] );
		$post_states             = get_post_states( $post );
		$this->assertArrayNotHasKey( $post_state, $post_states );
	}

	/**
	 * Data provider for test_filtered_post_status_shouldnt_be_included_in_post_state_array().
	 *
	 * @return array[] {
	 *     @type string $post_state The post state to test.
	 * }
	 */
	public static function data_filtered_post_status_shouldnt_be_included_in_post_state_array() {
		return array(
			array( 'pending' ),
			array( 'draft' ),
			array( 'private' ),
		);
	}

	/**
	 * Ensure the `get_post_states` function return a `protected` index in results array if post is password protected.
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_protected_index_when_post_is_password_protected() {
		$post        = get_post( self::$post_ids['password-protected'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'protected', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `private` index in results array if post has private status
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_private_index_when_post_is_private() {
		$post        = get_post( self::$post_ids['private'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'private', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `draft` index in results array if post has draft status
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_draft_index_when_post_is_draft() {
		$post        = get_post( self::$post_ids['draft'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'draft', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `Customization Draft` value in results array if post has `_customize_changeset_uuid` meta and post_status is `draft`
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_customization_draft_when_post_has_customize_changeset_uuid_meta_and_has_draft_status() {
		$post        = get_post( self::$post_ids['customization-draft'] );
		$post_states = get_post_states( $post );
		$this->assertContains( 'Customization Draft', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `Customization Draft` value in results array if post has `_customize_changeset_uuid` meta
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_customization_draft_when_post_has_customize_changeset_uuid_meta_and_is_trashed() {
		$post        = get_post( self::$post_ids['trashed-customization-draft'] );
		$post_states = get_post_states( $post );
		$this->assertContains( 'Customization Draft', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `pending` index in results array if post has pending status
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_pending_index_when_post_is_pending() {
		$post        = get_post( self::$post_ids['pending'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'pending', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `sticky` index in results array if post is sticky
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_sticky_index_when_post_is_sticky() {
		$post        = get_post( self::$post_ids['sticky'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'sticky', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `scheduled` index in results array if post has future status
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_scheduled_index_when_post_is_scheduled() {
		$post        = get_post( self::$post_ids['future'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'scheduled', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `page_on_front` index in results array if post is set as page on front
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_page_on_front_index_when_post_is_page_on_front() {
		$post        = get_post( self::$post_ids['page-show-on-front'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'page_on_front', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `page_for_posts` index in results array if post is set as page for posts
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_page_for_posts_index_when_post_is_page_for_posts() {
		$post        = get_post( self::$post_ids['page-for-posts'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'page_for_posts', $post_states );
	}

	/**
	 * Ensure the `get_post_states` function return a `page_for_posts` index in results array if post is set as page for posts
	 *
	 * @ticket 64026
	 */
	public function test_get_post_states_should_return_page_for_privacy_policy_index_when_post_is_page_for_privacy_policy() {
		$post        = get_post( self::$post_ids['page-for-privacy-policy'] );
		$post_states = get_post_states( $post );
		$this->assertArrayHasKey( 'page_for_privacy_policy', $post_states );
	}
}
