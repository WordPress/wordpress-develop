<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-terms-ability.php';

/**
 * Tests for the core/term-update ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Terms
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreTermUpdateAbility extends WP_Abilities_Terms_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/term-update';

	/**
	 * Builds the input of an update call.
	 *
	 * @since 7.3.0
	 *
	 * @param int                  $term_id  The term ID.
	 * @param array<string, mixed> $data     The attributes.
	 * @param list<string>|null    $fields   Optional. The requested fields. Default null.
	 * @param string|null          $taxonomy Optional. The taxonomy. Default the taxonomy of the term.
	 * @return array<string, mixed> The input.
	 */
	private function update_input( int $term_id, array $data, ?array $fields = null, ?string $taxonomy = null ): array {
		if ( null === $taxonomy ) {
			$taxonomy = get_term( $term_id )->taxonomy;
		}

		$input = array(
			'where' => array(
				'taxonomy' => $taxonomy,
				'id'       => $term_id,
			),
			'data'  => $data,
		);
		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
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

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/term-update ability should be registered.' );
		$this->assertSame( 'Update Term', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should not be marked destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
	}

	/**
	 * The input schema takes the term, the attributes and the field list.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The update ability should be registered.' );

		$expected = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'taxonomy', 'id' ),
					'properties'           => array(
						'taxonomy' => $this->taxonomy_schema(),
						'id'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => array(
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

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The update ability should be registered.' );

		$this->assert_schema( self::ITEM_SCHEMA, wp_get_ability( self::ABILITY )->get_output_schema(), 'The output schema should be exactly the documented one.' );
	}

	/**
	 * The permission callback refuses malformed input, taxonomies that are not exposed and IDs
	 * that are not whole numbers of at least 1.
	 *
	 * @ticket 66743
	 * @dataProvider data_unprepared_where_inputs_that_are_refused
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
	 * The permission callback reads the ID in every form the schema accepts, and checks the
	 * capability on it.
	 *
	 * @ticket 66743
	 * @dataProvider data_unprepared_where_forms
	 * @since 7.3.0
	 *
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 */
	public function test_permission_callback_reads_every_form_of_the_id( string $id_type, bool $as_object ): void {
		$permission = $this->get_ability_callbacks()['permission_callback'];
		$input      = array(
			'where' => $this->build_unprepared_where( self::$term_ids['news'], $id_type, $as_object ),
			'data'  => array( 'description' => 'Any' ),
		);

		$this->login_as( 'editor' );
		$this->assertTrue( $permission( $input ), 'The permission callback should let an editor through.' );

		$this->login_as( 'subscriber' );
		$this->assertFalse( $permission( $input ), 'The permission callback should refuse a subscriber.' );

		$this->login_as( 'logged_out' );
		$this->assertFalse( $permission( $input ), 'The permission callback should refuse a logged-out visitor.' );

		$this->assertFalse( $permission( (object) $input ), 'The permission callback should refuse a logged-out visitor for object input.' );
	}

	/**
	 * The permission callback lets a term that is not in the taxonomy through, so that the
	 * ability answers that it is not found.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_permission_callback_lets_a_term_that_is_not_in_the_taxonomy_through(): void {
		$this->login_as( 'subscriber' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		foreach ( array( self::MISSING_ID, self::$term_ids['alpha'] ) as $term_id ) {
			$this->assertTrue( $permission( $this->update_input( $term_id, array( 'name' => 'Any' ), null, 'category' ) ), "The permission callback should let term {$term_id} through." );
		}
	}

	/**
	 * Returns terms that the ability does not find.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{term: string, taxonomy: string, role: string}> The cases.
	 */
	public static function data_terms_that_are_not_found(): array {
		return array(
			'term that does not exist'                  => array( 'missing', 'category', 'administrator' ),
			'term of another taxonomy'                  => array( 'alpha', 'category', 'administrator' ),
			'category named as a tag'                   => array( 'product_updates', 'post_tag', 'administrator' ),
			'taxonomy turned off after registration'    => array( 'news', 'category', 'administrator' ),
			'term that does not exist for a subscriber' => array( 'missing', 'category', 'subscriber' ),
		);
	}

	/**
	 * A term that is missing, of another taxonomy or in a taxonomy that is not exposed gives one
	 * identical not-found error, also for a user who may edit nothing.
	 *
	 * @ticket 66743
	 * @dataProvider data_terms_that_are_not_found
	 * @since 7.3.0
	 *
	 * @param string $term     The term fixture name, or `missing`.
	 * @param string $taxonomy The taxonomy named in the input.
	 * @param string $role     The role.
	 */
	public function test_term_that_is_not_found( string $term, string $taxonomy, string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();
		$this->open_permission_gate();

		if ( 'news' === $term ) {
			get_taxonomy( 'category' )->show_in_abilities = false;
		}

		$term_id = 'missing' === $term ? self::MISSING_ID : self::$term_ids[ $term ];
		$result  = $this->run_rejected( $this->update_input( $term_id, array( 'name' => 'Product updates' ), null, $taxonomy ), $term );

		$this->assert_term_not_found( $result, $term );
	}

	/**
	 * The execute callback checks itself that the taxonomy is exposed.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_execute_refuses_a_taxonomy_that_is_not_exposed(): void {
		$menu_id = wp_create_nav_menu( 'Main' );

		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$result  = $this->call_rejected( $execute, $this->update_input( $menu_id, array( 'name' => 'Renamed' ), null, 'nav_menu' ), 'Menu' );

		$this->assert_term_not_found( $result, 'Menu' );
	}

	/**
	 * Returns who may edit terms of each taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{role: string, term: string, allowed: bool}> The cases.
	 */
	public static function data_update_by_role(): array {
		$cases = array();
		foreach ( array( 'news', 'beta' ) as $term ) {
			foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'no_role', 'logged_out' ) as $role ) {
				$cases[ "{$role} on {$term}" ] = array( $role, $term, in_array( $role, array( 'administrator', 'editor' ), true ) );
			}
		}

		return $cases;
	}

	/**
	 * Editing a term takes the capability to edit it; everyone else is refused by both callbacks
	 * and nothing is stored.
	 *
	 * @ticket 66743
	 * @dataProvider data_update_by_role
	 * @since 7.3.0
	 *
	 * @param string $role    The role.
	 * @param string $term    The term fixture name.
	 * @param bool   $allowed Whether the role may edit the term.
	 */
	public function test_update_by_role( string $role, string $term, bool $allowed ): void {
		$this->login_as( $role );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$term_id = self::$term_ids[ $term ];
		$input   = $this->update_input( $term_id, array( 'description' => "Edited by {$role}" ) );

		if ( $allowed ) {
			$result = $this->run_ability( $input );

			$this->assertSame( $this->expected_item( $term_id, self::DEFAULT_KEYS ), $result, 'The updated term should be returned with the default fields.' );
			$this->assertSame( "Edited by {$role}", get_term( $term_id )->description, 'The description should be stored.' );
			return;
		}

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );

		$result = $this->call_rejected( $execute, $input, 'Execute callback' );

		$this->assert_error( $result, 'term_cannot_edit', 403, 'Sorry, you are not allowed to edit this term.', 'Execute callback' );
	}

	/**
	 * A super admin who has no role on the site can edit a category.
	 *
	 * @ticket 66743
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_can_edit_a_category(): void {
		$this->login_as( 'super_admin' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['news'], array( 'description' => 'By the network' ) ) );

		$this->assertIsArray( $result, 'The category should be updated.' );
		$this->assertSame( 'By the network', get_term( self::$term_ids['news'] )->description, 'The description should be stored.' );
	}

	/**
	 * A visitor who is not logged in is refused even when a filter grants the capability to
	 * edit the term, and nothing is stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_logged_out_visitor_is_refused_even_with_the_capability(): void {
		$this->filter_term_capability( 'edit_term', self::$term_ids['news'], true );

		$this->login_as( 'logged_out' );

		$this->assertTrue( current_user_can( 'edit_term', self::$term_ids['news'] ), 'The filter should grant the capability to the visitor.' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$input      = $this->update_input( self::$term_ids['news'], array( 'description' => 'By a visitor' ) );

		$this->assertFalse( $permission( $input ), 'The permission callback should refuse the visitor.' );
		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
	}

	/**
	 * Editing one term can be granted to a user who may otherwise edit none.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_edit_term_granted_for_one_term(): void {
		$this->filter_term_capability( 'edit_term', self::$term_ids['news'], true );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['news'], array( 'description' => 'Granted' ) ) );

		$this->assertIsArray( $result, 'The granted term should be updated.' );
		$this->assertSame( 'Granted', get_term( self::$term_ids['news'] )->description, 'The description should be stored.' );

		$result = $this->run_rejected( $this->update_input( self::$term_ids['empty'], array( 'description' => 'Not granted' ) ), 'Other term' );

		$this->assert_refused( $result, 'Other term' );
	}

	/**
	 * Editing one term can be revoked from a user who may otherwise edit every term, and both
	 * callbacks follow it.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_edit_term_revoked_for_one_term(): void {
		$this->filter_term_capability( 'edit_term', self::$term_ids['news'], false );

		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = $this->update_input( self::$term_ids['news'], array( 'description' => 'Revoked' ) );

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
		$this->assert_error( $this->call_rejected( $execute, $input, 'Execute callback' ), 'term_cannot_edit', 403, 'Sorry, you are not allowed to edit this term.', 'Execute callback' );

		$this->assertIsArray( $this->run_ability( $this->update_input( self::$term_ids['empty'], array( 'description' => 'Still allowed' ) ) ), 'Another term should still be updated.' );
	}

	/**
	 * A taxonomy with its own capabilities requires its own capability to edit a term.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taxonomy_with_its_own_capabilities(): void {
		$this->register_taxonomy_with_own_capabilities();
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'tt_genre',
				'name'     => 'Jazz',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$this->assert_refused( $this->run_rejected( $this->update_input( $term_id, array( 'name' => 'Blues' ) ), 'Administrator' ), 'Administrator' );

		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( 'edit_genres' );
		wp_set_current_user( $user->ID );

		$result = $this->run_ability( $this->update_input( $term_id, array( 'name' => 'Blues' ) ) );

		$this->assertIsArray( $result, 'A user with the taxonomy capability should update the term.' );
		$this->assertSame( 'Blues', get_term( $term_id )->name, 'The name should be stored.' );
	}

	/**
	 * Returns attributes that would fail the attribute rules or the save.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{data: array<string, mixed>}> The attributes.
	 */
	public static function data_attributes_behind_the_capability(): array {
		return array(
			'valid attributes'        => array( array( 'description' => 'Fine' ) ),
			'parent of the term'      => array( array( 'parent' => 'news' ) ),
			'parent that is missing'  => array( array( 'parent' => 'missing' ) ),
			'slug without characters' => array( array( 'slug' => '!!!' ) ),
			'taken slug'              => array( array( 'slug' => 'empty' ) ),
		);
	}

	/**
	 * A user who may not edit the term gets the same refusal whatever the attributes hold.
	 *
	 * @ticket 66743
	 * @dataProvider data_attributes_behind_the_capability
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The attributes, with a fixture name as parent.
	 */
	public function test_capability_is_checked_before_the_attributes( array $data ): void {
		if ( isset( $data['parent'] ) ) {
			$data['parent'] = 'missing' === $data['parent'] ? self::MISSING_ID : self::$term_ids[ $data['parent'] ];
		}

		$this->login_as( 'author' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$result  = $this->call_rejected( $execute, $this->update_input( self::$term_ids['news'], $data ), 'Author' );

		$this->assert_error( $result, 'term_cannot_edit', 403, 'Sorry, you are not allowed to edit this term.', 'Author' );
	}

	/**
	 * Returns attributes that break an attribute rule, with the rule's attribute and message.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{term: string, data: array<string, mixed>, attribute: string, message: string}> The cases.
	 */
	public static function data_invalid_attributes(): array {
		$flat    = 'Cannot set parent term, taxonomy is not hierarchical.';
		$missing = 'Parent term does not exist.';
		$loop    = 'The parent term cannot be the term itself or one of its descendants.';
		$slug    = 'The slug must contain characters that are valid in a slug.';

		return array(
			'parent of zero on a tag'           => array( 'beta', array( 'parent' => 0 ), 'parent', $flat ),
			'parent on a tag before a bad slug' => array(
				'beta',
				array(
					'parent' => 'alpha',
					'slug'   => '!!!',
				),
				'parent',
				$flat,
			),
			'parent that does not exist'        => array( 'news', array( 'parent' => 'missing' ), 'parent', $missing ),
			'parent that is a tag'              => array( 'news', array( 'parent' => 'alpha' ), 'parent', $missing ),
			'parent that is the term itself'    => array( 'news', array( 'parent' => 'news' ), 'parent', $loop ),
			'parent that is a descendant'       => array( 'news', array( 'parent' => 'roadmap' ), 'parent', $loop ),
			'descendant before a bad slug'      => array(
				'news',
				array(
					'parent' => 'product_updates',
					'slug'   => '0',
				),
				'parent',
				$loop,
			),
			'slug of punctuation'               => array( 'news', array( 'slug' => '!!!' ), 'slug', $slug ),
			'slug of zero'                      => array( 'beta', array( 'slug' => '0' ), 'slug', $slug ),
		);
	}

	/**
	 * Attributes that break a rule are refused before anything is stored.
	 *
	 * @ticket 66743
	 * @dataProvider data_invalid_attributes
	 * @since 7.3.0
	 *
	 * @param string               $term      The term fixture name.
	 * @param array<string, mixed> $data      The attributes, with a fixture name as parent.
	 * @param string               $attribute The attribute in the error.
	 * @param string               $message   The expected message.
	 */
	public function test_invalid_attributes_are_refused( string $term, array $data, string $attribute, string $message ): void {
		if ( isset( $data['parent'] ) && is_string( $data['parent'] ) ) {
			$data['parent'] = 'missing' === $data['parent'] ? self::MISSING_ID : self::$term_ids[ $data['parent'] ];
		}

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_rejected( $this->update_input( self::$term_ids[ $term ], $data ), $attribute );

		$this->assert_invalid_attribute( $result, $attribute, $message, $attribute );
	}

	/**
	 * A slug taken by another term is Core's error, keyed by the slug, when the parent stays.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taken_slug_is_an_error(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$message = 'The slug &#8220;news&#8221; is already in use by another term.';
		$result  = $this->run_rejected( $this->update_input( self::$term_ids['empty'], array( 'slug' => 'news' ) ), 'Taken slug' );

		$this->assert_error( $result, 'duplicate_term_slug', 400, $message, 'Taken slug', array( 'params' => array( 'data[slug]' => $message ) ) );
	}

	/**
	 * A slug taken by another term is made unique when the parent changes too.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_taken_slug_with_a_new_parent_is_made_unique(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->update_input(
				self::$term_ids['empty'],
				array(
					'slug'   => 'roadmap',
					'parent' => self::$term_ids['news'],
				),
				array( 'slug', 'parent' )
			)
		);

		$this->assertSame(
			array(
				'id'     => self::$term_ids['empty'],
				'slug'   => 'roadmap-news',
				'parent' => self::$term_ids['news'],
			),
			$result,
			'The slug should be made unique with the parent slug.'
		);
		$this->assertSame( 'roadmap', get_term( self::$term_ids['roadmap'] )->slug, 'The slug of the other category should not change.' );
	}

	/**
	 * A term can take the name of another term.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_term_can_take_the_name_of_another_term(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['empty'], array( 'name' => 'News' ), array( 'name', 'slug' ) ) );

		$this->assertSame(
			array(
				'id'   => self::$term_ids['empty'],
				'name' => 'News',
				'slug' => 'empty',
			),
			$result,
			'The term should take the name and keep its slug.'
		);
	}

	/**
	 * A name that Core refuses comes back as Core's error, keyed by the name.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_blank_name_is_an_error(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$message = 'A name is required for this term.';
		$result  = $this->run_rejected( $this->update_input( self::$term_ids['news'], array( 'name' => '   ' ) ), 'Blank name' );

		$this->assert_error( $result, 'empty_term_name', 400, $message, 'Blank name', array( 'params' => array( 'data[name]' => $message ) ) );
	}

	/**
	 * Only the given attributes change; every other value of the term is kept.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_only_the_given_attributes_change(): void {
		$before = get_term( self::$term_ids['product_updates'] );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['product_updates'], array( 'description' => 'Release notes and roadmap updates.' ), array( 'name', 'description' ) ) );

		$this->assertSame(
			array(
				'id'          => self::$term_ids['product_updates'],
				'name'        => 'Product updates',
				'description' => 'Release notes and roadmap updates.',
			),
			$result,
			'The updated term should be returned with the chosen fields.'
		);

		$after = get_term( self::$term_ids['product_updates'] );

		$this->assertSame( $before->name, $after->name, 'The name should be kept.' );
		$this->assertSame( $before->slug, $after->slug, 'The slug should be kept.' );
		$this->assertSame( $before->parent, $after->parent, 'The parent should be kept.' );
		$this->assertSame( $before->term_group, $after->term_group, 'The term group should be kept.' );
	}

	/**
	 * Moving a category to the top level changes its link.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_moving_a_category_to_the_top_level(): void {
		$old_link = get_term_link( self::$term_ids['product_updates'], 'category' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['product_updates'], array( 'parent' => 0 ), array( 'name', 'parent', 'link' ) ) );

		$this->assertSame( $this->expected_item( self::$term_ids['product_updates'], array( 'name', 'parent', 'link' ) ), $result, 'The moved category should be returned with the chosen fields.' );
		$this->assertSame( 0, $result['parent'], 'The category should be at the top level.' );
		$this->assertNotSame( $old_link, $result['link'], 'The link should change with the parent.' );
	}

	/**
	 * Values are stored as Core sanitizes them, and backslashes are kept.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_values_are_stored_as_core_sanitizes_them(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->update_input(
				self::$term_ids['empty'],
				array(
					'name'        => '<b>Back\slash</b> & co',
					'slug'        => 'My Slug HERE',
					'description' => '<script>alert(1)</script><strong>Kept</strong> \ back',
				),
				self::ITEM_KEYS
			)
		);

		$term = get_term( self::$term_ids['empty'] );

		$this->assertSame( 'Back\slash &amp; co', $term->name, 'The name should be stored as Core sanitizes it.' );
		$this->assertSame( 'my-slug-here', $term->slug, 'The slug should be stored as Core sanitizes it.' );
		$this->assertSame( '<strong>Kept</strong> \ back', $term->description, 'The description should be stored as Core sanitizes it.' );
		$this->assertSame( $this->expected_item( self::$term_ids['empty'], self::ITEM_KEYS ), $result, 'The stored values should be returned.' );
	}

	/**
	 * Keys that are not allowed do not reach the save, also past the schema.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_keys_that_are_not_allowed_do_not_reach_the_save(): void {
		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$result  = $execute(
			$this->update_input(
				self::$term_ids['empty'],
				array(
					'description' => 'Allowed',
					'term_group'  => 5,
					'alias_of'    => 'news',
					'taxonomy'    => 'post_tag',
					'term_id'     => self::$term_ids['news'],
				)
			)
		);

		$this->assertIsArray( $result, 'The term should be updated.' );

		$term = get_term( self::$term_ids['empty'] );

		$this->assertSame( 'Allowed', $term->description, 'The allowed attribute should be stored.' );
		$this->assertSame( 0, $term->term_group, 'The term group should not be set.' );
		$this->assertSame( 'category', $term->taxonomy, 'The taxonomy should not change.' );
		$this->assertSame( 'News', get_term( self::$term_ids['news'] )->name, 'The term named by the extra ID should not change.' );
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

		$result = $this->run_ability( $this->update_input( self::$term_ids['empty'], array( 'description' => 'Moved away' ) ) );

		$this->assert_term_not_found( $result, 'Read back' );
		$this->assertSame( 'Moved away', get_term( self::$term_ids['empty'], 'post_tag' )->description, 'The save should stand.' );
	}

	/**
	 * The updated item is the item the query returns for the same term and fields.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_updated_item_is_the_query_item(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['roadmap'], array( 'name' => 'Same as query' ), self::ITEM_KEYS ) );

		$query = $this->run_query(
			array(
				'where'  => array(
					'taxonomy' => 'category',
					'ids'      => array( self::$term_ids['roadmap'] ),
				),
				'fields' => self::ITEM_KEYS,
			)
		);

		$this->assertSame( $query['items'] ?? null, array( $result ), 'The updated item should be the query item.' );
	}

	/**
	 * The updated item is read back by the term ID, which differs from the term-taxonomy ID.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_updated_term_is_read_back_by_its_term_id(): void {
		$this->offset_term_taxonomy_ids();

		$term_id = self::factory()->category->create( array( 'name' => 'Two IDs' ) );
		self::factory()->category->create( array( 'name' => 'Next to two IDs' ) );

		$this->assertNotSame( $term_id, get_term( $term_id )->term_taxonomy_id, 'The term ID and the term-taxonomy ID should differ.' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( $term_id, array( 'description' => 'Read back' ), self::ITEM_KEYS ) );

		$this->assertSame( $this->expected_item( $term_id, self::ITEM_KEYS ), $result, 'The updated item should be the saved term.' );
		$this->assertSame( 'Read back', $result['description'], 'The updated item should hold the new description.' );
	}

	/**
	 * The default category can be edited.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_default_category_can_be_edited(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->update_input( self::$term_ids['uncategorized'], array( 'name' => 'General' ), array( 'name' ) ) );

		$this->assertSame(
			array(
				'id'   => self::$term_ids['uncategorized'],
				'name' => 'General',
			),
			$result,
			'The default category should be renamed.'
		);
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

		$result = $this->run_past_validation( $this->update_input( self::$term_ids['news'], array( 'parent' => -1 ) ), 'Parent -1' );

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

		$result = $this->run_ability( $this->update_input( self::$term_ids['beta'], array( 'slug' => $slug ), array( 'slug' ) ) );

		$this->assertIsArray( $result, 'The tag should be updated.' );
		$this->assertSame( $expected, get_term( self::$term_ids['beta'], 'post_tag' )->slug, 'The slug should be stored as Core sanitizes it.' );
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
		$value = str_repeat( 'b', $length );

		$this->login_as( 'editor' );
		$this->register_abilities();

		if ( ! $valid ) {
			$this->assert_invalid_input( $this->run_rejected( $this->update_input( self::$term_ids['beta'], array( $attribute => $value ) ), $attribute ), $attribute );
			return;
		}

		$result = $this->run_ability( $this->update_input( self::$term_ids['beta'], array( $attribute => $value ), array( $attribute ) ) );

		$this->assertIsArray( $result, 'The tag should be updated.' );
		$this->assertSame( $value, $result[ $attribute ], "The {$attribute} should be stored in full." );
	}

	/**
	 * Input that fails the schema is refused before anything is stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_empty_data_is_refused(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_invalid_input( $this->run_rejected( $this->update_input( self::$term_ids['news'], array() ), 'Empty data' ), 'Empty data' );
	}
}
