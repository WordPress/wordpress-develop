<?php
/**
 * Test WP_Media months filter functionality
 *
 * @group media
 */
class Tests_Media_MonthsFilter extends WP_UnitTestCase {

	/**
	 * Array to store created attachments.
	 *
	 * @var array
	 */
	protected $attachments = array();

	public function set_up() {
		parent::set_up();

		$this->attachments[] = self::factory()->attachment->create_object(
			array(
				'file'           => 'test1.jpg',
				'post_parent'    => 0,
				'post_date'      => '2023-01-15 12:00:00',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->attachments[] = self::factory()->attachment->create_object(
			array(
				'file'           => 'test2.jpg',
				'post_parent'    => 0,
				'post_date'      => '2023-02-15 12:00:00',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	public function tear_down() {
		foreach ( $this->attachments as $attachment ) {
			wp_delete_attachment( $attachment, true );
		}

		parent::tear_down();
	}

	/**
	 * Test that the show_media_library_months_select filter works
	 * and prevents the expensive query when disabled.
	 *
	 * @ticket 41675
	 */
	public function test_show_media_library_months_select_filter() {
		global $wpdb;

		$queries_before = $wpdb->num_queries;

		$this->assertTrue( apply_filters( 'show_media_library_months_select', true ) );
		wp_enqueue_media();
		$this->assertGreaterThan( $queries_before, $wpdb->num_queries, 'No queries were run with filter enabled' );

		$queries_before = $wpdb->num_queries;

		add_filter( 'show_media_library_months_select', '__return_false' );
		$this->assertFalse( apply_filters( 'show_media_library_months_select', true ) );

		wp_enqueue_media();

		$this->assertEquals( $queries_before, $wpdb->num_queries, 'Queries were run with filter disabled' );
	}
}
