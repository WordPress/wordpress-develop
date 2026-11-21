<?php
/**
 * Tests for find_core_update().
 *
 * @group admin
 * @group upgrade
 *
 * @covers ::find_core_update
 */
class Tests_Admin_Includes_Update_FindCoreUpdate extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_without_updates() {
		delete_site_transient( 'update_core' );

		$this->assertFalse( find_core_update( '99.0', 'en_US' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_matching_update() {
		$update = (object) array(
			'response' => 'upgrade',
			'current'  => '99.0',
			'locale'   => 'en_US',
		);

		set_site_transient(
			'update_core',
			(object) array(
				'updates' => array( $update ),
			)
		);

		// Site transients may serialize through the options API, so compare by value.
		$this->assertEquals( $update, find_core_update( '99.0', 'en_US' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_locale() {
		set_site_transient(
			'update_core',
			(object) array(
				'updates' => array(
					(object) array(
						'response' => 'upgrade',
						'current'  => '99.0',
						'locale'   => 'en_US',
					),
				),
			)
		);

		$this->assertFalse( find_core_update( '99.0', 'de_DE' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_version() {
		set_site_transient(
			'update_core',
			(object) array(
				'updates' => array(
					(object) array(
						'response' => 'upgrade',
						'current'  => '99.0',
						'locale'   => 'en_US',
					),
				),
			)
		);

		$this->assertFalse( find_core_update( '98.0', 'en_US' ) );
	}
}
