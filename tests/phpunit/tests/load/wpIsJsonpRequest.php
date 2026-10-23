<?php

/**
 * Tests for wp_is_jsonp_request().
 *
 * @group load
 *
 * @covers ::wp_is_jsonp_request
 */
class Tests_Load_WpIsJsonpRequest extends WP_UnitTestCase {

	/**
	 * Whether $_GET['_jsonp'] was set before the test.
	 *
	 * @var bool
	 */
	private $had_jsonp_query_arg;

	/**
	 * Original $_GET['_jsonp'] value when present.
	 *
	 * @var mixed
	 */
	private $original_jsonp_query_arg;

	public function set_up() {
		parent::set_up();

		$this->had_jsonp_query_arg = array_key_exists( '_jsonp', $_GET );
		if ( $this->had_jsonp_query_arg ) {
			$this->original_jsonp_query_arg = $_GET['_jsonp'];
		}
	}

	public function tear_down() {
		if ( $this->had_jsonp_query_arg ) {
			$_GET['_jsonp'] = $this->original_jsonp_query_arg;
		} else {
			unset( $_GET['_jsonp'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that a missing _jsonp query arg is not a JSONP request.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_jsonp_query_arg_is_missing() {
		unset( $_GET['_jsonp'] );

		$this->assertFalse( wp_is_jsonp_request() );
	}

	/**
	 * Tests that a valid _jsonp callback is treated as a JSONP request.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_valid_jsonp_callback() {
		$_GET['_jsonp'] = 'callback';

		$this->assertTrue( wp_is_jsonp_request() );
	}

	/**
	 * Tests that dotted callback names remain valid.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_dotted_jsonp_callback() {
		$_GET['_jsonp'] = 'jQuery.callback';

		$this->assertTrue( wp_is_jsonp_request() );
	}

	/**
	 * Tests that an invalid _jsonp callback is rejected.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_jsonp_callbacks
	 *
	 * @param mixed $callback Invalid callback value.
	 */
	public function test_returns_false_for_invalid_jsonp_callback( $callback ) {
		$_GET['_jsonp'] = $callback;

		$this->assertFalse( wp_is_jsonp_request() );
	}

	/**
	 * Data provider for invalid JSONP callbacks.
	 *
	 * @return array[]
	 */
	public static function data_invalid_jsonp_callbacks() {
		return array(
			'parentheses' => array( 'alert(1)' ),
			'spaces'      => array( 'my callback' ),
		);
	}

	/**
	 * Tests that the rest_jsonp_enabled filter can disable JSONP requests.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_jsonp_is_disabled_by_filter() {
		$_GET['_jsonp'] = 'callback';

		add_filter( 'rest_jsonp_enabled', '__return_false' );

		$this->assertFalse( wp_is_jsonp_request() );
	}
}
