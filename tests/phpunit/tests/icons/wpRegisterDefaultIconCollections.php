<?php
/**
 * Unit tests covering the default icon collection registration.
 *
 * @package WordPress
 * @subpackage Icons
 * @since 7.2.0
 *
 * @group icons
 *
 * @covers ::_wp_register_default_icon_collections
 */
class Tests_Icons_WpRegisterDefaultIconCollections extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		/*
		 * Other suites reset the `WP_Icon_Collections_Registry` singleton, wiping the
		 * collections that `init` only registers once. Re-register them when the default
		 * collections are gone so order-dependent tests pass.
		 */
		if ( ! WP_Icon_Collections_Registry::get_instance()->is_registered( 'core' ) ) {
			_wp_register_default_icon_collections();
		}
	}

	/**
	 * @ticket 66114
	 */
	public function test_core_collection_is_public() {
		$collection = WP_Icon_Collections_Registry::get_instance()->get_registered( 'core' );

		$this->assertIsArray( $collection, 'The core collection should be registered.' );
		$this->assertTrue( $collection['public'], 'The core collection should be public.' );
	}

	/**
	 * @ticket 66114
	 */
	public function test_core_admin_collection_is_not_public() {
		$collection = WP_Icon_Collections_Registry::get_instance()->get_registered( 'core-admin' );

		$this->assertIsArray( $collection, 'The core-admin collection should be registered.' );
		$this->assertFalse(
			$collection['public'],
			'The core-admin collection should not be public, so that the admin icons stay out of the REST API.'
		);
	}
}
