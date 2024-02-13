<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsRegistry extends WP_UnitTestCase {

	public function test_add_provider() {
		$provider = new WP_Sitemaps_Test_Provider();
		$registry = new WP_Sitemaps_Registry();

		$actual    = $registry->add_provider( 'foo', $provider );
		$providers = $registry->get_providers();

		$this->assertTrue( $actual );
		$this->assertCount( 1, $providers );
		$this->assertSame( $providers['foo'], $provider, 'Can not confirm sitemap registration is working.' );
	}

	public function test_add_provider_prevent_duplicates() {
		$provider1 = new WP_Sitemaps_Test_Provider();
		$provider2 = new WP_Sitemaps_Test_Provider();
		$registry  = new WP_Sitemaps_Registry();

		$actual1   = $registry->add_provider( 'foo', $provider1 );
		$actual2   = $registry->add_provider( 'foo', $provider2 );
		$providers = $registry->get_providers();

		$this->assertTrue( $actual1 );
		$this->assertFalse( $actual2 );
		$this->assertCount( 1, $providers );
		$this->assertSame( $providers['foo'], $provider1, 'Can not confirm sitemap registration is working.' );
	}

	/**
	 * Tests that `WP_Sitemaps_Registry::get_provider()` returns `null` when
	 * the `$name` argument is not a string.
	 *
	 * @ticket 56336
	 *
	 * @covers WP_Sitemaps_Registry::get_provider
	 *
	 * @dataProvider data_get_provider_should_return_null_with_non_string_name
	 *
	 * @param mixed $name The non-string name.
	 */
	public function test_get_provider_should_return_null_with_non_string_name( $name ) {
		$registry = new WP_Sitemaps_Registry();
		$this->assertNull( $registry->get_provider( $name ) );
	}

	/**
	 * Data provider with non-string values.
	 *
	 * @return array
	 */
	public function data_get_provider_should_return_null_with_non_string_name() {
		return array(
			'array'        => array( array() ),
			'object'       => array( new stdClass() ),
			'bool (true)'  => array( true ),
			'bool (false)' => array( false ),
			'null'         => array( null ),
			'integer (0)'  => array( 0 ),
			'integer (1)'  => array( 1 ),
			'float (0.0)'  => array( 0.0 ),
			'float (1.1)'  => array( 1.1 ),
		);
	}
}
