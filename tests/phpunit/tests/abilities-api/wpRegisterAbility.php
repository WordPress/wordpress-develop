<?php declare( strict_types=1 );

/**
 * @covers wp_register_ability
 * @covers wp_unregister_ability
 * @covers wp_get_ability
 * @covers wp_get_all_abilities
 *
 * @group abilities-api
 */
class Test_Abilities_API_WpRegisterAbility extends WP_UnitTestCase {

	public static $test_ability_name       = 'test/add-numbers';
	public static $test_ability_properties = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		self::$test_ability_properties = array(
			'label'               => 'Add numbers',
			'description'         => 'Calculates the result of adding two numbers.',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'a' => array(
						'type'        => 'number',
						'description' => 'First number.',
						'required'    => true,
					),
					'b' => array(
						'type'        => 'number',
						'description' => 'Second number.',
						'required'    => true,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'        => 'number',
				'description' => 'The result of adding the two numbers.',
				'required'    => true,
			),
			'execute_callback'    => function ( array $input ): int {
				return $input['a'] + $input['b'];
			},
			'permission_callback' => function (): bool {
				return true;
			},
			'meta'                => array(
				'category' => 'math',
			),
		);
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		foreach ( wp_get_abilities() as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'test/' ) ) {
				wp_unregister_ability( $ability->get_name() );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests registering an ability with invalid name.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_ability_invalid_name(): void {
		do_action( 'abilities_api_init' );

		$result = wp_register_ability( 'invalid_name', array() );

		$this->assertNull( $result );
	}

	/**
	 * Tests registering an ability when `abilities_api_init` hook is not fired.
	 *
	 * @expectedIncorrectUsage wp_register_ability
	 */
	public function test_register_ability_no_abilities_api_init_hook(): void {
		global $wp_actions;

		// Store the original action count
		$original_count = isset( $wp_actions['abilities_api_init'] ) ? $wp_actions['abilities_api_init'] : 0;

		// Reset the action count to simulate it not being fired
		unset( $wp_actions['abilities_api_init'] );

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		// Restore the original action count
		if ( $original_count > 0 ) {
			$wp_actions['abilities_api_init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Tests registering a valid ability.
	 */
	public function test_register_valid_ability(): void {
		do_action( 'abilities_api_init' );

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertInstanceOf( WP_Ability::class, $result );
		$this->assertSame( self::$test_ability_name, $result->get_name() );
		$this->assertSame( self::$test_ability_properties['label'], $result->get_label() );
		$this->assertSame( self::$test_ability_properties['description'], $result->get_description() );
		$this->assertSame( self::$test_ability_properties['input_schema'], $result->get_input_schema() );
		$this->assertSame( self::$test_ability_properties['output_schema'], $result->get_output_schema() );
		$this->assertSame( self::$test_ability_properties['meta'], $result->get_meta() );
		$this->assertTrue(
			$result->has_permission(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);
		$this->assertSame(
			5,
			$result->execute(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);
	}

	/**
	 * Tests executing an ability with no permissions.
	 *
	 * @expectedIncorrectUsage WP_Ability::execute
	 */
	public function test_register_ability_no_permissions(): void {
		do_action( 'abilities_api_init' );

		self::$test_ability_properties['permission_callback'] = function (): bool {
			return false;
		};
		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertFalse(
			$result->has_permission(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);
		$this->assertNull(
			$result->execute(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);
	}

	/**
	 * Tests executing an ability with input not matching schema.
	 *
	 * @expectedIncorrectUsage WP_Ability::validate_input
	 * @expectedIncorrectUsage WP_Ability::execute
	 */
	public function test_execute_ability_no_input_schema_match(): void {
		do_action( 'abilities_api_init' );

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertNull(
			$result->execute(
				array(
					'a'       => 2,
					'b'       => 3,
					'unknown' => 1,
				)
			)
		);
	}

	/**
	 * Tests executing an ability with output not matching schema.
	 *
	 * @expectedIncorrectUsage WP_Ability::validate_output
	 */
	public function test_execute_ability_no_output_schema_match(): void {
		do_action( 'abilities_api_init' );

		self::$test_ability_properties['execute_callback'] = function (): bool {
			return true;
		};
		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertNull(
			$result->execute(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);
	}

	/**
	 * Tests permission callback receiving input not matching schema.
	 *
	 * @expectedIncorrectUsage WP_Ability::validate_input
	 */
	public function test_permission_callback_no_input_schema_match(): void {
		do_action( 'abilities_api_init' );

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$this->assertFalse(
			$result->has_permission(
				array(
					'a'       => 2,
					'b'       => 3,
					'unknown' => 1,
				)
			)
		);
	}

	/**
	 * Tests permission callback receiving input for contextual permission checks.
	 */
	public function test_permission_callback_receives_input(): void {
		do_action( 'abilities_api_init' );

		$received_input                                       = null;
		self::$test_ability_properties['permission_callback'] = function ( array $input ) use ( &$received_input ): bool {
			$received_input = $input;
			// Allow only if 'a' is greater than 'b'
			return $input['a'] > $input['b'];
		};

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		// Test with a > b (should be allowed)
		$this->assertTrue(
			$result->has_permission(
				array(
					'a' => 5,
					'b' => 3,
				)
			)
		);
		$this->assertSame(
			array(
				'a' => 5,
				'b' => 3,
			),
			$received_input
		);

		// Test with a < b (should be denied)
		$this->assertFalse(
			$result->has_permission(
				array(
					'a' => 2,
					'b' => 8,
				)
			)
		);
		$this->assertSame(
			array(
				'a' => 2,
				'b' => 8,
			),
			$received_input
		);
	}

	/**
	 * Tests unregistering existing ability.
	 */
	public function test_unregister_existing_ability() {
		do_action( 'abilities_api_init' );

		wp_register_ability( self::$test_ability_name, self::$test_ability_properties );

		$result = wp_unregister_ability( self::$test_ability_name );

		$this->assertEquals(
			new WP_Ability( self::$test_ability_name, self::$test_ability_properties ),
			$result
		);
	}

	/**
	 * Tests retrieving existing ability.
	 */
	public function test_get_existing_ability() {
		global $wp_abilities;

		$name       = self::$test_ability_name;
		$properties = self::$test_ability_properties;
		$callback   = function ( $instance ) use ( $name, $properties ) {
			wp_register_ability( $name, $properties );
		};

		add_action( 'abilities_api_init', $callback );

		// Temporarily set `$wp_abilities` to null to ensure `wp_get_ability()` triggers `abilities_api_init` action.
		$old_wp_abilities = $wp_abilities;
		$wp_abilities     = null;

		$result = wp_get_ability( $name );

		$wp_abilities = $old_wp_abilities;

		remove_action( 'abilities_api_init', $callback );

		$this->assertEquals(
			new WP_Ability( $name, $properties ),
			$result
		);
	}

	/**
	 * Tests retrieving all registered abilities.
	 */
	public function test_get_all_registered_abilities() {
		do_action( 'abilities_api_init' );

		$ability_one_name       = 'test/ability-one';
		$ability_one_properties = self::$test_ability_properties;
		wp_register_ability( $ability_one_name, $ability_one_properties );

		$ability_two_name       = 'test/ability-two';
		$ability_two_properties = self::$test_ability_properties;
		wp_register_ability( $ability_two_name, $ability_two_properties );

		$ability_three_name       = 'test/ability-three';
		$ability_three_properties = self::$test_ability_properties;
		wp_register_ability( $ability_three_name, $ability_three_properties );

		$expected = array(
			$ability_one_name   => new WP_Ability( $ability_one_name, $ability_one_properties ),
			$ability_two_name   => new WP_Ability( $ability_two_name, $ability_two_properties ),
			$ability_three_name => new WP_Ability( $ability_three_name, $ability_three_properties ),
		);

		$result = wp_get_abilities();
		$this->assertEquals( $expected, $result );
	}
}
