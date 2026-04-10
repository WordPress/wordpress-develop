<?php

/**
 * Tests for attachment page redirect when wp_attachment_pages_enabled is disabled.
 *
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_AttachmentRedirect extends WP_Canonical_UnitTestCase {

	/**
	 * Attachment post objects and related fixtures.
	 */
	public static $unattached;
	public static $public_parent;
	public static $attached_to_public;
	public static $private_parent;
	public static $attached_to_private;
	public static $draft_parent;
	public static $attached_to_draft;
	public static $editor_user;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_user = $factory->user->create( array( 'role' => 'editor' ) );

		// Unattached attachment.
		self::$unattached = $factory->post->create_and_get(
			array(
				'post_type'   => 'attachment',
				'post_title'  => 'Unattached Image',
				'post_name'   => 'unattached-image',
				'post_status' => 'inherit',
				'post_parent' => 0,
			)
		);
		update_post_meta( self::$unattached->ID, '_wp_attached_file', '2025/01/unattached-image.jpg' );

		// Attachment on a public post.
		self::$public_parent = $factory->post->create_and_get(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Public Post',
				'post_name'   => 'public-post',
				'post_status' => 'publish',
			)
		);
		self::$attached_to_public = $factory->post->create_and_get(
			array(
				'post_type'   => 'attachment',
				'post_title'  => 'Public Attached Image',
				'post_name'   => 'public-attached-image',
				'post_status' => 'inherit',
				'post_parent' => self::$public_parent->ID,
			)
		);
		update_post_meta( self::$attached_to_public->ID, '_wp_attached_file', '2025/01/public-attached-image.jpg' );

		// Attachment on a private post.
		self::$private_parent = $factory->post->create_and_get(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Private Post',
				'post_name'   => 'private-post',
				'post_status' => 'private',
				'post_author' => self::$editor_user,
			)
		);
		self::$attached_to_private = $factory->post->create_and_get(
			array(
				'post_type'   => 'attachment',
				'post_title'  => 'Private Attached Image',
				'post_name'   => 'private-attached-image',
				'post_status' => 'inherit',
				'post_parent' => self::$private_parent->ID,
			)
		);
		update_post_meta( self::$attached_to_private->ID, '_wp_attached_file', '2025/01/private-attached-image.jpg' );

		// Attachment on a draft post.
		self::$draft_parent = $factory->post->create_and_get(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Draft Post',
				'post_name'   => 'draft-post',
				'post_status' => 'draft',
				'post_author' => self::$editor_user,
			)
		);
		self::$attached_to_draft = $factory->post->create_and_get(
			array(
				'post_type'   => 'attachment',
				'post_title'  => 'Draft Attached Image',
				'post_name'   => 'draft-attached-image',
				'post_status' => 'inherit',
				'post_parent' => self::$draft_parent->ID,
			)
		);
		update_post_meta( self::$attached_to_draft->ID, '_wp_attached_file', '2025/01/draft-attached-image.jpg' );
	}

	/**
	 * Helper to get the expected redirect path for an attachment.
	 */
	private function get_expected_path( $attachment_id ) {
		return parse_url( wp_get_attachment_url( $attachment_id ), PHP_URL_PATH );
	}

	// -------------------------------------------------------------------------
	// Unattached attachment tests.
	// -------------------------------------------------------------------------

	/**
	 * Pretty permalink slug-based attachment URLs should redirect to the file URL
	 * when wp_attachment_pages_enabled is 0.
	 *
	 * This is the primary regression test: get_query_var( 'attachment_id' ) is only
	 * populated for ?attachment_id=123 URLs, not slug-based URLs. The fix falls back
	 * to get_queried_object_id().
	 */
	public function test_unattached_slug_url_redirects_when_pages_disabled() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertCanonical( '/unattached-image/', $this->get_expected_path( self::$unattached->ID ) );
	}

	/**
	 * Query string ?attachment_id=ID should also redirect when pages are disabled.
	 */
	public function test_unattached_query_var_url_redirects_when_pages_disabled() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertCanonical( '/?attachment_id=' . self::$unattached->ID, $this->get_expected_path( self::$unattached->ID ) );
	}

	// -------------------------------------------------------------------------
	// Attached to a public post.
	// -------------------------------------------------------------------------

	/**
	 * Attachment on a public post should redirect via slug URL.
	 */
	public function test_attached_to_public_post_slug_url_redirects() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertCanonical( '/public-attached-image/', $this->get_expected_path( self::$attached_to_public->ID ) );
	}

	// -------------------------------------------------------------------------
	// Attached to a private post — logged out (should NOT redirect).
	// -------------------------------------------------------------------------

	/**
	 * Attachment on a private post should not redirect for anonymous users,
	 * to avoid leaking the file URL.
	 */
	public function test_attached_to_private_post_no_redirect_for_anonymous() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( 0 );

		$this->assertCanonical( '/private-attached-image/', '/private-attached-image/' );
	}

	// -------------------------------------------------------------------------
	// Attached to a private post — authorized user (should redirect).
	// -------------------------------------------------------------------------

	/**
	 * Attachment on a private post should redirect for a user who can read it.
	 */
	public function test_attached_to_private_post_redirects_for_authorized_user() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::$editor_user );

		$this->assertCanonical( '/private-attached-image/', $this->get_expected_path( self::$attached_to_private->ID ) );
	}

	// -------------------------------------------------------------------------
	// Attached to a draft post (should NOT redirect).
	// -------------------------------------------------------------------------

	/**
	 * Attachment on a draft post should not redirect for anonymous users.
	 */
	public function test_attached_to_draft_post_no_redirect_for_anonymous() {
		update_option( 'wp_attachment_pages_enabled', 0 );
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( 0 );

		$this->assertCanonical( '/draft-attached-image/', '/draft-attached-image/' );
	}

	// -------------------------------------------------------------------------
	// Pages enabled — should NOT redirect.
	// -------------------------------------------------------------------------

	/**
	 * When attachment pages are enabled, slug URLs should not redirect to the file.
	 */
	public function test_no_redirect_when_attachment_pages_enabled() {
		update_option( 'wp_attachment_pages_enabled', 1 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertCanonical( '/unattached-image/', '/unattached-image/' );
	}
}
