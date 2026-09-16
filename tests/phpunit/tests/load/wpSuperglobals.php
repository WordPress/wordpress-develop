<?php

/**
 * Tests for WP_Superglobals and the wp_input_*() helper functions.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since x.x.x
 *
 * @group load
 *
 * @covers WP_Superglobals
 * @covers ::wp_input_get
 * @covers ::wp_input_post
 * @covers ::wp_input_request
 * @covers ::wp_input_cookie
 * @covers ::wp_input_server
 *
 * @ticket 22325
 */
class Tests_Load_WP_Superglobals extends WP_UnitTestCase {

	/**
	 * Backed-up superglobals for restoration in tear_down().
	 *
	 * @var array
	 */
	private $original_superglobals = array();

	/**
	 * Stores superglobals before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_superglobals = array(
			'_GET'     => $_GET,
			'_POST'    => $_POST,
			'_REQUEST' => $_REQUEST,
			'_COOKIE'  => $_COOKIE,
			'_SERVER'  => $_SERVER,
		);
	}

	/**
	 * Restores original superglobals and re-runs wp_magic_quotes().
	 */
	public function tear_down() {
		$_GET     = $this->original_superglobals['_GET'];
		$_POST    = $this->original_superglobals['_POST'];
		$_REQUEST = $this->original_superglobals['_REQUEST'];
		$_COOKIE  = $this->original_superglobals['_COOKIE'];
		$_SERVER  = $this->original_superglobals['_SERVER'];
		wp_magic_quotes();
		parent::tear_down();
	}

	/**
	 * Tests that get() returns unslashed values and defaults for missing keys.
	 *
	 * @ticket 22325
	 */
	public function test_get_returns_unslashed_value_and_default() {
		$data    = array( 'key' => addslashes( "it's a test" ) );
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$this->assertSame( "it's a test", $wrapper->get( 'key' ) );
		$this->assertNull( $wrapper->get( 'missing' ) );
		$this->assertSame( 'fallback', $wrapper->get( 'missing', 'fallback' ) );
	}

	/**
	 * Tests that get() unslashes nested array values.
	 *
	 * @ticket 22325
	 */
	public function test_get_returns_unslashed_nested_array() {
		$data    = array(
			'nested' => array(
				'child' => addslashes( "it's nested" ),
			),
		);
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$result = $wrapper->get( 'nested' );
		$this->assertSame( "it's nested", $result['child'] );
	}

	/**
	 * Tests has() for existing and missing keys.
	 *
	 * @ticket 22325
	 */
	public function test_has() {
		$data    = array( 'present' => 'value' );
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$this->assertTrue( $wrapper->has( 'present' ) );
		$this->assertFalse( $wrapper->has( 'absent' ) );
	}

	/**
	 * Tests that all() returns all values unslashed.
	 *
	 * @ticket 22325
	 */
	public function test_all_returns_all_unslashed_values() {
		$data    = array(
			'a' => addslashes( "it's a" ),
			'b' => addslashes( "it's b" ),
		);
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$expected = array(
			'a' => "it's a",
			'b' => "it's b",
		);
		$this->assertSame( $expected, $wrapper->all() );
	}

	/**
	 * Tests ArrayAccess: offsetExists, offsetGet, and read-only behavior.
	 *
	 * @ticket 22325
	 */
	public function test_array_access() {
		$data    = array( 'key' => addslashes( "it's a value" ) );
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		// offsetExists.
		$this->assertTrue( isset( $wrapper['key'] ) );
		$this->assertFalse( isset( $wrapper['missing'] ) );

		// offsetGet.
		$this->assertSame( "it's a value", $wrapper['key'] );
		$this->assertNull( $wrapper['missing'] );

		// offsetSet is a no-op.
		$wrapper['key'] = 'modified';
		$this->assertSame( "it's a value", $wrapper['key'] );

		// offsetUnset is a no-op.
		unset( $wrapper['key'] );
		$this->assertTrue( isset( $wrapper['key'] ) );
	}

	/**
	 * Tests Countable and IteratorAggregate implementations.
	 *
	 * @ticket 22325
	 */
	public function test_countable_and_iterable() {
		$data    = array(
			'a' => addslashes( "it's a" ),
			'b' => addslashes( "it's b" ),
		);
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$this->assertCount( 2, $wrapper );

		$result = array();
		foreach ( $wrapper as $key => $value ) {
			$result[ $key ] = $value;
		}

		$expected = array(
			'a' => "it's a",
			'b' => "it's b",
		);
		$this->assertSame( $expected, $result );
	}

