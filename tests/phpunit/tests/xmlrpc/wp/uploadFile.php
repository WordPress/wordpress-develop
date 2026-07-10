<?php

/**
 * @group xmlrpc
 * @requires function imagejpeg
 */
class Tests_XMLRPC_wp_uploadFile extends WP_XMLRPC_UnitTestCase {

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_valid_attachment() {
		$this->make_user_by_role( 'editor' );

		// Create attachment.
		$filename = ( DIR_TESTDATA . '/images/a2-small.jpg' );
		$contents = file_get_contents( $filename );
		$data     = array(
			'name' => 'a2-small.jpg',
			'type' => 'image/jpeg',
			'bits' => $contents,
		);

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertIsString( $result['id'] );
		$this->assertStringMatchesFormat( '%d', $result['id'] );
		$this->assertIsString( $result['file'] );
		$this->assertIsString( $result['url'] );
		$this->assertIsString( $result['type'] );
	}

	/**
	 * Tests that a non-array data argument returns an error instead of
	 * triggering a fatal error.
	 *
	 * The data argument (the fourth parameter) is expected to be a struct,
	 * which is passed to the method as an array. When it is any other type,
	 * the method must return an IXR_Error rather than attempting to access
	 * array offsets on a non-array value.
	 *
	 * @ticket 65611
	 */
	public function test_invalid_attachment_data_should_return_error() {
		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'anyuser', 'anypass', 'not-a-struct' ) );

		$this->assertIXRError( $result, 'A non-array data argument should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}
}
