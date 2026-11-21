<?php

declare( strict_types = 1 );

/**
 * Tests for the core media abilities (`core/media-*`).
 *
 * @group abilities-api
 * @covers WP_Abilities_Media
 */
class Tests_Abilities_API_WpRegisterCoreMediaAbilities extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private static $author_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Attachment IDs created for query tests.
	 *
	 * @var int[]
	 */
	private static $attachment_ids = array();

	/**
	 * Parent post ID for attachment tests.
	 *
	 * @var int
	 */
	private static $parent_post_id;

	/**
	 * Sets up shared users, attachments, and core abilities before the class runs.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$parent_post_id = $factory->post->create(
			array(
				'post_author' => self::$admin_id,
				'post_status' => 'publish',
			)
		);

		$image_id = $factory->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			self::$parent_post_id
		);
		wp_update_post(
			array(
				'ID'           => $image_id,
				'post_author'  => self::$admin_id,
				'post_title'   => 'Canola Field',
				'post_name'    => 'canola-field',
				'post_excerpt' => 'Yellow canola',
				'post_content' => 'Full description of canola.',
			)
		);
		update_post_meta( $image_id, '_wp_attachment_image_alt', 'Yellow canola field' );

		$pdf_id = $factory->attachment->create_object(
			array(
				'file'           => 'press-kit.pdf',
				'post_parent'    => 0,
				'post_author'    => self::$author_id,
				'post_title'     => 'Press Kit',
				'post_name'      => 'press-kit',
				'post_mime_type' => 'application/pdf',
			)
		);

		self::$attachment_ids = array(
			'image' => $image_id,
			'pdf'   => $pdf_id,
		);
	}

	/**
	 * Registers core categories and abilities before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wp_current_filter;

		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		try {
			wp_register_core_ability_categories();
		} finally {
			array_pop( $wp_current_filter );
		}

		$wp_current_filter[] = 'wp_abilities_api_init';
		try {
			wp_register_core_abilities();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Unregisters core abilities and categories after class.
	 */
	public static function tear_down_after_class(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down_after_class();
	}

	/**
	 * Tests that all four media abilities are registered with expected annotations.
	 *
	 * @ticket 66719
	 */
	public function test_media_abilities_are_registered(): void {
		$expected = array(
			'core/media-query'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'core/media-upload' => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
			'core/media-update' => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
			'core/media-delete' => array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			),
		);

		foreach ( $expected as $name => $annotations ) {
			$ability = wp_get_ability( $name );
			$this->assertInstanceOf( WP_Ability::class, $ability, "{$name} should be registered." );
			$this->assertSame( 'content', $ability->get_category(), "{$name} should use the content category." );
			$this->assertSame( $annotations, $ability->get_meta_item( 'annotations' ), "{$name} should have expected annotations." );
		}
	}

	/**
	 * Tests that `core/media-query` denies unauthenticated users.
	 *
	 * @ticket 66719
	 */
	public function test_media_query_requires_authenticated_user(): void {
		wp_set_current_user( 0 );

		$result = wp_get_ability( 'core/media-query' )->execute( array() );

		$this->assertWPError( $result, 'Unauthenticated user should be denied.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'Expected ability_invalid_permissions error code.' );
	}

	/**
	 * Tests that `core/media-query` rejects trash status filtering for subscribers.
	 *
	 * @ticket 66719
	 */
	public function test_media_query_forbids_trash_filter_for_subscriber(): void {
		wp_set_current_user( self::$subscriber_id );

		$result = wp_get_ability( 'core/media-query' )->execute(
			array(
				'where' => array( 'status' => array( 'trash' ) ),
			)
		);

		$this->assertWPError( $result, 'Subscriber should not be allowed to filter by trash.' );
		$this->assertSame( 'media_cannot_filter_by_status', $result->get_error_code(), 'Expected media_cannot_filter_by_status error code.' );
	}

	/**
	 * Tests that `core/media-query` filters by media type, parent post, and projects fields.
	 *
	 * @ticket 66719
	 */
	public function test_media_query_filters_and_projects_fields(): void {
		wp_set_current_user( self::$admin_id );

		$result = wp_get_ability( 'core/media-query' )->execute(
			array(
				'where'  => array(
					'media_type' => array( 'image' ),
					'post'       => self::$parent_post_id,
				),
				'fields' => array(
					'title.rendered',
					'alt_text',
					'media_details.width',
					'media_details.height',
					'post',
				),
			)
		);

		$this->assertIsArray( $result, 'Media query should return an array.' );
		$this->assertCount( 1, $result['items'], 'Should return one matching image attachment.' );

		$item = $result['items'][0];
		$this->assertSame( self::$attachment_ids['image'], $item['id'], 'Returned ID should match image attachment.' );
		$this->assertSame( array( 'rendered' => 'Canola Field' ), $item['title'], 'Title should include only rendered.' );
		$this->assertSame( 'Yellow canola field', $item['alt_text'], 'Alt text should match.' );
		$this->assertGreaterThan( 0, $item['media_details']['width'], 'Image width should be positive.' );
		$this->assertGreaterThan( 0, $item['media_details']['height'], 'Image height should be positive.' );
		$this->assertSame( self::$parent_post_id, $item['post'], 'Parent post ID should match.' );
		$this->assertArrayNotHasKey( 'mime_type', $item, 'Unrequested fields should be omitted.' );
	}

	/**
	 * Tests that `core/media-query` omits raw fields when user cannot edit the attachment.
	 *
	 * @ticket 66719
	 */
	public function test_media_query_hides_raw_fields_without_edit_cap(): void {
		wp_set_current_user( self::$subscriber_id );

		$result = wp_get_ability( 'core/media-query' )->execute(
			array(
				'where'  => array( 'ids' => array( self::$attachment_ids['image'] ) ),
				'fields' => array( 'title', 'caption', 'description' ),
			)
		);

		$this->assertIsArray( $result, 'Subscriber should be able to query readable media.' );
		$item = $result['items'][0];
		$this->assertArrayNotHasKey( 'raw', $item['title'], 'Subscribers should not receive title.raw.' );
		$this->assertArrayNotHasKey( 'raw', $item['caption'], 'Subscribers should not receive caption.raw.' );
		$this->assertArrayNotHasKey( 'raw', $item['description'], 'Subscribers should not receive description.raw.' );
	}

	/**
	 * Tests uploading a base64 image with metadata via `core/media-upload`.
	 *
	 * @ticket 66719
	 */
	public function test_media_upload_from_base64(): void {
		wp_set_current_user( self::$admin_id );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( DIR_TESTDATA . '/images/test-image.png' );
		$this->assertIsString( $bytes, 'Fixture PNG should be readable.' );

		$result = wp_get_ability( 'core/media-upload' )->execute(
			array(
				'data'   => array(
					'file'        => array(
						'base64'   => base64_encode( $bytes ),
						'filename' => 'uploaded-test.png',
					),
					'title'       => 'Uploaded PNG',
					'caption'     => 'PNG caption',
					'description' => 'PNG description',
					'alt_text'    => 'PNG alt',
					'slug'        => 'uploaded-png-slug',
					'post'        => self::$parent_post_id,
				),
				'fields' => array( 'title', 'alt_text', 'mime_type', 'media_type', 'post', 'slug' ),
			)
		);

		$this->assertIsArray( $result, 'Base64 upload should succeed.' );
		$this->assertSame( 'Uploaded PNG', $result['title']['raw'], 'Uploaded title should match.' );
		$this->assertSame( 'PNG alt', $result['alt_text'], 'Uploaded alt text should match.' );
		$this->assertSame( 'image/png', $result['mime_type'], 'Uploaded MIME type should be image/png.' );
		$this->assertSame( 'image', $result['media_type'], 'Uploaded media_type should be image.' );
		$this->assertSame( self::$parent_post_id, $result['post'], 'Uploaded post parent should match.' );
		$this->assertSame( 'uploaded-png-slug', $result['slug'], 'Uploaded slug should match.' );

		wp_delete_attachment( $result['id'], true );
	}

	/**
	 * Tests that `core/media-upload` rejects non-image URLs.
	 *
	 * @ticket 66719
	 */
	public function test_media_upload_rejects_non_image_url(): void {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args ) {
				if ( ! empty( $args['filename'] ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					file_put_contents( $args['filename'], '%PDF-1.4 test' );
				}
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/pdf',
					),
					'body'     => '%PDF-1.4 test',
				);
			},
			10,
			2
		);

		$result = wp_get_ability( 'core/media-upload' )->execute(
			array(
				'data' => array(
					'file' => array(
						'url' => 'https://example.org/press-kit.pdf',
					),
				),
			)
		);

		$this->assertWPError( $result, 'Non-image URL upload should fail.' );
		$this->assertSame( 'media_invalid_file', $result->get_error_code(), 'Expected media_invalid_file error code.' );
	}

	/**
	 * Tests `core/media-update` updates metadata and detaches parent post.
	 *
	 * @ticket 66719
	 */
	public function test_media_update_metadata_and_detach(): void {
		wp_set_current_user( self::$admin_id );

		$result = wp_get_ability( 'core/media-update' )->execute(
			array(
				'where'  => array( 'id' => self::$attachment_ids['image'] ),
				'data'   => array(
					'caption'  => 'Updated caption',
					'alt_text' => 'Updated alt text',
					'post'     => 0,
				),
				'fields' => array( 'caption', 'alt_text', 'post' ),
			)
		);

		$this->assertIsArray( $result, 'Media update should succeed.' );
		$this->assertSame( 'Updated caption', $result['caption']['raw'], 'Caption raw should be updated.' );
		$this->assertSame( 'Updated alt text', $result['alt_text'], 'Alt text should be updated.' );
		$this->assertSame( 0, $result['post'], 'Post parent 0 should detach the attachment.' );
	}

	/**
	 * Tests that `core/media-update` rejects attaching to a post the user cannot edit.
	 *
	 * @ticket 66719
	 */
	public function test_media_update_forbids_attaching_to_uneditable_post(): void {
		wp_set_current_user( self::$author_id );

		$result = wp_get_ability( 'core/media-update' )->execute(
			array(
				'where' => array( 'id' => self::$attachment_ids['pdf'] ),
				'data'  => array( 'post' => self::$parent_post_id ),
			)
		);

		$this->assertWPError( $result, 'Author should not attach media to another user post.' );
		$this->assertSame( 'media_cannot_attach', $result->get_error_code(), 'Expected media_cannot_attach error code.' );
	}

	/**
	 * Tests that `core/media-delete` requires `permanent: true` when MEDIA_TRASH is disabled,
	 * and permanently deletes when `permanent: true`.
	 *
	 * @ticket 66719
	 */
	public function test_media_delete_requires_permanent_without_trash_and_deletes(): void {
		wp_set_current_user( self::$admin_id );

		$temp_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'temp-delete.txt',
				'post_author'    => self::$admin_id,
				'post_mime_type' => 'text/plain',
			)
		);

		$trash_attempt = wp_get_ability( 'core/media-delete' )->execute(
			array(
				'where' => array( 'id' => $temp_id ),
			)
		);

		$this->assertWPError( $trash_attempt, 'Trashing should fail when MEDIA_TRASH is disabled.' );
		$this->assertSame( 'media_trash_not_supported', $trash_attempt->get_error_code(), 'Expected media_trash_not_supported error code.' );

		$delete_result = wp_get_ability( 'core/media-delete' )->execute(
			array(
				'where'     => array( 'id' => $temp_id ),
				'permanent' => true,
			)
		);

		$this->assertSame( array( 'outcome' => 'deleted' ), $delete_result, 'Permanent delete should return deleted outcome.' );
		$this->assertNull( get_post( $temp_id ), 'Attachment should no longer exist after permanent delete.' );
	}
}
