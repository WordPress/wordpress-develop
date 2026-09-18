<?php

/**
 * @group post
 *
 * @covers ::register_post_status
 */
class Tests_Post_RegisterPostStatus extends WP_UnitTestCase {

	/**
	 * Remove test statuses after each test.
	 */
	public function tear_down() {
		global $wp_post_statuses;

		unset(
			$wp_post_statuses['test_custom_status'],
			$wp_post_statuses['test_custom_status_with_desc']
		);

		parent::tear_down();
	}

	/**
	 * Test default property values when registering a post status.
	 *
	 * @ticket 3144
	 */
	public function test_register_post_status_defaults() {
		$status = register_post_status( 'test_custom_status' );

		$this->assertSame( 'test_custom_status', $status->name );
		$this->assertSame( 'test_custom_status', $status->label );
		$this->assertSame( '', $status->description );
		$this->assertFalse( $status->public );
		$this->assertTrue( $status->internal );
		$this->assertFalse( $status->protected );
		$this->assertFalse( $status->private );
		$this->assertFalse( $status->publicly_queryable );
		$this->assertTrue( $status->exclude_from_search );
		$this->assertFalse( $status->show_in_admin_all_list );
		$this->assertFalse( $status->show_in_admin_status_list );
		$this->assertFalse( $status->date_floating );
		$this->assertFalse( $status->_builtin );
	}

	/**
	 * Test registering a post status with a custom description.
	 *
	 * @ticket 3144
	 */
	public function test_register_post_status_with_description() {
		$description = 'A custom post status description.';
		$status      = register_post_status(
			'test_custom_status_with_desc',
			array(
				'label'       => 'Custom Status',
				'description' => $description,
				'public'      => true,
			)
		);

		$this->assertSame( $description, $status->description );
		$this->assertSame( $status, get_post_status_object( 'test_custom_status_with_desc' ) );
	}

	/**
	 * Test that core post statuses have their descriptions populated.
	 *
	 * @ticket 3144
	 *
	 * @dataProvider data_core_post_statuses_descriptions
	 *
	 * @param string $status_name         Post status name.
	 * @param string $expected_description Expected description.
	 */
	public function test_core_post_statuses_descriptions( $status_name, $expected_description ) {
		$status = get_post_status_object( $status_name );

		$this->assertNotNull( $status );
		$this->assertSame( $expected_description, $status->description );
	}

	/**
	 * Data provider for core post status descriptions.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_core_post_statuses_descriptions() {
		return array(
			'publish' => array( 'publish', 'Visible to everyone.' ),
			'future'  => array( 'future', 'Publish automatically on a chosen date.' ),
			'draft'   => array( 'draft', 'Not ready to publish.' ),
			'pending' => array( 'pending', 'Waiting for review before publishing.' ),
			'private' => array( 'private', 'Only visible to site admins and editors.' ),
		);
	}
}
