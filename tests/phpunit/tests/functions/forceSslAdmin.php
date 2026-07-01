<?php
/**
 * Test cases for the `force_ssl_admin()` and `force_ssl()` functions.
 *
 * @since 6.8.0
 *
 * @group functions
 *
 * @covers ::force_ssl_admin
 * @covers ::force_ssl
 */
class Tests_Functions_ForceSslAdmin extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Reset the `$forced` static variables before each test.
		force_ssl_admin( false );
		force_ssl( false );
	}

	/**
	 * Tests that force_ssl_admin() returns expected values based on various inputs.
	 *
	 * @dataProvider data_force_ssl_admin
	 *
	 * @param mixed $input    The input value to test.
	 * @param bool  $expected The expected result for subsequent calls.
	 */
	public function test_force_ssl_admin( $input, $expected ) {
		// The first call always returns the previous value.
		$this->assertFalse( force_ssl_admin( $input ), 'First call did not return the expected value' );

		// Call again to check subsequent behavior.
		$this->assertSame( $expected, force_ssl_admin( $input ), 'Subsequent call did not return the expected value' );
	}

	/**
	 * Data provider for testing force_ssl_admin().
	 *
	 * @return array[]
	 */
	public function data_force_ssl_admin() {
		return array(
			'default'          => array( null, false ),
			'true'             => array( true, true ),
			'false'            => array( false, false ),
			'non-empty string' => array( 'some string', true ),
			'empty string'     => array( '', false ),
			'integer 1'        => array( 1, true ),
			'integer 0'        => array( 0, false ),
		);
	}

	/**
	 * Tests that force_ssl() returns expected values based on various inputs.
	 *
	 * @ticket 28521
	 *
	 * @dataProvider data_force_ssl_admin
	 *
	 * @param mixed $input    The input value to test.
	 * @param bool  $expected The expected result for subsequent calls.
	 */
	public function test_force_ssl( $input, $expected ) {
		// The first call always returns the previous value.
		$this->assertFalse( force_ssl( $input ), 'First call did not return the expected value' );

		// Call again to check subsequent behavior.
		$this->assertSame( $expected, force_ssl( $input ), 'Subsequent call did not return the expected value' );
		$this->assertSame( $expected, force_ssl(), 'Getter did not return the expected value' );
	}
}
