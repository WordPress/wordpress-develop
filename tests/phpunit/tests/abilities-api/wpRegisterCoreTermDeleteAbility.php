<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-terms-ability.php';

/**
 * Tests for the core/term-delete ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Terms
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreTermDeleteAbility extends WP_Abilities_Terms_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/term-delete';

	/**
	 * Builds the input of a delete call.
	 *
	 * @since 7.3.0
	 *
	 * @param int         $term_id  The term ID.
	 * @param string|null $taxonomy Optional. The taxonomy. Default the taxonomy of the term.
	 * @return array<string, mixed> The input.
	 */
	private function delete_input( int $term_id, ?string $taxonomy = null ): array {
		if ( null === $taxonomy ) {
			$taxonomy = get_term( $term_id )->taxonomy;
		}

		return array(
			'where' => array(
				'taxonomy' => $taxonomy,
				'id'       => $term_id,
			),
		);
	}

	/**
	 * Asserts that a term is gone.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param int    $term_id The term ID.
	 * @param string $context What the call was, for the assertion messages.
	 */
	private function assert_deleted( $result, int $term_id, string $context ): void {
		$this->assertSame( array( 'outcome' => 'deleted' ), $result, "{$context}: the outcome should be deleted." );

		wp_cache_delete( $term_id, 'terms' );

		$this->assertNull( get_term( $term_id ), "{$context}: the term should be gone." );
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

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The core/term-delete ability should be registered.' );
		$this->assertSame( 'Delete Term', $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'], 'The ability should not be marked read-only.' );
		$this->assertTrue( $annotations['destructive'], 'The ability should be marked destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be marked idempotent.' );
	}

	/**
	 * The input schema takes only the term, and the output only the outcome.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The delete ability should be registered.' );

		$input = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where' => array(
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
			),
		);

		$output = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'outcome' ),
			'properties'           => array(
				'outcome' => array( 'type' => 'string' ),
			),
		);

		$ability = wp_get_ability( self::ABILITY );

		$this->assert_schema( $input, $ability->get_input_schema(), 'The input schema should be exactly the documented one.' );
		$this->assert_schema( $output, $ability->get_output_schema(), 'The output schema should be exactly the documented one.' );
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
		$input      = array( 'where' => $this->build_unprepared_where( self::$term_ids['empty'], $id_type, $as_object ) );

		$this->login_as( 'editor' );
		$this->assertTrue( $permission( $input ), 'The permission callback should let an editor through.' );

		$this->login_as( 'subscriber' );
		$this->assertFalse( $permission( $input ), 'The permission callback should refuse a subscriber.' );

		$this->login_as( 'logged_out' );
		$this->assertFalse( $permission( $input ), 'The permission callback should refuse a logged-out visitor.' );
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
			$this->assertTrue( $permission( $this->delete_input( $term_id, 'category' ) ), "The permission callback should let term {$term_id} through." );
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
			'category named as a tag'                   => array( 'empty', 'post_tag', 'administrator' ),
			'taxonomy turned off after registration'    => array( 'empty', 'category', 'administrator' ),
			'term that does not exist for a subscriber' => array( 'missing', 'category', 'subscriber' ),
		);
	}

	/**
	 * A term that is missing, of another taxonomy or in a taxonomy that is not exposed gives one
	 * identical not-found error, also for a user who may delete nothing.
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

		if ( 'empty' === $term && 'category' === $taxonomy ) {
			get_taxonomy( 'category' )->show_in_abilities = false;
		}

		$term_id = 'missing' === $term ? self::MISSING_ID : self::$term_ids[ $term ];
		$result  = $this->run_rejected( $this->delete_input( $term_id, $taxonomy ), $term );

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
		$result  = $this->call_rejected( $execute, $this->delete_input( $menu_id, 'nav_menu' ), 'Menu' );

		$this->assert_term_not_found( $result, 'Menu' );
	}

	/**
	 * A second delete of the same term is not found.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_second_delete_is_not_found(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$input = $this->delete_input( self::$term_ids['beta'] );

		$this->assert_deleted( $this->run_ability( $input ), self::$term_ids['beta'], 'First delete' );
		$this->assert_term_not_found( $this->run_rejected( $input, 'Second delete' ), 'Second delete' );
	}

	/**
	 * Returns who may delete terms of each taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{role: string, term: string, allowed: bool}> The cases.
	 */
	public static function data_delete_by_role(): array {
		$cases = array();
		foreach ( array( 'empty', 'beta' ) as $term ) {
			foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'no_role', 'logged_out' ) as $role ) {
				$cases[ "{$role} on {$term}" ] = array( $role, $term, in_array( $role, array( 'administrator', 'editor' ), true ) );
			}
		}

		return $cases;
	}

	/**
	 * Deleting a term takes the capability to delete it; everyone else is refused by both
	 * callbacks and nothing is stored.
	 *
	 * @ticket 66743
	 * @dataProvider data_delete_by_role
	 * @since 7.3.0
	 *
	 * @param string $role    The role.
	 * @param string $term    The term fixture name.
	 * @param bool   $allowed Whether the role may delete the term.
	 */
	public function test_delete_by_role( string $role, string $term, bool $allowed ): void {
		$this->login_as( $role );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$term_id = self::$term_ids[ $term ];
		$input   = $this->delete_input( $term_id );

		if ( $allowed ) {
			$this->assert_deleted( $this->run_ability( $input ), $term_id, $role );
			return;
		}

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );

		$result = $this->call_rejected( $execute, $input, 'Execute callback' );

		$this->assert_error( $result, 'term_cannot_delete', 403, 'Sorry, you are not allowed to delete this term.', 'Execute callback' );
	}

	/**
	 * A visitor who is not logged in is refused even when a filter grants the capability to
	 * delete the term, and nothing is stored.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_logged_out_visitor_is_refused_even_with_the_capability(): void {
		$this->filter_term_capability( 'delete_term', self::$term_ids['empty'], true );

		$this->login_as( 'logged_out' );

		$this->assertTrue( current_user_can( 'delete_term', self::$term_ids['empty'] ), 'The filter should grant the capability to the visitor.' );

		$permission = $this->get_ability_callbacks()['permission_callback'];
		$input      = $this->delete_input( self::$term_ids['empty'] );

		$this->assertFalse( $permission( $input ), 'The permission callback should refuse the visitor.' );
		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
	}

	/**
	 * A super admin who has no role on the site can delete a category, but not the default one.
	 *
	 * @ticket 66743
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_can_delete_a_category(): void {
		$this->login_as( 'super_admin' );
		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( self::$term_ids['empty'] ) ), self::$term_ids['empty'], 'Super admin' );

		$result = $this->run_rejected( $this->delete_input( self::$term_ids['uncategorized'] ), 'Default category' );

		$this->assert_error( $result, 'term_is_default', 400, 'The default category cannot be deleted.', 'Default category' );
	}

	/**
	 * Deleting one term can be granted to a user who may otherwise delete none.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_delete_term_granted_for_one_term(): void {
		$this->filter_term_capability( 'delete_term', self::$term_ids['empty'], true );

		$this->login_as( 'subscriber' );
		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( self::$term_ids['empty'] ) ), self::$term_ids['empty'], 'Granted' );
		$this->assert_refused( $this->run_rejected( $this->delete_input( self::$term_ids['roadmap'] ), 'Other term' ), 'Other term' );
	}

	/**
	 * Deleting one term can be revoked from a user who may otherwise delete every term, and both
	 * callbacks follow it.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_delete_term_revoked_for_one_term(): void {
		$this->filter_term_capability( 'delete_term', self::$term_ids['empty'], false );

		$this->login_as( 'administrator' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = $this->delete_input( self::$term_ids['empty'] );

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
		$this->assert_error( $this->call_rejected( $execute, $input, 'Execute callback' ), 'term_cannot_delete', 403, 'Sorry, you are not allowed to delete this term.', 'Execute callback' );

		$this->assert_deleted( $this->run_ability( $this->delete_input( self::$term_ids['roadmap'] ) ), self::$term_ids['roadmap'], 'Other term' );
	}

	/**
	 * A taxonomy with its own capabilities requires its own capability to delete a term.
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

		$this->assert_refused( $this->run_rejected( $this->delete_input( $term_id ), 'Administrator' ), 'Administrator' );

		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( 'delete_genres' );
		wp_set_current_user( $user->ID );

		$result = $this->run_ability( $this->delete_input( $term_id ) );

		$this->assert_deleted( $result, $term_id, 'Taxonomy capability' );
	}

	/**
	 * Returns default terms with the message their delete gives.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{kind: string, message: string}> The cases.
	 */
	public static function data_default_terms(): array {
		return array(
			'default category'           => array( 'category', 'The default category cannot be deleted.' ),
			'default term of a taxonomy' => array( 'default_term', 'The default term cannot be deleted.' ),
		);
	}

	/**
	 * A default term cannot be deleted by a user who may delete terms of its taxonomy.
	 *
	 * @ticket 66743
	 * @dataProvider data_default_terms
	 * @since 7.3.0
	 *
	 * @param string $kind    Which default term to delete.
	 * @param string $message The expected message.
	 */
	public function test_default_term_cannot_be_deleted( string $kind, string $message ): void {
		if ( 'default_term' === $kind ) {
			register_taxonomy(
				'tt_shelf',
				'post',
				array(
					'hierarchical'      => true,
					'show_in_abilities' => true,
					'default_term'      => array( 'name' => 'Unsorted' ),
				)
			);
			$term_id = (int) get_option( 'default_term_tt_shelf' );
		} else {
			$term_id = self::$term_ids['uncategorized'];
		}

		$this->assertInstanceOf( WP_Term::class, get_term( $term_id ), 'Precondition: the default term should exist.' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_rejected( $this->delete_input( $term_id ), $kind );

		$this->assert_error( $result, 'term_is_default', 400, $message, $kind );
	}

	/**
	 * Returns terms that a default option names although Core's delete does not refuse them as
	 * the default.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{kind: string}> The cases.
	 */
	public static function data_terms_named_only_by_a_default_option(): array {
		return array(
			'tag set in its default option' => array( 'default_option' ),
			'term set in the default term option of a taxonomy without one' => array( 'default_term_option' ),
		);
	}

	/**
	 * Names a term in a default option that does not make it the default term of its taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @param string $kind Which option names the term.
	 * @return int The term ID.
	 */
	private function name_term_in_a_default_option( string $kind ): int {
		if ( 'default_option' === $kind ) {
			update_option( 'default_post_tag', self::$term_ids['alpha'] );

			return self::$term_ids['alpha'];
		}

		register_taxonomy(
			'tt_shelf',
			'post',
			array(
				'hierarchical'      => true,
				'show_in_abilities' => true,
			)
		);
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'tt_shelf',
				'name'     => 'Unsorted',
			)
		);
		update_option( 'default_term_tt_shelf', $term_id );

		return $term_id;
	}

	/**
	 * A term that a default option names, but that Core's delete does not refuse as the default,
	 * is refused by the capability check, since Core denies its delete.
	 *
	 * @ticket 66743
	 * @dataProvider data_terms_named_only_by_a_default_option
	 * @since 7.3.0
	 *
	 * @param string $kind Which option names the term.
	 */
	public function test_term_named_only_by_a_default_option_is_refused_by_the_capability_check( string $kind ): void {
		$term_id = $this->name_term_in_a_default_option( $kind );

		$this->login_as( 'editor' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = $this->delete_input( $term_id );

		$this->assertTrue( current_user_can( get_taxonomy( get_term( $term_id )->taxonomy )->cap->delete_terms ), "{$kind}: precondition: the user should be able to delete terms of the taxonomy." );
		$this->assert_refused( $this->run_rejected( $input, "{$kind}: ability" ), "{$kind}: ability" );
		$this->assert_error( $this->call_rejected( $execute, $input, "{$kind}: execute callback" ), 'term_cannot_delete', 403, 'Sorry, you are not allowed to delete this term.', "{$kind}: execute callback" );
	}

	/**
	 * A term that a default option names, but that Core's delete does not refuse as the default,
	 * is deleted when its delete is granted.
	 *
	 * @ticket 66743
	 * @dataProvider data_terms_named_only_by_a_default_option
	 * @since 7.3.0
	 *
	 * @param string $kind Which option names the term.
	 */
	public function test_term_named_only_by_a_default_option_is_deleted_when_granted( string $kind ): void {
		$term_id = $this->name_term_in_a_default_option( $kind );
		$this->filter_term_capability( 'delete_term', $term_id, true );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->delete_input( $term_id ) );

		$this->assert_deleted( $result, $term_id, $kind );
	}

	/**
	 * Being granted the delete of the default category is not enough without the capability to
	 * delete categories.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_default_term_needs_the_taxonomy_capability(): void {
		$this->filter_term_capability( 'delete_term', self::$term_ids['uncategorized'], true );

		$this->login_as( 'subscriber' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = $this->delete_input( self::$term_ids['uncategorized'] );

		$this->assert_refused( $this->run_rejected( $input, 'Ability' ), 'Ability' );
		$this->assert_error( $this->call_rejected( $execute, $input, 'Execute callback' ), 'term_cannot_delete', 403, 'Sorry, you are not allowed to delete this term.', 'Execute callback' );
	}

	/**
	 * A term that is still there after the delete is a failure.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_term_still_there_after_the_delete_is_a_failure(): void {
		$term_id    = self::$term_ids['empty'];
		$as_default = static function () use ( $term_id ): int {
			return $term_id;
		};

		add_filter(
			'term_exists_default_query_args',
			static function ( array $args ) use ( $as_default ): array {
				add_filter( 'pre_option_default_category', $as_default );

				return $args;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot();
		$result = $this->run_ability( $this->delete_input( $term_id ) );

		remove_filter( 'pre_option_default_category', $as_default );

		$this->assertSame( $before, $this->snapshot(), 'Nothing stored should change.' );
		$this->assert_error( $result, 'term_delete_failed', 500, 'The term cannot be deleted.', 'Still there' );
		$this->assertInstanceOf( WP_Term::class, get_term( $term_id, 'category' ), 'The term should still exist.' );
	}

	/**
	 * Deleting a category moves its children to its parent, and only posts left without a
	 * category get the default category.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_delete_reassigns_children_and_posts_as_core_does(): void {
		$only = self::factory()->post->create( array( 'post_category' => array( self::$term_ids['product_updates'] ) ) );
		$both = self::factory()->post->create( array( 'post_category' => array( self::$term_ids['product_updates'], self::$term_ids['news'] ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( self::$term_ids['product_updates'] ) ), self::$term_ids['product_updates'], 'Delete' );

		clean_term_cache( self::$term_ids['roadmap'], 'category' );

		$this->assertSame( self::$term_ids['news'], get_term( self::$term_ids['roadmap'] )->parent, 'The child should move to the parent of the deleted category.' );
		$this->assertSame( array( self::$term_ids['uncategorized'] ), wp_get_post_categories( $only ), 'A post left without a category should get the default category.' );
		$this->assertSame( array( self::$term_ids['news'] ), wp_get_post_categories( $both ), 'A post with another category should not get the default category.' );
	}

	/**
	 * A tag is deleted.
	 *
	 * @ticket 66743
	 * @since 7.3.0
	 */
	public function test_deletes_a_tag(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_deleted( $this->run_ability( $this->delete_input( self::$term_ids['beta'] ) ), self::$term_ids['beta'], 'Tag' );
		$this->assertSame( array(), wp_get_post_tags( self::$post_ids['published'], array( 'fields' => 'ids' ) ), 'The post should have no tag left.' );
	}
}
