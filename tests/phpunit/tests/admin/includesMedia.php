<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesMedia extends WP_UnitTestCase {
	/**
	 * The ID of an administrator user.
	 *
	 * @var int
	 */
	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';

		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
		set_current_screen( 'post.php' );
	}

	public function tear_down(): void {
		unset( $_GET['image-editor'] );
		parent::tear_down();
	}

	/**
	 * @ticket 64929
	 */
	public function test_edit_form_image_editor_skips_fallback_link_for_non_previewable_video() {
		$attachment_id = $this->create_upload_attachment_from_contents( 'sample.avi', 'RIFF0000AVI LIST' );
		$post          = get_post( $attachment_id );

		ob_start();
		edit_form_image_editor( $post );
		$markup = ob_get_clean();

		$this->assertStringNotContainsString( 'wp-embedded-video', $markup );
		$this->assertStringNotContainsString( wp_get_attachment_url( $attachment_id ), $markup );
	}

	/**
	 * @ticket 64929
	 */
	public function test_edit_form_image_editor_keeps_preview_for_supported_video() {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/uploads/small-video.mp4' );
		$post          = get_post( $attachment_id );

		ob_start();
		edit_form_image_editor( $post );
		$markup = ob_get_clean();

		$this->assertStringContainsString( '<video', $markup );
		$this->assertStringNotContainsString( 'wp-embedded-video', $markup );
	}

	/**
	 * Creates an uploaded attachment from raw file contents.
	 *
	 * @param string $filename The file name to use for the upload.
	 * @param string $contents The file contents.
	 * @return int Attachment ID.
	 */
	private function create_upload_attachment_from_contents( $filename, $contents ) {
		$temp_file = trailingslashit( get_temp_dir() ) . wp_generate_password( 8, false ) . '-' . $filename;
		file_put_contents( $temp_file, $contents );

		$attachment_id = self::factory()->attachment->create_upload_object( $temp_file );

		unlink( $temp_file );

		$this->assertIsInt( $attachment_id );

		return $attachment_id;
	}
}
