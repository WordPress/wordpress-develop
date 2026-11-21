<?php

/**
 * Tests that the old Requests class is included
 * for plugins or themes that still use it.
 *
 * @group http
 */
class Tests_HTTP_IncludeOldRequestsClass extends WP_UnitTestCase {

	/**
	 * @ticket 57341
	 *
	 * @coversNothing
	 */
	public function test_should_include_old_requests_class() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'The PSR-0 `Requests_...` class names in the Requests library are deprecated.' );

		new Requests();
	}
}
