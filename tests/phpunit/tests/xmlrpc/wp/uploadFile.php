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
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 */
	public function test_invalid_attachment_data_should_return_error() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', 'not-a-struct' ) );
		$this->assertIXRError( $result, 'A non-array data argument should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Tests that too few arguments return an error instead of emitting a PHP
	 * notice for the undefined arguments.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 *
	 * @dataProvider data_insufficient_arguments
	 *
	 * @param array<int, mixed> $args The arguments to pass to the method.
	 */
	public function test_insufficient_arguments_should_return_error( $args ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( $args );
		$this->assertIXRError( $result, 'Insufficient arguments should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{args: array<int, mixed>}>
	 */
	public function data_insufficient_arguments() {
		return array(
			'no arguments'     => array(
				'args' => array(),
			),
			'only the blog ID' => array(
				'args' => array( 0 ),
			),
			'missing the data' => array(
				'args' => array( 0, 'editor', 'editor' ),
			),
		);
	}

	/**
	 * Tests that a data struct without a usable file name returns an error
	 * instead of emitting a PHP notice for the undefined array key.
	 *
	 * A file name is required to write the upload, so the request cannot
	 * succeed. It must fail with an IXR_Error rather than by reading an
	 * undefined array offset.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 *
	 * @dataProvider data_attachment_data_without_name
	 *
	 * @param array<string, mixed> $data The data argument to pass to the method.
	 */
	public function test_attachment_data_without_name_should_return_error( $data ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertIXRError( $result, 'A data argument without a name should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{data: array<string, mixed>}>
	 */
	public function data_attachment_data_without_name() {
		return array(
			'empty struct'       => array(
				'data' => array(),
			),
			'only type and bits' => array(
				'data' => array(
					'type' => 'image/jpeg',
					'bits' => 'contents',
				),
			),
			'non-string name'    => array(
				'data' => array(
					'name' => array( 'a2-small.jpg' ),
					'type' => 'image/jpeg',
					'bits' => 'contents',
				),
			),
		);
	}

	/**
	 * Tests that a data struct without the optional members is still accepted.
	 *
	 * Only the name is required. The type and bits members are tolerated when
	 * absent, and must not emit a PHP notice for the undefined array keys.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 *
	 * @dataProvider data_attachment_data_with_optional_members_omitted
	 *
	 * @param array<string, mixed> $data The data argument to pass to the method.
	 */
	public function test_attachment_data_with_optional_members_omitted_should_be_accepted( $data ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertNotIXRError( $result );
		$this->assertIsString( $result['id'] );
		$this->assertStringMatchesFormat( '%d', $result['id'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{data: array<string, mixed>}>
	 */
	public function data_attachment_data_with_optional_members_omitted() {
		return array(
			'missing type' => array(
				'data' => array(
					'name' => 'a2-small.jpg',
					'bits' => file_get_contents( DIR_TESTDATA . '/images/a2-small.jpg' ),
				),
			),
			'missing bits' => array(
				'data' => array(
					'name' => 'a2-small.jpg',
					'type' => 'image/jpeg',
				),
			),
		);
	}
}
