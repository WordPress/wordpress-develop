<?php declare( strict_types=1 );

/**
 * Tests for the abilities registry functionality.
 *
 * @covers WP_Abilities_Registry
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpAbilitiesRegistry extends WP_UnitTestCase {

	public static $test_ability_name = 'test/add-numbers';
	public static $test_ability_args = array();

	/**
	 * Mock abilities registry.
	 *
	 * @var WP_Abilities_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 */
	public function set_up(): void {
		require_once DIR_TESTDATA . '/../includes/class-tests-custom-ability-class.php';

		parent::set_up();

		$this->registry = new WP_Abilities_Registry();

		remove_all_filters( 'wp_register_ability_args' );

		// Simulates the Abilities API init hook to allow test ability category registration.
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		wp_register_ability_category(
			'math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations and calculations.',
			)
		);
		array_pop( $wp_current_filter );

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
				'foo' => 'bar',
			),
		);
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down(): void {
		$this->registry = null;

		remove_all_filters( 'wp_register_ability_args' );

		// Clean up registered test ability category.
		wp_unregister_ability_category( 'math' );

		parent::tear_down();
	}

	/**
	 * Should reject ability name without a namespace.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_name_without_namespace() {
		$result = $this->registry->register( 'without-namespace', self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability name with invalid characters.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_characters_in_name() {
		$result = $this->registry->register( 'still/_doing_it_wrong', array() );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability name with uppercase characters.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_uppercase_characters_in_name() {
		$result = $this->registry->register( 'Test/AddNumbers', self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration without a label.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_missing_label() {
		// Remove the label from the args.
		unset( self::$test_ability_args['label'] );

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration with invalid label type.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_label_type() {
		self::$test_ability_args['label'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration without a description.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_missing_description() {
		// Remove the description from the args.
		unset( self::$test_ability_args['description'] );

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration with invalid description type.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_description_type() {
		self::$test_ability_args['description'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Tests registering an ability with non-existent category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_ability_nonexistent_category(): void {
		$args = array_merge(
			self::$test_ability_args,
			array( 'category' => 'nonexistent' )
		);

		$result = $this->registry->register( self::$test_ability_name, $args );

		$this->assertNull( $result, 'Should return null when category does not exist.' );
	}

	/**
	 * Should reject ability registration without an execute callback.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_missing_execute_callback() {
		// Remove the execute_callback from the args.
		unset( self::$test_ability_args['execute_callback'] );

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration if the execute callback is not a callable.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_execute_callback_type() {
		self::$test_ability_args['execute_callback'] = 'not-a-callback';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should allow ability registration with custom ability_class that overrides do_execute.
	 *
	 * @ticket 64407
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 */
	public function test_register_with_custom_ability_class_without_execute_callback() {
		// Remove execute_callback and permission_callback since the custom class provides its own implementation.
		unset( self::$test_ability_args['execute_callback'] );
		unset( self::$test_ability_args['permission_callback'] );

		self::$test_ability_args['ability_class'] = 'Tests_Custom_Ability_Class';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		$this->assertInstanceOf( WP_Ability::class, $result, 'Should return a WP_Ability instance.' );
		$this->assertInstanceOf( Tests_Custom_Ability_Class::class, $result, 'Should return an instance of the custom class.' );

		// Verify the custom execute method works.
		$execute_result = $result->execute(
			array(
				'a' => 5,
				'b' => 3,
			)
		);
		$this->assertSame( 15, $execute_result, 'Custom do_execute should multiply instead of add.' );
	}

	/**
	 * Should reject ability registration without an execute callback.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_missing_permission_callback() {
		// Remove the permission_callback from the args.
		unset( self::$test_ability_args['permission_callback'] );

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration if the permission callback is not a callable.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_permission_callback_type() {
		self::$test_ability_args['permission_callback'] = 'not-a-callback';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration if the input schema is not an array.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_input_schema_type() {
		self::$test_ability_args['input_schema'] = 'not-an-array';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration if the output schema is not an array.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_output_schema_type() {
		self::$test_ability_args['output_schema'] = 'not-an-array';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}


	/**
	 * Should reject ability registration with invalid `annotations` type.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_annotations_type() {
		self::$test_ability_args['meta']['annotations'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration with invalid meta type.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_meta_type() {
		self::$test_ability_args['meta'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration with invalid show in REST type.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Ability::prepare_properties
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_show_in_rest_type() {
		self::$test_ability_args['meta']['show_in_rest'] = 5;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject registration for already registered ability.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_already_registered_ability() {
		$this->registry->register( self::$test_ability_name, self::$test_ability_args );

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		$this->assertNull( $result );
	}

	/**
	 * Should successfully register a new ability.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 */
	public function test_register_new_ability() {
		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		$this->assertEquals(
			new WP_Ability( self::$test_ability_name, self::$test_ability_args ),
			$result
		);
	}

	/**
	 * Should return false for ability that's not registered.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::is_registered
	 */
	public function test_is_registered_for_unknown_ability() {
		$result = $this->registry->is_registered( 'test/unknown' );
		$this->assertFalse( $result );
	}

	/**
	 * Should return true if ability is registered.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::is_registered
	 */
	public function test_is_registered_for_known_ability() {
		$this->registry->register( 'test/one', self::$test_ability_args );
		$this->registry->register( 'test/two', self::$test_ability_args );
		$this->registry->register( 'test/three', self::$test_ability_args );

		$result = $this->registry->is_registered( 'test/one' );
		$this->assertTrue( $result );
	}

	/**
	 * Should not find ability that's not registered.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::get_registered
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_registered
	 */
	public function test_get_registered_rejects_unknown_ability_name() {
		$ability = $this->registry->get_registered( 'test/unknown' );
		$this->assertNull( $ability );
	}

	/**
	 * Should find registered ability by name.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::get_registered
	 */
	public function test_get_registered_for_known_ability() {
		$this->registry->register( 'test/one', self::$test_ability_args );
		$this->registry->register( 'test/two', self::$test_ability_args );
		$this->registry->register( 'test/three', self::$test_ability_args );

		$result = $this->registry->get_registered( 'test/two' );
		$this->assertSame( 'test/two', $result->get_name() );
	}

	/**
	 * Unregistering should fail if an ability is not registered.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::unregister
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::unregister
	 */
	public function test_unregister_not_registered_ability() {
		$result = $this->registry->unregister( 'test/unregistered' );
		$this->assertNull( $result );
	}

	/**
	 * Should unregister ability by name.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::unregister
	 */
	public function test_unregister_for_known_ability() {
		$this->registry->register( 'test/one', self::$test_ability_args );
		$this->registry->register( 'test/two', self::$test_ability_args );
		$this->registry->register( 'test/three', self::$test_ability_args );

		$result = $this->registry->unregister( 'test/three' );
		$this->assertSame( 'test/three', $result->get_name() );

		$this->assertFalse( $this->registry->is_registered( 'test/three' ) );
	}

	/**
	 * Should retrieve all registered abilities.
	 *
	 * @ticket 64098
	 *
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::get_all_registered
	 */
	public function test_get_all_registered() {
		$ability_one_name = 'test/one';
		$this->registry->register( $ability_one_name, self::$test_ability_args );

		$ability_two_name = 'test/two';
		$this->registry->register( $ability_two_name, self::$test_ability_args );

		$ability_three_name = 'test/three';
		$this->registry->register( $ability_three_name, self::$test_ability_args );

		$result = $this->registry->get_all_registered();
		$this->assertCount( 3, $result );
		$this->assertSame( $ability_one_name, $result[ $ability_one_name ]->get_name() );
		$this->assertSame( $ability_two_name, $result[ $ability_two_name ]->get_name() );
		$this->assertSame( $ability_three_name, $result[ $ability_three_name ]->get_name() );
	}

	/**
	 * Test register_ability_args filter modifies the args before ability instantiation.
	 *
	 * @ticket 64098
	 */
	public function test_register_ability_args_filter_modifies_args() {
		$was_filter_callback_fired = false;

		// Define the filter.
		add_filter(
			'wp_register_ability_args',
			static function ( $args ) use ( &$was_filter_callback_fired ) {
				$args['label']             = 'Modified label';
				$original_execute_callback = $args['execute_callback'];
				$args['execute_callback']  = static function ( array $input ) use ( &$was_filter_callback_fired, $original_execute_callback ) {
					$was_filter_callback_fired = true;
					return $original_execute_callback( $input );
				};

				return $args;
			},
			10
		);

		// Register the ability.
		$ability = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		// Check the label was modified by the filter.
		$this->assertSame( 'Modified label', $ability->get_label() );

		// Call the execute callback.
		$result = $ability->execute(
			array(
				'a' => 1,
				'b' => 2,
			)
		);

		$this->assertTrue( $was_filter_callback_fired, 'The execute callback defined in the filter was not fired.' );
		$this->assertSame( 3, $result, 'The original execute callback did not return the expected result.' );
	}

	/**
	 * Test register_ability_args filter can block ability registration by returning invalid args.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_ability_args_filter_blocks_registration() {
		// Define the filter.
		add_filter(
			'wp_register_ability_args',
			static function ( $args ) {
				// Remove the label to make the args invalid.
				unset( $args['label'] );
				return $args;
			},
			10
		);

		// Register the ability.
		$ability = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		// Check the ability was not registered.
		$this->assertNull( $ability, 'The ability was registered even though the args were made invalid by the filter.' );
	}

	/**
	 * Test register_ability_args filter can block an invalid ability class from being used.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_ability_args_filter_blocks_invalid_ability_class() {
		// Define the filter.
		add_filter(
			'wp_register_ability_args',
			static function ( $args ) {
				// Set an invalid ability class.
				$args['ability_class'] = 'NonExistentClass';
				return $args;
			},
			10
		);
		// Register the ability.
		$ability = $this->registry->register( self::$test_ability_name, self::$test_ability_args );

		// Check the ability was not registered.
		$this->assertNull( $ability, 'The ability was registered even though the ability class was made invalid by the filter.' );
	}

	/**
	 * Tests register_ability_args filter is only applied to the specific ability being registered.
	 *
	 * @ticket 64098
	 */
	public function test_register_ability_args_filter_only_applies_to_specific_ability() {
		add_filter(
			'wp_register_ability_args',
			static function ( $args, $name ) {
				if ( self::$test_ability_name !== $name ) {
					// Do not modify args for other abilities.
					return $args;
				}

				$args['label'] = 'Modified label for specific ability';
				return $args;
			},
			10,
			2
		);

		// Register the first ability, which the filter should modify.
		$filtered_ability = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertSame( 'Modified label for specific ability', $filtered_ability->get_label() );

		$unfiltered_ability = $this->registry->register( 'test/another-ability', self::$test_ability_args );
		$this->assertNotSame( $filtered_ability->get_label(), $unfiltered_ability->get_label(), 'The filter incorrectly modified the args for an ability it should not have.' );
	}
}
