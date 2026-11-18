<?php

/**
 * Tests specific to `multisite_over_quota_message()` in multisite.
 *
 * The function is hooked to `upload_ui_over_quota` on every multisite request,
 * so it has to be available on the front end, where the admin includes are not
 * loaded.
 *
 * @group ms-required
 * @group multisite
 *
 * @covers ::multisite_over_quota_message
 */
class Tests_Multisite_MultisiteOverQuotaMessage extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_site_option( 'upload_space_check_disabled', false );
	}

	/**
	 * The default callback must exist wherever the hook is registered.
	 *
	 * @ticket 60925
	 */
	public function test_default_callback_is_callable() {
		$this->assertSame(
			10,
			has_action( 'upload_ui_over_quota', 'multisite_over_quota_message' ),
			'The default callback should be hooked to upload_ui_over_quota.'
		);
		$this->assertTrue(
			is_callable( 'multisite_over_quota_message' ),
			'The default callback should be callable.'
		);
	}

	/**
	 * The function must be defined in a file loaded on every multisite request,
	 * not in `wp-admin/includes/`, which is not loaded on the front end.
	 *
	 * The test bootstrap always loads the admin includes, so this checks where the
	 * function is defined instead of whether it exists.
	 *
	 * @ticket 60925
	 */
	public function test_function_is_defined_in_a_file_loaded_on_the_front_end() {
		$file = ( new ReflectionFunction( 'multisite_over_quota_message' ) )->getFileName();

		$this->assertSame(
			wp_normalize_path( ABSPATH . WPINC . '/ms-functions.php' ),
			wp_normalize_path( $file ),
			'multisite_over_quota_message() should be defined in wp-includes/ms-functions.php.'
		);
	}

	/**
	 * @ticket 60925
	 */
	public function test_message_includes_the_space_allocation() {
		update_option( 'blog_upload_space', 5 );

		ob_start();
		multisite_over_quota_message();
		$output = ob_get_clean();

		$this->assertSame(
			'<p>Sorry, you have used your space allocation of 5 MB. Please delete some files to upload more files.</p>',
			$output
		);
	}

	/**
	 * Printing the media templates on the front end of a site that is over its
	 * quota must not cause a fatal error.
	 *
	 * @ticket 60925
	 */
	public function test_print_media_templates_when_over_quota() {
		require_once ABSPATH . WPINC . '/media-template.php';

		update_option( 'blog_upload_space', 5 );

		add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );

		ob_start();
		wp_print_media_templates();
		$output = ob_get_clean();

		remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );

		$this->assertStringContainsString( 'Upload Limit Exceeded', $output );
		$this->assertStringContainsString( 'Sorry, you have used your space allocation of 5 MB.', $output );
	}

	public function filter_space_used_large() {
		return 10000000;
	}
}
