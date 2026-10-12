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
	 * Tests that an anonymous request with a non-array data argument returns
	 * the login error rather than triggering a fatal error.
	 *
	 * The reported fatal error was reached without credentials because the
	 * data struct was read before the login was attempted. The struct must
	 * only be read once the request is authenticated.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 */
	public function test_anonymous_request_with_invalid_attachment_data_should_return_login_error() {
		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'not-a-user', 'not-a-password', 'not-a-struct' ) );
		$this->assertIXRError( $result, 'An anonymous request should return an IXR_Error.' );
		$this->assertSame( 403, $result->code, 'The error code should be the 403 returned for a failed login.' );
	}

	/**
	 * Tests that a user who cannot upload files is rejected before the data is
	 * read.
	 *
	 * The capability is checked ahead of the attachment data, so a user who is
	 * not allowed to upload is told that rather than being told the data is
	 * malformed. Sending unusable data must not change which error comes back.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 */
	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'subscriber', 'subscriber', 'not-a-struct' ) );
		$this->assertIXRError( $result, 'A user who cannot upload files should return an IXR_Error.' );
		$this->assertSame( 401, $result->code, 'The error code should be the 401 returned for a missing capability.' );
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
	 * @param list<mixed> $args The arguments to pass to the method.
	 */
	public function test_insufficient_arguments_should_return_error( array $args ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( $args );
		$this->assertIXRError( $result, 'Insufficient arguments should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{args: list<mixed>}>
	 */
	public function data_insufficient_arguments(): array {
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
	public function test_attachment_data_without_name_should_return_error( array $data ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertIXRError( $result, 'A data argument without a name should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{data: array<string, mixed>}>
	 */
	public function data_attachment_data_without_name(): array {
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
	 * Tests that a file name left empty by sanitization returns the same error
	 * as an absent one.
	 *
	 * sanitize_file_name() strips special characters and then trims the
	 * remaining leading and trailing '.', '-' and '_' characters, so a name
	 * built only from those is reduced to an empty string. That leaves nothing
	 * to write, which is a malformed request rather than a server failure, so
	 * it must be reported as a 400 like any other unusable name instead of
	 * reaching wp_upload_bits() and surfacing as a 500.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 *
	 * @dataProvider data_attachment_data_with_unusable_name
	 *
	 * @param string $name The file name to pass to the method.
	 */
	public function test_attachment_data_with_unusable_name_should_return_error( string $name ) {
		$this->make_user_by_role( 'editor' );

		$data = array(
			'name' => $name,
			'type' => 'image/jpeg',
			'bits' => 'contents',
		);

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertIXRError( $result, 'A name left empty by sanitization should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{name: string}>
	 */
	public function data_attachment_data_with_unusable_name(): array {
		return array(
			'empty name'           => array(
				'name' => '',
			),
			'only dots'            => array(
				'name' => '...',
			),
			'only dashes'          => array(
				'name' => '---',
			),
			'only underscores'     => array(
				'name' => '___',
			),
			'only a space'         => array(
				'name' => ' ',
			),
			'only special chars'   => array(
				'name' => '///',
			),
			'only a question mark' => array(
				'name' => '?',
			),
		);
	}

	/**
	 * Tests that a data struct with a non-string type or bits member returns an
	 * error instead of triggering a fatal error.
	 *
	 * A struct sent for either member arrives as an array. An array reaches
	 * fwrite() by way of wp_upload_bits(), which throws a TypeError, and it
	 * survives sanitize_mime_type() to reach the database as the attachment's
	 * post MIME type. Both members must be rejected before that point.
	 *
	 * @ticket 65611
	 *
	 * @covers wp_xmlrpc_server::mw_newMediaObject
	 *
	 * @dataProvider data_attachment_data_with_invalid_members
	 *
	 * @param array<string, mixed> $data The data argument to pass to the method.
	 */
	public function test_attachment_data_with_invalid_members_should_return_error( array $data ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertIXRError( $result, 'A data argument with a non-string member should return an IXR_Error.' );
		$this->assertSame( 400, $result->code, 'The error code should be 400.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{data: array<string, mixed>}>
	 */
	public function data_attachment_data_with_invalid_members(): array {
		return array(
			'non-string bits' => array(
				'data' => array(
					'name' => 'a2-small.jpg',
					'type' => 'image/jpeg',
					'bits' => array( 'contents' ),
				),
			),
			'non-string type' => array(
				'data' => array(
					'name' => 'a2-small.jpg',
					'type' => array( 'image/jpeg' ),
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
	public function test_attachment_data_with_optional_members_omitted_should_be_accepted( array $data ) {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->mw_newMediaObject( array( 0, 'editor', 'editor', $data ) );
		$this->assertNotIXRError( $result );
		$this->assertIsString( $result['id'] );
		$this->assertStringMatchesFormat( '%d', $result['id'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{data: array<string, mixed>}>
	 */
	public function data_attachment_data_with_optional_members_omitted(): array {
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
