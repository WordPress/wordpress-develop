<?php

/**
 * Tests for get_post_statuses() and get_page_statuses().
 *
 * @group post
 */
class Tests_Post_GetPostStatuses extends WP_UnitTestCase {

	/**
	 * Tests that get_post_statuses() includes custom registered non-internal statuses.
	 *
	 * @ticket 24722
	 *
	 * @covers ::get_post_statuses
	 */
	public function test_get_post_statuses_includes_custom_registered_status() {
		register_post_status(
			'custom-status',
			array(
				'label'  => 'Custom Status',
				'public' => true,
			)
		);

		$statuses = get_post_statuses();

		$this->assertArrayHasKey( 'custom-status', $statuses );
		$this->assertSame( 'Custom Status', $statuses['custom-status'] );
	}

	/**
	 * Tests that get_post_statuses() does not include custom internal statuses.
	 *
	 * @ticket 24722
	 *
	 * @covers ::get_post_statuses
	 */
	public function test_get_post_statuses_excludes_custom_internal_status() {
		register_post_status(
			'custom-internal-status',
			array(
				'label'    => 'Custom Internal Status',
				'internal' => true,
			)
		);

		$statuses = get_post_statuses();

		$this->assertArrayNotHasKey( 'custom-internal-status', $statuses );
	}

	/**
	 * Tests that get_page_statuses() includes custom registered non-internal statuses.
	 *
	 * @ticket 24722
	 *
	 * @covers ::get_page_statuses
	 */
	public function test_get_page_statuses_includes_custom_registered_status() {
		register_post_status(
			'custom-page-status',
			array(
				'label'  => 'Custom Page Status',
				'public' => true,
			)
		);

		$statuses = get_page_statuses();

		$this->assertArrayHasKey( 'custom-page-status', $statuses );
		$this->assertSame( 'Custom Page Status', $statuses['custom-page-status'] );
	}

	/**
	 * Tests that get_page_statuses() does not include custom internal statuses.
	 *
	 * @ticket 24722
	 *
	 * @covers ::get_page_statuses
	 */
	public function test_get_page_statuses_excludes_custom_internal_status() {
		register_post_status(
			'custom-internal-page-status',
			array(
				'label'    => 'Custom Internal Page Status',
				'internal' => true,
			)
		);

		$statuses = get_page_statuses();

		$this->assertArrayNotHasKey( 'custom-internal-page-status', $statuses );
	}

	/**
	 * Unregisters custom post statuses to prevent state leak between tests.
	 */
	public function tear_down() {
		_unregister_post_status( 'custom-status' );
		_unregister_post_status( 'custom-internal-status' );
		_unregister_post_status( 'custom-page-status' );
		_unregister_post_status( 'custom-internal-page-status' );

		parent::tear_down();
	}
}
