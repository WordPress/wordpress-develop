<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Quick Edit AJAX functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_inline_save
 */
class Tests_Ajax_wpAjaxInlineSave extends WP_Ajax_UnitTestCase {

	/**
	 * @ticket 26948
	 *
	 * @covers ::edit_post
	 */
	public function test_dont_process_terms_if_taxonomy_does_not_allow_show_on_quick_edit() {
		register_taxonomy(
			'wptests_tax_1',
			'post',
			array(
				'show_in_quick_edit' => false,
				'hierarchical'       => true,
			)
		);
		register_taxonomy(
			'wptests_tax_2',
			'post',
			array(
				'show_in_quick_edit' => true,
				'hierarchical'       => true,
			)
		);

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax_1',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax_2',
			)
		);

		// Become an administrator.
		$this->_setRole( 'administrator' );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => get_current_user_id(),
			)
		);

		// Set up a request.
		$_POST['_inline_edit'] = wp_create_nonce( 'inlineeditnonce' );
		$_POST['post_ID']      = $post->ID;
		$_POST['post_type']    = $post->post_type;
		$_POST['content']      = $post->post_content;
		$_POST['excerpt']      = $post->post_excerpt;
		$_POST['_status']      = $post->post_status;
		$_POST['post_status']  = $post->post_status;
		$_POST['screen']       = 'post';
		$_POST['post_view']    = 'excerpt';
		$_POST['tax_input']    = array(
			'wptests_tax_1' => array( $t1 ),
			'wptests_tax_2' => array( $t2 ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// 'wptests_tax_1' terms should have been refused.
		$post_terms_1 = wp_get_object_terms( $post->ID, 'wptests_tax_1' );
		$this->assertEmpty( $post_terms_1 );

		// 'wptests_tax_2' terms should have been added successfully.
		$post_terms_2 = wp_get_object_terms( $post->ID, 'wptests_tax_2' );
		$this->assertSameSets( array( $t2 ), wp_list_pluck( $post_terms_2, 'term_id' ) );
	}

	/**
	 * When updating a draft in quick edit mode, it should not set the publish date of the post if the date passed is unchanged.
	 *
	 * @ticket 19907
	 *
	 * @covers ::edit_post
	 */
	public function test_quick_edit_draft_should_not_set_publish_date() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		$user = get_current_user_id();

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_author' => $user,
			)
		);

		$this->assertSame( 'draft', $post->post_status );

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		// Set up a request.
		$_POST['_inline_edit'] = wp_create_nonce( 'inlineeditnonce' );
		$_POST['post_ID']      = $post->ID;
		$_POST['post_type']    = 'post';
		$_POST['content']      = 'content test';
		$_POST['excerpt']      = 'excerpt test';
		$_POST['_status']      = $post->post_status;
		$_POST['post_status']  = $post->post_status;
		$_POST['post_author']  = $user;
		$_POST['screen']       = 'edit-post';
		$_POST['post_view']    = 'list';
		$_POST['edit_date']    = 'false';
		$_POST['mm']           = get_the_date( 'm', $post );
		$_POST['jj']           = get_the_date( 'd', $post );
		$_POST['aa']           = get_the_date( 'Y', $post );
		$_POST['hh']           = get_the_date( 'H', $post );
		$_POST['mn']           = get_the_date( 'i', $post );
		$_POST['ss']           = get_the_date( 's', $post );

		// Make the request.
		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$post = get_post( $post->ID );

		$post_date = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $_POST['aa'], $_POST['mm'], $_POST['jj'], $_POST['hh'], $_POST['mn'], $_POST['ss'] );

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );
	}

	/**
	 * When updating a draft in quick edit mode, it should set the publish date of the post if there is a new date set.
	 *
	 * @ticket 59125
	 *
	 * @covers ::edit_post
	 */
	public function test_quick_edit_draft_should_set_publish_date() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		$user = get_current_user_id();

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_author' => $user,
			)
		);

		$this->assertSame( 'draft', $post->post_status );

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		// Set up a request.
		$_POST['_inline_edit'] = wp_create_nonce( 'inlineeditnonce' );
		$_POST['post_ID']      = $post->ID;
		$_POST['post_type']    = 'post';
		$_POST['content']      = 'content test';
		$_POST['excerpt']      = 'excerpt test';
		$_POST['_status']      = $post->post_status;
		$_POST['post_status']  = $post->post_status;
		$_POST['post_author']  = $user;
		$_POST['screen']       = 'edit-post';
		$_POST['post_view']    = 'list';
		$_POST['edit_date']    = 'true';
		$_POST['mm']           = '09';
		$_POST['jj']           = 11;
		$_POST['aa']           = 2020;
		$_POST['hh']           = 19;
		$_POST['mn']           = 20;
		$_POST['ss']           = 11;

		// Make the request.
		try {
			$this->_handleAjax( 'inline-save' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$post = get_post( $post->ID );

		$this->assertSame( '2020-09-11 19:20:11', $post->post_date_gmt );
	}
}
