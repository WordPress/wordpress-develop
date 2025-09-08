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
	 * @var \WP_Abilities_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->registry = new WP_Abilities_Registry();

		self::$test_ability_args = array(
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
			'execute_callback'    => static function ( array $input ): int {
				return $input['a'] + $input['b'];
			},
			'permission_callback' => static function (): bool {
				return true;
			},
			'meta'                => array(
				'category' => 'math',
			),
		);
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down(): void {
		$this->registry = null;

		parent::tear_down();
	}

	/**
	 * Should reject ability name without a namespace.
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
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_description_type() {
		self::$test_ability_args['description'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration without an execute callback.
	 *
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_execute_callback_type() {
		self::$test_ability_args['execute_callback'] = 'not-a-callback';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration if the permission callback is not a callable.
	 *
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
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
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_incorrect_output_schema_type() {
		self::$test_ability_args['output_schema'] = 'not-an-array';

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject ability registration with invalid meta type.
	 *
	 * @covers WP_Abilities_Registry::register
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_register_invalid_meta_type() {
		self::$test_ability_args['meta'] = false;

		$result = $this->registry->register( self::$test_ability_name, self::$test_ability_args );
		$this->assertNull( $result );
	}

	/**
	 * Should reject registration for already registered ability.
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
	 * @covers WP_Abilities_Registry::is_registered
	 */
	public function test_is_registered_for_unknown_ability() {
		$result = $this->registry->is_registered( 'test/unknown' );
		$this->assertFalse( $result );
	}

	/**
	 * Should return true if ability is registered.
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
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::get_registered
	 */
	public function test_get_registered_for_known_ability() {
		$this->registry->register( 'test/one', self::$test_ability_args );
		$this->registry->register( 'test/two', self::$test_ability_args );
		$this->registry->register( 'test/three', self::$test_ability_args );

		$result = $this->registry->get_registered( 'test/two' );
		$this->assertEquals( 'test/two', $result->get_name() );
	}

	/**
	 * Unregistering should fail if a ability is not registered.
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
	 * @covers WP_Abilities_Registry::register
	 * @covers WP_Abilities_Registry::unregister
	 */
	public function test_unregister_for_known_ability() {
		$this->registry->register( 'test/one', self::$test_ability_args );
		$this->registry->register( 'test/two', self::$test_ability_args );
		$this->registry->register( 'test/three', self::$test_ability_args );

		$result = $this->registry->unregister( 'test/three' );
		$this->assertEquals( 'test/three', $result->get_name() );

		$this->assertFalse( $this->registry->is_registered( 'test/three' ) );
	}

	/**
	 * Should retrieve all registered abilities.
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
	 * Direct instantiation of WP_Ability with invalid properties should throw an exception.
	 *
	 * @covers WP_Ability::__construct
	 * @covers WP_Ability::prepare_properties
	 */
	public function test_wp_ability_invalid_properties_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		new WP_Ability(
			'test/invalid',
			array(
				'label'            => '',
				'description'      => '',
				'execute_callback' => null,
			)
		);
	}
}
