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

	/**
	 * Tests that the sitemap provider can be filtered before being added.
	 *
	 * @covers WP_Sitemaps_Registry::add_provider
	 */
	public function test_add_provider_filter() {
		$original_provider = new WP_Sitemaps_Test_Provider();
		$filtered_provider = new WP_Sitemaps_Test_Provider();

		add_filter(
			'wp_sitemaps_add_provider',
			static function () use ( $filtered_provider ) {
				return $filtered_provider;
			}
		);

		$registry = new WP_Sitemaps_Registry();
		$actual   = $registry->add_provider( 'foo', $original_provider );

		$this->assertTrue( $actual );
		$this->assertSame( $filtered_provider, $registry->get_provider( 'foo' ) );
	}

	/**
	 * Tests that add_provider() returns false when the wp_sitemaps_add_provider filter returns an invalid value.
	 *
	 * @covers WP_Sitemaps_Registry::add_provider
	 *
	 * @dataProvider data_invalid_sitemap_providers
	 *
	 * @param mixed $invalid_provider Invalid provider value.
	 */
	public function test_add_provider_filter_rejects_non_provider( $invalid_provider ) {
		add_filter(
			'wp_sitemaps_add_provider',
			static function () use ( $invalid_provider ) {
				return $invalid_provider;
			}
		);

		$registry = new WP_Sitemaps_Registry();
		$actual   = $registry->add_provider( 'foo', new WP_Sitemaps_Test_Provider() );

		$this->assertFalse( $actual );
		$this->assertNull( $registry->get_provider( 'foo' ) );
		$this->assertSame( array(), $registry->get_providers() );
	}

	/**
	 * Data provider for {@see self::test_add_provider_filter_rejects_non_provider()}.
	 *
	 * @return array
	 */
	public function data_invalid_sitemap_providers() {
		return array(
			'null'     => array( null ),
			'false'    => array( false ),
			'true'     => array( true ),
			'string'   => array( 'invalid' ),
			'integer'  => array( 123 ),
			'array'    => array( array() ),
			'stdClass' => array( new stdClass() ),
		);
	}

	/**
	 * Tests that get_provider() returns null for unregistered provider names.
	 *
	 * @covers WP_Sitemaps_Registry::get_provider
	 */
	public function test_get_provider_unregistered_name() {
		$registry = new WP_Sitemaps_Registry();

		$this->assertNull( $registry->get_provider( 'non_existent_provider' ) );
	}

	/**
	 * Tests that get_providers() returns an empty array when no providers are registered.
	 *
	 * @covers WP_Sitemaps_Registry::get_providers
	 */
	public function test_get_providers_empty() {
		$registry = new WP_Sitemaps_Registry();

		$this->assertSame( array(), $registry->get_providers() );
	}

	/**
	 * Tests that get_providers() returns all registered providers keyed by name.
	 *
	 * @covers WP_Sitemaps_Registry::get_providers
	 */
	public function test_get_providers_multiple() {
		$provider_1 = new WP_Sitemaps_Test_Provider();
		$provider_2 = new WP_Sitemaps_Test_Provider();
		$registry   = new WP_Sitemaps_Registry();

		$registry->add_provider( 'foo', $provider_1 );
		$registry->add_provider( 'bar', $provider_2 );

		$expected = array(
			'foo' => $provider_1,
			'bar' => $provider_2,
		);

		$this->assertSame( $expected, $registry->get_providers() );
	}
}
