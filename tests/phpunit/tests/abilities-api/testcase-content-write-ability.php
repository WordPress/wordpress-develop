<?php

declare( strict_types=1 );

/**
 * Base test case for the abilities that write content: core/content-create,
 * core/content-update and core/content-delete.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_Content_Write_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The name of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = '';

	/**
	 * The content abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const CONTENT_ABILITIES = array( 'core/content-query', 'core/content-create', 'core/content-update', 'core/content-delete' );

	/**
	 * An ID that no post or user has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	protected const MISSING_ID = 987654321;

	/**
	 * The roles of the matrix tests.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	/**
	 * The statuses of the matrix posts. `trash_<status>` is a post trashed from `<status>`.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const MATRIX_STATUSES = array( 'draft', 'pending', 'publish', 'future', 'private', 'trash_draft', 'trash_publish' );

	/**
	 * Shared user IDs keyed by role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids = array();

	/**
	 * Shared post IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $post_ids = array();

	/**
	 * Forgets the shared fixtures, so a test class never reads the ones of another.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		self::$user_ids = array();
		self::$post_ids = array();
	}

	/**
	 * Unregisters the content abilities and registers the content category.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
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
	 * Unregisters the content abilities and the category, and resets the post types.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( self::CONTENT_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		/*
		 * A test may turn off `show_in_abilities` on `post` and `page`, and the next class's
		 * wpSetUpBeforeClass() runs before the next set_up() resets the post types.
		 */
		$this->reset_post_types();

		parent::tear_down();
	}

	/**
	 * Registers the content abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	protected function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Content() )->register();
	}

	/**
	 * Registers the content abilities and returns the callbacks of the ability under test.
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
	 * Logs in as the shared user with the given role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 */
	protected function login_as( string $role ): void {
		wp_set_current_user( self::$user_ids[ $role ] );
	}

	/**
	 * Takes a snapshot of the stored content: the number of posts per type and status,
	 * revisions included, the number of meta rows and term relationships, and, when a post
	 * is given, the row and meta of that post.
	 *
	 * @since 7.3.0
	 *
	 * @param int|null $post_id Optional. The post ID. Default null.
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot( ?int $post_id = null ): array {
		global $wpdb;

		$snapshot = array(
			'posts' => $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS total FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A ),
			'meta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'terms' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships}" ),
		);

		if ( null !== $post_id ) {
			$snapshot['post']      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
			$snapshot['post_meta'] = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A );
		}

		return $snapshot;
	}

	/**
	 * Asserts that a result is an error with the given code and status.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $code    The expected error code.
	 * @param int    $status  The expected error status.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_error( $result, string $code, int $status, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail." );
		$this->assertSame( $code, $result->get_error_code(), "{$context}: the error code should be {$code}." );

		$data = $result->get_error_data();
		$this->assertIsArray( $data, "{$context}: the error should carry data." );
		$this->assertSame( $status, $data['status'] ?? null, "{$context}: the error status should be {$status}." );
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
	 * Asserts that a result is the invalid attribute error for the given attribute.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result    The ability result.
	 * @param string $attribute The attribute name.
	 * @param string $context   What the call was, for the assertion messages.
	 */
	protected function assert_invalid_attribute( $result, string $attribute, string $context ): void {
		$this->assert_error( $result, 'content_invalid_attribute', 400, $context );

		$message = $result->get_error_message();
		$this->assertNotSame( '', $message, "{$context}: the message should give the reason." );
		$this->assertSame( array( "data[{$attribute}]" => $message ), $result->get_error_data()['params'] ?? array(), "{$context}: the params should hold only the input path of the attribute, with the reason." );
	}

	/**
	 * Asserts that a result is the not-found error.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_not_found( $result, string $context ): void {
		$this->assert_error( $result, 'content_not_found', 404, $context );
		$this->assertSame( 'The requested content was not found.', $result->get_error_message(), "{$context}: the message should be the not-found message." );
	}

	/**
	 * Removes the `description` keywords of a schema at every depth.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $schema The schema.
	 * @return array<string, mixed> The schema without descriptions.
	 */
	protected function strip_descriptions( array $schema ): array {
		unset( $schema['description'] );
		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) ) {
				$schema[ $key ] = $this->strip_descriptions( $value );
			}
		}

		return $schema;
	}

	/**
	 * Returns the forms a `where` can take in input that was not prepared.
	 *
	 * @return array<string, array{id_type: string, as_object: bool}> The type of the ID, and
	 *                                                                 whether `where` is an object.
	 */
	public static function data_unprepared_where_forms(): array {
		return array(
			'integer ID'              => array( 'integer', false ),
			'integer string ID'       => array( 'string', false ),
			'float ID'                => array( 'float', false ),
			'where that is an object' => array( 'integer', true ),
		);
	}

	/**
	 * Builds a `where` for a post in one of the forms of unprepared input.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $id_type   The type to send the ID as.
	 * @param bool   $as_object Whether to send `where` as an object.
	 * @return array<string, mixed>|stdClass The `where`.
	 */
	protected function build_unprepared_where( int $post_id, string $id_type, bool $as_object ) {
		$id = $post_id;
		settype( $id, $id_type );

		$where = array(
			'type' => get_post_type( $post_id ),
			'id'   => $id,
		);

		return $as_object ? (object) $where : $where;
	}

	/**
	 * Returns posts that cannot be reached as a `post`.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_posts_that_are_not_posts(): array {
		return array(
			'revision'       => array( 'revision' ),
			'attachment'     => array( 'attachment' ),
			'page'           => array( 'admin_page' ),
			'missing'        => array( 'missing' ),
			'reusable block' => array( 'reusable_block' ),
			'template'       => array( 'template' ),
			'menu item'      => array( 'menu_item' ),
			'autosave'       => array( 'autosave' ),
		);
	}

	/**
	 * Returns statuses that can never be set.
	 *
	 * @return array<string, array{status: string}> The statuses.
	 */
	public static function data_statuses_that_can_never_be_set(): array {
		return array(
			'trash'             => array( 'trash' ),
			'auto-draft'        => array( 'auto-draft' ),
			'inherit'           => array( 'inherit' ),
			'unregistered'      => array( 'cw_unregistered' ),
			'request-pending'   => array( 'request-pending' ),
			'request-confirmed' => array( 'request-confirmed' ),
			'upper case'        => array( 'PUBLISH' ),
			'trailing space'    => array( 'publish ' ),
			'empty string'      => array( '' ),
			'comma-joined list' => array( 'draft,publish' ),
		);
	}

	/**
	 * Returns authors that a user without `edit_others_posts` may not name.
	 *
	 * @return array<string, array{author: string}> The authors, as fixture names.
	 */
	public static function data_authors_named_without_edit_others_posts(): array {
		return array(
			'existing user' => array( 'editor' ),
			'missing user'  => array( 'missing' ),
		);
	}

	/**
	 * Returns date values without a usable value for a direct call.
	 *
	 * @return array<string, array{date: array<string, string>}> The dates.
	 */
	public static function data_dates_without_a_usable_value(): array {
		return array(
			'empty date'                      => array( array() ),
			'site date that cannot be parsed' => array( array( 'site' => 'not a date' ) ),
			'GMT date that cannot be parsed'  => array( array( 'gmt' => 'not a date' ) ),
		);
	}
}
