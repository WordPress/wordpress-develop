<?php
/**
 * Tests for the wp_reset_vars() function.
 *
 * @group admin
 * @group admin-includes
 * @covers ::wp_reset_vars
 */
class Tests_wp_reset_vars extends WP_UnitTestCase {

	/**
	 * Backs up global variables to be restored after each test.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Backs up $_GET and $_POST.
	 *
	 * @var array
	 */
	private $original_get  = array();
	private $original_post = array();

	public function set_up() {
		parent::set_up();
		$this->original_get  = $_GET;
		$this->original_post = $_POST;
	}

	public function tear_down() {
		$_GET  = $this->original_get;
		$_POST = $this->original_post;

		foreach ( $this->original_globals as $var => $value ) {
			$GLOBALS[ $var ] = $value;
		}

		parent::tear_down();
	}

	/**
	 * Tests wp_reset_vars() with various inputs.
	 *
	 * @ticket 65180
	 * @dataProvider data_wp_reset_vars
	 *
	 * @param array $vars           Array of variables to reset.
	 * @param array $get            Mocked $_GET array.
	 * @param array $post           Mocked $_POST array.
	 * @param array $expected_values Expected values for the globals.
	 */
	public function test_wp_reset_vars( $vars, $get, $post, $expected_values ) {
		if ( empty( $vars ) ) {
			$this->assertTrue( true ); // Avoid risky test.
			return;
		}
		// Back up current globals that will be affected.
		foreach ( $vars as $var ) {
			if ( isset( $GLOBALS[ $var ] ) ) {
				$this->original_globals[ $var ] = $GLOBALS[ $var ];
			} else {
				// Use a unique marker if it wasn't set.
				$this->original_globals[ $var ] = null;
			}
		}

		$_GET  = $get;
		$_POST = $post;

		wp_reset_vars( $vars );

		foreach ( $expected_values as $var => $expected_value ) {
			$this->assertSame( $expected_value, $GLOBALS[ $var ], "Global \$$var does not match expected value." );
		}
	}

	/**
	 * Data provider for test_wp_reset_vars.
	 *
	 * @return array<string, array{
	 *     vars:            string[],
	 *     get:             array<string, mixed>,
	 *     post:            array<string, mixed>,
	 *     expected_values: array<string, mixed>,
	 * }>
	 */
	public function data_wp_reset_vars(): array {
		return array(
			'empty_vars'           => array(
				'vars'            => array(),
				'get'             => array( 'a' => '1' ),
				'post'            => array( 'b' => '2' ),
				'expected_values' => array(),
			),
			'reset_from_post'      => array(
				'vars'            => array( 'var1' ),
				'get'             => array( 'var1' => 'get_val' ),
				'post'            => array( 'var1' => 'post_val' ),
				'expected_values' => array( 'var1' => 'post_val' ),
			),
			'reset_from_get'       => array(
				'vars'            => array( 'var1' ),
				'get'             => array( 'var1' => 'get_val' ),
				'post'            => array(),
				'expected_values' => array( 'var1' => 'get_val' ),
			),
			'reset_to_empty'       => array(
				'vars'            => array( 'var1' ),
				'get'             => array(),
				'post'            => array(),
				'expected_values' => array( 'var1' => '' ),
			),
			'multiple_vars'        => array(
				'vars'            => array( 'v1', 'v2', 'v3' ),
				'get'             => array(
					'v1' => 'g1',
					'v2' => 'g2',
				),
				'post'            => array( 'v1' => 'p1' ),
				'expected_values' => array(
					'v1' => 'p1',
					'v2' => 'g2',
					'v3' => '',
				),
			),
			'handles_empty_string' => array(
				'vars'            => array( 'v1' ),
				'get'             => array( 'v1' => 'g1' ),
				'post'            => array( 'v1' => '' ), // empty() will be true for ''
				'expected_values' => array( 'v1' => 'g1' ),
			),
			'handles_zero'         => array(
				'vars'            => array( 'v1' ),
				'get'             => array( 'v1' => 'g1' ),
				'post'            => array( 'v1' => '0' ), // empty() will be true for '0'
				'expected_values' => array( 'v1' => 'g1' ),
			),
		);
	}
}
