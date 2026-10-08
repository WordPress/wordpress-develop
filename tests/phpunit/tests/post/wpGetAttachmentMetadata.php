<?php

/**
 * Tests for wp_get_attachment_metadata().
 *
 * @group post
 * @group media
 *
 * @covers ::wp_get_attachment_metadata
 */
class Tests_Post_WpGetAttachmentMetadata extends WP_UnitTestCase {

	/**
	 * Ensure metadata that was never stored is reported as missing.
	 */
	public function test_should_return_false_when_no_metadata_is_stored() {
		$attachment_id = $this->create_attachment();

		$this->assertFalse( wp_get_attachment_metadata( $attachment_id ) );
	}

	/**
	 * Ensure stored metadata that is not an array is reported as a failure.
	 *
	 * The documented return of `array|false` has to hold on the `$unfiltered` path too, since
	 * callers such as wp-admin/post.php read the metadata that way in order to modify it and
	 * pass it back to wp_update_attachment_metadata().
	 *
	 * @ticket 65748
	 *
	 * @dataProvider data_non_array_stored_metadata_values
	 *
	 * @param mixed $metadata Value to store as `_wp_attachment_metadata`.
	 */
	public function test_should_return_false_when_the_stored_metadata_is_not_an_array( $metadata ) {
		$attachment_id = $this->create_attachment();

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );

