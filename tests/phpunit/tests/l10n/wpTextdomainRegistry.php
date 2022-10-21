<?php

/**
 * @group l10n
 * @group i18n
 * @ticket 39210
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
	 * @covers ::set_default_path
	 */
	public function test_set_default_path() {
		$this->instance->set_default_path( 'foo', WP_LANG_DIR );
		$this->assertTrue( $this->instance->has( 'foo' ) );
	}

	/**
	 * @covers ::get
	 */
	public function test_get() {
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
