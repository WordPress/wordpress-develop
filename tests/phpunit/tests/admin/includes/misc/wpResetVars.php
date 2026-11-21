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
	 * Backs up whether global variables existed before each test.
	 *
	 * @var array
	 */
	private $had_globals = array();

	/**
	 * Backs up $_GET and $_POST.
	 *
	 * @var array
	 */
	private $original_get  = array();
	private $original_post = array();

	public function set_up() {
		parent::set_up();
		$this->original_get     = $_GET;
		$this->original_post    = $_POST;
		$this->original_globals = array();
		$this->had_globals      = array();
	}

	public function tear_down() {
		$_GET  = $this->original_get;
		$_POST = $this->original_post;

		foreach ( $this->had_globals as $var => $had_global ) {
			if ( $had_global ) {
				$GLOBALS[ $var ] = $this->original_globals[ $var ];
			} else {
				unset( $GLOBALS[ $var ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests wp_reset_vars() with various inputs.
	 *
	 * @ticket 65180
	 * @dataProvider data_wp_reset_vars
	 *
	 * @param array $vars            Array of variables to reset.
	 * @param array $get             Mocked $_GET array.
	 * @param array $post            Mocked $_POST array.
	 * @param array $expected_values Expected values for the globals.
	 */
	public function test_wp_reset_vars( $vars, $get, $post, $expected_values ) {
		// Back up current globals that will be affected and initialize them with a sentinel value.
		foreach ( $vars as $var ) {
			$this->had_globals[ $var ] = array_key_exists( $var, $GLOBALS );
			if ( $this->had_globals[ $var ] ) {
				$this->original_globals[ $var ] = $GLOBALS[ $var ];
			}
			$GLOBALS[ $var ] = 'initial_value';
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
	public static function data_wp_reset_vars(): array {
		return array(
			'multiple_vars' => array(
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
			'handles_zero'  => array(
				'vars'            => array( 'v1', 'v2' ),
				'get'             => array(
					'v1' => 'g1',
					'v2' => '0',
				),
				'post'            => array(
					'v1' => '0',
					'v2' => '',
				),
				'expected_values' => array(
					'v1' => 'g1',
					'v2' => '',
				),
			),
		);
	}
}