		$this->assertFalse( wp_get_attachment_metadata( $attachment_id ), 'The filtered metadata should have been reported as missing.' );
		$this->assertFalse( wp_get_attachment_metadata( $attachment_id, true ), 'The unfiltered metadata should have been reported as missing.' );
	}

	/**
	 * Ensure the `sizes` key is not invented for attachments that have no sub-sizes.
	 *
	 * An attachment is not necessarily an image. Audio, video and document attachments
	 * legitimately store metadata without a `sizes` key, and fabricating one would both
	 * blur that distinction and pollute the stored metadata for any caller that reads
	 * the metadata, modifies it, and passes it back to wp_update_attachment_metadata().
	 *
	 * @ticket 65748
	 */
	public function test_should_not_add_a_sizes_key_when_the_metadata_has_none() {
		$metadata = array(
			'bitrate'    => 128000,
			'length'     => 191,
			'fileformat' => 'mp3',
		);

		$attachment_id = $this->create_attachment( $metadata );

		$this->assertSame( $metadata, wp_get_attachment_metadata( $attachment_id ) );
	}

	/**
	 * Ensure a usable `sizes` array is passed through untouched.
	 *
	 * @ticket 65748
	 */
	public function test_should_preserve_a_usable_sizes_array() {
		$metadata = array(
			'file'  => '2026/08/image.jpg',
			'sizes' => array(
				'thumbnail' => array(
					'file'      => 'image-150x150.jpg',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/jpeg',
				),
			),
		);

		$attachment_id = $this->create_attachment( $metadata );

		$this->assertSame( $metadata, wp_get_attachment_metadata( $attachment_id ) );
	}

	/**
	 * Ensure a `sizes` key holding something other than an array is replaced with an empty array.
	 *
	 * Callers such as wp_save_image() pass `$meta['sizes']` straight to array_merge(), which
	 * is a fatal error for a scalar. Guarding the value here means every caller can rely on
	 * `sizes` being an array whenever the key is present.
	 *
	 * @ticket 65748
	 *
	 * @dataProvider data_non_array_sizes_values
	 *
	 * @param mixed $sizes Value to store under the `sizes` key.
	 */
	public function test_should_replace_a_non_array_sizes_value_with_an_empty_array( $sizes ) {
		$attachment_id = $this->create_attachment(
			array(
				'file'  => '2026/08/image.jpg',
				'sizes' => $sizes,
			)
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata, 'The metadata should have been returned as an array.' );
		$this->assertArrayHasKey( 'sizes', $metadata, 'The `sizes` key should still be present.' );
		$this->assertSame( array(), $metadata['sizes'], 'The unusable `sizes` value should have been replaced.' );
	}

	/**
	 * Ensure the stored metadata is returned verbatim when filters are skipped.
	 *
	 * Passing `$unfiltered` as true is documented as skipping the filters, and callers such as
	 * wp-admin/post.php read the metadata this way in order to modify and re-save it. Normalizing
	 * the value here would write the normalization back into the database.
	 *
	 * @ticket 65748
	 *
	 * @dataProvider data_non_array_sizes_values
	 *
	 * @param mixed $sizes Value to store under the `sizes` key.
	 */
	public function test_should_not_replace_a_non_array_sizes_value_when_unfiltered( $sizes ) {
		$metadata = array(
			'file'  => '2026/08/image.jpg',
			'sizes' => $sizes,
		);

		$attachment_id = $this->create_attachment( $metadata );

		$this->assertSame( $metadata, wp_get_attachment_metadata( $attachment_id, true ) );
	}

	/**
	 * Ensure a filtered value that is not an array is reported as a failure.
	 *
	 * The function documents a return of `array|false`, so a filter returning something else
	 * should surface as a failure rather than being handed to callers that expect an array.
	 *
	 * @ticket 65748
	 *
	 * @dataProvider data_non_array_filter_return_values
	 *
	 * @param mixed $value Value for the filter to return.
	 */
	public function test_should_return_false_when_the_filter_returns_a_non_array( $value ) {
		$attachment_id = $this->create_attachment( array( 'file' => '2026/08/image.jpg' ) );

		add_filter(
			'wp_get_attachment_metadata',
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->assertFalse( wp_get_attachment_metadata( $attachment_id ) );
	}

	/**
	 * Ensure the `sizes` value is normalized after the filter has run, not before.
	 *
	 * @ticket 65748
	 */
	public function test_should_normalize_a_sizes_value_introduced_by_the_filter() {
		// Stored without a `sizes` key, so the key can only come from the filter.
		$attachment_id = $this->create_attachment( array( 'file' => '2026/08/image.jpg' ) );

		add_filter(
			'wp_get_attachment_metadata',
			static function ( array $data ): array {
				$data['sizes'] = 'not-an-array';
				return $data;
			}
		);

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata, 'The metadata should have been returned as an array.' );
		$this->assertArrayHasKey( 'sizes', $metadata, 'The `sizes` key should still be present.' );
		$this->assertSame( array(), $metadata['sizes'], 'The value set by the filter should have been replaced.' );
	}

	/**
	 * Ensure the filter is not applied when filters are skipped.
	 */
	public function test_should_not_apply_the_filter_when_unfiltered() {
		$metadata = array( 'file' => '2026/08/image.jpg' );

		$attachment_id = $this->create_attachment( $metadata );

		add_filter( 'wp_get_attachment_metadata', '__return_empty_array' );

		$this->assertSame( $metadata, wp_get_attachment_metadata( $attachment_id, true ) );
	}

	/**
	 * Data provider.
	 *
	 * Only values that survive a round trip through the meta table are listed. A value that
	 * comes back falsy, such as an empty string, was already treated as missing metadata.
	 *
	 * @return array<non-empty-string, array{ 0: mixed }>
	 */
	public function data_non_array_stored_metadata_values(): array {
		return array(
			'string'  => array( 'not-an-array' ),
			'integer' => array( 1 ),
			'float'   => array( 1.5 ),
			'object'  => array( new stdClass() ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-empty-string, array{ 0: mixed }>
	 */
	public function data_non_array_sizes_values(): array {
		return array(
			'null'          => array( null ),
			'empty string'  => array( '' ),
			'string'        => array( 'not-an-array' ),
			'boolean false' => array( false ),
			'integer'       => array( 0 ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-empty-string, array{ 0: mixed }>
	 */
	public function data_non_array_filter_return_values(): array {
		return array(
			'null'          => array( null ),
			'empty string'  => array( '' ),
			'string'        => array( 'not-an-array' ),
			'boolean false' => array( false ),
			'boolean true'  => array( true ),
			'integer'       => array( 1 ),
			'float'         => array( 1.5 ),
			'object'        => array( new stdClass() ),
		);
	}

	/**
	 * Creates an attachment, optionally storing metadata for it.
	 *
	 * The metadata is stored with update_post_meta() rather than wp_update_attachment_metadata()
	 * so that it reaches the database without passing through the update filter, leaving the
	 * stored value entirely under the control of the test.
	 *
	 * @param array<string, mixed>|null $metadata Optional. Metadata to store as
	 *                                            `_wp_attachment_metadata`. Default null, meaning
	 *                                            no metadata is stored at all.
	 * @return int Attachment ID.
	 */
	private function create_attachment( ?array $metadata = null ): int {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => '2026/08/image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->assertIsInt( $attachment_id, 'Failed to create the attachment fixture.' );

		if ( null !== $metadata ) {
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		return $attachment_id;
	}
}
