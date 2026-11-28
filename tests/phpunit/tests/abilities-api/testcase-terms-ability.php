<?php

declare( strict_types=1 );

/**
 * Base test case for the term abilities: core/terms-query, core/term-create,
 * core/term-update and core/term-delete.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_Terms_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The name of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = '';

	/**
	 * The term abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const TERM_ABILITIES = array( 'core/terms-query', 'core/term-create', 'core/term-update', 'core/term-delete' );

	/**
	 * An ID that no term or post has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	protected const MISSING_ID = 987654321;

	/**
	 * The keys of an item, in their order.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const ITEM_KEYS = array( 'id', 'taxonomy', 'name', 'slug', 'description', 'parent', 'count', 'link' );

	/**
	 * The keys of an item when no fields are requested.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const DEFAULT_KEYS = array( 'id', 'taxonomy', 'name', 'slug', 'link' );

	/**
	 * The schema of the `fields` input.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, mixed>
	 */
	protected const FIELDS_SCHEMA = array(
		'type'        => 'array',
		'uniqueItems' => true,
		'items'       => array(
			'type' => 'string',
			'enum' => array( 'id', 'taxonomy', 'name', 'slug', 'description', 'parent', 'count', 'link' ),
		),
	);

	/**
	 * The schema of an output item.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, mixed>
	 */
	protected const ITEM_SCHEMA = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'id' ),
		'properties'           => array(
			'id'          => array( 'type' => 'integer' ),
			'taxonomy'    => array( 'type' => 'string' ),
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'parent'      => array( 'type' => 'integer' ),
			'count'       => array( 'type' => 'integer' ),
			'link'        => array(
				'type'   => 'string',
				'format' => 'uri',
			),
		),
	);

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids = array();

	/**
	 * Shared term IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $term_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $post_ids = array();

	/**
	 * Creates the shared users, terms and posts.
	 *
	 * Categories: News > Product updates > Roadmap, Empty and the default category.
	 * Tags: alpha and beta. The published post has News and beta, the private post alpha.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$user_ids = array(
			'administrator' => $factory->user->create( array( 'role' => 'administrator' ) ),
			'editor'        => $factory->user->create( array( 'role' => 'editor' ) ),
			'author'        => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor'   => $factory->user->create( array( 'role' => 'contributor' ) ),
			'subscriber'    => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'no_role'       => self::create_user_without_a_role( $factory ),
		);

		if ( is_multisite() ) {
			self::$user_ids['super_admin'] = self::create_user_without_a_role( $factory );
			grant_super_admin( self::$user_ids['super_admin'] );
		}

		$news            = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'News',
			)
		);
		$product_updates = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Product updates',
				'parent'   => $news,
			)
		);

		self::$term_ids = array(
			'uncategorized'   => (int) get_option( 'default_category' ),
			'news'            => $news,
			'product_updates' => $product_updates,
			'roadmap'         => $factory->term->create(
				array(
					'taxonomy' => 'category',
					'name'     => 'Roadmap',
					'parent'   => $product_updates,
				)
			),
			'empty'           => $factory->term->create(
				array(
					'taxonomy' => 'category',
					'name'     => 'Empty',
				)
			),
			'alpha'           => $factory->term->create(
				array(
					'taxonomy' => 'post_tag',
					'name'     => 'alpha',
				)
			),
			'beta'            => $factory->term->create(
				array(
					'taxonomy' => 'post_tag',
					'name'     => 'beta',
				)
			),
		);

		self::$post_ids = array(
			'published' => $factory->post->create(
				array(
					'post_author'   => self::$user_ids['administrator'],
					'post_status'   => 'publish',
					'post_category' => array( $news ),
					'tags_input'    => array( 'beta' ),
				)
			),
			'private'   => $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_status' => 'private',
					'tags_input'  => array( 'alpha' ),
				)
			),
			'page'      => $factory->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_type'   => 'page',
					'post_status' => 'publish',
				)
			),
		);
	}

	/**
	 * Revokes the super admin and forgets the shared fixtures, so a test class never reads
	 * the ones of another.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		if ( isset( self::$user_ids['super_admin'] ) ) {
			revoke_super_admin( self::$user_ids['super_admin'] );
		}

		self::$user_ids = array();
		self::$term_ids = array();
		self::$post_ids = array();
	}

	/**
	 * Creates a user who is logged in but has no role on the site.
	 *
	 * On multisite the user is not a member of the site at all.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 * @return int The user ID.
	 */
	private static function create_user_without_a_role( $factory ): int {
		$user_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			remove_user_from_blog( $user_id, get_current_blog_id() );
		} else {
			get_userdata( $user_id )->set_role( '' );
		}

		return $user_id;
	}

	/**
	 * Unregisters the term abilities and registers the content category.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::TERM_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( ! wp_has_ability_category( 'content' ) ) {
			global $wp_current_filter;
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
			wp_register_ability_category(
				'content',
				array(
					'label'       => 'Content',
					'description' => 'Content.',
				)
			);
		}
	}

	/**
	 * Unregisters the term abilities and the category, and resets the taxonomies.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( self::TERM_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		/*
		 * A test may turn off `show_in_abilities` on `category` and `post_tag`, and the next
		 * class's wpSetUpBeforeClass() runs before the next set_up() resets the taxonomies.
		 */
		$this->reset_taxonomies();

		parent::tear_down();
	}

	/**
	 * Registers the term abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	protected function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Terms() )->register();
	}

	/**
	 * Registers the term abilities and returns the callbacks of the ability under test.
	 *
	 * The callbacks call private methods, so tests that skip input validation or the
	 * permission check capture them from the registration arguments.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, callable> The permission and execute callbacks, keyed by argument name.
	 */
	protected function get_ability_callbacks(): array {
		$ability   = static::ABILITY;
		$callbacks = array();

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callbacks, $ability ): array {
				if ( $ability === $name ) {
					$callbacks = array(
						'permission_callback' => $args['permission_callback'],
						'execute_callback'    => $args['execute_callback'],
					);
				}

				return $args;
			},
			10,
			2
		);

		$this->register_abilities();

		$this->assertArrayHasKey( 'execute_callback', $callbacks, "The {$ability} ability should be registered with callbacks." );

		return $callbacks;
	}

	/**
	 * Runs the registered ability under test with the given input.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The ability input.
	 * @return mixed The ability result.
	 */
	protected function run_ability( $input ) {
		$ability = static::ABILITY;

		$this->assertTrue( wp_has_ability( $ability ), "The {$ability} ability should be registered." );

		return wp_get_ability( $ability )->execute( $input );
	}

	/**
	 * Runs the registered core/terms-query ability with the given input.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return mixed The ability result.
	 */
	protected function run_query( array $input ) {
		$this->assertTrue( wp_has_ability( 'core/terms-query' ), 'The core/terms-query ability should be registered.' );

		return wp_get_ability( 'core/terms-query' )->execute( $input );
	}

	/**
	 * Lets every call of the ability under test past its permission callback.
	 *
	 * @since 7.3.0
	 */
	protected function open_permission_gate(): void {
		$ability = static::ABILITY;

		add_filter(
			'wp_ability_permission_result',
			static function ( $permission, string $name ) use ( $ability ) {
				return $ability === $name ? true : $permission;
			},
			10,
			2
		);
	}

	/**
	 * Logs in as the shared user with the given role or fixture name, or logs out.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name, or `logged_out`.
	 */
	protected function login_as( string $role ): void {
		wp_set_current_user( 'logged_out' === $role ? 0 : self::$user_ids[ $role ] );
	}

	/**
	 * Grants or revokes a term capability on one term with a `map_meta_cap` filter.
	 *
	 * @since 7.3.0
	 *
	 * @param string $capability The term capability, such as `edit_term`.
	 * @param int    $term_id    The term ID.
	 * @param bool   $grant      True to grant the capability, false to revoke it.
	 */
	protected function filter_term_capability( string $capability, int $term_id, bool $grant ): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user_id, array $args ) use ( $capability, $term_id, $grant ): array {
				if ( $capability === $cap && isset( $args[0] ) && $term_id === (int) $args[0] ) {
					return $grant ? array( 'exist' ) : array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			4
		);
	}

	/**
	 * Registers a hierarchical taxonomy with its own capabilities, exposed to abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param bool $hierarchical Optional. Whether the taxonomy is hierarchical. Default true.
	 */
	protected function register_taxonomy_with_own_capabilities( bool $hierarchical = true ): void {
		register_taxonomy(
			'tt_genre',
			'post',
			array(
				'hierarchical'      => $hierarchical,
				'show_in_abilities' => true,
				'capabilities'      => array(
					'manage_terms' => 'manage_genres',
					'edit_terms'   => 'edit_genres',
					'delete_terms' => 'delete_genres',
					'assign_terms' => 'assign_genres',
				),
			)
		);
	}

	/**
	 * Stores a term-taxonomy row that belongs to no term, so that the terms created afterwards
	 * have a term-taxonomy ID that differs from their term ID.
	 *
	 * @since 7.3.0
	 */
	protected function offset_term_taxonomy_ids(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'term_id'     => self::MISSING_ID,
				'taxonomy'    => 'tt_stray',
				'description' => '',
				'parent'      => 0,
				'count'       => 0,
			)
		);
	}

	/**
	 * Asserts that the schema refuses input, then runs the ability under test with the same
	 * input let past validation by the `wp_ability_validate_input` filter, and asserts that
	 * nothing stored changed.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input   The ability input, which the schema refuses.
	 * @param string $context What the call was, for the assertion messages.
	 * @return mixed The ability result.
	 */
	protected function run_past_validation( $input, string $context ) {
		$this->assert_invalid_input( $this->run_rejected( $input, "{$context} (validated)" ), "{$context} (validated)" );

		$ability = static::ABILITY;
		$skip    = static function ( $is_valid, $input, string $name ) use ( $ability ) {
			return $ability === $name ? true : $is_valid;
		};

		add_filter( 'wp_ability_validate_input', $skip, 10, 3 );
		$result = $this->run_rejected( $input, $context );
		remove_filter( 'wp_ability_validate_input', $skip, 10 );

		return $result;
	}

	/**
	 * Takes a snapshot of the stored terms: the rows of the term tables, the default-term
	 * options of every taxonomy and the `terms` last-changed value.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot(): array {
		global $wpdb;

		$options = array();
		foreach ( get_taxonomies() as $taxonomy ) {
			$options[ "default_{$taxonomy}" ]      = get_option( "default_{$taxonomy}" );
			$options[ "default_term_{$taxonomy}" ] = get_option( "default_term_{$taxonomy}" );
		}

		return array(
			'terms'              => $wpdb->get_results( "SELECT * FROM {$wpdb->terms} ORDER BY term_id", ARRAY_A ),
			'term_taxonomy'      => $wpdb->get_results( "SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id", ARRAY_A ),
			'termmeta'           => $wpdb->get_results( "SELECT * FROM {$wpdb->termmeta} ORDER BY meta_id", ARRAY_A ),
			'term_relationships' => $wpdb->get_results( "SELECT * FROM {$wpdb->term_relationships} ORDER BY object_id, term_taxonomy_id", ARRAY_A ),
			'options'            => $options,
			'last_changed'       => wp_cache_get_last_changed( 'terms' ),
		);
	}

	/**
	 * Runs the ability under test with input that must be rejected, and asserts that nothing
	 * stored changed.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input   The ability input.
	 * @param string $context What the call was, for the assertion messages.
	 * @return mixed The ability result.
	 */
	protected function run_rejected( $input, string $context ) {
		$before = $this->snapshot();
		$result = $this->run_ability( $input );

		$this->assertSame( $before, $this->snapshot(), "{$context}: nothing stored should change." );

		return $result;
	}

	/**
	 * Calls a callback that must reject the call, and asserts that nothing stored changed.
	 *
	 * @since 7.3.0
	 *
	 * @param callable $callback The callback, such as an execute callback.
	 * @param mixed    $input    The input passed to the callback.
	 * @param string   $context  What the call was, for the assertion messages.
	 * @return mixed The callback result.
	 */
	protected function call_rejected( callable $callback, $input, string $context ) {
		$before = $this->snapshot();
		$result = $callback( $input );

		$this->assertSame( $before, $this->snapshot(), "{$context}: nothing stored should change." );

		return $result;
	}

	/**
	 * Returns a schema without its descriptions, with the keys of every object sorted and
	 * the values of `enum` and `required` sorted, so that two schemas compare by content.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $schema The schema.
	 * @return array<mixed> The normalized schema.
	 */
	protected function normalize_schema( array $schema ): array {
		if ( isset( $schema['description'] ) && is_string( $schema['description'] ) ) {
			unset( $schema['description'] );
		}

		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = $this->normalize_schema( $value );
			}
		}

		foreach ( array( 'enum', 'required' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) && array_is_list( $schema[ $keyword ] ) ) {
				sort( $schema[ $keyword ] );
			}
		}

		if ( ! array_is_list( $schema ) ) {
			ksort( $schema );
		}

		return $schema;
	}

	/**
	 * Asserts that a schema is exactly the expected one, apart from descriptions and order.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $expected The expected schema.
	 * @param array<string, mixed> $actual   The schema of the ability.
	 * @param string               $message  The assertion message.
	 */
	protected function assert_schema( array $expected, array $actual, string $message ): void {
		$this->assertSame( $this->normalize_schema( $expected ), $this->normalize_schema( $actual ), $message );
	}

	/**
	 * Returns the schema of `where.taxonomy` and `data.taxonomy` with the default exposed taxonomies.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected function taxonomy_schema(): array {
		return array(
			'type' => 'string',
			'enum' => array( 'category', 'post_tag' ),
		);
	}

	/**
	 * Asserts that a result is an error with exactly the given code, message and data.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed                $result  The ability result.
	 * @param string               $code    The expected error code.
	 * @param int                  $status  The expected error status.
	 * @param string               $message The expected error message.
	 * @param string               $context What the call was, for the assertion messages.
	 * @param array<string, mixed> $extra   Optional. The expected error data next to the status.
	 *                                      Default empty array.
	 */
	protected function assert_error( $result, string $code, int $status, string $message, string $context, array $extra = array() ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( array( $code ), $result->get_error_codes(), "{$context}: the error should hold only the code {$code}." );
		$this->assertSame( $message, $result->get_error_message(), "{$context}: the error message should match." );
		$this->assertSameSetsWithIndex( array( 'status' => $status ) + $extra, $result->get_error_data(), "{$context}: the error data should hold the status {$status} and nothing else unexpected." );
	}

	/**
	 * Asserts that a result is the error of a call that the permission callback refused.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_refused( $result, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), "{$context}: the permission callback should refuse the call." );
	}

	/**
	 * Asserts that a result is the error of input that fails the schema.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_invalid_input( $result, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), "{$context}: the input should fail the schema." );
	}

	/**
	 * Asserts that a result is the not-found error of a term.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_term_not_found( $result, string $context ): void {
		$this->assert_error( $result, 'term_not_found', 404, 'The requested term was not found.', $context );
	}

	/**
	 * Asserts that a result is the not-found error of a taxonomy.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_taxonomy_not_found( $result, string $context ): void {
		$this->assert_error( $result, 'terms_taxonomy_not_found', 404, 'The requested taxonomy was not found.', $context );
	}

	/**
	 * Asserts that a result is the invalid attribute error for the given attribute.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result    The ability result.
	 * @param string $attribute The attribute name.
	 * @param string $message   The expected message.
	 * @param string $context   What the call was, for the assertion messages.
	 */
	protected function assert_invalid_attribute( $result, string $attribute, string $message, string $context ): void {
		$this->assert_error( $result, 'term_invalid_attribute', 400, $message, $context, array( 'params' => array( "data[{$attribute}]" => $message ) ) );
	}

	/**
	 * Builds the item that the abilities should return for a stored term.
	 *
	 * @since 7.3.0
	 *
	 * @param int          $term_id The term ID.
	 * @param list<string> $keys    The requested keys. `id` is always included.
	 * @return array<string, mixed> The item, with its keys in their order.
	 */
	protected function expected_item( int $term_id, array $keys ): array {
		wp_cache_delete( $term_id, 'terms' );

		$term = get_term( $term_id );

		$this->assertInstanceOf( WP_Term::class, $term, 'Precondition: the term should exist.' );

		$values = array(
			'id'          => $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => $term->parent,
			'count'       => $term->count,
			'link'        => get_term_link( $term ),
		);

		if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
			unset( $values['parent'] );
		}

		$item = array();
		foreach ( self::ITEM_KEYS as $key ) {
			if ( array_key_exists( $key, $values ) && ( 'id' === $key || in_array( $key, $keys, true ) ) ) {
				$item[ $key ] = $values[ $key ];
			}
		}

		return $item;
	}

	/**
	 * Returns the forms the ID of a `where` can take in input that was not prepared, which
	 * the permission callback reads as the ID.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{id_type: string, as_object: bool}> The type of the ID, and
	 *                                                                 whether `where` is an object.
	 */
	public static function data_unprepared_where_forms(): array {
		return array(
			'integer ID'              => array( 'integer', false ),
			'integer string ID'       => array( 'string', false ),
			'whole float ID'          => array( 'float', false ),
			'where that is an object' => array( 'integer', true ),
		);
	}

	/**
	 * Builds a `where` for a term in one of the forms of unprepared input.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $term_id   The term ID.
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 * @return array<string, mixed>|stdClass The `where`.
	 */
	protected function build_unprepared_where( int $term_id, string $id_type, bool $as_object ) {
		$id = $term_id;
		settype( $id, $id_type );

		$where = array(
			'taxonomy' => get_term( $term_id )->taxonomy,
			'id'       => $id,
		);

		return $as_object ? (object) $where : $where;
	}

	/**
	 * Returns unprepared `where` values that the update and delete permission callbacks refuse.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_unprepared_where_inputs_that_are_refused(): array {
		$where = static function ( $id ): array {
			return array(
				'where' => array(
					'taxonomy' => 'category',
					'id'       => $id,
				),
			);
		};

		return array(
			'input that is a string'          => array( 'where' ),
			'input that is null'              => array( null ),
			'input that is an integer'        => array( 5 ),
			'missing where'                   => array( array() ),
			'where that is a string'          => array( array( 'where' => 'category' ) ),
			'missing taxonomy'                => array( array( 'where' => array( 'id' => 5 ) ) ),
			'taxonomy that is not a string'   => array(
				array(
					'where' => array(
						'taxonomy' => array( 'category' ),
						'id'       => 5,
					),
				),
			),
			'taxonomy that is not exposed'    => array(
				array(
					'where' => array(
						'taxonomy' => 'nav_menu',
						'id'       => 5,
					),
				),
			),
			'taxonomy that does not exist'    => array(
				array(
					'where' => array(
						'taxonomy' => 'tt_missing',
						'id'       => 5,
					),
				),
			),
			'missing ID'                      => array( array( 'where' => array( 'taxonomy' => 'category' ) ) ),
			'ID of zero'                      => array( $where( 0 ) ),
			'negative ID'                     => array( $where( -1 ) ),
			'fractional ID'                   => array( $where( 1.5 ) ),
			'ID that is not a number'         => array( $where( 'abc' ) ),
			'ID that starts with a number'    => array( $where( '5abc' ) ),
			'ID that is an empty string'      => array( $where( '' ) ),
			'ID that is true'                 => array( $where( true ) ),
			'ID that is null'                 => array( $where( null ) ),
			'ID beyond the exact float range' => array( $where( 2.0 ** 64 ) ),
		);
	}
}
