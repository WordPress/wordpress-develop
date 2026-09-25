<?php

/**
 * Test the `trackback_response()` function.
 *
 * @group trackback
 * @covers ::trackback_response
 */
class Tests_Trackback_trackbackResponse extends WP_UnitTestCase {

	/**
	 * Post used to load wp-trackback.php and define trackback_response().
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Loads wp-trackback.php with a valid request so its function definitions are available.
	 */
	public function set_up() {
		parent::set_up();

		global $wp, $wpdb;

		$this->post_id = $this->factory()->post->create();
		$wp            = new stdClass();
		$post_id       = $this->post_id;
		$old_get       = $_GET;
		$old_post      = $_POST;

		$_GET  = array( 'tb_id' => $this->post_id );
		$_POST = array(
			'url'       => 'https://example.com/trackback',
			'title'     => 'Trackback title',
			'blog_name' => 'Trackback blog',
		);

		if ( ! function_exists( 'trackback_response' ) ) {
			set_error_handler(
				static function ( $severity, $message ) {
					return E_WARNING === $severity && str_contains( $message, 'Cannot modify header information' );
				}
			);
			ob_start();
			require ABSPATH . 'wp-trackback.php';
			ob_end_clean();
			restore_error_handler();
		}

		$_GET  = $old_get;
		$_POST = $old_post;
	}

	/**
	 * Tests that trackback_response() returns a successful XML response.
	 *
	 * @ticket 66011
	 */
	public function test_trackback_response_success() {
		set_error_handler(
			static function ( $severity, $message ) {
				return E_WARNING === $severity && str_contains( $message, 'Cannot modify header information' );
			}
		);
		ob_start();
		trackback_response();
		$response = ob_get_clean();
		restore_error_handler();

		$this->assertSame(
			"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<response>\n<error>0</error>\n</response>",
			$response
		);
	}
}
