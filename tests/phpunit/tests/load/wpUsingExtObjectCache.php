<?php

/**
 * Tests for wp_using_ext_object_cache().
 *
 * @group load
 *
 * @covers ::wp_using_ext_object_cache
 */
class Tests_Load_wpUsingExtObjectCache extends WP_UnitTestCase {

	/**
	 * Whether $_wp_using_ext_object_cache existed before the test ran.
	 *
	 * The global is only ever set when an object cache drop-in is present,
	 * so it may legitimately be absent and must be restored as such.
	 *
	 * @var bool
	 */
	private $orig_using_ext_cache_set;

	/**
	 * The original value of $_wp_using_ext_object_cache, if it was set.
	 *
	 * @var mixed
	 */
	private $orig_using_ext_cache;

	public function set_up() {
		parent::set_up();

		$this->orig_using_ext_cache_set = array_key_exists( '_wp_using_ext_object_cache', $GLOBALS );
		$this->orig_using_ext_cache     = $this->orig_using_ext_cache_set ? $GLOBALS['_wp_using_ext_object_cache'] : null;
	}

	public function tear_down() {
		if ( $this->orig_using_ext_cache_set ) {
			$GLOBALS['_wp_using_ext_object_cache'] = $this->orig_using_ext_cache;
		} else {
			unset( $GLOBALS['_wp_using_ext_object_cache'] );
		}

		parent::tear_down();
	}

	public function test_should_always_return_boolean() {
		wp_using_ext_object_cache( 1 );
		$this->assertIsBool( wp_using_ext_object_cache() );
	}
}
