<?php

/**
 * @group functions.php
 * @group query
 * @covers ::wp_nonce_ays
 */
class Tests_Functions_wp_die extends WP_UnitTestCase {

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {


	}
	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {

//		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 99 );
	}

	/**
	 * Retrieves the `wp_die()` handler.
	 *
	 * @param callable $handler The current die handler.
	 * @return callable The test die handler.
	 */
	public function get_wp_die_handler( $handler ) {
		return array( $this, 'mock_die' );
	}

	function mock_die( $message, $title = '', $args = array() ){
		global $results;

		$results['message'] = $message;
		$results['title']  = $title;
		$results['args'] = $args;
	}

	public function test_wp_die() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'random_string' );
		$this->expectExceptionCode( 0 );

		wp_die( 'random_string' );

	}

	/**
	 *
	 */
	public function test_wp_die_defaults() {
		global $results;
		$results = array();

		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 99 );
		/**
		 *     @type int    $response       The HTTP response code. Default 200 for Ajax requests, 500 otherwise.
		 *     @type string $link_url       A URL to include a link to. Only works in combination with $link_text.
		 *                                  Default empty string.
		 *     @type string $link_text      A label for the link to include. Only works in combination with $link_url.
		 *                                  Default empty string.
		 *     @type bool   $back_link      Whether to include a link to go back. Default false.
		 *     @type string $text_direction The text direction. This is only useful internally, when WordPress is still
		 *                                  loading and the site's locale is not set up yet. Accepts 'rtl' and 'ltr'.
		 *                                  Default is the value of is_rtl().
		 *     @type string $charset        Character set of the HTML output. Default 'utf-8'.
		 *     @type string $code           Error code to use. Default is 'wp_die', or the main error code if $message
		 *                                  is a WP_Error.
		 *     @type bool   $exit           Whether to exit the process after completion. Default true.
		 */
		$args = array(
			'response' => 'response',
			'link_url' => 'link_url',
			'link_text ' => 'link_text',
			'back_link ' => 'back_link',
			'text_direction' => 'text_direction',
			'charset ' => 'charset',
			'code' => 'code',
			'exit' => 'exit',
		);
		wp_die( 'message', 'title', $args );
		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 99 );

		$expected["message"]= "message";
		$expected["title"]= 'title';
		$expected["args"]= $args;



		$this->assertEquals($results, $expected );
	}

	public function test_wp_die_title_int() {
		global $results;
		$results = array();

		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 99 );
		/**
		 *     @type int    $response       The HTTP response code. Default 200 for Ajax requests, 500 otherwise.
		 *     @type string $link_url       A URL to include a link to. Only works in combination with $link_text.
		 *                                  Default empty string.
		 *     @type string $link_text      A label for the link to include. Only works in combination with $link_url.
		 *                                  Default empty string.
		 *     @type bool   $back_link      Whether to include a link to go back. Default false.
		 *     @type string $text_direction The text direction. This is only useful internally, when WordPress is still
		 *                                  loading and the site's locale is not set up yet. Accepts 'rtl' and 'ltr'.
		 *                                  Default is the value of is_rtl().
		 *     @type string $charset        Character set of the HTML output. Default 'utf-8'.
		 *     @type string $code           Error code to use. Default is 'wp_die', or the main error code if $message
		 *                                  is a WP_Error.
		 *     @type bool   $exit           Whether to exit the process after completion. Default true.
		 */
		$args = array(
			'response' => 555,
		);
		wp_die( 'message', 555 );
		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 99 );

		$expected["message"]= "message";
		$expected["title"]= '';
		$expected["args"]= $args;


		$this->assertEquals($results, $expected );
	}
}
