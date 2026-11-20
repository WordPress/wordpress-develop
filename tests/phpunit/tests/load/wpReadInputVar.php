<?php

/**
 * Tests for the functions that read input vars.
 *
 * @group load
 *
 * @covers ::wp_read_get_input_var_string
 * @covers ::wp_read_post_input_var_string
 * @covers ::wp_read_request_input_var_string
 * @covers ::wp_read_get_input_var_string_array
 * @covers ::wp_read_post_input_var_string_array
 * @covers ::wp_read_request_input_var_string_array
 * @covers ::_wp_read_input_var_string
 * @covers ::_wp_read_input_var_string_array
 */
class Tests_Load_wpReadInputVar extends WP_UnitTestCase {

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
	 * Original $_REQUEST value.
	 *
	 * @var array
	 */
	private $original_request;

	public function set_up() {
		parent::set_up();

		$this->original_get     = $_GET;
		$this->original_post    = $_POST;
		$this->original_request = $_REQUEST;
	}

	public function tear_down() {
		$_GET     = $this->original_get;
		$_POST    = $this->original_post;
		$_REQUEST = $this->original_request;

		parent::tear_down();
	}

	/**
	 * Sets an input var the way wp_magic_quotes() leaves it.
	 *
	 * @param string $source One of 'get', 'post', or 'request'.
	 * @param string $name   Name of the input var.
	 * @param mixed  $value  Unslashed value.
	 */
	private function set_input_var( $source, $name, $value ) {
		$slashed = add_magic_quotes( array( $name => $value ) );

		if ( 'get' === $source ) {
			$_GET[ $name ] = $slashed[ $name ];
		} elseif ( 'post' === $source ) {
			$_POST[ $name ] = $slashed[ $name ];
		} else {
			$_REQUEST[ $name ] = $slashed[ $name ];
		}
	}

	/**
	 * Tests reading a string input var.
	 *
	 * @ticket 22325
	 *
	 * @dataProvider data_read_input_var_string
	 *
	 * @param string      $source   One of 'get', 'post', or 'request'.
	 * @param mixed       $value    Unslashed value of the input var.
	 * @param string|null $expected Expected result.
	 */
	public function test_read_input_var_string( $source, $value, $expected ) {
		$this->set_input_var( $source, 'test_var', $value );

		$function = "wp_read_{$source}_input_var_string";

		$this->assertSame( $expected, $function( 'test_var' ) );
	}

	/**
	 * Data provider for test_read_input_var_string().
	 *
	 * @return array[]
	 */
	public function data_read_input_var_string() {
		$cases = array(
			'plain string'           => array( 'hello', 'hello' ),
			'slashed string'         => array( "O'Reilly \"quoted\" back\\slash", "O'Reilly \"quoted\" back\\slash" ),
			'empty string'           => array( '', '' ),
			'zero string'            => array( '0', '0' ),
			'integer set by code'    => array( 5, '5' ),
			'list array like ?s[]=1' => array( array( '1' ), null ),
			'keyed array like ?s[a]' => array( array( 'a' => '1' ), null ),
			'empty array'            => array( array(), null ),
		);

		$data = array();
		foreach ( array( 'get', 'post', 'request' ) as $source ) {
			foreach ( $cases as $label => $case ) {
				$data[ "{$source}: {$label}" ] = array_merge( array( $source ), $case );
			}
		}

		return $data;
	}

	/**
	 * Tests that a string input var that is not set returns null.
	 *
	 * @ticket 22325
	 */
	public function test_read_input_var_string_returns_null_when_not_set() {
		unset( $_GET['test_var'], $_POST['test_var'], $_REQUEST['test_var'] );

		$this->assertNull( wp_read_get_input_var_string( 'test_var' ), 'GET' );
		$this->assertNull( wp_read_post_input_var_string( 'test_var' ), 'POST' );
		$this->assertNull( wp_read_request_input_var_string( 'test_var' ), 'REQUEST' );
	}

	/**
	 * Tests that each function reads only its own superglobal.
	 *
	 * @ticket 22325
	 */
	public function test_read_input_var_string_reads_only_its_own_source() {
		unset( $_GET['test_var'], $_POST['test_var'], $_REQUEST['test_var'] );
		$this->set_input_var( 'post', 'test_var', 'from post' );

		$this->assertNull( wp_read_get_input_var_string( 'test_var' ), 'GET' );
		$this->assertSame( 'from post', wp_read_post_input_var_string( 'test_var' ), 'POST' );
		$this->assertNull( wp_read_request_input_var_string( 'test_var' ), 'REQUEST' );
	}

	/**
	 * Tests reading an array of strings from an input var.
	 *
	 * @ticket 22325
	 *
	 * @dataProvider data_read_input_var_string_array
	 *
	 * @param string     $source   One of 'get', 'post', or 'request'.
	 * @param mixed      $value    Unslashed value of the input var.
	 * @param array|null $expected Expected result.
	 */
	public function test_read_input_var_string_array( $source, $value, $expected ) {
		$this->set_input_var( $source, 'test_var', $value );

		$function = "wp_read_{$source}_input_var_string_array";

		$this->assertSame( $expected, $function( 'test_var' ) );
	}

	/**
	 * Data provider for test_read_input_var_string_array().
	 *
	 * @return array[]
	 */
	public function data_read_input_var_string_array() {
		$cases = array(
			'list of strings'           => array( array( 'a', "b'c" ), array( 'a', "b'c" ) ),
			'keys are kept'             => array(
				array(
					'first'  => '1',
					'second' => '2',
				),
				array(
					'first'  => '1',
					'second' => '2',
				),
			),
			'nested values are skipped' => array(
				array(
					'1',
					array( '2' ),
					'k' => array( 'x' => 'y' ),
					'z' => "it's",
				),
				array(
					0   => '1',
					'z' => "it's",
				),
			),
			'integers set by code'      => array( array( 1, 2 ), array( '1', '2' ) ),
			'empty array'               => array( array(), array() ),
			'string like ?ids=1'        => array( '1', null ),
			'empty string like ?ids='   => array( '', null ),
		);

		$data = array();
		foreach ( array( 'get', 'post', 'request' ) as $source ) {
			foreach ( $cases as $label => $case ) {
				$data[ "{$source}: {$label}" ] = array_merge( array( $source ), $case );
			}
		}

		return $data;
	}

	/**
	 * Tests that an array input var that is not set returns null.
	 *
	 * @ticket 22325
	 */
	public function test_read_input_var_string_array_returns_null_when_not_set() {
		unset( $_GET['test_var'], $_POST['test_var'], $_REQUEST['test_var'] );

		$this->assertNull( wp_read_get_input_var_string_array( 'test_var' ), 'GET' );
		$this->assertNull( wp_read_post_input_var_string_array( 'test_var' ), 'POST' );
		$this->assertNull( wp_read_request_input_var_string_array( 'test_var' ), 'REQUEST' );
	}
}
