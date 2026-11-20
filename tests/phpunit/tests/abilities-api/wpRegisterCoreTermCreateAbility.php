<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-terms-ability.php';

/**
 * Tests for the core/term-create ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Terms
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreTermCreateAbility extends WP_Abilities_Terms_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/term-create';

	/**
	 * Builds the input of a create call.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $taxonomy The taxonomy.
	 * @param array<string, mixed> $data     The attributes next to the taxonomy.
	 * @param list<string>|null    $fields   Optional. The requested fields. Default null.
	 * @return array<string, mixed> The input.
	 */
	private function create_input( string $taxonomy, array $data, ?array $fields = null ): array {
		$input = array( 'data' => array( 'taxonomy' => $taxonomy ) + $data );
		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
	}

	/**
	 * Asserts that a create call failed with the given error and stored nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input   The input.
	 * @param string               $code    The expected error code.
	 * @param int                  $status  The expected status.
	 * @param string               $message The expected message.
	 * @param string               $context What the call was, for the assertion messages.
	 * @param array<string, mixed> $extra   Optional. The expected error data next to the status.
	 *                                      Default empty array.
	 */
	private function assert_create_fails( array $input, string $code, int $status, string $message, string $context, array $extra = array() ): void {
		$result = $this->run_rejected( $input, $context );

		$this->assert_error( $result, $code, $status, $message, $context, $extra );
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->register_abilities();

		$ability = wp_get_ability( self::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/term-create ability should be registered.' );
		$this->assertSame( 'Create Term', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should not be marked destructive.' );
		$this->assertFalse( $annotations['idempotent'], 'The ability should not be marked idempotent.' );
	}

	/**
	 * The input schema takes the taxonomy, the attributes and the field list.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'taxonomy', 'name' ),
					'properties'           => array(
						'taxonomy'    => $this->taxonomy_schema(),
						'name'        => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 200,
						),
						'slug'        => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'description' => array( 'type' => 'string' ),
						'parent'      => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
				),
				'fields' => self::FIELDS_SCHEMA,
			),
		);

		$this->assert_schema( $expected, wp_get_ability( self::ABILITY )->get_input_schema(), 'The input schema should be exactly the documented one.' );
	}

	/**
	 * The output is one item, the same object as an item of the query.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_output_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The create ability should be registered.' );

		$this->assert_schema( self::ITEM_SCHEMA, wp_get_ability( self::ABILITY )->get_output_schema(), 'The output schema should be exactly the documented one.' );
	}

	/**
	 * Returns raw input that the permission callback refuses even for an administrator.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_inputs_refused_by_the_permission_callback(): array {
		return array(
			'input that is a string'        => array( 'category' ),
			'input that is null'            => array( null ),
			'missing data'                  => array( array() ),
			'data that is a string'         => array( array( 'data' => 'category' ) ),
			'data that is an integer'       => array( array( 'data' => 1 ) ),
			'missing taxonomy'              => array( array( 'data' => array( 'name' => 'Refused' ) ) ),
			'taxonomy that is not a string' => array( array( 'data' => array( 'taxonomy' => array( 'category' ) ) ) ),
			'taxonomy that is not exposed'  => array( array( 'data' => array( 'taxonomy' => 'nav_menu' ) ) ),
			'taxonomy that does not exist'  => array( array( 'data' => array( 'taxonomy' => 'tt_missing' ) ) ),
			'taxonomy in where'             => array( array( 'where' => array( 'taxonomy' => 'category' ) ) ),
			'object data, hidden taxonomy'  => array( (object) array( 'data' => (object) array( 'taxonomy' => 'nav_menu' ) ) ),
		);
	}

	/**
	 * The permission callback refuses malformed input and taxonomies that are not exposed.
	 *
	 * @ticket 66743
	 * @dataProvider data_inputs_refused_by_the_permission_callback
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_refuses_malformed_input( $input ): void {
		$this->login_as( 'administrator' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( $input ), 'The permission callback should refuse the input.' );
	}

	/**
	 * Returns input forms that count as objects.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_object_input_forms(): array {
		$data = array( 'taxonomy' => 'category' );

		return array(
			'arrays'                  => array( array( 'data' => $data ) ),
			'input that is an object' => array( (object) array( 'data' => $data ) ),
			'data that is an object'  => array( array( 'data' => (object) $data ) ),
			'both that are objects'   => array( (object) array( 'data' => (object) $data ) ),
		);
	}

	/**
	 * The permission callback accepts the input and `data` as arrays or objects, and reads
	 * nothing in `data` but the taxonomy.
	 *
	 * @ticket 66743
	 * @dataProvider data_object_input_forms
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_accepts_arrays_and_objects( $input ): void {
		$this->login_as( 'editor' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertTrue( $permission( $input ), 'The permission callback should accept the input.' );
	}

	/**
	 * Returns who may create terms in each taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{role: string, taxonomy: string, allowed: bool}> The cases.
	 */
	public static function data_create_by_role(): array {
		$allowed = array(
			'category' => array( 'administrator', 'editor' ),
			'post_tag' => array( 'administrator', 'editor', 'author', 'contributor' ),
		);

		$cases = array();
		foreach ( $allowed as $taxonomy => $roles ) {
			foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'no_role', 'logged_out' ) as $role ) {
				$cases[ "{$role} in {$taxonomy}" ] = array( $role, $taxonomy, in_array( $role, $roles, true ) );
			}
		}

		return $cases;
	}

	/**
	 * Creating a category takes the capability to edit categories, creating a tag the capability
	 * to assign tags; everyone else is refused by both callbacks and nothing is stored.
	 *
	 * @ticket 66743
	 * @dataProvider data_create_by_role
	 * @since 7.3.0
	 *
	 * @param string $role     The role.
	 * @param string $taxonomy The taxonomy.
	 * @param bool   $allowed  Whether the role may create the term.
	 */
	public function test_create_by_role( string $role, string $taxonomy, bool $allowed ): void {
		$this->login_as( $role );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = $this->create_input( $taxonomy, array( 'name' => "Made by {$role}" ) );

		if ( $allowed ) {
			$result = $this->run_ability( $input );

			$this->assertIsArray( $result, 'The term should be created.' );
			$this->assertSame( $this->expected_item( $result['id'], self::DEFAULT_KEYS ), $result, 'The created term should be returned with the default fields.' );
			$this->assertSame( "Made by {$role}", get_term( $result['id'], $taxonomy )->name, 'The term should be stored in the taxonomy.' );
			return;
		}

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );

		$result = $this->call_rejected( $execute, $input, 'Execute callback' );

		$this->assert_error( $result, 'term_cannot_create', 403, 'Sorry, you are not allowed to create terms in this taxonomy.', 'Execute callback' );
	}

	/**
	 * A visitor who is not logged in is refused even when a filter grants the capability to
	 * create terms, and nothing is stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_logged_out_visitor_is_refused_even_with_the_capability(): void {
		$capability = get_taxonomy( 'category' )->cap->edit_terms;

		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ) use ( $capability ): array {
				return $capability === $cap ? array( 'exist' ) : $caps;
			},
			10,
			2
		);

		$this->login_as( 'logged_out' );

		$this->assertTrue( current_user_can( $capability ), 'The filter should grant the capability to the visitor.' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$input      = $this->create_input( 'category', array( 'name' => 'By a visitor' ) );

		$this->assertFalse( $permission( $input ), 'The permission callback should refuse the visitor.' );
		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
	}

	/**
	 * A super admin who has no role on the site can create a category.
	 *
	 * @ticket 66743
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_can_create_a_category(): void {
		$this->login_as( 'super_admin' );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'category', array( 'name' => 'By the network' ) ) );

		$this->assertIsArray( $result, 'The category should be created.' );
		$this->assertSame( 'By the network', get_term( $result['id'], 'category' )->name, 'The category should be stored.' );
	}

	/**
	 * Returns the capability a taxonomy with its own capabilities requires to create a term.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{hierarchical: bool, capability: string}> The cases.
	 */
	public static function data_own_capabilities(): array {
		return array(
			'hierarchical taxonomy' => array( true, 'edit_genres' ),
			'flat taxonomy'         => array( false, 'assign_genres' ),
		);
	}

	/**
	 * A taxonomy with its own capabilities requires its own capability, not the default one.
	 *
	 * @ticket 66743
	 * @dataProvider data_own_capabilities
	 * @since 7.3.0
	 *
	 * @param bool   $hierarchical Whether the taxonomy is hierarchical.
	 * @param string $capability   The capability that allows creating terms.
	 */
	public function test_taxonomy_with_its_own_capabilities( bool $hierarchical, string $capability ): void {
		$this->register_taxonomy_with_own_capabilities( $hierarchical );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_rejected( $this->create_input( 'tt_genre', array( 'name' => 'Jazz' ) ), 'Administrator' );

		$this->assert_refused( $result, 'Administrator' );

		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( $capability );
		wp_set_current_user( $user->ID );

		$result = $this->run_ability( $this->create_input( 'tt_genre', array( 'name' => 'Jazz' ) ) );

		$this->assertIsArray( $result, 'A user with the taxonomy capability should create the term.' );
		$this->assertSame( 'Jazz', get_term( $result['id'], 'tt_genre' )->name, 'The term should be stored.' );
	}

	/**
	 * Returns taxonomies that the execute callback does not find.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{taxonomy: string}> The taxonomies.
	 */
	public static function data_taxonomies_that_are_not_found(): array {
		return array(
			'taxonomy turned off after registration' => array( 'category' ),
			'taxonomy that is not exposed'           => array( 'nav_menu' ),
			'taxonomy that does not exist'           => array( 'tt_missing' ),
		);
	}

	/**
	 * The execute callback checks itself that the taxonomy is exposed.
	 *
	 * @ticket 66743
	 * @dataProvider data_taxonomies_that_are_not_found
	 * @since 7.3.0
	 *
	 * @param string $taxonomy The taxonomy.
	 */
	public function test_execute_refuses_a_taxonomy_that_is_not_exposed( string $taxonomy ): void {
		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		get_taxonomy( 'category' )->show_in_abilities = false;

		$result = $this->call_rejected( $execute, $this->create_input( $taxonomy, array( 'name' => 'Nowhere' ) ), $taxonomy );

		$this->assert_taxonomy_not_found( $result, $taxonomy );
	}

	/**
	 * Returns attributes that would fail the attribute rules.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{data: array<string, mixed>}> The attributes.
	 */
	public static function data_invalid_attributes_for_a_tag(): array {
		return array(
			'valid attributes'        => array( array( 'name' => 'Fine' ) ),
			'parent on a tag'         => array(
				array(
					'name'   => 'Fine',
					'parent' => 0,
				),
			),
			'slug without characters' => array(
				array(
					'name' => 'Fine',
					'slug' => '!!!',
				),
			),
			'blank name'              => array( array( 'name' => '   ' ) ),
		);
	}

	/**
	 * A user who may not create terms gets the same refusal whatever the attributes hold.
	 *
	 * @ticket 66743
	 * @dataProvider data_invalid_attributes_for_a_tag
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The attributes.
	 */
	public function test_capability_is_checked_before_the_attributes( array $data ): void {
		$this->login_as( 'subscriber' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$result  = $this->call_rejected( $execute, $this->create_input( 'post_tag', $data ), 'Subscriber' );

		$this->assert_error( $result, 'term_cannot_create', 403, 'Sorry, you are not allowed to create terms in this taxonomy.', 'Subscriber' );
	}

	/**
	 * Returns attributes that break an attribute rule, with the rule's attribute and message.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{taxonomy: string, data: array<string, mixed>, attribute: string, message: string}> The cases.
	 */
	public static function data_invalid_attributes(): array {
		$flat    = 'Cannot set parent term, taxonomy is not hierarchical.';
		$missing = 'Parent term does not exist.';
		$slug    = 'The slug must contain characters that are valid in a slug.';

		return array(
			'parent of zero on a tag'           => array( 'post_tag', array( 'parent' => 0 ), 'parent', $flat ),
			'parent on a tag before a bad slug' => array(
				'post_tag',
				array(
					'parent' => 'missing',
					'slug'   => '!!!',
				),
				'parent',
				$flat,
			),
			'parent that does not exist'        => array( 'category', array( 'parent' => 'missing' ), 'parent', $missing ),
			'parent that is a tag'              => array( 'category', array( 'parent' => 'alpha' ), 'parent', $missing ),
			'missing parent before a bad slug'  => array(
				'category',
				array(
					'parent' => 'missing',
					'slug'   => '!!!',
				),
				'parent',
				$missing,
			),
			'slug of punctuation'               => array( 'category', array( 'slug' => '!!!' ), 'slug', $slug ),
			'slug of zero'                      => array( 'post_tag', array( 'slug' => '0' ), 'slug', $slug ),
		);
	}

	/**
	 * Attributes that break a rule are refused before anything is stored.
	 *
	 * @ticket 66743
	 * @dataProvider data_invalid_attributes
	 * @since 7.3.0
	 *
	 * @param string               $taxonomy  The taxonomy.
	 * @param array<string, mixed> $data      The attributes, with a fixture name as parent.
	 * @param string               $attribute The attribute in the error.
	 * @param string               $message   The expected message.
	 */
	public function test_invalid_attributes_are_refused( string $taxonomy, array $data, string $attribute, string $message ): void {
		if ( isset( $data['parent'] ) && is_string( $data['parent'] ) ) {
			$data['parent'] = 'missing' === $data['parent'] ? self::MISSING_ID : self::$term_ids[ $data['parent'] ];
		}

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_rejected( $this->create_input( $taxonomy, array( 'name' => 'Rule breaker' ) + $data ), $attribute );

		$this->assert_invalid_attribute( $result, $attribute, $message, $attribute );
	}

	/**
	 * A top-level category can be created with a parent of zero.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_parent_of_zero_creates_a_top_level_category(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->create_input(
				'category',
				array(
					'name'   => 'Top',
					'parent' => 0,
				),
				array( 'parent' )
			)
		);

		$this->assertIsArray( $result, 'The category should be created.' );
		$this->assertSame( 0, $result['parent'], 'The category should be at the top level.' );
	}

	/**
	 * A child category is created with the chosen fields.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_creates_a_child_category_with_chosen_fields(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->create_input(
				'category',
				array(
					'name'   => 'Release notes',
					'parent' => self::$term_ids['news'],
				),
				array( 'name', 'slug', 'parent', 'link' )
			)
		);

		$this->assertIsArray( $result, 'The category should be created.' );
		$this->assertSame( array( 'id', 'name', 'slug', 'parent', 'link' ), array_keys( $result ), 'The item should hold the ID and the chosen fields.' );
		$this->assertSame( 'Release notes', $result['name'], 'The name should be returned.' );
		$this->assertSame( 'release-notes', $result['slug'], 'The slug should be made from the name.' );
		$this->assertSame( self::$term_ids['news'], $result['parent'], 'The parent should be returned.' );
		$this->assertSame( get_term_link( $result['id'], 'category' ), $result['link'], 'The link should be the link of the stored term.' );
		$this->assertSame( self::$term_ids['news'], get_term( $result['id'], 'category' )->parent, 'The parent should be stored.' );
	}

	/**
	 * A tag is created with the default fields, and creating it again is an error that names it.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_creating_an_existing_term_again_is_an_error(): void {
		$this->login_as( 'contributor' );
		$this->register_abilities();

		$input  = $this->create_input( 'post_tag', array( 'name' => 'gamma' ) );
		$result = $this->run_ability( $input );

		$this->assertIsArray( $result, 'The tag should be created.' );
		$this->assertSame( $this->expected_item( $result['id'], self::DEFAULT_KEYS ), $result, 'The tag should be returned with the default fields.' );

		$this->assert_create_fails( $input, 'term_exists', 400, 'A term with the name provided already exists in this taxonomy.', 'Again', array( 'term_id' => $result['id'] ) );
	}

	/**
	 * Creating a child category that exists under the same parent is an error that names it.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_creating_an_existing_child_category_again_is_an_error(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$input = $this->create_input(
			'category',
			array(
				'name'   => 'Product updates',
				'parent' => self::$term_ids['news'],
			)
		);

		$this->assert_create_fails( $input, 'term_exists', 400, 'A term with the name provided already exists with this parent.', 'Child', array( 'term_id' => self::$term_ids['product_updates'] ) );
	}

	/**
	 * A slug that is taken is made unique.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taken_slug_is_made_unique(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->create_input(
				'category',
				array(
					'name' => 'Other news',
					'slug' => 'news',
				),
				array( 'slug' )
			)
		);

		$this->assertIsArray( $result, 'The category should be created.' );
		$this->assertSame( 'news-2', $result['slug'], 'The slug should be made unique.' );
		$this->assertSame( 'news', get_term( self::$term_ids['news'] )->slug, 'The slug of the other category should not change.' );
	}

	/**
	 * Returns values that Core sanitizes, with the stored values, for each user who creates them.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{role: string, data: array<string, string>, expected: array<string, string>}> The cases.
	 */
	public static function data_values_sanitized_by_core(): array {
		$values = array(
			'name with an ampersand'  => array(
				array( 'name' => 'A & B' ),
				array(
					'name' => 'A &amp; B',
					'slug' => 'a-b',
				),
			),
			'name with markup'        => array(
				array( 'name' => '<b>Bold</b> name' ),
				array(
					'name' => 'Bold name',
					'slug' => 'bold-name',
				),
			),
			'name with spaces'        => array(
				array( 'name' => '  Spaced   name  ' ),
				array( 'name' => 'Spaced name' ),
			),
			'name with a backslash'   => array(
				array( 'name' => 'Back\slash' ),
				array(
					'name' => 'Back\slash',
					'slug' => 'backslash',
				),
			),
			'slug with capitals'      => array(
				array(
					'name' => 'Slugged',
					'slug' => 'My Slug HERE',
				),
				array( 'slug' => 'my-slug-here' ),
			),
			'description with markup' => array(
				array(
					'name'        => 'Described',
					'description' => '<script>alert(1)</script><strong>Kept</strong> \ back',
				),
				array( 'description' => '<strong>Kept</strong> \ back' ),
			),
		);

		$cases = array();
		foreach ( array( 'contributor', 'administrator' ) as $role ) {
			foreach ( $values as $name => list( $data, $expected ) ) {
				$cases[ "{$name}, by {$role}" ] = array( $role, $data, $expected );
			}
		}

		return $cases;
	}

	/**
	 * Values are stored as Core sanitizes them, for every user, and backslashes are kept.
	 *
	 * @ticket 66743
	 * @dataProvider data_values_sanitized_by_core
	 * @since 7.3.0
	 *
	 * @param string                $role     The role.
	 * @param array<string, string> $data     The attributes.
	 * @param array<string, string> $expected The stored values, by attribute.
	 */
	public function test_values_are_stored_as_core_sanitizes_them( string $role, array $data, array $expected ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'post_tag', $data, self::ITEM_KEYS ) );

		$this->assertIsArray( $result, 'The tag should be created.' );

		$term = get_term( $result['id'], 'post_tag' );

		foreach ( $expected as $key => $value ) {
			$this->assertSame( $value, $term->$key, "The {$key} should be stored as Core sanitizes it." );
			$this->assertSame( $value, $result[ $key ], "The {$key} should be returned as stored." );
		}
	}

	/**
	 * Only the allowed attributes the input gives reach the save, slashed.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_only_the_given_allowed_attributes_reach_the_save(): void {
		$saved = array();
		add_filter(
			'pre_insert_term',
			static function ( $term, string $taxonomy, array $args ) use ( &$saved ) {
				$saved[] = array(
					'term' => $term,
					'args' => $args,
				);

				return $term;
			},
			10,
			3
		);

		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];

		$this->assertIsArray( $this->run_ability( $this->create_input( 'category', array( 'name' => 'Only a name' ) ) ), 'The category with only a name should be created.' );
		$this->assertIsArray(
			$this->run_ability(
				$this->create_input(
					'category',
					array(
						'name'        => 'Every\attribute',
						'slug'        => 'every-attribute',
						'description' => 'With a \ backslash',
						'parent'      => self::$term_ids['news'],
					)
				)
			),
			'The category with every attribute should be created.'
		);
		$this->assertIsArray(
			$execute(
				$this->create_input(
					'category',
					array(
						'name'        => 'Extra keys',
						'term_group'  => 5,
						'alias_of'    => 'news',
						'term_id'     => self::$term_ids['news'],
						'description' => 'Extra',
					)
				)
			),
			'The category with extra keys should be created.'
		);

		$this->assertCount( 3, $saved, 'Each call should reach the save once.' );
		$this->assertSame( 'Only a name', $saved[0]['term'], 'The name should reach the save.' );
		$this->assertSame( array(), $saved[0]['args'], 'Nothing but the name should reach the save.' );
		$this->assertSame( 'Every\\\\attribute', $saved[1]['term'], 'The name should reach the save slashed.' );
		$this->assertSame(
			array(
				'slug'        => 'every-attribute',
				'description' => 'With a \\\\ backslash',
				'parent'      => self::$term_ids['news'],
			),
			$saved[1]['args'],
			'Every allowed attribute should reach the save, slashed.'
		);
		$this->assertSame( array( 'description' => 'Extra' ), $saved[2]['args'], 'Keys that are not allowed should not reach the save.' );

		$extra = get_term_by( 'name', 'Extra keys', 'category' );

		$this->assertInstanceOf( WP_Term::class, $extra, 'The category with extra keys should be stored.' );
		$this->assertSame( 0, $extra->term_group, 'The term group should not be set.' );
		$this->assertNotSame( self::$term_ids['news'], $extra->term_id, 'The ID should not be taken from the input.' );
	}

	/**
	 * Returns names that Core refuses, with its error.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{name: string, code: string, message: string}> The cases.
	 */
	public static function data_names_refused_by_core(): array {
		return array(
			'blank name'                     => array( '   ', 'empty_term_name', 'A name is required for this term.' ),
			'name that sanitizes to nothing' => array( '<b></b>', 'invalid_term_name', 'Invalid term name.' ),
		);
	}

	/**
	 * A name that Core refuses comes back as Core's error, keyed by the name.
	 *
	 * @ticket 66743
	 * @dataProvider data_names_refused_by_core
	 * @since 7.3.0
	 *
	 * @param string $name    The name.
	 * @param string $code    The expected error code.
	 * @param string $message The expected message.
	 */
	public function test_name_refused_by_core_is_an_error( string $name, string $code, string $message ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_create_fails( $this->create_input( 'category', array( 'name' => $name ) ), $code, 400, $message, $code, array( 'params' => array( 'data[name]' => $message ) ) );
	}

	/**
	 * Returns save errors with the data they come back with.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{code: string, status: int}> The cases.
	 */
	public static function data_save_errors(): array {
		return array(
			'insert database error' => array( 'db_insert_error', 500 ),
			'update database error' => array( 'db_update_error', 500 ),
			'any other code'        => array( 'tt_refused', 400 ),
		);
	}

	/**
	 * A save error comes back with only its first code, its message and its status.
	 *
	 * @ticket 66743
	 * @dataProvider data_save_errors
	 * @since 7.3.0
	 *
	 * @param string $code   The error code.
	 * @param int    $status The expected status.
	 */
	public function test_save_error_comes_back_with_only_its_code_message_and_status( string $code, int $status ): void {
		add_filter(
			'pre_insert_term',
			static function () use ( $code ) {
				$error = new WP_Error(
					$code,
					'The save was refused.',
					array(
						'status' => 418,
						'secret' => 'database detail',
					)
				);
				$error->add( 'tt_second', 'A second error.', 'more detail' );

				return $error;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_create_fails( $this->create_input( 'category', array( 'name' => 'Refused save' ) ), $code, $status, 'The save was refused.', $code );
	}

	/**
	 * A term that cannot be read back after the save is not found, and the save stands.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_term_that_cannot_be_read_back_is_not_found(): void {
		add_action(
			'saved_term',
			static function ( int $term_id, int $tt_id ) {
				global $wpdb;

				$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => 'post_tag' ), array( 'term_taxonomy_id' => $tt_id ) );
				clean_term_cache( $term_id, 'category' );
				clean_term_cache( $term_id, 'post_tag' );
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'category', array( 'name' => 'Moved away' ) ) );

		$this->assert_term_not_found( $result, 'Read back' );
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'name', 'Moved away', 'post_tag' ), 'The saved term should stand.' );
	}

	/**
	 * The created item is the item the query returns for the same term and fields.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_created_item_is_the_query_item(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->create_input(
				'category',
				array(
					'name'        => 'Same as query',
					'description' => 'Described',
					'parent'      => self::$term_ids['news'],
				),
				self::ITEM_KEYS
			)
		);

		$this->assertIsArray( $result, 'The category should be created.' );

		$query = $this->run_query(
			array(
				'where'  => array(
					'taxonomy' => 'category',
					'ids'      => array( $result['id'] ),
				),
				'fields' => self::ITEM_KEYS,
			)
		);

		$this->assertSame( $query['items'] ?? null, array( $result ), 'The created item should be the query item.' );
	}

	/**
	 * The created item is read back by the term ID, which differs from the term-taxonomy ID.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_created_term_is_read_back_by_its_term_id(): void {
		$this->offset_term_taxonomy_ids();

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->create_input( 'category', array( 'name' => 'Two IDs' ), self::ITEM_KEYS ) );
		$term   = get_term_by( 'name', 'Two IDs', 'category' );

		$this->assertInstanceOf( WP_Term::class, $term, 'The category should be stored.' );
		$this->assertNotSame( $term->term_id, $term->term_taxonomy_id, 'The term ID and the term-taxonomy ID should differ.' );
		$this->assertSame( $this->expected_item( $term->term_id, self::ITEM_KEYS ), $result, 'The created item should be the saved term.' );
	}

	/**
	 * A parent below 0 that reaches the execute callback is not a term, and nothing is stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_parent_below_zero_is_refused(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_past_validation(
			$this->create_input(
				'category',
				array(
					'name'   => 'Below zero',
					'parent' => -1,
				)
			),
			'Parent -1'
		);

		$this->assert_invalid_attribute( $result, 'parent', 'Parent term does not exist.', 'Parent -1' );
	}

	/**
	 * A slug longer than 200 characters is saved as Core sanitizes it and returned as stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_slug_longer_than_200_characters_is_saved_as_core_sanitizes_it(): void {
		$slug     = str_repeat( 'Ab!', 100 );
		$expected = sanitize_title( $slug );

		$this->assertGreaterThan( 200, strlen( $slug ), 'Precondition: the slug should be longer than 200 characters.' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->create_input(
				'post_tag',
				array(
					'name' => 'Long slug',
					'slug' => $slug,
				),
				array( 'slug' )
			)
		);

		$this->assertIsArray( $result, 'The tag should be created.' );
		$this->assertSame( $expected, get_term( $result['id'], 'post_tag' )->slug, 'The slug should be stored as Core sanitizes it.' );
		$this->assertSame( $expected, $result['slug'], 'The slug should be returned as stored.' );
	}

	/**
	 * Returns attributes at and beyond their length limit.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{attribute: string, length: int, valid: bool}> The cases.
	 */
	public static function data_lengths(): array {
		return array(
			'name of 200 characters' => array( 'name', 200, true ),
			'name of 201 characters' => array( 'name', 201, false ),
			'slug of 200 characters' => array( 'slug', 200, true ),
		);
	}

	/**
	 * The name takes at most 200 characters, and a slug of 200 characters is stored in full.
	 *
	 * @ticket 66743
	 * @dataProvider data_lengths
	 * @since 7.3.0
	 *
	 * @param string $attribute The attribute.
	 * @param int    $length    The length of the value.
	 * @param bool   $valid     Whether the value is accepted.
	 */
	public function test_length_limits( string $attribute, int $length, bool $valid ): void {
		$value = str_repeat( 'a', $length );
		$data  = array(
			'name'     => 'Long one',
			$attribute => $value,
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		if ( ! $valid ) {
			$this->assert_invalid_input( $this->run_rejected( $this->create_input( 'post_tag', $data ), $attribute ), $attribute );
			return;
		}

		$result = $this->run_ability( $this->create_input( 'post_tag', $data, array( $attribute ) ) );

		$this->assertIsArray( $result, 'The tag should be created.' );
		$this->assertSame( $value, $result[ $attribute ], "The {$attribute} should be stored in full." );
	}
}
