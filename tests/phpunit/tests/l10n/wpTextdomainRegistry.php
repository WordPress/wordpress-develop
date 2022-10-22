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
	 * @covers ::set_custom_path
	 */
	public function test_set_custom_path() {
		$this->instance->set_custom_path( 'foo', WP_LANG_DIR . '/bar' );
		$this->assertTrue( $this->instance->has( 'foo' ) );
		$this->assertSame( WP_LANG_DIR . '/bar/', $this->instance->get( 'foo', 'de_DE' ) );
	}

	/**
	 * @covers ::get
	 */
	public function test_get() {
		$reflection          = new ReflectionClass( $this->instance );
		$reflection_property = $reflection->getProperty( 'cached_mo_files' );
		$reflection_property->setAccessible( true );
		$this->assertFalse( $this->instance->get( 'unknown-plugin', 'de_DE' ) );
		$this->assertSame(
			WP_LANG_DIR . '/plugins/',
			$this->instance->get( 'internationalized-plugin', 'de_DE' )
		);
		$this->assertSame(
			WP_LANG_DIR . '/plugins/',
			$this->instance->get( 'internationalized-plugin', 'es_ES' )
		);
		$this->assertFalse(
			$this->instance->get( 'internationalized-plugin', 'en_US' )
		);
		$this->assertArrayHasKey(
			WP_LANG_DIR . '/plugins',
			$reflection_property->getValue( $this->instance )
		);
	}

	/**
	 * @covers ::set
	 * @covers ::get
	 * @covers ::has
	 */
	public function test_set() {
		$this->instance->set( 'foo-plugin', 'de_DE', '/foo/bar' );
		$this->assertSame(
			'/foo/bar/',
			$this->instance->get( 'foo-plugin', 'de_DE' )
		);
		$this->assertTrue( $this->instance->has( 'foo-plugin' ) );
	}
}
