<?php
/**
 * Tests for the WP_Filesystem_Direct::__construct() method.
 *
 * @package WordPress
 */

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::__construct
 */
class Tests_Filesystem_WpFilesystemDirect_Construct extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that the $method and $errors properties are set upon
	 * the instantiation of a WP_Filesystem_Direct object.
	 *
	 * @ticket 57774
	 */
	public function test_should_set_method_and_errors() {
		// For coverage reports, a new object must be created in the method.
		$filesystem = new WP_Filesystem_Direct( null );

		$this->assertSame(
			'direct',
			$filesystem->method,
			'The "$method" property is not set to "direct".'
		);

		$this->assertInstanceOf(
			'WP_Error',
			$filesystem->errors,
			'The "$errors" property is not set to a WP_Error object.'
		);
	}
}
