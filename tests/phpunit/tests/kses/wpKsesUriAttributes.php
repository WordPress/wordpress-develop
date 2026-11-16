<?php

/**
 * Tests for wp_kses_uri_attributes().
 *
 * @group kses
 *
 * @covers ::wp_kses_uri_attributes
 */
class Tests_Kses_wpKsesUriAttributes extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_expected_default_uri_attributes() {
		$attributes = wp_kses_uri_attributes();

		$this->assertContains( 'href', $attributes );
		$this->assertContains( 'src', $attributes );
		$this->assertContains( 'action', $attributes );
		$this->assertContains( 'formaction', $attributes );
		$this->assertNotContains( 'class', $attributes );
	}

	/**
	 * @ticket 65819
	 */
	public function test_wp_kses_uri_attributes_filter() {
		add_filter(
			'wp_kses_uri_attributes',
			static function ( $attributes ) {
				$attributes[] = 'data-url';
				return $attributes;
			}
		);

		$this->assertContains( 'data-url', wp_kses_uri_attributes() );
	}
}
