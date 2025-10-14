<?php declare( strict_types=1 );

/**
 * Tests for the ability category functionality.
 *
 * @covers WP_Ability_Category
 * @covers WP_Abilities_Category_Registry
 * @covers wp_register_ability_category
 * @covers wp_unregister_ability_category
 * @covers wp_get_ability_category
 * @covers wp_get_ability_categories
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpAbilityCategory extends WP_UnitTestCase {

	/**
	 * Category registry instance.
	 *
	 * @var \WP_Abilities_Category_Registry
	 */
	private $registry;

	/**
	 * Captured `_doing_it_wrong` calls during a test.
	 *
	 * @var array<int,array{function:string,message:string,version:string}>
	 */
	private $doing_it_wrong_log = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->registry           = WP_Abilities_Category_Registry::get_instance();
		$this->doing_it_wrong_log = array();

		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 3 );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ) );
		$this->doing_it_wrong_log = array();

		// Clean up all test categories.
		$categories = $this->registry->get_all_registered();
		foreach ( $categories as $category ) {
			if ( 0 !== strpos( $category->get_slug(), 'test-' ) ) {
				continue;
			}
			$this->registry->unregister( $category->get_slug() );
		}

		parent::tear_down();
	}

	/**
	 * Records `_doing_it_wrong` calls for later assertions.
	 *
	 * @param string $the_method Function name flagged by `_doing_it_wrong`.
	 * @param string $message  Message supplied to `_doing_it_wrong`.
	 * @param string $version  Version string supplied to `_doing_it_wrong`.
	 */
	public function record_doing_it_wrong( string $the_method, string $message, string $version ): void {
		$this->doing_it_wrong_log[] = array(
			'function' => $the_method,
			'message'  => $message,
			'version'  => $version,
		);
	}

	/**
	 * Asserts that `_doing_it_wrong` was triggered for the expected function.
	 *
	 * @param string      $the_method         Function name expected to trigger `_doing_it_wrong`.
	 * @param string|null $message_contains Optional. String that should be contained in the error message.
	 */
	private function assertDoingItWrongTriggered( string $the_method, ?string $message_contains = null ): void {
		foreach ( $this->doing_it_wrong_log as $entry ) {
			if ( $the_method === $entry['function'] ) {
				// If message check is specified, verify it contains the expected text.
				if ( null !== $message_contains && false === strpos( $entry['message'], $message_contains ) ) {
					continue;
				}
				return;
			}
		}

		if ( null !== $message_contains ) {
			$this->fail(
				sprintf(
					'Failed asserting that _doing_it_wrong() was triggered for %s with message containing "%s".',
					$the_method,
					$message_contains
				)
			);
		} else {
			$this->fail( sprintf( 'Failed asserting that _doing_it_wrong() was triggered for %s.', $the_method ) );
		}
	}

	/**
	 * Helper to register a category during the hook.
	 */
	private function register_category_during_hook( string $slug, array $args ): ?WP_Ability_Category {
		$result   = null;
		$callback = static function () use ( $slug, $args, &$result ): void {
			$result = wp_register_ability_category( $slug, $args );
		};

		add_action( 'abilities_api_categories_init', $callback );
		do_action( 'abilities_api_categories_init', WP_Abilities_Category_Registry::get_instance() );
		remove_action( 'abilities_api_categories_init', $callback );

		return $result;
	}

	/**
	 * Test registering a valid category.
	 */
	public function test_register_valid_category(): void {
		$result = $this->register_category_during_hook(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'test-math', $result->get_slug() );
		$this->assertSame( 'Math', $result->get_label() );
		$this->assertSame( 'Mathematical operations.', $result->get_description() );
	}

	/**
	 * Test registering category with invalid slug format.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_invalid_slug_format(): void {
		// Uppercase characters not allowed.
		$result = $this->register_category_during_hook(
			'Test-Math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register', 'slug must contain only lowercase' );
	}

	/**
	 * Test registering category with invalid slug - underscore.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_invalid_slug_underscore(): void {
		$result = $this->register_category_during_hook(
			'test_math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register', 'slug must contain only lowercase' );
	}

	/**
	 * Test registering category without label.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_missing_label(): void {
		$result = $this->register_category_during_hook(
			'test-math',
			array(
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category without description.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_missing_description(): void {
		$result = $this->register_category_during_hook(
			'test-math',
			array(
				'label' => 'Math',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category before abilities_api_categories_init hook.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_before_init_hook(): void {
		global $wp_actions;

		// Store original count.
		$original_count = isset( $wp_actions['abilities_api_categories_init'] ) ? $wp_actions['abilities_api_categories_init'] : 0;

		// Reset to simulate hook not fired.
		unset( $wp_actions['abilities_api_categories_init'] );

		$result = wp_register_ability_category(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		// Restore original count.
		if ( $original_count > 0 ) {
			$wp_actions['abilities_api_categories_init'] = $original_count;
		}

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register', 'abilities_api_categories_init' );
	}

	/**
	 * Test registering duplicate category.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_duplicate_category(): void {
		$result   = null;
		$callback = static function () use ( &$result ): void {
			wp_register_ability_category(
				'test-math',
				array(
					'label'       => 'Math',
					'description' => 'Mathematical operations.',
				)
			);

			$result = wp_register_ability_category(
				'test-math',
				array(
					'label'       => 'Math 2',
					'description' => 'Another math category.',
				)
			);
		};

		add_action( 'abilities_api_categories_init', $callback );
		do_action( 'abilities_api_categories_init', WP_Abilities_Category_Registry::get_instance() );
		remove_action( 'abilities_api_categories_init', $callback );

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register', 'already registered' );
	}

	/**
	 * Test unregistering existing category.
	 */
	public function test_unregister_existing_category(): void {
		$this->register_category_during_hook(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$result = wp_unregister_ability_category( 'test-math' );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertFalse( $this->registry->is_registered( 'test-math' ) );
	}

	/**
	 * Test unregistering non-existent category.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::unregister
	 */
	public function test_unregister_nonexistent_category(): void {
		$result = wp_unregister_ability_category( 'test-nonexistent' );

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::unregister' );
	}

	/**
	 * Test retrieving existing category.
	 */
	public function test_get_existing_category(): void {
		$this->register_category_during_hook(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$result = wp_get_ability_category( 'test-math' );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'test-math', $result->get_slug() );
	}

	/**
	 * Test retrieving non-existent category.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::get_registered
	 */
	public function test_get_nonexistent_category(): void {
		$result = wp_get_ability_category( 'test-nonexistent' );

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::get_registered' );
	}

	/**
	 * Test retrieving all registered categories.
	 */
	public function test_get_all_categories(): void {
		$this->register_category_during_hook(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->register_category_during_hook(
			'test-system',
			array(
				'label'       => 'System',
				'description' => 'System operations.',
			)
		);

		$categories = wp_get_ability_categories();

		$this->assertIsArray( $categories );
		$this->assertCount( 2, $categories );
		$this->assertArrayHasKey( 'test-math', $categories );
		$this->assertArrayHasKey( 'test-system', $categories );
	}

	/**
	 * Test category is_registered method.
	 */
	public function test_category_is_registered(): void {
		$this->assertFalse( $this->registry->is_registered( 'test-math' ) );

		$this->register_category_during_hook(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'test-math' ) );
	}

	/**
	 * Test ability can only be registered with existing category.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::register
	 */
	public function test_ability_requires_existing_category(): void {
		do_action( 'abilities_api_init' );

		// Ensure category doesn't exist - test should fail if it does.
		$this->assertFalse(
			WP_Abilities_Category_Registry::get_instance()->is_registered( 'test-nonexistent' ),
			'The test-nonexistent category should not be registered - test isolation may be broken'
		);

		// Try to register ability with non-existent category.
		$result = wp_register_ability(
			'test/calculator',
			array(
				'label'               => 'Calculator',
				'description'         => 'Performs calculations.',
				'category'            => 'test-nonexistent',
				'execute_callback'    => static function () {
					return 42;
				},
				'permission_callback' => '__return_true',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Registry::register', 'not registered' );
	}

	/**
	 * Test ability can be registered with valid category.
	 */
	public function test_ability_with_valid_category(): void {
		$category_callback = static function (): void {
			wp_register_ability_category(
				'test-math',
				array(
					'label'       => 'Math',
					'description' => 'Mathematical operations.',
				)
			);
		};

		add_action( 'abilities_api_categories_init', $category_callback );
		do_action( 'abilities_api_categories_init', WP_Abilities_Category_Registry::get_instance() );
		remove_action( 'abilities_api_categories_init', $category_callback );
		do_action( 'abilities_api_init' );

		$result = wp_register_ability(
			'test/calculator',
			array(
				'label'               => 'Calculator',
				'description'         => 'Performs calculations.',
				'category'            => 'test-math',
				'execute_callback'    => static function () {
					return 42;
				},
				'permission_callback' => '__return_true',
			)
		);

		$this->assertInstanceOf( WP_Ability::class, $result );
		$this->assertSame( 'test-math', $result->get_category() );

		// Cleanup.
		wp_unregister_ability( 'test/calculator' );
	}

	/**
	 * Test category registry singleton.
	 */
	public function test_category_registry_singleton(): void {
		$instance1 = WP_Abilities_Category_Registry::get_instance();
		$instance2 = WP_Abilities_Category_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test category with special characters in label and description.
	 */
	public function test_category_with_special_characters(): void {
		$result = $this->register_category_during_hook(
			'test-special',
			array(
				'label'       => 'Math & Science <tag>',
				'description' => 'Operations with "quotes" and \'apostrophes\'.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'Math & Science <tag>', $result->get_label() );
		$this->assertSame( 'Operations with "quotes" and \'apostrophes\'.', $result->get_description() );
	}

	/**
	 * Data provider for valid category slugs.
	 *
	 * @return array<int,array<string>>
	 */
	public function valid_slug_provider(): array {
		return array(
			array( 'test-simple' ),
			array( 'test-multiple-words' ),
			array( 'test-with-numbers-123' ),
			array( 'test-a' ),
			array( 'test-123' ),
		);
	}

	/**
	 * Test category slug validation with valid formats.
	 *
	 * @dataProvider valid_slug_provider
	 */
	public function test_category_slug_valid_formats( string $slug ): void {
		$result = $this->register_category_during_hook(
			$slug,
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result, "Slug '{$slug}' should be valid" );
	}

	/**
	 * Data provider for invalid category slugs.
	 *
	 * @return array<int,array<string>>
	 */
	public function invalid_slug_provider(): array {
		return array(
			array( 'Test-Uppercase' ),
			array( 'test_underscore' ),
			array( 'test.dot' ),
			array( 'test/slash' ),
			array( 'test space' ),
			array( '-test-start-dash' ),
			array( 'test-end-dash-' ),
			array( 'test--double-dash' ),
		);
	}

	/**
	 * Test category slug validation with invalid formats.
	 *
	 * @dataProvider invalid_slug_provider
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_category_slug_invalid_formats( string $slug ): void {
		$result = $this->register_category_during_hook(
			$slug,
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->assertNull( $result, "Slug '{$slug}' should be invalid" );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category with non-string label.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_category_constructor_non_string_label(): void {
		$result = $this->register_category_during_hook(
			'test-invalid',
			array(
				'label'       => 123, // Integer instead of string
				'description' => 'Valid description.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category with empty label.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_category_constructor_empty_label(): void {
		$result = $this->register_category_during_hook(
			'test-invalid',
			array(
				'label'       => '',
				'description' => 'Valid description.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category with non-string description.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_category_constructor_non_string_description(): void {
		$result = $this->register_category_during_hook(
			'test-invalid',
			array(
				'label'       => 'Valid Label',
				'description' => array( 'invalid' ), // Array instead of string
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test registering category with empty description.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_category_constructor_empty_description(): void {
		$result = $this->register_category_during_hook(
			'test-invalid',
			array(
				'label'       => 'Valid Label',
				'description' => '',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register' );
	}

	/**
	 * Test register_ability_category_args filter.
	 */
	public function test_register_category_args_filter(): void {
		add_filter(
			'register_ability_category_args',
			static function ( $args, $slug ) {
				if ( 'test-filtered' === $slug ) {
					$args['label']       = 'Filtered Label';
					$args['description'] = 'Filtered Description';
				}
				return $args;
			},
			10,
			2
		);

		$result = $this->register_category_during_hook(
			'test-filtered',
			array(
				'label'       => 'Original Label',
				'description' => 'Original Description.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'Filtered Label', $result->get_label() );
		$this->assertSame( 'Filtered Description', $result->get_description() );
	}

	/**
	 * Test that WP_Ability_Category cannot be unserialized.
	 */
	public function test_category_wakeup_throws_exception(): void {
		$category = $this->register_category_during_hook(
			'test-serialize',
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->expectException( \LogicException::class );
		$serialized = serialize( $category );
		unserialize( $serialized );
	}

	/**
	 * Test registering a category with valid meta.
	 */
	public function test_register_category_with_valid_meta(): void {
		$meta = array(
			'icon'     => 'dashicons-calculator',
			'priority' => 10,
			'custom'   => array( 'key' => 'value' ),
		);

		$result = $this->register_category_during_hook(
			'test-meta',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
				'meta'        => $meta,
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'test-meta', $result->get_slug() );
		$this->assertSame( $meta, $result->get_meta() );
	}

	/**
	 * Test registering a category with empty meta array.
	 */
	public function test_register_category_with_empty_meta(): void {
		$result = $this->register_category_during_hook(
			'test-empty-meta',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
				'meta'        => array(),
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( array(), $result->get_meta() );
	}

	/**
	 * Test registering a category without meta returns empty array.
	 */
	public function test_register_category_without_meta_returns_empty_array(): void {
		$result = $this->register_category_during_hook(
			'test-no-meta',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( array(), $result->get_meta() );
	}

	/**
	 * Test registering a category with invalid meta (non-array).
	 *
	 * @expectedIncorrectUsage WP_Abilities_Category_Registry::register
	 */
	public function test_register_category_with_invalid_meta(): void {
		$result = $this->register_category_during_hook(
			'test-invalid-meta',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
				'meta'        => 'invalid-string',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Abilities_Category_Registry::register', 'valid `meta` array' );
	}

	/**
	 * Test registering a category with unknown property triggers _doing_it_wrong.
	 *
	 * @expectedIncorrectUsage WP_Ability_Category::__construct
	 */
	public function test_register_category_with_unknown_property(): void {
		$result = $this->register_category_during_hook(
			'test-unknown-property',
			array(
				'label'            => 'Math',
				'description'      => 'Mathematical operations.',
				'unknown_property' => 'some value',
			)
		);

		// Category should still be created.
		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		// But _doing_it_wrong should be triggered.
		$this->assertDoingItWrongTriggered( 'WP_Ability_Category::__construct', 'not a valid property' );
	}
}
