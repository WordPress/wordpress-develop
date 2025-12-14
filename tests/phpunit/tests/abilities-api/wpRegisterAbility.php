<?php declare( strict_types=1 );

/**
 * Mock used to test a custom ability class.
 */
class Mock_Custom_Ability extends WP_Ability {
	protected function do_execute( $input = null ) {
		return 9999;
	}
}

/**
 * Tests for registering, unregistering and retrieving abilities.
 *
 * @covers wp_register_ability
 * @covers wp_unregister_ability
 * @covers wp_get_ability
 * @covers wp_has_ability
 * @covers wp_get_all_abilities
 *
 * @group abilities-api
 */
class Test_Abilities_API_WpRegisterAbility extends WP_UnitTestCase {

	public static $test_ability_name = 'test/add-numbers';
	public static $test_ability_args = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		global $wp_current_filter;

		parent::set_up();

		// Simulate the init hook for ability categories to allow test ability category registration.
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		wp_register_ability_category(
			'math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations and calculations.',
			)
		);

		self::$test_ability_args = array(
			'label'               => 'Add numbers',
			'description'         => 'Calculates the result of adding two numbers.',
			'category'            => 'math',
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
			'execute_callback'    => static function ( array $input ): int {
				return $input['a'] + $input['b'];
			},
			'permission_callback' => static function (): bool {
				return true;
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
				),
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		global $wp_current_filter;

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! str_starts_with( $ability->get_name(), 'test/' ) ) {
				continue;
			}

			wp_unregister_ability( $ability->get_name() );
		}

		// Clean up registered test ability category.
		wp_unregister_ability_category( 'math' );

		parent::tear_down();
	}

	/**
	 * Simulates the `wp_abilities_api_init` action.
	 */
	private function simulate_doing_wp_abilities_init_action() {
		global $wp_current_filter;

		$wp_current_filter[] = 'wp_abilities_api_init';
	}

	/**
	 * Tests registering an ability with invalid name.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_ability_invalid_name(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability( 'invalid_name', array() );

		$this->assertNull( $result );
	}

	/**
	 * Tests registering an ability when `wp_abilities_api_init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage wp_register_ability
	 */
	public function test_register_ability_no_abilities_api_init_action(): void {
		$this->assertFalse( doing_action( 'wp_abilities_api_init' ) );

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$this->assertNull( $result );
	}

	/**
	 * Tests registering an ability when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_instance
	 */
	public function test_register_ability_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Tests registering a valid ability.
	 *
	 * @ticket 64098
	 */
	public function test_register_valid_ability(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$expected_annotations = array_merge(
			self::$test_ability_args['meta']['annotations'],
			array(
				'idempotent' => false,
			)
		);
		$expected_meta        = array_merge(
			self::$test_ability_args['meta'],
			array(
				'annotations'  => $expected_annotations,
				'show_in_rest' => true,
			)
		);

		$this->assertInstanceOf( WP_Ability::class, $result );
		$this->assertSame( self::$test_ability_name, $result->get_name() );
		$this->assertSame( self::$test_ability_args['label'], $result->get_label() );
		$this->assertSame( self::$test_ability_args['description'], $result->get_description() );
		$this->assertSame( self::$test_ability_args['input_schema'], $result->get_input_schema() );
		$this->assertSame( self::$test_ability_args['output_schema'], $result->get_output_schema() );
		$this->assertEquals( $expected_meta, $result->get_meta() );
		$this->assertTrue(
			$result->check_permissions(
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
	 * @ticket 64098
	 */
	public function test_register_ability_no_permissions(): void {
		$this->simulate_doing_wp_abilities_init_action();

		self::$test_ability_args['permission_callback'] = static function (): bool {
			return false;
		};
		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$this->assertFalse(
			$result->check_permissions(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);

		$actual = $result->execute(
			array(
				'a' => 2,
				'b' => 3,
			)
		);
		$this->assertWPError(
			$actual,
			'Execution should fail due to no permissions'
		);
		$this->assertSame( 'ability_invalid_permissions', $actual->get_error_code() );
	}

	/**
	 * Tests registering an ability with a custom ability class.
	 *
	 * @ticket 64098
	 */
	public function test_register_ability_custom_ability_class(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability(
			self::$test_ability_name,
			array_merge(
				self::$test_ability_args,
				array(
					'ability_class' => Mock_Custom_Ability::class,
				)
			)
		);

		$this->assertInstanceOf( Mock_Custom_Ability::class, $result );
		$this->assertSame(
			9999,
			$result->execute(
				array(
					'a' => 2,
					'b' => 3,
				)
			)
		);

		// Try again with an invalid class throws a doing it wrong.
		$this->setExpectedIncorrectUsage( WP_Abilities_Registry::class . '::register' );
		wp_register_ability(
			self::$test_ability_name,
			array_merge(
				self::$test_ability_args,
				array(
					'ability_class' => 'Non_Existent_Class',
				)
			)
		);
	}

	/**
	 * Tests executing an ability with input not matching schema.
	 *
	 * @ticket 64098
	 */
	public function test_execute_ability_no_input_schema_match(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$actual = $result->execute(
			array(
				'a'       => 2,
				'b'       => 3,
				'unknown' => 1,
			)
		);

		$this->assertWPError(
			$actual,
			'Execution should fail due to input not matching schema.'
		);
		$this->assertSame( 'ability_invalid_input', $actual->get_error_code() );
		$this->assertSame(
			'Ability "test/add-numbers" has invalid input. Reason: unknown is not a valid property of Object.',
			$actual->get_error_message()
		);
	}

	/**
	 * Tests executing an ability with output not matching schema.
	 *
	 * @ticket 64098
	 */
	public function test_execute_ability_no_output_schema_match(): void {
		$this->simulate_doing_wp_abilities_init_action();

		self::$test_ability_args['execute_callback'] = static function (): bool {
			return true;
		};

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$actual = $result->execute(
			array(
				'a' => 2,
				'b' => 3,
			)
		);
		$this->assertWPError(
			$actual,
			'Execution should fail due to output not matching schema.'
		);
		$this->assertSame( 'ability_invalid_output', $actual->get_error_code() );
		$this->assertSame(
			'Ability "test/add-numbers" has invalid output. Reason: output is not of type number.',
			$actual->get_error_message()
		);
	}

	/**
	 * Tests input validation failing due to schema mismatch.
	 *
	 * @ticket 64098
	 */
	public function test_validate_input_no_input_schema_match(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$actual = $result->validate_input(
			array(
				'a'       => 2,
				'b'       => 3,
				'unknown' => 1,
			)
		);

		$this->assertWPError(
			$actual,
			'Input validation should fail due to input not matching schema.'
		);
		$this->assertSame( 'ability_invalid_input', $actual->get_error_code() );
		$this->assertSame(
			'Ability "test/add-numbers" has invalid input. Reason: unknown is not a valid property of Object.',
			$actual->get_error_message()
		);
	}

	/**
	 * Tests permission callback receiving input for contextual permission checks.
	 *
	 * @ticket 64098
	 */
	public function test_permission_callback_receives_input(): void {
		$this->simulate_doing_wp_abilities_init_action();

		$received_input                                 = null;
		self::$test_ability_args['permission_callback'] = static function ( array $input ) use ( &$received_input ): bool {
			$received_input = $input;
			// Allow only if 'a' is greater than 'b'
			return $input['a'] > $input['b'];
		};

		$result = wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		// Test with a > b (should be allowed)
		$this->assertTrue(
			$result->check_permissions(
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
			$result->check_permissions(
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
	 * Tests unregistering an ability when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_instance
	 */
	public function test_unregister_ability_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_unregister_ability( self::$test_ability_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Tests unregistering existing ability.
	 *
	 * @ticket 64098
	 */
	public function test_unregister_existing_ability() {
		$this->simulate_doing_wp_abilities_init_action();

		wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$result = wp_unregister_ability( self::$test_ability_name );

		$this->assertEquals(
			new WP_Ability( self::$test_ability_name, self::$test_ability_args ),
			$result
		);
	}

	/**
	 * Tests retrieving an ability when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_instance
	 */
	public function test_get_ability_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_get_ability( self::$test_ability_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Tests retrieving existing ability registered with the `wp_abilities_api_init` callback.
	 *
	 * @ticket 64098
	 */
	public function test_get_existing_ability_using_callback() {
		$this->simulate_doing_wp_abilities_init_action();

		$name     = self::$test_ability_name;
		$args     = self::$test_ability_args;
		$callback = static function ( $instance ) use ( $name, $args ) {
			wp_register_ability( $name, $args );
		};

		add_action( 'wp_abilities_api_init', $callback );

		// Reset the Registry, to ensure it's empty before the test.
		$registry_reflection = new ReflectionClass( WP_Abilities_Registry::class );
		$instance_prop       = $registry_reflection->getProperty( 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance_prop->setAccessible( true );
		}
		$instance_prop->setValue( null, null );

		$result = wp_get_ability( $name );

		remove_action( 'wp_abilities_api_init', $callback );

		$this->assertEquals(
			new WP_Ability( $name, $args ),
			$result,
			'Ability does not share expected properties.'
		);
	}

	/**
	 * Tests checking if an ability is registered when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_instance
	 */
	public function test_has_ability_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_has_ability( self::$test_ability_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertFalse( $result );
	}

	/**
	 * Tests checking if an ability is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_ability() {
		$this->simulate_doing_wp_abilities_init_action();

		wp_register_ability( self::$test_ability_name, self::$test_ability_args );

		$result = wp_has_ability( self::$test_ability_name );

		$this->assertTrue( $result );
	}

	/**
	 * Tests checking if a non-existent ability is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_nonexistent_ability() {
		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_has_ability( 'test/non-existent' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests retrieving all registered abilities when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_instance
	 */
	public function test_get_abilities_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_abilities_init_action();

		$result = wp_get_abilities();

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertSame( array(), $result );
	}

	/**
	 * Tests retrieving all registered abilities.
	 *
	 * @ticket 64098
	 */
	public function test_get_all_registered_abilities() {
		$this->simulate_doing_wp_abilities_init_action();

		$ability_one_name = 'test/ability-one';
		$ability_one_args = self::$test_ability_args;
		wp_register_ability( $ability_one_name, $ability_one_args );

		$ability_two_name = 'test/ability-two';
		$ability_two_args = self::$test_ability_args;
		wp_register_ability( $ability_two_name, $ability_two_args );

		$ability_three_name = 'test/ability-three';
		$ability_three_args = self::$test_ability_args;
		wp_register_ability( $ability_three_name, $ability_three_args );

		$expected = array(
			$ability_one_name   => new WP_Ability( $ability_one_name, $ability_one_args ),
			$ability_two_name   => new WP_Ability( $ability_two_name, $ability_two_args ),
			$ability_three_name => new WP_Ability( $ability_three_name, $ability_three_args ),
		);

		$result = wp_get_abilities();
		$this->assertEquals( $expected, $result );
	}
}
