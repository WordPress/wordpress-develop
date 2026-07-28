<?php
/**
 * @group post
 * @group template
 */
class Tests_Post_GetThePasswordForm extends WP_UnitTestCase {

	/**
	 * Tests that the_password_form_redirect_url filter can modify the redirect destination.
	 *
	 * @ticket 64785
	 */
	public function test_the_password_form_redirect_url_filter() {
		$post_id    = self::factory()->post->create();
		$custom_url = 'https://example.com/custom-preview-link/';

		$callback = function () use ( $custom_url ) {
			return $custom_url;
		};

		add_filter( 'the_password_form_redirect_url', $callback );

		$form = get_the_password_form( $post_id );

		$this->assertStringContainsString( 'value="' . $custom_url . '"', $form );
	}
}
