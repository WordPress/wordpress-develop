<?php

/**
 * Tests for wp_magic_quotes().
 *
 * @group load
 *
 * @covers ::wp_magic_quotes
 */
class Tests_Load_WpMagicQuotes extends WP_UnitTestCase {

	/**
	 * Original $_GET value.
	 *
	 * @var array
	 */
	private $original_get;

	/**
	 * Original $_POST value.
	 *
	 * @var array
	 */
	private $original_post;

	/**
	 * Original $_COOKIE value.
	 *
	 * @var array
	 */
	private $original_cookie;

	/**
	 * Original $_SERVER value.
	 *
	 * @var array
	 */
	private $original_server;

	/**
	 * Original $_REQUEST value.
	 *
	 * @var array
	 */
	private $original_request;

	public function set_up() {
		parent::set_up();

		$this->original_get     = $_GET;
		$this->original_post    = $_POST;
		$this->original_cookie  = $_COOKIE;
		$this->original_server  = $_SERVER;
		$this->original_request = $_REQUEST;
	}

	public function tear_down() {
		$_GET     = $this->original_get;
		$_POST    = $this->original_post;
		$_COOKIE  = $this->original_cookie;
		$_SERVER  = $this->original_server;
		$_REQUEST = $this->original_request;

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_escapes_quotes_in_superglobals() {
		$_GET                   = array( 'q' => "It's a query" );
		$_POST                  = array( 'name' => 'Say "hello"' );
		$_COOKIE                = array( 'cookie' => "O'Reilly" );
		$_SERVER['HTTP_X_TEST'] = "It's a header";
		$_REQUEST               = array();

		wp_magic_quotes();

		$this->assertSame( "It\'s a query", $_GET['q'] );
		$this->assertSame( 'Say \"hello\"', $_POST['name'] );
		$this->assertSame( "O\'Reilly", $_COOKIE['cookie'] );
		$this->assertSame( "It\'s a header", $_SERVER['HTTP_X_TEST'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_rebuilds_request_from_get_and_post() {
		$_GET     = array(
			'shared' => 'from get',
			'only'   => "get's value",
		);
		$_POST    = array(
			'shared' => 'from post',
			'body'   => 'Say "hi"',
		);
		$_COOKIE  = array( 'cookie' => 'should not appear' );
		$_REQUEST = array( 'stale' => 'should be replaced' );

		wp_magic_quotes();

		$this->assertSame(
			array(
				'shared' => 'from post',
				'only'   => "get\'s value",
				'body'   => 'Say \"hi\"',
			),
			$_REQUEST
		);
		$this->assertArrayNotHasKey( 'stale', $_REQUEST );
		$this->assertArrayNotHasKey( 'cookie', $_REQUEST );
	}
}
