<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Add Meta AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_add_meta
 */
class Tests_Ajax_wpAjaxAddMeta extends WP_Ajax_UnitTestCase {

	/**
	 * @ticket 43559
	 *
	 * @covers ::add_post_meta
	 */
	public function test_wp_ajax_add_meta_allows_empty_values_on_adding() {
		$post = self::factory()->post->create();

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_id'              => $post,
			'metakeyinput'         => 'testkey',
			'metavalue'            => '',
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '', get_post_meta( $post, 'testkey', true ) );
	}

	/**
	 * @ticket 43559
	 *
	 * @covers ::update_metadata_by_mid
	 */
	public function test_wp_ajax_add_meta_allows_empty_values_on_updating() {
		$post = self::factory()->post->create();

		$meta_id = add_post_meta( $post, 'testkey', 'hello' );

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
			'post_id'              => $post,
			'meta'                 => array(
				$meta_id => array(
					'key'   => 'testkey',
					'value' => '',
				),
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( '', get_post_meta( $post, 'testkey', true ) );
	}

	/**
	 * Adding meta to an auto-draft should only create the meta once.
	 *
	 * @ticket 66016
	 */
	public function test_adding_meta_to_an_auto_draft_should_not_duplicate_the_meta() {
		$post = self::factory()->post->create(
			array(
				'post_status' => 'auto-draft',
			)
		);

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_id'              => $post,
			'metakeyinput'         => 'testkey',
			'metavalue'            => 'testvalue',
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertSame( array( 'testvalue' ), get_post_meta( $post, 'testkey' ) );
	}

	/**
	 * Adding meta to an auto-draft should save the post as a draft
	 * without changing its comment or ping status.
	 *
	 * @ticket 66016
	 */
	public function test_adding_meta_to_an_auto_draft_should_not_change_the_comment_and_ping_status() {
		$post = self::factory()->post->create(
			array(
				'post_status'    => 'auto-draft',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$_POST = array(
			'post_id'              => $post,
			'metakeyinput'         => 'testkey',
			'metavalue'            => 'testvalue',
			'_ajax_nonce-add-meta' => wp_create_nonce( 'add-meta' ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'add-meta' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$post = get_post( $post );

		$this->assertSame( 'draft', $post->post_status, 'The auto-draft should have been saved as a draft.' );
		$this->assertSame( 'open', $post->comment_status, 'The comment status of the post should not have changed.' );
		$this->assertSame( 'open', $post->ping_status, 'The ping status of the post should not have changed.' );
	}
}
