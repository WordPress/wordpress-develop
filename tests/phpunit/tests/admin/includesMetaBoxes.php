<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesMetaBoxes extends WP_UnitTestCase {
	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

		wp_set_current_user( self::$admin_id );
	}

	public function test_post_submit_meta_box_status_controls_are_rendered_as_radios() {
		$post   = self::factory()->post->create_and_get( array( 'post_status' => 'draft' ) );
		$output = $this->get_post_submit_meta_box_output( $post );

		$this->assertStringNotContainsString( '<select name="post_status" id="post_status">', $output );
		$this->assertStringContainsString( '<fieldset id="post_status" class="post-status-options">', $output );
		$this->assertMatchesRegularExpression( '/<legend class="screen-reader-text">\s*Set status\s*<\/legend>/', $output );
		$this->assertStringContainsString( 'type="radio" name="post_status" id="post-status-radio-pending" value="pending"', $output );
		$this->assertStringContainsString( 'type="radio" name="post_status" id="post-status-radio-draft" value="draft"', $output );

		$this->assertMatchesRegularExpression(
			'/<fieldset id="post_status" class="post-status-options">.*post-status-radio-pending.*post-status-radio-draft.*<\/fieldset>/s',
			$output
		);
		$this->assertMatchesRegularExpression(
			'/<\/fieldset>\s*<p>\s*<a href="#post_status" class="save-post-status hide-if-no-js button">OK<\/a>\s*<a href="#post_status" class="cancel-post-status hide-if-no-js button-cancel">Cancel<\/a>\s*<\/p>/',
			$output
		);
	}

	private function get_post_submit_meta_box_output( WP_Post $post ) {
		$GLOBALS['post'] = $post;

		ob_start();
		post_submit_meta_box( $post );
		return ob_get_clean();
	}
}
