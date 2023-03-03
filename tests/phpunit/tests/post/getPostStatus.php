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
		$post_statuses = array( 'publish', 'future', 'draft', 'auto-draft', 'trash', 'private', 'delete' );
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
}
