<?php

/**
 * Admin ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests for the wp_ajax_find_posts() AJAX handler.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_find_posts
 */
class Tests_Ajax_wpAjaxFindPosts extends WP_Ajax_UnitTestCase {

	/**
	 * A user who can upload files but cannot edit other users' posts (Author).
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Another user whose private and draft content must stay hidden (Editor).
	 *
	 * @var int
	 */
	protected static $other_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
		self::$other_id  = $factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Runs the find-posts handler and returns the success HTML, or '' on error.
	 *
	 * @param string $search Value for the `ps` search field.
	 * @return string
	 */
	protected function find_posts( $search ) {
		$_POST                = array();
		$_POST['ps']          = $search;
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );

		$html = '';
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {};
			}
		);

		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		if ( is_array( $response ) && ! empty( $response['success'] ) ) {
			$html = $response['data'];
		}

		return $html;
	}

	/**
	 * An Author must not see another user's private post through find-posts.
	 *
	 * @ticket 40340
	 */
	public function test_private_post_of_other_user_is_hidden() {
		$title = 'wpAjaxFindPosts private ' . __FUNCTION__;
		self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'private',
				'post_author' => self::$other_id,
			)
		);

		wp_set_current_user( self::$author_id );
		$html = $this->find_posts( $title );

		$this->assertStringNotContainsString( $title, $html );
	}

	/**
	 * An Author must not see another user's draft through find-posts.
	 *
	 * @ticket 40340
	 */
	public function test_draft_post_of_other_user_is_hidden() {
		$title = 'wpAjaxFindPosts draft ' . __FUNCTION__;
		self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'draft',
				'post_author' => self::$other_id,
			)
		);

		wp_set_current_user( self::$author_id );
		$html = $this->find_posts( $title );

		$this->assertStringNotContainsString( $title, $html );
	}

	/**
	 * The author's own editable posts still show up, so the modal keeps working.
	 *
	 * @ticket 40340
	 */
	public function test_own_post_is_still_listed() {
		$title = 'wpAjaxFindPosts own ' . __FUNCTION__;
		self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => self::$author_id,
			)
		);

		wp_set_current_user( self::$author_id );
		$html = $this->find_posts( $title );

		$this->assertStringContainsString( $title, $html );
	}

	/**
	 * A user who can edit others' posts (Editor) still sees their private posts.
	 *
	 * @ticket 40340
	 */
	public function test_editor_still_sees_private_posts() {
		$title = 'wpAjaxFindPosts editor ' . __FUNCTION__;
		self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'private',
				'post_author' => self::$other_id,
			)
		);

		wp_set_current_user( self::$other_id );
		$html = $this->find_posts( $title );

		$this->assertStringContainsString( $title, $html );
	}
}
