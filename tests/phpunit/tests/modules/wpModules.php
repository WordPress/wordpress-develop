<?php
/**
 * @group modules
 * @covers WP_Modules::get_version_query_string
 */
class Tests_WP_Modules extends WP_UnitTestCase {
	/**
	 * Tests the functionality of the `get_version_query_string` method to ensure
	 * proper version strings are returned.
	 *
	 * @ticket 56313
	 *
	 * @covers WP_Modules::get_version_query_string
	 */
	public function test_get_version_query_string() {
		$get_version_query_string = new ReflectionMethod( 'WP_Modules', 'get_version_query_string' );
		$get_version_query_string->setAccessible( true );

		$result = $get_version_query_string->invoke( null, '1.0' );
		$this->assertEquals( '?ver=1.0', $result );

		$result = $get_version_query_string->invoke( null, false );
		$this->assertEquals( '?ver=' . get_bloginfo( 'version' ), $result );

		$result = $get_version_query_string->invoke( null, null );
		$this->assertEquals( '', $result );
	}
}
