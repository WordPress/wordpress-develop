<?php

/**
 * @group l10n
 * @group i18n
 *
 * @coversDefaultClass WP_Textdomain_Registry
 */
class Tests_L10n_wpTextdomainRegistry extends WP_UnitTestCase {
	/**
	 * @var WP_Textdomain_Registry
	 */
	protected $instance;

	public function set_up() {
		parent::set_up();

		$this->instance = new WP_Textdomain_Registry();
	}

	/**
	 * @covers ::has
	 * @covers ::get
	 * @covers ::set_custom_path
	 */
	public function test_set_custom_path() {
		$reflection          = new ReflectionClass( $this->instance );
		$reflection_property = $reflection->getProperty( 'cached_mo_files' );
		$reflection_property->setAccessible( true );

		$this->assertEmpty(
			$reflection_property->getValue( $this->instance ),
			'Cache not empty by default'
		);

		$this->instance->set_custom_path( 'foo', WP_LANG_DIR . '/bar' );

		$this->assertTrue(
			$this->instance->has( 'foo' ),
			'Incorrect availability status for textdomain with custom path'
		);
		$this->assertFalse(
			$this->instance->get( 'foo', 'en_US' ),
			'Should not return custom path for textdomain and en_US locale'
		);
		$this->assertSame(
			WP_LANG_DIR . '/bar/',
			$this->instance->get( 'foo', 'de_DE' ),
			'Custom path for textdomain not returned'
		);
		$this->assertArrayHasKey(
			WP_LANG_DIR . '/bar',
			$reflection_property->getValue( $this->instance ),
			'Custom path missing from cache'
		);
	}

	/**
	 * @covers ::get
	 * @dataProvider data_domains_locales
	 */
	public function test_get( $domain, $locale, $expected ) {
		$reflection          = new ReflectionClass( $this->instance );
		$reflection_property = $reflection->getProperty( 'cached_mo_files' );
		$reflection_property->setAccessible( true );

		$actual = $this->instance->get( $domain, $locale );
		$this->assertSame(
			$expected,
			$actual,
			'Expected languages directory path not matching actual one'
		);

		$this->assertArrayHasKey(
			WP_LANG_DIR . '/plugins',
			$reflection_property->getValue( $this->instance ),
			'Default plugins path missing from cache'
		);
	}

	/**
	 * @covers ::set
	 * @covers ::get
	 */
	public function test_set_populates_cache() {
		$this->instance->set( 'foo-plugin', 'de_DE', '/foo/bar' );

		$this->assertSame(
			'/foo/bar/',
			$this->instance->get( 'foo-plugin', 'de_DE' )
		);
	}

	public function data_domains_locales() {
		return array(
			'Non-existent plugin'            => array(
				'unknown-plugin',
				'en_US',
				false,
			),
			'Non-existent plugin with de_DE' => array(
				'unknown-plugin',
				'de_DE',
				false,
			),
			'Available de_DE translations'   => array(
				'internationalized-plugin',
				'de_DE',
				WP_LANG_DIR . '/plugins/',
			),
			'Available es_ES translations'   => array(
				'internationalized-plugin',
				'es_ES',
				WP_LANG_DIR . '/plugins/',
			),
			'Unavailable fr_FR translations' => array(
				'internationalized-plugin',
				'fr_FR',
				false,
			),
			'Unavailable en_US translations' => array(
				'internationalized-plugin',
				'en_US',
				false,
			),
		);
	}
}
