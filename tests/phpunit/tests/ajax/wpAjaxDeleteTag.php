<?php

/**
 * Admin ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Class for testing ajax delete tag functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_tag
 */
class Tests_Ajax_wpAjaxDeleteTag extends WP_Ajax_UnitTestCase {

	/**
	 * Tests that deleting a tag returns JSON success with updated total count.
	 *
	 * @ticket 50082
	 *
	 * @covers ::wp_ajax_delete_tag
	 * @covers ::wp_count_terms
	 */
	public function test_delete_tag_returns_json_success_with_total() {
		$this->_setRole( 'administrator' );

		$term = wp_insert_term( 'tag-to-delete', 'post_tag' );
		wp_insert_term( 'tag-to-keep', 'post_tag' );

		$_POST = array(
			'action'   => 'delete-tag',
			'tag_ID'   => $term['term_id'],
			'taxonomy' => 'post_tag',
			'_wpnonce' => wp_create_nonce( 'delete-tag_' . $term['term_id'] ),
		);

		try {
			$this->_handleAjax( 'delete-tag' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		// One term remains after deletion.
		$this->assertSame( 1, $response['data']['total'] );
	}

	/**
	 * Tests that deleting a tag without permission returns -1.
	 *
	 * @ticket 50082
	 *
	 * @covers ::wp_ajax_delete_tag
	 */
	public function test_delete_tag_without_capability_should_error() {
		$this->_setRole( 'subscriber' );

		$term = self::factory()->term->create_and_get(
			array( 'taxonomy' => 'post_tag' )
		);

		$_POST = array(
			'action'   => 'delete-tag',
			'tag_ID'   => $term->term_id,
			'taxonomy' => 'post_tag',
			'_wpnonce' => wp_create_nonce( 'delete-tag_' . $term->term_id ),
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'delete-tag' );
	}
}
