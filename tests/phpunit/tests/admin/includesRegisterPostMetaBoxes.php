<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

/**
 * Tests for register_and_do_post_meta_boxes() and the meta boxes of the featured image and the metadata.
 *
 * @group admin
 *
 * @covers ::register_and_do_post_meta_boxes
 */
class Tests_Admin_includesRegisterPostMetaBoxes extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original meta boxes.
	 *
	 * @var array
	 */
	private $original_meta_boxes;

	/**
	 * The original thumbnail support of the theme.
	 *
	 * @var mixed
	 */
	private $original_thumbnail_support;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_meta_boxes        = $GLOBALS['wp_meta_boxes'] ?? array();
		$this->original_thumbnail_support = $GLOBALS['_wp_theme_features']['post-thumbnails'] ?? null;

		$GLOBALS['wp_meta_boxes'] = array();

		add_theme_support( 'post-thumbnails' );
	}

	public function tear_down() {
		$GLOBALS['wp_meta_boxes'] = $this->original_meta_boxes;

		if ( null === $this->original_thumbnail_support ) {
			unset( $GLOBALS['_wp_theme_features']['post-thumbnails'] );
		} else {
			$GLOBALS['_wp_theme_features']['post-thumbnails'] = $this->original_thumbnail_support;
		}

		wp_dequeue_script( 'image-edit' );
		wp_dequeue_style( 'imgareaselect' );

		parent::tear_down();
	}

	/**
	 * Registers the meta boxes of a post and returns them.
	 *
	 * @param WP_Post $post The post.
	 * @return array[] The meta boxes by ID, with the context, the priority, the callback and the arguments.
	 */
	private function register( $post ) {
		set_current_screen( $post->post_type );

		register_and_do_post_meta_boxes( $post );

		$boxes = array();

		foreach ( $GLOBALS['wp_meta_boxes'][ $post->post_type ] ?? array() as $context => $priorities ) {
			foreach ( $priorities as $priority => $priority_boxes ) {
				foreach ( $priority_boxes as $id => $box ) {
					if ( false === $box ) {
						continue;
					}

					$boxes[ $id ] = array(
						'context'  => $context,
						'priority' => $priority,
						'callback' => $box['callback'],
						'args'     => $box['args'],
						'title'    => $box['title'],
					);
				}
			}
		}

		return $boxes;
	}

	/**
	 * Returns the IDs of the meta boxes, sorted.
	 *
	 * @param array[] $boxes The meta boxes.
	 * @return string[] The IDs.
	 */
	private function get_ids( $boxes ) {
		$ids = array_keys( $boxes );

		sort( $ids );

		return $ids;
	}

	/**
	 * Tests the meta boxes of a post.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_meta_boxes_of_a_post() {
		$boxes = $this->register( get_post( self::factory()->post->create() ) );

		$this->assertSame(
			array( 'authordiv', 'categorydiv', 'commentsdiv', 'commentstatusdiv', 'postcustom', 'postexcerpt', 'postimagediv', 'slugdiv', 'submitdiv', 'tagsdiv-post_tag', 'trackbacksdiv' ),
			$this->get_ids( $boxes )
		);
		$this->assertSame( 'side', $boxes['submitdiv']['context'] );
		$this->assertSame( 'post_submit_meta_box', $boxes['submitdiv']['callback'] );
		$this->assertSame( 'Publish', $boxes['submitdiv']['title'] );
		$this->assertSame( 'side', $boxes['postimagediv']['context'] );
		$this->assertSame( 'low', $boxes['postimagediv']['priority'] );
		$this->assertSame( 'post_thumbnail_meta_box', $boxes['postimagediv']['callback'] );
		$this->assertSame( 'normal', $boxes['postexcerpt']['context'] );
		$this->assertSame( 'post_excerpt_meta_box', $boxes['postexcerpt']['callback'] );
		$this->assertSame( 'post_trackback_meta_box', $boxes['trackbacksdiv']['callback'] );
		$this->assertSame( 'post_comment_status_meta_box', $boxes['commentstatusdiv']['callback'] );
		$this->assertSame( 'post_comment_meta_box', $boxes['commentsdiv']['callback'] );
		$this->assertSame( 'post_slug_meta_box', $boxes['slugdiv']['callback'] );
		$this->assertSame( 'post_author_meta_box', $boxes['authordiv']['callback'] );
		$this->assertSame( array( '__back_compat_meta_box' => true ), $boxes['submitdiv']['args'] );
	}

	/**
	 * Tests the meta boxes of a page.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_meta_boxes_of_a_page() {
		$boxes = $this->register( get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$this->assertSame(
			array( 'authordiv', 'commentsdiv', 'commentstatusdiv', 'pageparentdiv', 'postcustom', 'postimagediv', 'slugdiv', 'submitdiv' ),
			$this->get_ids( $boxes )
		);
		$this->assertSame( 'page_attributes_meta_box', $boxes['pageparentdiv']['callback'] );
		$this->assertSame( 'side', $boxes['pageparentdiv']['context'] );
		$this->assertSame( 'Page Attributes', $boxes['pageparentdiv']['title'] );
	}

	/**
	 * Tests the meta boxes of an attachment.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_meta_boxes_of_an_attachment() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$boxes = $this->register( get_post( $attachment_id ) );

		$this->assertSame( 'attachment_submit_meta_box', $boxes['submitdiv']['callback'] );
		$this->assertSame( 'Save', $boxes['submitdiv']['title'] );
		$this->assertArrayNotHasKey( 'attachment-id3', $boxes, 'An image has no metadata box.' );
		$this->assertTrue( wp_script_is( 'image-edit', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'imgareaselect', 'enqueued' ) );
		$this->assertSame( 10, has_action( 'edit_form_after_title', 'edit_form_image_editor' ) );
	}

	/**
	 * Tests the metadata meta box of an audio attachment.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_metadata_meta_box_of_an_audio_attachment() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'song.mp3',
				'post_mime_type' => 'audio/mpeg',
			)
		);

		$boxes = $this->register( get_post( $attachment_id ) );

		$this->assertSame( 'attachment_id3_data_meta_box', $boxes['attachment-id3']['callback'] );
		$this->assertSame( 'Metadata', $boxes['attachment-id3']['title'] );
		$this->assertSame( 'normal', $boxes['attachment-id3']['context'] );
	}

	/**
	 * Tests the featured image of audio and video attachments.
	 *
	 * @ticket 66605
	 *
	 * @dataProvider data_attachment_types
	 *
	 * @param string $mime_type The MIME type of the attachment.
	 * @param string $type      The type of the attachment.
	 */
	public function test_should_register_the_featured_image_of_an_audio_or_video_attachment_when_it_is_supported( $mime_type, $type ) {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'media.file',
				'post_mime_type' => $mime_type,
			)
		);

		// The theme supports the featured image of the posts only.
		$GLOBALS['_wp_theme_features']['post-thumbnails'] = array( array( 'post' ) );

		remove_post_type_support( 'attachment', 'thumbnail' );
		remove_post_type_support( "attachment:$type", 'thumbnail' );

		$this->assertArrayNotHasKey( 'postimagediv', $this->register( get_post( $attachment_id ) ), 'The attachment has no support.' );

		$GLOBALS['wp_meta_boxes'] = array();

		add_post_type_support( "attachment:$type", 'thumbnail' );

		$boxes = $this->register( get_post( $attachment_id ) );

		add_post_type_support( 'attachment', 'thumbnail' );

		$this->assertArrayHasKey( 'postimagediv', $boxes );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attachment_types() {
		return array(
			'audio' => array( 'audio/mpeg', 'audio' ),
			'video' => array( 'video/mp4', 'video' ),
		);
	}

	/**
	 * Tests the revisions meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_revisions_meta_box_with_more_than_one_revision() {
		$post_id = self::factory()->post->create( array( 'post_content' => 'One' ) );

		$this->assertArrayNotHasKey( 'revisionsdiv', $this->register( get_post( $post_id ) ), 'A post without revisions.' );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Two',
			)
		);
		wp_save_post_revision( $post_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Three',
			)
		);
		wp_save_post_revision( $post_id );

		$GLOBALS['wp_meta_boxes'] = array();

		$boxes = $this->register( get_post( $post_id ) );
		$total = wp_get_latest_revision_id_and_total_count( $post_id );

		$this->assertGreaterThan( 1, $total['count'] );
		$this->assertSame( 'post_revisions_meta_box', $boxes['revisionsdiv']['callback'] );
		$this->assertSame( 'normal', $boxes['revisionsdiv']['context'] );
		$this->assertSame(
			array(
				'revisions_count'        => $total['count'],
				'revision_id'            => $total['latest_id'],
				'__back_compat_meta_box' => true,
			),
			$boxes['submitdiv']['args']
		);
	}

	/**
	 * Creates revisions of a post.
	 *
	 * @param int $post_id The ID of the post.
	 * @param int $count   The number of revisions.
	 */
	private function create_revisions( $post_id, $count ) {
		for ( $i = 0; $i < $count; $i++ ) {
			wp_insert_post(
				array(
					'post_type'   => 'revision',
					'post_status' => 'inherit',
					'post_parent' => $post_id,
					'post_name'   => "$post_id-revision-v1-$i",
					'post_title'  => "Revision $i",
				)
			);
		}
	}

	/**
	 * Tests that one revision is not enough for the revisions meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_revisions_meta_box_with_one_revision() {
		$post_id = self::factory()->post->create();

		$this->create_revisions( $post_id, 1 );

		$this->assertArrayNotHasKey( 'revisionsdiv', $this->register( get_post( $post_id ) ) );

		$GLOBALS['wp_meta_boxes'] = array();

		$this->create_revisions( $post_id, 1 );

		$this->assertArrayHasKey( 'revisionsdiv', $this->register( get_post( $post_id ) ), 'Two revisions are enough.' );
	}

	/**
	 * Tests that a post type without the support of the revisions has no revisions meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_revisions_meta_box_without_the_support_of_the_post_type() {
		$post_id = self::factory()->post->create();

		$this->create_revisions( $post_id, 2 );

		remove_post_type_support( 'post', 'revisions' );

		$boxes = $this->register( get_post( $post_id ) );

		add_post_type_support( 'post', 'revisions' );

		$this->assertArrayNotHasKey( 'revisionsdiv', $boxes );
	}

	/**
	 * Tests that an auto draft with revisions has no revisions meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_revisions_meta_box_for_an_auto_draft_with_revisions() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'auto-draft' ) );

		$this->create_revisions( $post_id, 2 );

		$this->assertArrayNotHasKey( 'revisionsdiv', $this->register( get_post( $post_id ) ) );
	}

	/**
	 * Tests the featured image of an audio attachment for a theme that supports it.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_featured_image_of_an_audio_attachment_when_the_theme_supports_it() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'song.mp3',
				'post_mime_type' => 'audio/mpeg',
			)
		);

		remove_post_type_support( 'attachment', 'thumbnail' );
		remove_post_type_support( 'attachment:audio', 'thumbnail' );

		$GLOBALS['_wp_theme_features']['post-thumbnails'] = array( array( 'post', 'attachment:audio' ) );

		$boxes = $this->register( get_post( $attachment_id ) );

		add_post_type_support( 'attachment', 'thumbnail' );
		add_post_type_support( 'attachment:audio', 'thumbnail' );

		$this->assertArrayHasKey( 'postimagediv', $boxes );
	}

	/**
	 * Tests the page attributes meta box for a post type with page templates.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_page_attributes_meta_box_for_a_post_type_with_templates() {
		$post = get_post( self::factory()->post->create() );

		$this->assertArrayNotHasKey( 'pageparentdiv', $this->register( $post ) );

		$GLOBALS['wp_meta_boxes'] = array();

		add_filter(
			'theme_post_templates',
			static function () {
				return array( 'wide.php' => 'Wide' );
			}
		);

		$this->assertArrayHasKey( 'pageparentdiv', $this->register( $post ) );
	}

	/**
	 * Tests the meta boxes of the comments for a post with comments.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_comments_meta_box_for_a_post_with_comments() {
		remove_post_type_support( 'post', 'comments' );

		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$boxes = $this->register( get_post( $post_id ) );

		add_post_type_support( 'post', 'comments' );

		$this->assertArrayNotHasKey( 'commentstatusdiv', $boxes );
		$this->assertArrayHasKey( 'commentsdiv', $boxes );
	}

	/**
	 * Tests the discussion meta box for a post that is open for pings only.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_discussion_meta_box_for_a_post_open_for_pings() {
		remove_post_type_support( 'post', 'comments' );

		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'closed',
				'ping_status'    => 'open',
			)
		);

		$boxes = $this->register( get_post( $post_id ) );

		add_post_type_support( 'post', 'comments' );

		$this->assertArrayHasKey( 'commentstatusdiv', $boxes );
	}

	/**
	 * Tests that an auto draft has no revisions meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_revisions_meta_box_for_an_auto_draft() {
		$post = get_post( self::factory()->post->create( array( 'post_status' => 'auto-draft' ) ) );

		$this->assertArrayNotHasKey( 'revisionsdiv', $this->register( $post ) );
	}

	/**
	 * Tests the post formats meta box.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_post_formats_meta_box_when_the_theme_supports_them() {
		$original = $GLOBALS['_wp_theme_features']['post-formats'] ?? null;
		$post     = get_post( self::factory()->post->create() );

		$this->assertArrayNotHasKey( 'formatdiv', $this->register( $post ) );

		$GLOBALS['wp_meta_boxes'] = array();

		add_theme_support( 'post-formats', array( 'aside' ) );

		$boxes = $this->register( $post );

		if ( null === $original ) {
			unset( $GLOBALS['_wp_theme_features']['post-formats'] );
		} else {
			$GLOBALS['_wp_theme_features']['post-formats'] = $original;
		}

		$GLOBALS['wp_meta_boxes'] = array();

		add_theme_support( 'post-formats', array( 'aside' ) );

		$page_boxes = $this->register( get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		if ( null === $original ) {
			unset( $GLOBALS['_wp_theme_features']['post-formats'] );
		} else {
			$GLOBALS['_wp_theme_features']['post-formats'] = $original;
		}

		$this->assertArrayNotHasKey( 'formatdiv', $page_boxes, 'A page does not support the post formats.' );
		$this->assertSame( 'post_format_meta_box', $boxes['formatdiv']['callback'] );
		$this->assertSame( 'side', $boxes['formatdiv']['context'] );
		$this->assertSame( 'Format', $boxes['formatdiv']['title'] );
	}

	/**
	 * Tests the meta boxes of the taxonomies.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_meta_boxes_of_the_taxonomies() {
		register_taxonomy( 'flat_group', 'post', array( 'show_ui' => true ) );
		register_taxonomy(
			'tree_group',
			'post',
			array(
				'show_ui'      => true,
				'hierarchical' => true,
				'label'        => 'Tree groups',
			)
		);
		register_taxonomy( 'hidden_group', 'post', array( 'show_ui' => false ) );
		register_taxonomy(
			'no_box_group',
			'post',
			array(
				'show_ui'     => true,
				'meta_box_cb' => false,
			)
		);
		register_taxonomy(
			'custom_box_group',
			'post',
			array(
				'show_ui'     => true,
				'meta_box_cb' => 'post_tags_meta_box',
			)
		);

		$boxes = $this->register( get_post( self::factory()->post->create() ) );

		foreach ( array( 'flat_group', 'tree_group', 'hidden_group', 'no_box_group', 'custom_box_group' ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}

		$this->assertArrayHasKey( 'tagsdiv-flat_group', $boxes );
		$this->assertArrayHasKey( 'tree_groupdiv', $boxes );
		$this->assertArrayHasKey( 'tagsdiv-custom_box_group', $boxes );
		$this->assertArrayNotHasKey( 'tagsdiv-hidden_group', $boxes );
		$this->assertArrayNotHasKey( 'hidden_groupdiv', $boxes );
		$this->assertArrayNotHasKey( 'tagsdiv-no_box_group', $boxes );
		$this->assertSame( 'post_categories_meta_box', $boxes['categorydiv']['callback'] );
		$this->assertSame( 'side', $boxes['tree_groupdiv']['context'] );
		$this->assertSame( 'Tree groups', $boxes['tree_groupdiv']['title'] );
		$this->assertSame(
			array(
				'taxonomy'               => 'tree_group',
				'__back_compat_meta_box' => true,
			),
			$boxes['tree_groupdiv']['args']
		);
	}

	/**
	 * Tests the meta boxes of the features of the post type.
	 *
	 * @ticket 66605
	 *
	 * @dataProvider data_features
	 *
	 * @param string $feature The feature of the post type.
	 * @param string $box_id  The ID of the meta box of the feature.
	 */
	public function test_should_register_the_meta_boxes_of_the_features_of_the_post_type( $feature, $box_id ) {
		$post = get_post( self::factory()->post->create() );

		remove_post_type_support( 'post', $feature );

		$without = $this->register( $post );

		$GLOBALS['wp_meta_boxes'] = array();

		add_post_type_support( 'post', $feature );

		$with = $this->register( $post );

		$this->assertArrayNotHasKey( $box_id, $without );
		$this->assertArrayHasKey( $box_id, $with );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_features() {
		return array(
			'excerpt'       => array( 'excerpt', 'postexcerpt' ),
			'trackbacks'    => array( 'trackbacks', 'trackbacksdiv' ),
			'custom fields' => array( 'custom-fields', 'postcustom' ),
			'author'        => array( 'author', 'authordiv' ),
			'thumbnail'     => array( 'thumbnail', 'postimagediv' ),
		);
	}

	/**
	 * Tests the custom fields meta box, which is for the classic editor unless the user turned it on.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_custom_fields_meta_box_with_the_setting_of_the_user() {
		$post = get_post( self::factory()->post->create() );

		$boxes = $this->register( $post );

		$this->assertSame(
			array(
				'__back_compat_meta_box'             => true,
				'__block_editor_compatible_meta_box' => true,
			),
			$boxes['postcustom']['args']
		);

		update_user_meta( self::$admin_id, 'enable_custom_fields', '1' );

		$GLOBALS['wp_meta_boxes'] = array();

		$boxes = $this->register( $post );

		$this->assertFalse( $boxes['postcustom']['args']['__back_compat_meta_box'] );
	}

	/**
	 * Tests that the featured image needs a user who can upload files.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_featured_image_for_a_user_who_cannot_upload_files() {
		$post = get_post( self::factory()->post->create() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertArrayNotHasKey( 'postimagediv', $this->register( $post ) );
	}

	/**
	 * Tests that the featured image needs the support of the theme.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_featured_image_without_the_support_of_the_theme() {
		unset( $GLOBALS['_wp_theme_features']['post-thumbnails'] );

		$this->assertArrayNotHasKey( 'postimagediv', $this->register( get_post( self::factory()->post->create() ) ) );
	}

	/**
	 * Tests the meta boxes of the comments.
	 *
	 * @ticket 66605
	 */
	public function test_should_register_the_meta_boxes_of_the_comments_only_when_they_are_needed() {
		remove_post_type_support( 'post', 'comments' );

		$post = get_post(
			self::factory()->post->create(
				array(
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			)
		);

		$boxes = $this->register( $post );

		add_post_type_support( 'post', 'comments' );

		$this->assertArrayNotHasKey( 'commentstatusdiv', $boxes );
		$this->assertArrayNotHasKey( 'commentsdiv', $boxes );

		$GLOBALS['wp_meta_boxes'] = array();

		$boxes = $this->register( $post );

		$this->assertArrayHasKey( 'commentstatusdiv', $boxes, 'The support of the post type should be enough.' );
		$this->assertArrayHasKey( 'commentsdiv', $boxes );
	}

	/**
	 * Tests that the comments box is only registered for a post that is public or private.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_comments_meta_box_for_a_draft() {
		$post = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );

		$boxes = $this->register( $post );

		$this->assertArrayHasKey( 'commentstatusdiv', $boxes );
		$this->assertArrayNotHasKey( 'commentsdiv', $boxes );

		$GLOBALS['wp_meta_boxes'] = array();

		$private = get_post( self::factory()->post->create( array( 'post_status' => 'private' ) ) );

		$this->assertArrayHasKey( 'commentsdiv', $this->register( $private ) );
	}

	/**
	 * Tests the slug meta box for a pending post.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_slug_meta_box_of_a_pending_post_for_a_user_who_cannot_publish() {
		$post = get_post( self::factory()->post->create( array( 'post_status' => 'pending' ) ) );

		$this->assertArrayHasKey( 'slugdiv', $this->register( $post ), 'An administrator can publish.' );

		$GLOBALS['wp_meta_boxes'] = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertArrayNotHasKey( 'slugdiv', $this->register( $post ) );

		$GLOBALS['wp_meta_boxes'] = array();

		$draft = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );

		$this->assertArrayHasKey( 'slugdiv', $this->register( $draft ), 'A draft keeps the slug box.' );
	}

	/**
	 * Tests that the author meta box needs a user who can edit the posts of others.
	 *
	 * @ticket 66605
	 */
	public function test_should_not_register_the_author_meta_box_for_a_user_who_cannot_edit_others_posts() {
		$post = get_post( self::factory()->post->create() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->assertArrayNotHasKey( 'authordiv', $this->register( $post ) );
	}

	/**
	 * Tests the actions that run after the meta boxes are registered.
	 *
	 * @ticket 66605
	 */
	public function test_should_run_the_actions_of_the_meta_boxes() {
		$post   = get_post( self::factory()->post->create() );
		$events = array();

		add_action(
			'add_meta_boxes',
			static function ( $post_type, $action_post ) use ( &$events, $post ) {
				$events[] = array( 'add_meta_boxes', $post_type, $action_post === $post );
			},
			10,
			2
		);
		add_action(
			'add_meta_boxes_post',
			static function ( $action_post ) use ( &$events, $post ) {
				$events[] = array( 'add_meta_boxes_post', $action_post === $post );
			}
		);
		add_action(
			'do_meta_boxes',
			static function ( $post_type, $context, $action_post ) use ( &$events, $post ) {
				$events[] = array( 'do_meta_boxes', $post_type, $context, $action_post === $post );
			},
			10,
			3
		);

		$this->register( $post );

		$this->assertSame(
			array(
				array( 'add_meta_boxes', 'post', true ),
				array( 'add_meta_boxes_post', true ),
				array( 'do_meta_boxes', 'post', 'normal', true ),
				array( 'do_meta_boxes', 'post', 'advanced', true ),
				array( 'do_meta_boxes', 'post', 'side', true ),
			),
			$events
		);
	}

	/**
	 * Tests that a meta box added on the action is kept.
	 *
	 * @ticket 66605
	 */
	public function test_should_keep_the_meta_boxes_added_on_the_action() {
		add_action(
			'add_meta_boxes',
			static function () {
				add_meta_box( 'extra-box', 'Extra', '__return_empty_string', null, 'side' );
			}
		);

		$this->assertArrayHasKey( 'extra-box', $this->register( get_post( self::factory()->post->create() ) ) );
	}

	/**
	 * Tests the meta box of the featured image.
	 *
	 * @ticket 66605
	 *
	 * @covers ::post_thumbnail_meta_box
	 */
	public function test_post_thumbnail_meta_box_shows_the_featured_image() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'featured.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		ob_start();
		post_thumbnail_meta_box( get_post( $post_id ) );
		$without = ob_get_clean();

		set_post_thumbnail( $post_id, $attachment_id );

		ob_start();
		post_thumbnail_meta_box( get_post( $post_id ) );
		$with = ob_get_clean();

		$this->assertSame( _wp_post_thumbnail_html( '', $post_id ), $without );
		$this->assertSame( _wp_post_thumbnail_html( $attachment_id, $post_id ), $with );
		$this->assertNotSame( $without, $with );
		$this->assertStringContainsString( 'id="remove-post-thumbnail"', $with );
	}

	/**
	 * Tests the metadata meta box of an audio file.
	 *
	 * @ticket 66605
	 *
	 * @covers ::attachment_id3_data_meta_box
	 */
	public function test_attachment_id3_data_meta_box() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'song.mp3',
				'post_mime_type' => 'audio/mpeg',
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'artist' => 'The "Band"',
				'album'  => 'The Album',
			)
		);

		ob_start();
		attachment_id3_data_meta_box( get_post( $attachment_id ) );
		$output = ob_get_clean();

		$this->assertSame( count( wp_get_attachment_id3_keys( get_post( $attachment_id ), 'edit' ) ), substr_count( $output, '<input type="text"' ) );
		$this->assertStringContainsString( 'name="id3_artist" id="id3_artist" class="large-text" value="The &quot;Band&quot;"', $output );
		$this->assertStringContainsString( 'name="id3_album" id="id3_album" class="large-text" value="The Album"', $output );
		$this->assertStringContainsString( '<label for="title">Artist</label>', $output );
		$this->assertStringContainsString( '<label for="title">Album</label>', $output );
	}

	/**
	 * Tests the metadata meta box of a new attachment.
	 *
	 * @ticket 66605
	 *
	 * @covers ::attachment_id3_data_meta_box
	 */
	public function test_attachment_id3_data_meta_box_of_a_new_attachment() {
		$post            = new WP_Post( new stdClass() );
		$post->post_type = 'attachment';

		ob_start();
		attachment_id3_data_meta_box( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="id3_artist" id="id3_artist" class="large-text" value=""', $output );
		$this->assertStringContainsString( 'name="id3_album" id="id3_album" class="large-text" value=""', $output );
	}
}
