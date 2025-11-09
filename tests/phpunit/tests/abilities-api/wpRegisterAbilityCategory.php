<?php declare( strict_types=1 );

/**
 * Tests for the ability category registry functionality.
 *
 * @covers wp_register_ability_category
 * @covers wp_unregister_ability_category
 * @covers wp_has_ability_category
 * @covers wp_get_ability_category
 * @covers wp_get_ability_categories
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterAbilityCategory extends WP_UnitTestCase {

	public static $test_ability_category_name = 'test-math';
	public static $test_ability_category_args = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		self::$test_ability_category_args = array(
			'label'       => 'Math',
			'description' => 'Mathematical operations.',
		);
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Clean up any test ability categories registered during tests.
		foreach ( wp_get_ability_categories() as $ability_category ) {
			if ( ! str_starts_with( $ability_category->get_slug(), 'test-' ) ) {
				continue;
			}

			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down();
	}

	/**
	 * Simulates the `wp_abilities_api_categories_init` action.
	 */
	private function simulate_doing_wp_ability_categories_init_action() {
		global $wp_current_filter;

		$wp_current_filter[] = 'wp_abilities_api_categories_init';
	}

	/**
	 * Test registering ability category before `wp_abilities_api_categories_init` hook.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage wp_register_ability_category
	 */
	public function test_register_category_before_init_hook(): void {
		$this->assertFalse( doing_action( 'wp_abilities_api_categories_init' ) );

		$result = wp_register_ability_category(
			self::$test_ability_category_name,
			self::$test_ability_category_args
		);

		$this->assertNull( $result );
	}

	/**
	 * Tests registering an ability category when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_instance
	 */
	public function test_register_ability_category_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_register_ability_category(
			self::$test_ability_category_name,
			self::$test_ability_category_args
		);

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Test registering a valid ability category.
	 *
	 * @ticket 64098
	 */
	public function test_register_valid_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_register_ability_category(
			self::$test_ability_category_name,
			self::$test_ability_category_args
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( self::$test_ability_category_name, $result->get_slug() );
		$this->assertSame( 'Math', $result->get_label() );
		$this->assertSame( 'Mathematical operations.', $result->get_description() );
	}

	/**
	 * Tests unregistering an ability category when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_instance
	 */
	public function test_unregister_ability_category_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_unregister_ability_category( self::$test_ability_category_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Test unregistering non-existent ability category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::unregister
	 */
	public function test_unregister_nonexistent_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_unregister_ability_category( 'test-nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * Test unregistering existing ability category.
	 *
	 * @ticket 64098
	 */
	public function test_unregister_existing_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		wp_register_ability_category(
			self::$test_ability_category_name,
			self::$test_ability_category_args
		);

		$result = wp_unregister_ability_category( self::$test_ability_category_name );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertFalse( wp_has_ability_category( self::$test_ability_category_name ) );
	}

	/**
	 * Tests checking if an ability category is registered when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_instance
	 */
	public function test_has_ability_category_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_has_ability_category( self::$test_ability_category_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertFalse( $result );
	}

	/**
	 * Tests checking if a non-existent ability category is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_nonexistent_ability_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_has_ability_category( 'test/non-existent' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests checking if an ability category is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_ability_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		$category_slug = self::$test_ability_category_name;

		wp_register_ability_category(
			$category_slug,
			self::$test_ability_category_args
		);

		$result = wp_has_ability_category( $category_slug );

		$this->assertTrue( $result );
	}

	/**
	 * Tests retrieving an ability category when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_instance
	 */
	public function test_get_ability_category_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_get_ability_category( self::$test_ability_category_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertNull( $result );
	}

	/**
	 * Test retrieving non-existent ability category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_registered
	 */
	public function test_get_nonexistent_category(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_get_ability_category( 'test-nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * Test retrieving existing ability category registered with the `wp_abilities_api_categories_init` callback.
	 *
	 * @ticket 64098
	 */
	public function test_get_existing_category_using_callback(): void {
		$name     = self::$test_ability_category_name;
		$args     = self::$test_ability_category_args;
		$callback = static function ( $instance ) use ( $name, $args ) {
			wp_register_ability_category( $name, $args );
		};

		add_action( 'wp_abilities_api_categories_init', $callback );

		// Reset the Registry, to ensure it's empty before the test.
		$registry_reflection = new ReflectionClass( WP_Ability_Categories_Registry::class );
		$instance_prop       = $registry_reflection->getProperty( 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance_prop->setAccessible( true );
		}
		$instance_prop->setValue( null, null );

		$result = wp_get_ability_category( $name );

		remove_action( 'wp_abilities_api_categories_init', $callback );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( self::$test_ability_category_name, $result->get_slug() );
	}

	/**
	 * Test retrieving all registered ability categories when `init` action has not fired.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_instance
	 */
	public function test_get_ability_categories_no_init_action(): void {
		global $wp_actions;

		// Store the original action count.
		$original_count = isset( $wp_actions['init'] ) ? $wp_actions['init'] : 0;

		// Reset the action count to simulate it not being fired.
		unset( $wp_actions['init'] );

		$this->simulate_doing_wp_ability_categories_init_action();

		$result = wp_get_ability_categories( self::$test_ability_category_name );

		// Restore the original action count.
		if ( $original_count > 0 ) {
			$wp_actions['init'] = $original_count;
		}

		$this->assertSame( array(), $result );
	}

	/**
	 * Test retrieving all registered ability categories.
	 *
	 * @ticket 64098
	 */
	public function test_get_all_categories(): void {
		$this->simulate_doing_wp_ability_categories_init_action();

		wp_register_ability_category(
			self::$test_ability_category_name,
			self::$test_ability_category_args
		);

		wp_register_ability_category(
			'test-system',
			array(
				'label'       => 'System',
				'description' => 'System operations.',
			)
		);

		$categories = wp_get_ability_categories();

		$this->assertIsArray( $categories );
		$this->assertCount( 2, $categories );
		$this->assertArrayHasKey( self::$test_ability_category_name, $categories );
		$this->assertArrayHasKey( 'test-system', $categories );
	}
}
