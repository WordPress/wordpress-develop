<?php

/**
 * Tests for wp_check_for_changed_dates().
 *
 * @group post
 *
 * @covers ::wp_check_for_changed_dates
 */
class Tests_Post_WpCheckForChangedDates extends WP_UnitTestCase {

	/**
	 * Returns the post after the change, with a new date.
	 *
	 * @param WP_Post $post_before Post before the change.
	 * @param string  $new_date    New date.
	 * @return WP_Post Post after the change.
	 */
	private function with_new_date( $post_before, $new_date ) {
		$post            = clone $post_before;
		$post->post_date = $new_date;

		return $post;
	}

	/**
	 * Creates a post with a date.
	 *
	 * @param array $args Optional. Arguments of the post.
	 * @return WP_Post The post.
	 */
	private function create_post_dated( $args = array() ) {
		return get_post(
			self::factory()->post->create(
				array_merge(
					array(
						'post_status'   => 'publish',
						'post_date'     => '2020-03-10 12:00:00',
						'post_date_gmt' => '2020-03-10 12:00:00',
					),
					$args
				)
			)
		);
	}

	/**
	 * Tests that the old date of a published post is stored, as a date without the time.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_old_date_of_a_published_post() {
		$post_before = $this->create_post_dated();

		wp_check_for_changed_dates( $post_before->ID, $this->with_new_date( $post_before, '2020-04-15 08:30:00' ), $post_before );

		$this->assertSame( array( '2020-03-10' ), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that nothing is stored when only the time changes.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_anything_when_only_the_time_changes() {
		$post_before = $this->create_post_dated();

		wp_check_for_changed_dates( $post_before->ID, $this->with_new_date( $post_before, '2020-03-10 23:59:59' ), $post_before );

		$this->assertSame( array(), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that nothing is stored for a post that is not published.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_statuses_that_are_not_published
	 *
	 * @param string $status Status of the post.
	 */
	public function test_should_not_store_the_old_date_of_a_post_that_is_not_published( $status ) {
		$post_before = $this->create_post_dated( array( 'post_status' => $status ) );

		wp_check_for_changed_dates( $post_before->ID, $this->with_new_date( $post_before, '2020-04-15 08:30:00' ), $post_before );

		$this->assertSame( array(), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_statuses_that_are_not_published() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	/**
	 * Tests that nothing is stored for a post type that is hierarchical.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_the_old_date_of_a_hierarchical_post_type() {
		$page_before = $this->create_post_dated( array( 'post_type' => 'page' ) );

		wp_check_for_changed_dates( $page_before->ID, $this->with_new_date( $page_before, '2020-04-15 08:30:00' ), $page_before );

		$this->assertSame( array(), get_post_meta( $page_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that the old date of an attachment with the status `inherit` is stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_old_date_of_an_attachment() {
		$attachment_before = get_post(
			self::factory()->attachment->create_object(
				array(
					'file'           => 'image.jpg',
					'post_mime_type' => 'image/jpeg',
					'post_date'      => '2020-03-10 12:00:00',
					'post_date_gmt'  => '2020-03-10 12:00:00',
				)
			)
		);

		$this->assertSame( 'inherit', $attachment_before->post_status );

		wp_check_for_changed_dates( $attachment_before->ID, $this->with_new_date( $attachment_before, '2020-04-15 08:30:00' ), $attachment_before );

		$this->assertSame( array( '2020-03-10' ), get_post_meta( $attachment_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that an old date is only stored once.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_store_an_old_date_twice() {
		$post_before = $this->create_post_dated();
		add_post_meta( $post_before->ID, '_wp_old_date', '2020-03-10' );

		wp_check_for_changed_dates( $post_before->ID, $this->with_new_date( $post_before, '2020-04-15 08:30:00' ), $post_before );

		$this->assertSame( array( '2020-03-10' ), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that a new date that was used before is removed from the old dates.
	 *
	 * @ticket 66495
	 */
	public function test_should_remove_the_new_date_from_the_old_dates() {
		$post_before = $this->create_post_dated();
		add_post_meta( $post_before->ID, '_wp_old_date', '2020-04-15' );

		// The post goes back to an earlier date.
		wp_check_for_changed_dates( $post_before->ID, $this->with_new_date( $post_before, '2020-04-15 08:30:00' ), $post_before );

		$this->assertSame( array( '2020-03-10' ), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}

	/**
	 * Tests that the function runs when posts and attachments are updated, and stores the old date.
	 *
	 * @ticket 66495
	 */
	public function test_should_run_when_a_post_is_updated() {
		$this->assertSame( 12, has_action( 'post_updated', 'wp_check_for_changed_dates' ) );
		$this->assertSame( 12, has_action( 'attachment_updated', 'wp_check_for_changed_dates' ) );

		$post_before = $this->create_post_dated();

		wp_update_post(
			array(
				'ID'            => $post_before->ID,
				'post_date'     => '2020-04-15 08:30:00',
				'post_date_gmt' => '2020-04-15 08:30:00',
			)
		);

		$this->assertSame( array( '2020-03-10' ), get_post_meta( $post_before->ID, '_wp_old_date' ) );
	}
}
