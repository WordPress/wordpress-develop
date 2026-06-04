<?php declare( strict_types=1 );

/**
 * Tests for the filtering support added to wp_get_abilities().
 *
 * @covers wp_get_abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpGetAbilities extends WP_UnitTestCase {

	/**
	 * Test ability names registered during a test, used for teardown cleanup.
	 *
	 * @var string[]
	 */
	private $registered_ability_names = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		global $wp_current_filter;

		parent::set_up();

		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		wp_register_ability_category(
			'math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);
		wp_register_ability_category(
			'text',
			array(
				'label'       => 'Text',
				'description' => 'Text operations.',
			)
		);
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		foreach ( $this->registered_ability_names as $name ) {
			wp_unregister_ability( $name );
		}

		$this->registered_ability_names = array();

		wp_unregister_ability_category( 'math' );
		wp_unregister_ability_category( 'text' );

		parent::tear_down();
	}

	/**
	 * Simulates the `wp_abilities_api_init` action.
	 */
	private function simulate_wp_abilities_init(): void {
		global $wp_current_filter;

		$wp_current_filter[] = 'wp_abilities_api_init';
	}

	/**
	 * Registers a test ability and tracks its name for teardown.
	 *
	 * @param string  $name      The ability name.
	 * @param array   $overrides Optional args to merge into the defaults.
	 * @return WP_Ability|null The registered ability, or null on failure.
	 */
	private function register_test_ability( string $name, array $overrides = array() ): ?WP_Ability {
		$args = array_merge(
			array(
				'label'               => 'Test Ability',
				'description'         => 'A test ability.',
				'category'            => 'math',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'execute_callback'    => static function (): array {
					return array();
				},
				'permission_callback' => static function (): bool {
					return true;
				},
				'meta'                => array(),
			),
			$overrides
		);

		$ability = wp_register_ability( $name, $args );

		if ( null !== $ability ) {
			$this->registered_ability_names[] = $name;
		}

		return $ability;
	}

	// -------------------------------------------------------------------------
	// Category filter
	// -------------------------------------------------------------------------

	/**
	 * Tests filtering by a single category string.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_single_category(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/math-add', array( 'category' => 'math' ) );
		$this->register_test_ability( 'test/text-upper', array( 'category' => 'text' ) );

		$result = wp_get_abilities( array( 'category' => 'math' ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/math-add', $names );
		$this->assertNotContains( 'test/text-upper', $names );
	}

	/**
	 * Tests that passing an array of categories uses OR logic.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_category_array_uses_or_logic(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/math-add', array( 'category' => 'math' ) );
		$this->register_test_ability( 'test/text-upper', array( 'category' => 'text' ) );

		$result = wp_get_abilities( array( 'category' => array( 'math', 'text' ) ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/math-add', $names );
		$this->assertContains( 'test/text-upper', $names );
	}

	/**
	 * Tests that filtering by a non-existent category returns an empty array.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_nonexistent_category_returns_empty(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/math-add', array( 'category' => 'math' ) );

		$result = wp_get_abilities( array( 'category' => 'nonexistent' ) );

		$this->assertSame( array(), $result );
	}

	// -------------------------------------------------------------------------
	// Namespace filter
	// -------------------------------------------------------------------------

	/**
	 * Tests filtering by namespace prefix.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_namespace(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'other/ability-two', array( 'category' => 'text' ) );

		$result = wp_get_abilities( array( 'namespace' => 'test' ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-one', $names );
		$this->assertNotContains( 'other/ability-two', $names );
	}

	/**
	 * Tests that a namespace with a trailing slash produces the same result as one without.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_namespace_trailing_slash_is_normalized(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$get_names           = static function ( array $abilities ): array {
			return array_map(
				static function ( WP_Ability $a ) {
					return $a->get_name();
				},
				$abilities
			);
		};
		$names_without_slash = $get_names( wp_get_abilities( array( 'namespace' => 'test' ) ) );
		$names_with_slash    = $get_names( wp_get_abilities( array( 'namespace' => 'test/' ) ) );

		$this->assertSame( $names_without_slash, $names_with_slash );
	}

	/**
	 * Tests that a non-matching namespace returns an empty array.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_nonexistent_namespace_returns_empty(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$result = wp_get_abilities( array( 'namespace' => 'nonexistent' ) );

		$this->assertSame( array(), $result );
	}

	// -------------------------------------------------------------------------
	// Meta filter
	// -------------------------------------------------------------------------

	/**
	 * Tests filtering by a single meta key/value pair.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_meta_single_key(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability(
			'test/ability-rest',
			array( 'meta' => array( 'show_in_rest' => true ) )
		);
		$this->register_test_ability(
			'test/ability-no-rest',
			array( 'meta' => array( 'show_in_rest' => false ) )
		);

		$result = wp_get_abilities( array( 'meta' => array( 'show_in_rest' => true ) ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-rest', $names );
		$this->assertNotContains( 'test/ability-no-rest', $names );
	}

	/**
	 * Tests that multiple meta conditions use AND logic.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_meta_multiple_keys_uses_and_logic(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability(
			'test/ability-both',
			array(
				'meta' => array(
					'show_in_rest' => true,
					'public'       => true,
				),
			)
		);
		$this->register_test_ability(
			'test/ability-one-key',
			array(
				'meta' => array(
					'show_in_rest' => true,
					'public'       => false,
				),
			)
		);

		$result = wp_get_abilities(
			array(
				'meta' => array(
					'show_in_rest' => true,
					'public'       => true,
				),
			)
		);
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-both', $names );
		$this->assertNotContains( 'test/ability-one-key', $names );
	}

	/**
	 * Tests filtering by nested meta arrays.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_nested_meta(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability(
			'test/ability-public-mcp',
			array( 'meta' => array( 'mcp' => array( 'public' => true ) ) )
		);
		$this->register_test_ability(
			'test/ability-private-mcp',
			array( 'meta' => array( 'mcp' => array( 'public' => false ) ) )
		);

		$result = wp_get_abilities( array( 'meta' => array( 'mcp' => array( 'public' => true ) ) ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-public-mcp', $names );
		$this->assertNotContains( 'test/ability-private-mcp', $names );
	}

	/**
	 * Tests that an ability without the required meta key is excluded.
	 *
	 * @ticket 64990
	 */
	public function test_filter_by_missing_meta_key_excludes_ability(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-no-meta', array( 'meta' => array() ) );

		$result = wp_get_abilities( array( 'meta' => array( 'show_in_rest' => true ) ) );
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertNotContains( 'test/ability-no-meta', $names );
	}

	// -------------------------------------------------------------------------
	// item_include_callback
	// -------------------------------------------------------------------------

	/**
	 * Tests that item_include_callback can include or exclude abilities per item.
	 *
	 * @ticket 64990
	 */
	public function test_item_include_callback_filters_per_item(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-alpha' );
		$this->register_test_ability( 'test/ability-beta' );

		$result = wp_get_abilities(
			array(
				'item_include_callback' => static function ( WP_Ability $ability ): bool {
					return 'test/ability-alpha' === $ability->get_name();
				},
			)
		);
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-alpha', $names );
		$this->assertNotContains( 'test/ability-beta', $names );
	}

	/**
	 * Tests that item_include_callback returning false for all abilities yields an empty result.
	 *
	 * @ticket 64990
	 */
	public function test_item_include_callback_returning_false_yields_empty_result(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$result = wp_get_abilities(
			array(
				'item_include_callback' => static function ( WP_Ability $ability ): bool {
					return false;
				},
			)
		);

		$this->assertSame( array(), $result );
	}

	/**
	 * Tests that item_include_callback receives a WP_Ability instance.
	 *
	 * @ticket 64990
	 */
	public function test_item_include_callback_receives_ability_instance(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$received = null;
		wp_get_abilities(
			array(
				'namespace'             => 'test',
				'item_include_callback' => static function ( WP_Ability $ability ) use ( &$received ): bool {
					$received = $ability;
					return true;
				},
			)
		);

		$this->assertInstanceOf( WP_Ability::class, $received );
		$this->assertSame( 'test/ability-one', $received->get_name() );
	}

	// -------------------------------------------------------------------------
	// wp_get_abilities_item_include filter hook
	// -------------------------------------------------------------------------

	/**
	 * Tests that wp_get_abilities_item_include filter can exclude an ability.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_item_include_filter_can_exclude_ability(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'test/ability-two' );

		$filter = static function ( bool $should_include, WP_Ability $ability ): bool {
			return 'test/ability-two' !== $ability->get_name();
		};

		add_filter( 'wp_get_abilities_item_include', $filter, 10, 2 );
		$result = wp_get_abilities( array( 'namespace' => 'test' ) );
		remove_filter( 'wp_get_abilities_item_include', $filter, 10 );

		$names = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-one', $names );
		$this->assertNotContains( 'test/ability-two', $names );
	}

	/**
	 * Tests that wp_get_abilities_item_include filter receives the ability and original args.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_item_include_filter_receives_ability_and_args(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$received_ability = null;
		$received_args    = null;
		$query_args       = array( 'namespace' => 'test' );

		$filter = static function (
			bool $should_include,
			WP_Ability $ability,
			array $args
		) use (
			&$received_ability,
			&$received_args
		): bool {
			$received_ability = $ability;
			$received_args    = $args;
			return $should_include;
		};

		add_filter( 'wp_get_abilities_item_include', $filter, 10, 3 );
		wp_get_abilities( $query_args );
		remove_filter( 'wp_get_abilities_item_include', $filter, 10 );

		$this->assertInstanceOf( WP_Ability::class, $received_ability );
		$this->assertSame( $query_args, $received_args );
	}

	// -------------------------------------------------------------------------
	// result_callback
	// -------------------------------------------------------------------------

	/**
	 * Tests that result_callback receives the full array of matched abilities.
	 *
	 * @ticket 64990
	 */
	public function test_result_callback_receives_matched_abilities(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'test/ability-two' );

		$received = null;
		wp_get_abilities(
			array(
				'namespace'       => 'test',
				'result_callback' => static function ( array $abilities ) use ( &$received ): array {
					$received = $abilities;
					return $abilities;
				},
			)
		);

		$this->assertIsArray( $received );
		$this->assertCount( 2, $received );
		$this->assertContainsOnlyInstancesOf( WP_Ability::class, $received );
	}

	/**
	 * Tests that result_callback can reshape the result array.
	 *
	 * @ticket 64990
	 */
	public function test_result_callback_can_reshape_result(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'test/ability-two' );

		$result = wp_get_abilities(
			array(
				'namespace'       => 'test',
				'result_callback' => static function ( array $abilities ): array {
					return array_slice( $abilities, 0, 1 );
				},
			)
		);

		$this->assertCount( 1, $result );
	}

	// -------------------------------------------------------------------------
	// wp_get_abilities_result filter hook
	// -------------------------------------------------------------------------

	/**
	 * Tests that wp_get_abilities_result filter can reshape the final result.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_result_filter_can_reshape_result(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'test/ability-two' );

		$filter = static function ( array $abilities ): array {
			return array_slice( $abilities, 0, 1 );
		};

		add_filter( 'wp_get_abilities_result', $filter );
		$result = wp_get_abilities( array( 'namespace' => 'test' ) );
		remove_filter( 'wp_get_abilities_result', $filter );

		$this->assertCount( 1, $result );
	}

	/**
	 * Tests that wp_get_abilities_result filter receives the matched abilities and original args.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_result_filter_receives_abilities_and_args(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$received_abilities = null;
		$received_args      = null;
		$query_args         = array( 'namespace' => 'test' );

		$filter = static function (
			array $abilities,
			array $args
		) use (
			&$received_abilities,
			&$received_args
		): array {
			$received_abilities = $abilities;
			$received_args      = $args;
			return $abilities;
		};

		add_filter( 'wp_get_abilities_result', $filter, 10, 2 );
		wp_get_abilities( $query_args );
		remove_filter( 'wp_get_abilities_result', $filter, 10 );

		$this->assertIsArray( $received_abilities );
		$this->assertSame( $query_args, $received_args );
	}

	// -------------------------------------------------------------------------
	// Filters fire on no-arg calls
	// -------------------------------------------------------------------------

	/**
	 * Tests that the wp_get_abilities_item_include filter fires when wp_get_abilities()
	 * is called with no arguments.
	 *
	 * The most common call path (no args) is also the path security and visibility
	 * plugins most need to participate in, so the filter must run there.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_item_include_filter_fires_with_no_args(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );
		$this->register_test_ability( 'test/ability-two' );

		$received_names = array();

		$filter = static function ( bool $should_include, WP_Ability $ability ) use ( &$received_names ): bool {
			$received_names[] = $ability->get_name();
			return $should_include;
		};

		add_filter( 'wp_get_abilities_item_include', $filter, 10, 2 );
		wp_get_abilities();
		remove_filter( 'wp_get_abilities_item_include', $filter, 10 );

		$this->assertContains( 'test/ability-one', $received_names );
		$this->assertContains( 'test/ability-two', $received_names );
	}

	/**
	 * Tests that the wp_get_abilities_result filter fires when wp_get_abilities()
	 * is called with no arguments.
	 *
	 * @ticket 64990
	 */
	public function test_wp_get_abilities_result_filter_fires_with_no_args(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-one' );

		$call_count         = 0;
		$received_abilities = null;
		$received_args      = null;

		$filter = static function (
			array $abilities,
			array $args
		) use (
			&$call_count,
			&$received_abilities,
			&$received_args
		): array {
			++$call_count;
			$received_abilities = $abilities;
			$received_args      = $args;
			return $abilities;
		};

		add_filter( 'wp_get_abilities_result', $filter, 10, 2 );
		wp_get_abilities();
		remove_filter( 'wp_get_abilities_result', $filter, 10 );

		$this->assertSame( 1, $call_count );
		$this->assertIsArray( $received_abilities );
		$this->assertSame( array(), $received_args );
	}

	// -------------------------------------------------------------------------
	// Combined filters
	// -------------------------------------------------------------------------

	/**
	 * Tests that category and meta filters are combined with AND logic between them.
	 *
	 * @ticket 64990
	 */
	public function test_combined_category_and_meta_filters(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability(
			'test/math-rest',
			array(
				'category' => 'math',
				'meta'     => array( 'show_in_rest' => true ),
			)
		);
		$this->register_test_ability(
			'test/math-no-rest',
			array(
				'category' => 'math',
				'meta'     => array( 'show_in_rest' => false ),
			)
		);
		$this->register_test_ability(
			'test/text-rest',
			array(
				'category' => 'text',
				'meta'     => array( 'show_in_rest' => true ),
			)
		);

		$result = wp_get_abilities(
			array(
				'category' => 'math',
				'meta'     => array( 'show_in_rest' => true ),
			)
		);
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/math-rest', $names );
		$this->assertNotContains( 'test/math-no-rest', $names );
		$this->assertNotContains( 'test/text-rest', $names );
	}

	/**
	 * Tests that namespace and item_include_callback filters are applied together.
	 *
	 * @ticket 64990
	 */
	public function test_combined_namespace_and_item_include_callback_filters(): void {
		$this->simulate_wp_abilities_init();

		$this->register_test_ability( 'test/ability-alpha' );
		$this->register_test_ability( 'test/ability-beta' );
		$this->register_test_ability( 'other/ability-gamma', array( 'category' => 'text' ) );

		$result = wp_get_abilities(
			array(
				'namespace'             => 'test',
				'item_include_callback' => static function ( WP_Ability $ability ): bool {
					return 'test/ability-alpha' === $ability->get_name();
				},
			)
		);
		$names  = array_map(
			static function ( WP_Ability $a ) {
				return $a->get_name();
			},
			$result
		);

		$this->assertContains( 'test/ability-alpha', $names );
		$this->assertNotContains( 'test/ability-beta', $names );
		$this->assertNotContains( 'other/ability-gamma', $names );
	}
}
