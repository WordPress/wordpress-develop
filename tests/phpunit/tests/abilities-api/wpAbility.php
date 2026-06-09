<?php declare( strict_types=1 );

/**
 * Tests for the abilities registry functionality.
 *
 * @covers WP_Ability
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpAbility extends WP_UnitTestCase {

	public static $test_ability_name       = 'test/calculator';
	public static $test_ability_properties = array();

	/**
	 * Set up each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		self::$test_ability_properties = array(
			'label'               => 'Calculator',
			'description'         => 'Calculates the result of math operations.',
			'category'            => 'math',
			'output_schema'       => array(
				'type'        => 'number',
				'description' => 'The result of performing a math operation.',
				'required'    => true,
			),
			'execute_callback'    => static function (): int {
				return 0;
			},
			'permission_callback' => static function (): bool {
				return true;
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
				),
			),
		);
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	/**
	 * Direct instantiation of WP_Ability with invalid properties should throw an exception.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Ability::__construct
	 * @covers WP_Ability::prepare_properties
	 */
	public function test_wp_ability_invalid_properties_throws_exception() {
		$this->expectException( InvalidArgumentException::class );
		new WP_Ability(
			'test/invalid',
			array(
				'label'            => '',
				'description'      => '',
				'execute_callback' => null,
			)
		);
	}

	/*
	 * Tests that getting non-existing metadata item returns default value.
	 *
	 * @ticket 64098
	 */
	public function test_meta_get_non_existing_item_returns_default() {
		$ability = new WP_Ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertNull(
			$ability->get_meta_item( 'non_existing' ),
			'Non-existing metadata item should return null.'
		);
	}

	/**
	 * Tests that getting non-existing metadata item with custom default returns that default.
	 *
	 * @ticket 64098
	 */
	public function test_meta_get_non_existing_item_with_custom_default() {
		$ability = new WP_Ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertSame(
			'default_value',
			$ability->get_meta_item( 'non_existing', 'default_value' ),
			'Non-existing metadata item should return custom default value.'
		);
	}

	/**
	 * Tests getting all annotations when selective overrides are applied.
	 *
	 * @ticket 64098
	 */
	public function test_get_merged_annotations_from_meta() {
		$ability = new WP_Ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertSame(
			array_merge(
				self::$test_ability_properties['meta']['annotations'],
				array(
					'idempotent' => null,
				)
			),
			$ability->get_meta_item( 'annotations' )
		);
	}

	/**
	 * Tests getting default annotations when not provided.
	 *
	 * @ticket 64098
	 */
	public function test_get_default_annotations_from_meta() {
		$args = self::$test_ability_properties;
		unset( $args['meta']['annotations'] );

		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertSame(
			array(
				'readonly'    => null,
				'destructive' => null,
				'idempotent'  => null,
			),
			$ability->get_meta_item( 'annotations' )
		);
	}

	/**
	 * Tests getting all annotations when values overridden.
	 *
	 * @ticket 64098
	 */
	public function test_get_overridden_annotations_from_meta() {
		$annotations = array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => false,
		);
		$args        = array_merge(
			self::$test_ability_properties,
			array(
				'meta' => array(
					'annotations' => $annotations,
				),
			)
		);

		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertSame( $annotations, $ability->get_meta_item( 'annotations' ) );
	}

	/**
	 * Tests that invalid `annotations` value throws an exception.
	 *
	 * @ticket 64098
	 */
	public function test_annotations_from_meta_throws_exception() {
		$args = array_merge(
			self::$test_ability_properties,
			array(
				'meta' => array(
					'annotations' => 5,
				),
			)
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The ability meta should provide a valid `annotations` array.' );

		new WP_Ability( self::$test_ability_name, $args );
	}

	/**
	 * Tests that `show_in_rest` metadata defaults to false when not provided.
	 *
	 * @ticket 64098
	 */
	public function test_meta_show_in_rest_defaults_to_false() {
		$ability = new WP_Ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertFalse(
			$ability->get_meta_item( 'show_in_rest' ),
			'`show_in_rest` metadata should default to false.'
		);
	}

	/**
	 * Tests that `show_in_rest` metadata can be set to true.
	 *
	 * @ticket 64098
	 */
	public function test_meta_show_in_rest_can_be_set_to_true() {
		$args    = array_merge(
			self::$test_ability_properties,
			array(
				'meta' => array(
					'show_in_rest' => true,
				),
			)
		);
		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertTrue(
			$ability->get_meta_item( 'show_in_rest' ),
			'`show_in_rest` metadata should be true.'
		);
	}

	/**
	 * Tests that `show_in_rest` can be set to false.
	 *
	 * @ticket 64098
	 */
	public function test_show_in_rest_can_be_set_to_false() {
		$args    = array_merge(
			self::$test_ability_properties,
			array(
				'meta' => array(
					'show_in_rest' => false,
				),
			)
		);
		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertFalse(
			$ability->get_meta_item( 'show_in_rest' ),
			'`show_in_rest` metadata should be false.'
		);
	}

	/**
	 * Tests that invalid `show_in_rest` value throws an exception.
	 *
	 * @ticket 64098
	 */
	public function test_show_in_rest_throws_exception() {
		$args = array_merge(
			self::$test_ability_properties,
			array(
				'meta' => array(
					'show_in_rest' => 5,
				),
			)
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The ability meta should provide a valid `show_in_rest` boolean.' );

		new WP_Ability( self::$test_ability_name, $args );
	}

	/**
	 * Data provider for testing the execution of the ability.
	 *
	 * @return array<string, array{0: array, 1: callable, 2: mixed, 3: mixed}> Data sets with different configurations.
	 */
	public function data_execute_input() {
		return array(
			'null input'    => array(
				array(
					'type'        => array( 'null', 'integer' ),
					'description' => 'The null or integer to convert to integer.',
					'required'    => true,
				),
				static function ( $input ): int {
					return null === $input ? 0 : (int) $input;
				},
				null,
				0,
			),
			'boolean input' => array(
				array(
					'type'        => 'boolean',
					'description' => 'The boolean to convert to integer.',
					'required'    => true,
				),
				static function ( bool $input ): int {
					return $input ? 1 : 0;
				},
				true,
				1,
			),
			'integer input' => array(
				array(
					'type'        => 'integer',
					'description' => 'The integer to add 5 to.',
					'required'    => true,
				),
				static function ( int $input ): int {
					return 5 + $input;
				},
				2,
				7,
			),
			'number input'  => array(
				array(
					'type'        => 'number',
					'description' => 'The floating number to round.',
					'required'    => true,
				),
				static function ( float $input ): int {
					return (int) round( $input );
				},
				2.7,
				3,
			),
			'string input'  => array(
				array(
					'type'        => 'string',
					'description' => 'The string to measure the length of.',
					'required'    => true,
				),
				static function ( string $input ): int {
					return strlen( $input );
				},
				'Hello world!',
				12,
			),
			'object input'  => array(
				array(
					'type'                 => 'object',
					'description'          => 'An object containing two numbers to add.',
					'properties'           => array(
						'a' => array(
							'type'        => 'integer',
							'description' => 'First number.',
							'required'    => true,
						),
						'b' => array(
							'type'        => 'integer',
							'description' => 'Second number.',
							'required'    => true,
						),
					),
					'additionalProperties' => false,
				),
				static function ( array $input ): int {
					return $input['a'] + $input['b'];
				},
				array(
					'a' => 2,
					'b' => 3,
				),
				5,
			),
			'array input'   => array(
				array(
					'type'        => 'array',
					'description' => 'An array containing two numbers to add.',
					'required'    => true,
					'minItems'    => 2,
					'maxItems'    => 2,
					'items'       => array(
						'type' => 'integer',
					),
				),
				static function ( array $input ): int {
					return $input[0] + $input[1];
				},
				array( 2, 3 ),
				5,
			),
		);
	}

	/**
	 * Tests the execution of the ability.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_execute_input
	 *
	 * @param array    $input_schema      The input schema for the ability.
	 * @param callable $execute_callback  The execute callback for the ability.
	 * @param mixed    $input             The input to pass to the execute method.
	 * @param mixed    $result            The expected result from the execute method.
	 */
	public function test_execute_input( $input_schema, $execute_callback, $input, $result ) {
		$args = array_merge(
			self::$test_ability_properties,
			array(
				'input_schema'     => $input_schema,
				'execute_callback' => $execute_callback,
			)
		);

		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertSame( $result, $ability->execute( $input ) );
	}

	/**
	 * A static method to be used as a callback in tests.
	 *
	 * @param string $input An input string.
	 * @return int The length of the input string.
	 */
	public static function my_static_execute_callback( string $input ): int {
		return strlen( $input );
	}

	/**
	 * An instance method to be used as a callback in tests.
	 *
	 * @param string $input An input string.
	 * @return int The length of the input string.
	 */
	public function my_instance_execute_callback( string $input ): int {
		return strlen( $input );
	}

	/**
	 * Data provider for testing different types of execute callbacks.
	 *
	 * @return array<string, array{0: callable}> Data sets with different execute callbacks.
	 */
	public function data_execute_callback() {
		return array(
			'function name string'       => array(
				'strlen',
			),
			'closure'                    => array(
				static function ( string $input ): int {
					return strlen( $input );
				},
			),
			'static class method string' => array(
				'Tests_Abilities_API_WpAbility::my_static_execute_callback',
			),
			'static class method array'  => array(
				array( 'Tests_Abilities_API_WpAbility', 'my_static_execute_callback' ),
			),
			'object method'              => array(
				array( $this, 'my_instance_execute_callback' ),
			),
		);
	}

	/**
	 * Tests the execution of the ability with different types of callbacks.
	 *
	 * @ticket 64098
	 *
	 * @dataProvider data_execute_callback
	 *
	 * @param callable $execute_callback The execute callback to test.
	 */
	public function test_execute_with_different_callbacks( $execute_callback ) {
		$args = array_merge(
			self::$test_ability_properties,
			array(
				'input_schema'     => array(
					'type'        => 'string',
					'description' => 'Test input string.',
					'required'    => true,
				),
				'execute_callback' => $execute_callback,
			)
		);

		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertSame( 6, $ability->execute( 'hello!' ) );
	}

	/**
	 * Tests the execution of the ability with no input.
	 *
	 * @ticket 64098
	 */
	public function test_execute_no_input() {
		$args = array_merge(
			self::$test_ability_properties,
			array(
				'execute_callback' => static function (): int {
					return 42;
				},
			)
		);

		$ability = new WP_Ability( self::$test_ability_name, $args );

		$this->assertSame( 42, $ability->execute() );
	}

	/**
	 * Tests that before_execute_ability action is fired with correct parameters.
	 *
	 * @ticket 64098
	 */
	public function test_before_execute_ability_action() {
		$action_ability_name = null;
		$action_input        = null;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'input_schema'     => array(
					'type'        => 'integer',
					'description' => 'Test input parameter.',
					'required'    => true,
				),
				'execute_callback' => static function ( int $input ): int {
					return $input * 2;
				},
			)
		);

		$callback = static function ( $ability_name, $input ) use ( &$action_ability_name, &$action_input ) {
			$action_ability_name = $ability_name;
			$action_input        = $input;
		};

		add_action( 'wp_before_execute_ability', $callback, 10, 2 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute( 5 );

		remove_action( 'wp_before_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertSame( 5, $action_input, 'Action should receive correct input' );
		$this->assertSame( 10, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that before_execute_ability action is fired with null input when no input schema is defined.
	 *
	 * @ticket 64098
	 */
	public function test_before_execute_ability_action_no_input() {
		$action_ability_name = null;
		$action_input        = null;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'execute_callback' => static function (): int {
					return 42;
				},
			)
		);

		$callback = static function ( $ability_name, $input ) use ( &$action_ability_name, &$action_input ) {
			$action_ability_name = $ability_name;
			$action_input        = $input;
		};

		add_action( 'wp_before_execute_ability', $callback, 10, 2 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'wp_before_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertNull( $action_input, 'Action should receive null input when no input provided' );
		$this->assertSame( 42, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that after_execute_ability action is fired with correct parameters.
	 *
	 * @ticket 64098
	 */
	public function test_after_execute_ability_action() {
		$action_ability_name = null;
		$action_input        = null;
		$action_result       = null;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'input_schema'     => array(
					'type'        => 'integer',
					'description' => 'Test input parameter.',
					'required'    => true,
				),
				'execute_callback' => static function ( int $input ): int {
					return $input * 3;
				},
			)
		);

		$callback = static function ( $ability_name, $input, $result ) use ( &$action_ability_name, &$action_input, &$action_result ) {
			$action_ability_name = $ability_name;
			$action_input        = $input;
			$action_result       = $result;
		};

		add_action( 'wp_after_execute_ability', $callback, 10, 3 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute( 7 );

		remove_action( 'wp_after_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertSame( 7, $action_input, 'Action should receive correct input' );
		$this->assertSame( 21, $action_result, 'Action should receive correct result' );
		$this->assertSame( 21, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that after_execute_ability action is fired with null input when no input schema is defined.
	 *
	 * @ticket 64098
	 */
	public function test_after_execute_ability_action_no_input() {
		$action_ability_name = null;
		$action_input        = null;
		$action_result       = null;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'output_schema'    => array(),
				'execute_callback' => static function (): string {
					return 'test-result';
				},
			)
		);

		$callback = static function ( $ability_name, $input, $result ) use ( &$action_ability_name, &$action_input, &$action_result ) {
			$action_ability_name = $ability_name;
			$action_input        = $input;
			$action_result       = $result;
		};

		add_action( 'wp_after_execute_ability', $callback, 10, 3 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'wp_after_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertNull( $action_input, 'Action should receive null input when no input provided' );
		$this->assertSame( 'test-result', $action_result, 'Action should receive correct result' );
		$this->assertSame( 'test-result', $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that neither action is fired when execution fails due to permission issues.
	 *
	 * @ticket 64098
	 */
	public function test_actions_not_fired_on_permission_failure() {
		$before_action_fired = false;
		$after_action_fired  = false;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'permission_callback' => static function (): bool {
					return false;
				},
			)
		);

		$before_callback = static function () use ( &$before_action_fired ) {
			$before_action_fired = true;
		};

		$after_callback = static function () use ( &$after_action_fired ) {
			$after_action_fired = true;
		};

		add_action( 'wp_before_execute_ability', $before_callback );
		add_action( 'wp_after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'wp_before_execute_ability', $before_callback );
		remove_action( 'wp_after_execute_ability', $after_callback );

		$this->assertFalse( $before_action_fired, 'before_execute_ability action should not be fired on permission failure' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired on permission failure' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error on permission failure' );
	}

	/**
	 * Tests that after_execute_ability action is not fired when execution callback returns WP_Error.
	 *
	 * @ticket 64098
	 */
	public function test_after_action_not_fired_on_execution_error() {
		$before_action_fired = false;
		$after_action_fired  = false;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'execute_callback' => static function () {
					return new WP_Error( 'test_error', 'Test execution error' );
				},
			)
		);

		$before_callback = static function () use ( &$before_action_fired ) {
			$before_action_fired = true;
		};

		$after_callback = static function () use ( &$after_action_fired ) {
			$after_action_fired = true;
		};

		add_action( 'wp_before_execute_ability', $before_callback );
		add_action( 'wp_after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'wp_before_execute_ability', $before_callback );
		remove_action( 'wp_after_execute_ability', $after_callback );

		$this->assertTrue( $before_action_fired, 'before_execute_ability action should be fired even if execution fails' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired when execution returns WP_Error' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error from execution callback' );
	}

	/**
	 * Tests that after_execute_ability action is not fired when output validation fails.
	 *
	 * @ticket 64098
	 */
	public function test_after_action_not_fired_on_output_validation_error() {
		$before_action_fired = false;
		$after_action_fired  = false;

		$args = array_merge(
			self::$test_ability_properties,
			array(
				'output_schema'    => array(
					'type'        => 'string',
					'description' => 'Expected string output.',
					'required'    => true,
				),
				'execute_callback' => static function (): int {
					return 42;
				},
			)
		);

		$before_callback = static function () use ( &$before_action_fired ) {
			$before_action_fired = true;
		};

		$after_callback = static function () use ( &$after_action_fired ) {
			$after_action_fired = true;
		};

		add_action( 'wp_before_execute_ability', $before_callback );
		add_action( 'wp_after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'wp_before_execute_ability', $before_callback );
		remove_action( 'wp_after_execute_ability', $after_callback );

		$this->assertTrue( $before_action_fired, 'before_execute_ability action should be fired even if output validation fails' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired when output validation fails' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error for output validation failure' );
	}
}
