<?php

/**
 * Tests for the `wp_get_media_library_attachment_months()` function.
 *
 * @group media
 * @covers ::wp_get_media_library_attachment_months
 */
class Tests_Media_wpGetMediaLibraryAttachmentMonths extends WP_UnitTestCase {

	/**
	 * Tests that the function returns the correct months and caches the result.
	 *
	 * @ticket 63279
	 */
	public function test_returns_months_and_caches_result() {
		self::factory()->post->create(
			array(
				'post_type' => 'attachment',
				'post_date' => '2025-03-15 00:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_type' => 'attachment',
				'post_date' => '2024-11-01 00:00:00',
			)
		);

		delete_transient( 'wp_media_library_attachment_months' );

		$months = wp_get_media_library_attachment_months();

		$this->assertCount( 2, $months );
		$this->assertSame( 2025, $months[0]->year );
		$this->assertSame( 3, $months[0]->month );
		$this->assertSame( 2024, $months[1]->year );
		$this->assertSame( 11, $months[1]->month );

		// Verify the result was cached.
		$this->assertEquals( $months, get_transient( 'wp_media_library_attachment_months' ) );
	}

	/**
	 * Tests that the filter bypasses transient and query.
	 *
	 * @ticket 63279
	 */
	public function test_filter_overrides_result() {
		self::factory()->post->create(
			array(
				'post_type' => 'attachment',
				'post_date' => '2025-06-01 00:00:00',
			)
		);

		delete_transient( 'wp_media_library_attachment_months' );

		// Confirm the function returns data without the filter.
		$this->assertNotEmpty( wp_get_media_library_attachment_months() );

		delete_transient( 'wp_media_library_attachment_months' );

		$override = array(
			(object) array(
				'year'  => 2020,
				'month' => 1,
			),
		);

		add_filter(
			'media_library_months_with_files',
			static function () use ( $override ) {
				return $override;
			}
		);

		$months = wp_get_media_library_attachment_months();

		$this->assertSame( $override, $months );
	}

	/**
	 * Tests that the transient is deleted when a new attachment is created.
	 *
	 * @ticket 63279
	 */
	public function test_transient_is_invalidated_on_new_attachment() {
		set_transient( 'wp_media_library_attachment_months', array() );

		self::factory()->post->create( array( 'post_type' => 'attachment' ) );

		$this->assertFalse( get_transient( 'wp_media_library_attachment_months' ) );
	}
}
