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
			'output_schema'       => array(
				'type'        => 'number',
				'description' => 'The result of performing a math operation.',
				'required'    => true,
			),
			'permission_callback' => static function (): bool {
				return true;
			},
			'meta'                => array(
				'category' => 'math',
			),
		);
	}

	/**
	 * Data provider for testing the execution of the ability.
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
	 * @dataProvider data_execute_input
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
	 * Tests the execution of the ability with no input.
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

		add_action( 'before_execute_ability', $callback, 10, 2 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute( 5 );

		remove_action( 'before_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertSame( 5, $action_input, 'Action should receive correct input' );
		$this->assertSame( 10, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that before_execute_ability action is fired with null input when no input schema is defined.
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

		add_action( 'before_execute_ability', $callback, 10, 2 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'before_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertNull( $action_input, 'Action should receive null input when no input provided' );
		$this->assertSame( 42, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that after_execute_ability action is fired with correct parameters.
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

		add_action( 'after_execute_ability', $callback, 10, 3 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute( 7 );

		remove_action( 'after_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertSame( 7, $action_input, 'Action should receive correct input' );
		$this->assertSame( 21, $action_result, 'Action should receive correct result' );
		$this->assertSame( 21, $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that after_execute_ability action is fired with null input when no input schema is defined.
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

		add_action( 'after_execute_ability', $callback, 10, 3 );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'after_execute_ability', $callback );

		$this->assertSame( self::$test_ability_name, $action_ability_name, 'Action should receive correct ability name' );
		$this->assertNull( $action_input, 'Action should receive null input when no input provided' );
		$this->assertSame( 'test-result', $action_result, 'Action should receive correct result' );
		$this->assertSame( 'test-result', $result, 'Ability should execute correctly' );
	}

	/**
	 * Tests that neither action is fired when execution fails due to permission issues.
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
				'execute_callback'    => static function (): int {
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

		add_action( 'before_execute_ability', $before_callback );
		add_action( 'after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'before_execute_ability', $before_callback );
		remove_action( 'after_execute_ability', $after_callback );

		$this->assertFalse( $before_action_fired, 'before_execute_ability action should not be fired on permission failure' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired on permission failure' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error on permission failure' );
	}

	/**
	 * Tests that after_execute_ability action is not fired when execution callback returns WP_Error.
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

		add_action( 'before_execute_ability', $before_callback );
		add_action( 'after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'before_execute_ability', $before_callback );
		remove_action( 'after_execute_ability', $after_callback );

		$this->assertTrue( $before_action_fired, 'before_execute_ability action should be fired even if execution fails' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired when execution returns WP_Error' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error from execution callback' );
	}

	/**
	 * Tests that after_execute_ability action is not fired when output validation fails.
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

		add_action( 'before_execute_ability', $before_callback );
		add_action( 'after_execute_ability', $after_callback );

		$ability = new WP_Ability( self::$test_ability_name, $args );
		$result  = $ability->execute();

		remove_action( 'before_execute_ability', $before_callback );
		remove_action( 'after_execute_ability', $after_callback );

		$this->assertTrue( $before_action_fired, 'before_execute_ability action should be fired even if output validation fails' );
		$this->assertFalse( $after_action_fired, 'after_execute_ability action should not be fired when output validation fails' );
		$this->assertInstanceOf( WP_Error::class, $result, 'Should return WP_Error for output validation failure' );
	}
}