	/**
	 * Tests that the wrapper references live data, reflecting changes
	 * and new keys added to the underlying array.
	 *
	 * @ticket 22325
	 */
	public function test_wrapper_reflects_live_superglobal_changes() {
		$data    = array( 'key' => 'original' );
		$wrapper = new WP_Superglobals( $data, '$_TEST' );

		$this->assertSame( 'original', $wrapper->get( 'key' ) );

		// Modify existing key.
		$data['key'] = addslashes( "it's modified" );
		$this->assertSame( "it's modified", $wrapper->get( 'key' ) );

		// Add new key.
		$data['new_key'] = 'new_value';
		$this->assertTrue( $wrapper->has( 'new_key' ) );
		$this->assertSame( 'new_value', $wrapper->get( 'new_key' ) );
	}

	/**
	 * Tests global wrappers with real superglobals after wp_magic_quotes().
	 *
	 * @ticket 22325
	 */
	public function test_global_wrappers_after_wp_magic_quotes() {
		global $wp_get, $wp_post, $wp_request, $wp_cookie, $wp_server;

		$this->assertInstanceOf( WP_Superglobals::class, $wp_get );
		$this->assertInstanceOf( WP_Superglobals::class, $wp_post );
		$this->assertInstanceOf( WP_Superglobals::class, $wp_request );
		$this->assertInstanceOf( WP_Superglobals::class, $wp_cookie );
		$this->assertInstanceOf( WP_Superglobals::class, $wp_server );

		$_GET  = array( 'from_get' => "get's value" );
		$_POST = array( 'from_post' => "post's value" );
		wp_magic_quotes();

		// $_GET is slashed, wrapper is not.
		$this->assertSame( addslashes( "get's value" ), $_GET['from_get'] );
		$this->assertSame( "get's value", $wp_get['from_get'] );
		$this->assertSame( "post's value", $wp_post['from_post'] );

		// $_REQUEST merges GET + POST.
		$this->assertSame( "get's value", $wp_request->get( 'from_get' ) );
		$this->assertSame( "post's value", $wp_request->get( 'from_post' ) );
	}

	/**
	 * Tests each wp_input_*() helper returns unslashed values.
	 *
	 * @dataProvider data_wp_input_helpers
	 *
	 * @ticket 22325
	 *
	 * @param string $superglobal    The superglobal name ('_GET', '_POST', '_COOKIE', '_SERVER').
	 * @param string $function_name The helper function name.
	 */
	public function test_wp_input_helpers_return_unslashed( $superglobal, $function_name ) {
		$GLOBALS[ $superglobal ] = array( 'test_key' => "it's a test" );
		wp_magic_quotes();

		$this->assertSame( "it's a test", call_user_func( $function_name, 'test_key' ) );
		$this->assertNull( call_user_func( $function_name, 'missing' ) );
		$this->assertSame( 'default', call_user_func( $function_name, 'missing', 'default' ) );
	}

	/**
	 * Data provider for test_wp_input_helpers_return_unslashed().
	 *
	 * @return array[]
	 */
	public static function data_wp_input_helpers() {
		return array(
			'GET'    => array( '_GET', 'wp_input_get' ),
			'POST'   => array( '_POST', 'wp_input_post' ),
			'COOKIE' => array( '_COOKIE', 'wp_input_cookie' ),
			'SERVER' => array( '_SERVER', 'wp_input_server' ),
		);
	}

	/**
	 * Tests that wp_input_request() merges GET and POST, with POST taking precedence.
	 *
	 * @ticket 22325
	 */
	public function test_wp_input_request_merges_get_and_post() {
		$_GET  = array(
			'only_get' => "get's value",
			'shared'   => "get's version",
		);
		$_POST = array(
			'only_post' => "post's value",
			'shared'    => "post's version",
		);
		wp_magic_quotes();

		$this->assertSame( "get's value", wp_input_request( 'only_get' ) );
		$this->assertSame( "post's value", wp_input_request( 'only_post' ) );
		$this->assertSame( "post's version", wp_input_request( 'shared' ) );
		$this->assertNull( wp_input_request( 'missing' ) );
	}

	/**
	 * Tests that helpers handle array values and special characters.
	 *
	 * @ticket 22325
	 */
	public function test_wp_input_helpers_handle_complex_values() {
		$_GET  = array(
			'tags'  => array( "it's tag1", "it's tag2" ),
			'empty' => '',
		);
		$_POST = array(
			'quotes' => 'He said "hello"',
			'path'   => 'C:\Users\test',
		);
		wp_magic_quotes();

		$this->assertSame( array( "it's tag1", "it's tag2" ), wp_input_get( 'tags' ) );
		$this->assertSame( '', wp_input_get( 'empty' ) );
		$this->assertSame( 'He said "hello"', wp_input_post( 'quotes' ) );
		$this->assertSame( 'C:\Users\test', wp_input_post( 'path' ) );
	}
}
