<?php

/**
 * Tests for the `_wp_log_image_processing_error()` function.
 *
 * @group media
 *
 * @covers ::_wp_log_image_processing_error
 */
class Tests_Media_wpLogImageProcessingError extends WP_UnitTestCase {

	/**
	 * Tests that structured image processing error details are exposed through an action.
	 */
	public function test_fires_action_with_error_details() {
		$error         = new WP_Error( 'image_test_error', 'The image operation failed.' );
		$attachment_id = 123;
		$file          = '/tmp/test-image.jpg';
		$operation     = 'creating a test image sub-size';
		$actual        = array();

		add_action(
			'wp_image_processing_error',
			static function ( $action_error, $action_attachment_id, $action_file, $action_operation ) use ( &$actual ) {
				$actual = array( $action_error, $action_attachment_id, $action_file, $action_operation );
			},
			10,
			4
		);

		_wp_log_image_processing_error( $error, $attachment_id, $file, $operation );

		$this->assertSame(
			array( $error, $attachment_id, $file, $operation ),
			$actual,
			'The action did not receive the expected image processing error details.'
		);
	}
}
