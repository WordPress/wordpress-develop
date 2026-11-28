<?php

declare( strict_types=1 );

/**
 * Tests for the post fields and post query filters that plugins add to content abilities.
 *
 * @covers ::wp_register_ability_post_field
 * @covers ::wp_register_ability_post_query_filter
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterAbilityPostExtensions extends WP_UnitTestCase {

	/**
	 * The meta key that the test filters and fields read the language from.
	 *
	 * @var string
	 */
	private const LANGUAGE_KEY = '_test_language';

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @var array<string, int>
	 */
	private static $post_ids = array();

	/**
	 * Shared administrator ID.
	 *
	 * @var int
	 */
	private static $admin_id = 0;

	/**
	 * Creates shared posts in two languages, and a page.
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		$languages = array(
			'en_1' => 'en',
			'en_2' => 'en',
			'en_3' => 'en',
			'fi_1' => 'fi',
			'fi_2' => 'fi',
		);
		foreach ( $languages as $key => $language ) {
			self::$post_ids[ $key ] = $factory->post->create(
				array(
					'post_status' => 'publish',
					'meta_input'  => array( self::LANGUAGE_KEY => $language ),
				)
			);
		}

		self::$post_ids['page_en'] = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'meta_input'  => array( self::LANGUAGE_KEY => 'en' ),
			)
		);
	}

	/**
	 * Logs in, and sets up the content ability category.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		if ( wp_has_ability( 'core/content-query' ) ) {
			wp_unregister_ability( 'core/content-query' );
		}

		if ( ! wp_has_ability_category( 'content' ) ) {
			$this->in_action(
				'wp_abilities_api_categories_init',
				static function (): void {
					wp_register_ability_category(
						'content',
						array(
							'label'       => 'Content',
							'description' => 'Content.',
						)
					);
				}
			);
		}
	}

	/**
	 * Removes the ability, its category, and the registered extensions.
	 */
	public function tear_down(): void {
		if ( wp_has_ability( 'core/content-query' ) ) {
			wp_unregister_ability( 'core/content-query' );
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		unset( $GLOBALS['wp_ability_post_extensions'] );

		parent::tear_down();
	}

	/**
	 * Runs a callback as if the given action were firing.
	 *
	 * @param string   $action   The action name.
	 * @param callable $callback The callback to run.
	 * @return mixed The callback result.
	 */
	private function in_action( string $action, callable $callback ) {
		global $wp_current_filter;
		$wp_current_filter[] = $action; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.

		try {
			return $callback();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Registers the core/content-query ability.
	 */
	private function register_ability(): void {
		$this->in_action(
			'wp_abilities_api_init',
			static function (): void {
				( new WP_Abilities_Content() )->register();
			}
		);
	}

	/**
	 * Registers a `test/language` field and query filter that read the language from post meta.
	 *
	 * @param list<string> $object_types The post types the field and filter apply to.
	 */
	private function register_language_extensions( array $object_types = array() ): void {
		$this->in_action(
			'wp_abilities_api_extensions_init',
			static function () use ( $object_types ): void {
				wp_register_ability_post_field(
					'test',
					'language',
					array(
						'schema'       => array( 'type' => array( 'string', 'null' ) ),
						'get_callback' => static function ( WP_Post $post ): ?string {
							$language = get_post_meta( $post->ID, self::LANGUAGE_KEY, true );
							return '' !== $language ? $language : null;
						},
						'object_types' => $object_types,
					)
				);
				wp_register_ability_post_query_filter(
					'test',
					'language',
					array(
						'schema'       => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'callback'     => static function ( string $language ): array {
							return array(
								'meta_query' => array(
									array(
										'key'   => self::LANGUAGE_KEY,
										'value' => $language,
									),
								),
							);
						},
						'object_types' => $object_types,
					)
				);
			}
		);
	}

	/**
	 * Registers a `test/<name>` query filter with the given callback.
	 *
	 * @param string   $name     The filter name.
	 * @param callable $callback The filter callback.
	 */
	private function register_query_filter( string $name, callable $callback ): void {
		$this->in_action(
			'wp_abilities_api_extensions_init',
			static function () use ( $name, $callback ): void {
				wp_register_ability_post_query_filter(
					'test',
					$name,
					array(
						'schema'   => array( 'type' => 'boolean' ),
						'callback' => $callback,
					)
				);
			}
		);
	}

	/**
	 * Runs core/content-query with the given input.
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return mixed The ability result.
	 */
	private function query( array $input ) {
		return wp_get_ability( 'core/content-query' )->execute( $input );
	}

	/**
	 * Without registered extensions, the schemas do not change.
	 *
	 * @ticket 66550
	 */
	public function test_schemas_have_no_extensions_without_registrations(): void {
		$this->register_ability();
		$ability = wp_get_ability( 'core/content-query' );

		$this->assertArrayNotHasKey( 'extensions', $ability->get_input_schema()['properties']['where']['properties'], 'The where filter should not list extensions.' );
		$this->assertArrayNotHasKey( 'extensions', $ability->get_output_schema()['properties']['items']['items']['properties'], 'The post output should not list extensions.' );
		$this->assertNotContains( 'extensions', $ability->get_input_schema()['properties']['fields']['items']['enum'], 'The fields enum should not list extensions.' );
	}

	/**
	 * Registered extensions are added to the input and output schemas and to the field paths.
	 *
	 * @ticket 66550
	 */
	public function test_schemas_list_registered_extensions(): void {
		$this->register_language_extensions();
		$this->register_ability();
		$ability = wp_get_ability( 'core/content-query' );

		$where = $ability->get_input_schema()['properties']['where']['properties'];
		$this->assertSame( array( 'test' ), array_keys( $where['extensions']['properties'] ), 'The where filter should list the namespace.' );
		$this->assertFalse( $where['extensions']['additionalProperties'], 'The extensions filter should stay closed.' );
		$this->assertSame( 'string', $where['extensions']['properties']['test']['properties']['language']['type'], 'The filter schema should be used.' );

		$post = $ability->get_output_schema()['properties']['items']['items']['properties'];
		$this->assertSame( array( 'string', 'null' ), $post['extensions']['properties']['test']['properties']['language']['type'], 'The field schema should be used.' );

		$enum = $ability->get_input_schema()['properties']['fields']['items']['enum'];
		foreach ( array( 'extensions', 'extensions.test', 'extensions.test.language' ) as $path ) {
			$this->assertContains( $path, $enum, "The fields enum should list {$path}." );
		}
	}

	/**
	 * A requested extension field is returned, and is not returned by default.
	 *
	 * @ticket 66550
	 *
	 * @dataProvider data_extension_field_paths
	 *
	 * @param string $path A field path that includes the extension field.
	 */
	public function test_returns_requested_extension_field( string $path ): void {
		$this->register_language_extensions();
		$this->register_ability();

		$where = array(
			'type' => 'post',
			'ids'  => array( self::$post_ids['fi_1'] ),
		);

		$result = $this->query(
			array(
				'where'  => $where,
				'fields' => array( $path ),
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( array( 'test' => array( 'language' => 'fi' ) ), $result['items'][0]['extensions'], 'The field should be returned under its namespace.' );

		$result = $this->query( array( 'where' => $where ) );
		$this->assertArrayNotHasKey( 'extensions', $result['items'][0], 'Extension fields should not be returned by default.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_extension_field_paths(): array {
		return array(
			'the field'     => array( 'extensions.test.language' ),
			'the namespace' => array( 'extensions.test' ),
			'all'           => array( 'extensions' ),
		);
	}

	/**
	 * An extension field is left out of posts of post types it does not apply to.
	 *
	 * @ticket 66550
	 */
	public function test_extension_field_is_left_out_for_other_post_types(): void {
		$this->register_language_extensions( array( 'post' ) );
		$this->register_ability();

		$result = $this->query(
			array(
				'where'  => array(
					'type' => 'page',
					'ids'  => array( self::$post_ids['page_en'] ),
				),
				'fields' => array( 'extensions' ),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'id' => self::$post_ids['page_en'] ), $result['items'][0], 'Only the ID should be returned.' );
	}

	/**
	 * A query filter limits the results and the totals, on every page.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_limits_results_and_totals(): void {
		$this->register_language_extensions();
		$this->register_ability();

		$input = array(
			'where'      => array(
				'type'       => 'post',
				'extensions' => array( 'test' => array( 'language' => 'en' ) ),
			),
			'fields'     => array( 'extensions.test.language' ),
			'pagination' => array( 'per_page' => 2 ),
		);

		$first = $this->query( $input );
		$this->assertNotWPError( $first );
		$this->assertSame(
			array(
				'total'       => 3,
				'total_pages' => 2,
			),
			$first['pagination'],
			'The totals should only count posts in the language.'
		);

		$input['pagination']['page'] = 2;

		$second = $this->query( $input );
		$this->assertNotWPError( $second );
		$this->assertCount( 1, $second['items'], 'The last page should hold the remaining post.' );

		$ids = array_merge( wp_list_pluck( $first['items'], 'id' ), wp_list_pluck( $second['items'], 'id' ) );
		$this->assertEqualSets( array( self::$post_ids['en_1'], self::$post_ids['en_2'], self::$post_ids['en_3'] ), $ids, 'Only the posts in the language should be returned.' );
	}

	/**
	 * Query filters join with the core filters and with each other, so a post must match all.
	 *
	 * @ticket 66550
	 */
	public function test_query_filters_join_with_and(): void {
		$this->register_language_extensions();
		$this->register_query_filter(
			'only_first',
			static function (): array {
				return array( 'post__in' => array( self::$post_ids['en_1'], self::$post_ids['fi_1'] ) );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'ids'        => array( self::$post_ids['en_1'], self::$post_ids['en_2'], self::$post_ids['fi_1'] ),
					'extensions' => array(
						'test' => array(
							'language'   => 'en',
							'only_first' => true,
						),
					),
				),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array( self::$post_ids['en_1'] ), wp_list_pluck( $result['items'], 'id' ), 'Only the post that matches every filter should be returned.' );
		$this->assertSame( 1, $result['pagination']['total'], 'The total should count only that post.' );
	}

	/**
	 * Exclusions from a query filter apply to the IDs that `where.ids` and other filters allow.
	 *
	 * @ticket 66550
	 *
	 * @dataProvider data_exclusion_orders
	 *
	 * @param list<string> $order The order the filters are given in.
	 */
	public function test_query_filter_exclusions_apply_to_inclusions( array $order ): void {
		$this->register_query_filter(
			'exclude_first',
			static function (): array {
				return array( 'post__not_in' => array( self::$post_ids['en_1'] ) );
			}
		);
		$this->register_query_filter(
			'include_two',
			static function (): array {
				return array( 'post__in' => array( self::$post_ids['en_1'], self::$post_ids['en_2'] ) );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'ids'        => array( self::$post_ids['en_1'], self::$post_ids['en_2'], self::$post_ids['en_3'] ),
					'extensions' => array( 'test' => array_fill_keys( $order, true ) ),
				),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array( self::$post_ids['en_2'] ), wp_list_pluck( $result['items'], 'id' ), 'The excluded post should be left out.' );
		$this->assertSame( 1, $result['pagination']['total'], 'The total should not count the excluded post.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: list<string>}>
	 */
	public static function data_exclusion_orders(): array {
		return array(
			'exclusion first' => array( array( 'exclude_first', 'include_two' ) ),
			'inclusion first' => array( array( 'include_two', 'exclude_first' ) ),
		);
	}

	/**
	 * A query filter receives its value converted to its schema type.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_receives_value_of_its_schema_type(): void {
		$received = null;
		$this->in_action(
			'wp_abilities_api_extensions_init',
			static function () use ( &$received ): void {
				wp_register_ability_post_query_filter(
					'test',
					'languages',
					array(
						'schema'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'callback' => static function ( array $languages ) use ( &$received ): array {
							$received = $languages;
							return array();
						},
					)
				);
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'extensions' => array( 'test' => array( 'languages' => 'en,fi' ) ),
				),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'en', 'fi' ), $received, 'A list given as a string should reach the callback as an array.' );
	}

	/**
	 * Exclusions that remove every allowed ID match no post.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_exclusions_can_remove_every_id(): void {
		$this->register_query_filter(
			'exclude_all',
			static function (): array {
				return array( 'post__not_in' => array( self::$post_ids['en_1'] ) );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'ids'        => array( self::$post_ids['en_1'] ),
					'extensions' => array( 'test' => array( 'exclude_all' => true ) ),
				),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array(), $result['items'], 'No post should be returned.' );
		$this->assertSame( 0, $result['pagination']['total'], 'The total should be zero.' );
	}

	/**
	 * A query filter that allows no ID matches no post, rather than every post.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_with_empty_post__in_matches_no_post(): void {
		$this->register_query_filter(
			'none',
			static function (): array {
				return array( 'post__in' => array() );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'extensions' => array( 'test' => array( 'none' => true ) ),
				),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( array(), $result['items'], 'No post should be returned.' );
		$this->assertSame( 0, $result['pagination']['total'], 'The total should be zero.' );
	}

	/**
	 * A query filter that a post type does not support is refused.
	 *
	 * @ticket 66550
	 */
	public function test_refuses_query_filter_for_other_post_types(): void {
		$this->register_language_extensions( array( 'post' ) );
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'page',
					'extensions' => array( 'test' => array( 'language' => 'en' ) ),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'content_invalid_filter', $result->get_error_code() );
	}

	/**
	 * A query filter that is not registered fails input validation.
	 *
	 * @ticket 66550
	 */
	public function test_unregistered_query_filter_fails_validation(): void {
		$this->register_language_extensions();
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'extensions' => array( 'test' => array( 'missing' => 'en' ) ),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * A query filter can refuse the query with a WP_Error.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_can_refuse_the_query(): void {
		$this->register_query_filter(
			'refuse',
			static function (): WP_Error {
				return new WP_Error( 'test_refused', 'Refused.' );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'extensions' => array( 'test' => array( 'refuse' => true ) ),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'test_refused', $result->get_error_code() );
	}

	/**
	 * A query filter that returns other query arguments fails the query.
	 *
	 * @ticket 66550
	 */
	public function test_query_filter_cannot_return_other_query_args(): void {
		$this->register_query_filter(
			'widen',
			static function (): array {
				return array( 'post_status' => 'any' );
			}
		);
		$this->register_ability();

		$result = $this->query(
			array(
				'where' => array(
					'type'       => 'post',
					'extensions' => array( 'test' => array( 'widen' => true ) ),
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_query_filter', $result->get_error_code() );
	}

	/**
	 * Extensions can only be registered on the `wp_abilities_api_extensions_init` action.
	 *
	 * @ticket 66550
	 *
	 * @expectedIncorrectUsage wp_register_ability_post_field
	 */
	public function test_field_registration_requires_the_extensions_action(): void {
		$result = wp_register_ability_post_field(
			'test',
			'language',
			array(
				'schema'       => array( 'type' => 'string' ),
				'get_callback' => '__return_empty_string',
			)
		);

		$this->assertFalse( $result );
	}

	/**
	 * Invalid registrations are rejected.
	 *
	 * @ticket 66550
	 *
	 * @dataProvider data_invalid_registrations
	 *
	 * @expectedIncorrectUsage wp_register_ability_post_query_filter
	 *
	 * @param string               $extension_namespace The namespace.
	 * @param string               $name                The filter name.
	 * @param array<string, mixed> $args                The filter arguments.
	 */
	public function test_rejects_invalid_registrations( string $extension_namespace, string $name, array $args ): void {
		$result = $this->in_action(
			'wp_abilities_api_extensions_init',
			static function () use ( $extension_namespace, $name, $args ): bool {
				return wp_register_ability_post_query_filter( $extension_namespace, $name, $args );
			}
		);

		$this->assertFalse( $result );
		$this->assertSame( array(), _wp_get_ability_post_extensions( 'query_filters' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	public static function data_invalid_registrations(): array {
		$valid = array(
			'schema'   => array( 'type' => 'string' ),
			'callback' => '__return_empty_array',
		);

		return array(
			'namespace with a dot'    => array( 'my.plugin', 'language', $valid ),
			'uppercase name'          => array( 'test', 'Language', $valid ),
			'missing schema'          => array( 'test', 'language', array( 'callback' => '__return_empty_array' ) ),
			'invalid callback'        => array( 'test', 'language', array_merge( $valid, array( 'callback' => 'not_a_function' ) ) ),
			'invalid object types'    => array( 'test', 'language', array_merge( $valid, array( 'object_types' => 'post' ) ) ),
			'non-string object types' => array( 'test', 'language', array_merge( $valid, array( 'object_types' => array( 1 ) ) ) ),
		);
	}

	/**
	 * An extension cannot be registered twice.
	 *
	 * @ticket 66550
	 *
	 * @expectedIncorrectUsage wp_register_ability_post_query_filter
	 */
	public function test_rejects_duplicate_registration(): void {
		$this->register_language_extensions();

		$result = $this->in_action(
			'wp_abilities_api_extensions_init',
			static function (): bool {
				return wp_register_ability_post_query_filter(
					'test',
					'language',
					array(
						'schema'   => array( 'type' => 'string' ),
						'callback' => '__return_empty_array',
					)
				);
			}
		);

		$this->assertFalse( $result );
	}
}
