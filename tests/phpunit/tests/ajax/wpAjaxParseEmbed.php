<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests the capability checks in wp_ajax_parse_embed().
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 * @group oembed
 *
 * @covers ::wp_ajax_parse_embed
 */
class Tests_Ajax_wpAjaxParseEmbed extends WP_Ajax_UnitTestCase {

	const EMBED_URL = 'https://ticket-44399.example.org/video/1';

	private static $contributor;
	private static $subscriber;
	private static $editor_post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$contributor = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$subscriber  = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$editor_post = $factory->post->create(
			array(
				'post_author' => $factory->user->create( array( 'role' => 'editor' ) ),
				'post_status' => 'publish',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		// Resolve the test URL locally so no HTTP request is made.
		wp_embed_register_handler(
			'ticket_44399',
			'#^https://ticket-44399\.example\.org/video/(\d+)$#',
			static function ( $matches ) {
				return '<div class="ticket-44399-embed">' . $matches[1] . '</div>';
			}
		);
	}

	public function tear_down() {
		wp_embed_unregister_handler( 'ticket_44399' );
		parent::tear_down();
	}

	/**
	 * Makes a parse-embed request and returns the decoded response.
	 *
	 * @param int $post_id Optional. Post ID to send as post_ID. Default 0.
	 * @return array Decoded JSON response.
	 */
	private function parse_embed( $post_id = 0 ) {
		$_POST = array(
			'action'    => 'parse-embed',
			'shortcode' => '[embed]' . self::EMBED_URL . '[/embed]',
		);

		if ( $post_id ) {
			$_POST['post_ID'] = $post_id;
		}

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * @ticket 44399
	 */
	public function test_user_with_edit_posts_can_parse_embed_without_post() {
		wp_set_current_user( self::$contributor );

		$response = $this->parse_embed();

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'ticket-44399-embed', $response['data']['body'] );
	}

	/**
	 * @ticket 44399
	 */
	public function test_user_without_edit_posts_cannot_parse_embed_without_post() {
		wp_set_current_user( self::$subscriber );

		$response = $this->parse_embed();

		$this->assertFalse( $response['success'] );
		$this->assertArrayNotHasKey( 'data', $response );
	}

	/**
	 * @ticket 44399
	 */
	public function test_user_cannot_parse_embed_for_post_they_cannot_edit() {
		wp_set_current_user( self::$contributor );

		$response = $this->parse_embed( self::$editor_post );

		$this->assertFalse( $response['success'] );
		$this->assertArrayNotHasKey( 'data', $response );
	}

	/**
	 * @ticket 44399
	 */
	public function test_embed_url_meta_cap_can_be_remapped() {
		wp_set_current_user( self::$subscriber );

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) {
				return 'embed_url' === $cap ? array( 'read' ) : $caps;
			},
			10,
			2
		);

		$response = $this->parse_embed();

		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'ticket-44399-embed', $response['data']['body'] );
	}
}
