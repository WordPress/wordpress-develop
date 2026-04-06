<?php declare( strict_types=1 );

/**
 * Tests for the ability category functionality.
 *
 * @covers WP_Ability_Category
 * @covers WP_Ability_Categories_Registry
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpAbilityCategoryRegistry extends WP_UnitTestCase {

	/**
	 * Category registry instance.
	 *
	 * @var WP_Ability_Categories_Registry
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

		$this->registry           = new WP_Ability_Categories_Registry();
		$this->doing_it_wrong_log = array();

		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 3 );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ) );
		$this->doing_it_wrong_log = array();

		$this->registry = null;

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
	 * Test registering a valid category.
	 *
	 * @ticket 64098
	 */
	public function test_register_valid_category(): void {
		$result = $this->registry->register(
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
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_category_invalid_slug_format(): void {
		// Uppercase characters not allowed.
		$result = $this->registry->register(
			'Test-Math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register', 'slug must contain only lowercase' );
	}

	/**
	 * Test registering category with invalid slug - underscore.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_category_invalid_slug_underscore(): void {
		$result = $this->registry->register(
			'test_math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register', 'slug must contain only lowercase' );
	}

	/**
	 * Test registering category without label.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_category_missing_label(): void {
		$result = $this->registry->register(
			'test-math',
			array(
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering category without description.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_category_missing_description(): void {
		$result = $this->registry->register(
			'test-math',
			array(
				'label' => 'Math',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering duplicate category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_duplicate_category(): void {
		$result = $this->registry->register(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result );

		$result = $this->registry->register(
			'test-math',
			array(
				'label'       => 'Math 2',
				'description' => 'Another math category.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register', 'already registered' );
	}

	/**
	 * Test unregistering existing category.
	 *
	 * @ticket 64098
	 */
	public function test_unregister_existing_category(): void {
		$this->registry->register(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$result = $this->registry->unregister( 'test-math' );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertFalse( $this->registry->is_registered( 'test-math' ) );
	}

	/**
	 * Test unregistering non-existent category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::unregister
	 */
	public function test_unregister_nonexistent_category(): void {
		$result = $this->registry->unregister( 'test-nonexistent' );

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::unregister' );
	}

	/**
	 * Test retrieving existing category.
	 *
	 * @ticket 64098
	 */
	public function test_get_existing_category(): void {
		$this->registry->register(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$result = $this->registry->get_registered( 'test-math' );

		$this->assertInstanceOf( WP_Ability_Category::class, $result );
		$this->assertSame( 'test-math', $result->get_slug() );
	}

	/**
	 * Test retrieving non-existent category.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::get_registered
	 */
	public function test_get_nonexistent_category(): void {
		$result = $this->registry->get_registered( 'test-nonexistent' );

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::get_registered' );
	}

	/**
	 * Tests checking if an ability category is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_ability_category(): void {
		$category_slug = 'test-math';
		$this->registry->register(
			$category_slug,
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$result = $this->registry->is_registered( $category_slug );

		$this->assertTrue( $result );
	}

	/**
	 * Tests checking if a non-existent ability category is registered.
	 *
	 * @ticket 64098
	 */
	public function test_has_registered_nonexistent_ability_category(): void {
		$result = $this->registry->is_registered( 'test/non-existent' );

		$this->assertFalse( $result );
	}

	/**
	 * Test retrieving all registered categories.
	 *
	 * @ticket 64098
	 */
	public function test_get_all_categories(): void {
		$this->registry->register(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->registry->register(
			'test-system',
			array(
				'label'       => 'System',
				'description' => 'System operations.',
			)
		);

		$categories = $this->registry->get_all_registered();

		$this->assertIsArray( $categories );
		$this->assertCount( 2, $categories );
		$this->assertArrayHasKey( 'test-math', $categories );
		$this->assertArrayHasKey( 'test-system', $categories );
	}

	/**
	 * Test category is_registered method.
	 *
	 * @ticket 64098
	 */
	public function test_category_is_registered(): void {
		$this->assertFalse( $this->registry->is_registered( 'test-math' ) );

		$this->registry->register(
			'test-math',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'test-math' ) );
	}

	/**
	 * Test category with special characters in label and description.
	 *
	 * @ticket 64098
	 */
	public function test_category_with_special_characters(): void {
		$result = $this->registry->register(
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
	 * Data provider for valid ability category slugs.
	 *
	 * @return array<int, array<string>> Valid ability category slugs.
	 */
	public function data_valid_slug_provider(): array {
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
	 * @ticket 64098
	 *
	 * @dataProvider data_valid_slug_provider
	 *
	 * @param string $slug The category slug to test.
	 */
	public function test_category_slug_valid_formats( string $slug ): void {
		$result = $this->registry->register(
			$slug,
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $result, "Slug '{$slug}' should be valid" );
	}

	/**
	 * Data provider for invalid ability category slugs.
	 *
	 * @return array<int, array<string>> Invalid ability category slugs.
	 */
	public function data_invalid_slug_provider(): array {
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
	 * @ticket 64098
	 *
	 * @dataProvider data_invalid_slug_provider
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 *
	 * @param string $slug The category slug to test.
	 */
	public function test_category_slug_invalid_formats( string $slug ): void {
		$result = $this->registry->register(
			$slug,
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->assertNull( $result, "Slug '{$slug}' should be invalid" );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering category with non-string label.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_category_constructor_non_string_label(): void {
		$result = $this->registry->register(
			'test-invalid',
			array(
				'label'       => 123, // Integer instead of string
				'description' => 'Valid description.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering category with empty label.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_category_constructor_empty_label(): void {
		$result = $this->registry->register(
			'test-invalid',
			array(
				'label'       => '',
				'description' => 'Valid description.',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering category with non-string description.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_category_constructor_non_string_description(): void {
		$result = $this->registry->register(
			'test-invalid',
			array(
				'label'       => 'Valid Label',
				'description' => array( 'invalid' ), // Array instead of string
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test registering category with empty description.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_category_constructor_empty_description(): void {
		$result = $this->registry->register(
			'test-invalid',
			array(
				'label'       => 'Valid Label',
				'description' => '',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register' );
	}

	/**
	 * Test register_ability_category_args filter.
	 *
	 * @ticket 64098
	 */
	public function test_register_category_args_filter(): void {
		add_filter(
			'wp_register_ability_category_args',
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

		$result = $this->registry->register(
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
	 *
	 * @ticket 64098
	 */
	public function test_category_wakeup_throws_exception(): void {
		$category = $this->registry->register(
			'test-serialize',
			array(
				'label'       => 'Test',
				'description' => 'Test description.',
			)
		);

		$this->expectException( LogicException::class );
		$serialized = serialize( $category );
		unserialize( $serialized );
	}

	/**
	 * Test registering a category with valid meta.
	 *
	 * @ticket 64098
	 */
	public function test_register_category_with_valid_meta(): void {
		$meta = array(
			'icon'     => 'dashicons-calculator',
			'priority' => 10,
			'custom'   => array( 'key' => 'value' ),
		);

		$result = $this->registry->register(
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
	 *
	 * @ticket 64098
	 */
	public function test_register_category_with_empty_meta(): void {
		$result = $this->registry->register(
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
	 *
	 * @ticket 64098
	 */
	public function test_register_category_without_meta_returns_empty_array(): void {
		$result = $this->registry->register(
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
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Categories_Registry::register
	 */
	public function test_register_category_with_invalid_meta(): void {
		$result = $this->registry->register(
			'test-invalid-meta',
			array(
				'label'       => 'Math',
				'description' => 'Mathematical operations.',
				'meta'        => 'invalid-string',
			)
		);

		$this->assertNull( $result );
		$this->assertDoingItWrongTriggered( 'WP_Ability_Categories_Registry::register', 'valid `meta` array' );
	}

	/**
	 * Test registering a category with unknown property triggers _doing_it_wrong.
	 *
	 * @ticket 64098
	 *
	 * @expectedIncorrectUsage WP_Ability_Category::__construct
	 */
	public function test_register_category_with_unknown_property(): void {
		$result = $this->registry->register(
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

	/**
	 * Test category registry singleton.
	 *
	 * @ticket 64098
	 */
	public function test_category_registry_singleton(): void {
		$instance1 = WP_Ability_Categories_Registry::get_instance();
		$instance2 = WP_Ability_Categories_Registry::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}
}
