<?php

/**
 * Tests for the `wp_get_media_library_attachment_months()` function.
 *
 * @group media
 * @covers ::wp_get_media_library_attachment_months
 */
class Tests_Media_wpGetMediaLibraryAttachmentMonths extends WP_UnitTestCase {

	/**
	 * Tests that the function returns the correct months.
	 *
	 * @ticket 63279
	 */
	public function test_returns_months() {
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

		$months = wp_get_media_library_attachment_months();

		$this->assertCount( 2, $months );
		$this->assertSame( '2025', $months[0]->year );
		$this->assertSame( '3', $months[0]->month );
		$this->assertSame( '2024', $months[1]->year );
		$this->assertSame( '11', $months[1]->month );
	}

	/**
	 * Tests that the filter overrides the query result.
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

		$override = array(
			(object) array(
				'year'  => '2020',
				'month' => '1',
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
}
