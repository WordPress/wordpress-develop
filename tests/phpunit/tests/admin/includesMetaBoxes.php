<?php
/**
 * @group admin
 */
class Tests_Admin_IncludesMetaBoxes extends WP_UnitTestCase {
	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	public static $editor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public function test_post_submit_meta_box_renders_visibility_controls() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$editor_id,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( self::$editor_id );

		$output = $this->render_post_submit_meta_box( $post );

		$this->assertStringContainsString( '<fieldset id="post-visibility-fieldset">', $output );
		$this->assertMatchesRegularExpression( '/name="visibility" id="visibility-radio-public" value="public"\\s+checked=\'checked\'/', $output );
		$this->assertStringContainsString( 'name="visibility" id="visibility-radio-password" value="password"', $output );
		$this->assertStringContainsString( 'name="visibility" id="visibility-radio-private" value="private"', $output );
		$this->assertStringContainsString( 'name="hidden_post_visibility" id="hidden-post-visibility" value="public"', $output );
		$this->assertStringContainsString( 'id="sticky-span"', $output );
		$this->assertStringContainsString( 'id="password-span"', $output );
		$this->assertStringContainsString( 'name="post_password" id="post_password"', $output );
		$this->assertStringNotContainsString( '<select name="visibility" id="post-visibility">', $output );
		$this->assertStringNotContainsString( 'name="visibility_password"', $output );
	}

	private function render_post_submit_meta_box( WP_Post $post ) {
		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

		$previous_post   = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post;

		ob_start();
		post_submit_meta_box( $post );
		$output = ob_get_clean();

		if ( null === $previous_post ) {
			unset( $GLOBALS['post'] );
		} else {
			$GLOBALS['post'] = $previous_post;
		}

		return $output;
	}
}
