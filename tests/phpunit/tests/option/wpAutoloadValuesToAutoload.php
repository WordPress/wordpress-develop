<?php

/**
 * Tests for the wp_autoload_values_to_autoload function.
 *
 * @group option
 *
 * @covers ::wp_autoload_values_to_autoload
 */
class Tests_Option_wpAutoloadValuesToAutoload extends WP_UnitTestCase {

	/**
	 * @ticket 42441
	 */
	public function test_wp_autoload_values_to_autoload() {
		$this->assertSameSets( array( WP_Option_Autoload::LEGACY_YES, WP_Option_Autoload::ON, WP_Option_Autoload::AUTO_ON, WP_Option_Autoload::AUTO ), wp_autoload_values_to_autoload() );
	}

	/**
	 * @ticket 42441
	 */
	public function test_wp_autoload_values_to_autoload_filter_remove() {

		add_filter(
			'wp_autoload_values_to_autoload',
			static function () {
				return array( WP_Option_Autoload::LEGACY_YES );
			}
		);

		$this->assertSameSets( array( WP_Option_Autoload::LEGACY_YES ), wp_autoload_values_to_autoload() );
	}

	/**
	 * @ticket 42441
	 */
	public function test_wp_autoload_values_to_autoload_filter_extra() {

		add_filter(
			'wp_autoload_values_to_autoload',
			static function () {
				return array( WP_Option_Autoload::LEGACY_YES, WP_Option_Autoload::ON, WP_Option_Autoload::AUTO_ON, WP_Option_Autoload::AUTO, 'extra' );
			}
		);

		$this->assertSameSets( array( WP_Option_Autoload::LEGACY_YES, WP_Option_Autoload::ON, WP_Option_Autoload::AUTO_ON, WP_Option_Autoload::AUTO ), wp_autoload_values_to_autoload() );
	}

	/**
	 * @ticket 42441
	 */
	public function test_wp_autoload_values_to_autoload_filter_replace() {

		add_filter(
			'wp_autoload_values_to_autoload',
			static function () {
				return array( WP_Option_Autoload::LEGACY_YES, WP_Option_Autoload::ON, WP_Option_Autoload::AUTO_ON, 'extra' );
			}
		);

		$this->assertSameSets( array( WP_Option_Autoload::LEGACY_YES, WP_Option_Autoload::ON, WP_Option_Autoload::AUTO_ON ), wp_autoload_values_to_autoload() );
	}
}
