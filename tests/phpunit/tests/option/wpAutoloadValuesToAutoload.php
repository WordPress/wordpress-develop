<?php

/**
 * Tests for the wp_autoload_values_to_autoload function.
 *
 * @group Option
 *
 * @covers ::wp_autoload_values_to_autoload
 */
class Tests_Option_wpAutoloadValuesToAutoload extends WP_UnitTestCase{

	/**
	 * @ticket 42441
	 */
	public function test_wp_autoload_values_to_autoload() {
		$this->assertSameSets( array( 'yes', 'on', 'auto-on', 'auto' ), wp_autoload_values_to_autoload() );
		$this->assertSame( "'yes', 'on', 'auto-on', 'auto'", wp_autoload_values_to_autoload( true ) );
	}
}
