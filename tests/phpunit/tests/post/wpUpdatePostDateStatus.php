<?php

/**
 * Test wp_update_post() date handling when changing post status
 *
 * @group post
 * @covers ::wp_update_post
 */
class Tests_Post_wpUpdatePostDateStatus extends WP_UnitTestCase {

	/**
	 * Data provider for test_update_post_preserves_date
	 *
	 * @return array[] Test parameters
	 */
	public function data_post_status_transitions() {
		return array(
			'pending to future' => array(
				'initial_status' => 'pending',
			),
			'draft to future'   => array(
				'initial_status' => 'draft',
			),
		);
	}

	/**
	 * Test that wp_update_post() preserves post_date when changing to future status
	 *
	 * @ticket 62468
	 *
	 * @dataProvider data_post_status_transitions
	 * @covers ::wp_update_post
	 *
	 * @param string $initial_status Initial post status
	 */
	public function test_update_post_preserves_date( $initial_status ) {
		$post_data = array(
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
			'post_status'  => $initial_status,
			'post_author'  => self::factory()->user->create( array( 'role' => 'editor' ) ),
		);

		$post_id = wp_insert_post( $post_data );
		$this->assertIsInt( $post_id );

		// Set future date (1 day from now)
		$future_date     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$future_date_gmt = get_gmt_from_date( $future_date );

		$update_data = array(
			'ID'            => $post_id,
			'post_status'   => 'future',
			'post_date'     => $future_date,
			'post_date_gmt' => $future_date_gmt,
		);

		$update_result = wp_update_post( $update_data );
		$this->assertIsInt( $update_result );

		$updated_post = get_post( $post_id );

		$this->assertEquals( 'future', $updated_post->post_status );
		$this->assertEquals( $future_date, $updated_post->post_date );
		$this->assertEquals( $future_date_gmt, $updated_post->post_date_gmt );
	}
}
