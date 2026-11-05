<?php

/**
 * Tests for update_attached_file().
 *
 * @group post
 *
 * @covers ::update_attached_file
 */
class Tests_Post_UpdateAttachedFile extends WP_UnitTestCase {

	/**
	 * Creates an attachment without a file.
	 *
	 * @return int ID of the attachment.
	 */
	private function create_attachment() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'original.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		return $attachment_id;
	}

	/**
	 * Tests that false is returned for an attachment that does not exist.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_an_attachment_that_does_not_exist() {
		$this->assertFalse( update_attached_file( 987654321, 'image.jpg' ) );
		$this->assertSame( array(), get_post_meta( 987654321, '_wp_attached_file' ) );
	}

	/**
	 * Tests that the path is stored relative to the uploads directory.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_path_relative_to_the_uploads_directory() {
		$attachment_id = $this->create_attachment();
		$upload_dir    = wp_upload_dir();

		$result = update_attached_file( $attachment_id, $upload_dir['basedir'] . '/2020/01/photo.jpg' );

		$this->assertNotFalse( $result );
		$this->assertSame( '2020/01/photo.jpg', get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertSame( $upload_dir['basedir'] . '/2020/01/photo.jpg', get_attached_file( $attachment_id ) );
	}

	/**
	 * Tests that a path that is already relative is stored as it is.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_a_relative_path_as_it_is() {
		$attachment_id = $this->create_attachment();

		update_attached_file( $attachment_id, '2021/05/relative.jpg' );

		$this->assertSame( '2021/05/relative.jpg', get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	/**
	 * Tests that a path outside the uploads directory is stored as it is.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_a_path_outside_the_uploads_directory_as_it_is() {
		$attachment_id = $this->create_attachment();

		update_attached_file( $attachment_id, '/somewhere/else/photo.jpg' );

		$this->assertSame( '/somewhere/else/photo.jpg', get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	/**
	 * Tests that the file is replaced when there is one already.
	 *
	 * @ticket 66495
	 */
	public function test_should_replace_the_stored_file() {
		$attachment_id = $this->create_attachment();

		update_attached_file( $attachment_id, '2020/01/first.jpg' );
		update_attached_file( $attachment_id, '2020/02/second.jpg' );

		$this->assertSame( array( '2020/02/second.jpg' ), get_post_meta( $attachment_id, '_wp_attached_file' ) );
	}

	/**
	 * Tests that the stored file is deleted when the path is empty.
	 *
	 * @ticket 66495
	 */
	public function test_should_delete_the_stored_file_when_the_path_is_empty() {
		$attachment_id = $this->create_attachment();

		update_attached_file( $attachment_id, '2020/01/first.jpg' );

		$this->assertTrue( update_attached_file( $attachment_id, '' ) );
		$this->assertSame( array(), get_post_meta( $attachment_id, '_wp_attached_file' ) );
	}

	/**
	 * Tests that the `update_attached_file` filter gets the path and the ID, and its result is the file that is stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_file_that_the_filter_returns() {
		$attachment_id = $this->create_attachment();

		$received = null;
		add_filter(
			'update_attached_file',
			static function ( $file, $id ) use ( &$received ) {
				$received = array( $file, $id );

				return '2022/03/filtered.jpg';
			},
			10,
			2
		);

		update_attached_file( $attachment_id, '2020/01/original.jpg' );

		$this->assertSame( array( '2020/01/original.jpg', $attachment_id ), $received );
		$this->assertSame( '2022/03/filtered.jpg', get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}
}
