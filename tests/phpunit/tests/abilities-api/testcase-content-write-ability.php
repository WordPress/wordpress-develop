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
	 * A GMT date that stays in the future.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const FUTURE_GMT = '2099-06-01T12:00:00';

	/**
	 * The label of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const LABEL = '';

	/**
	 * The annotations of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, bool>
	 */
	protected const ANNOTATIONS = array();

	/**
	 * The roles of the matrix tests.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

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
	 * Registers custom statuses of every visibility, before the abilities are registered.
	 *
	 * @since 7.3.0
	 */
	protected function register_custom_statuses(): void {
		register_post_status(
			'cw_public',
			array(
				'label'  => 'Public',
				'public' => true,
			)
		);
		register_post_status(
			'cw_private',
			array(
				'label'   => 'Private',
				'private' => true,
			)
		);
		register_post_status(
			'cw_viewable',
			array(
				'label'              => 'Viewable',
				'internal'           => false,
				'public'             => false,
				'publicly_queryable' => true,
			)
		);
		register_post_status(
			'cw_hidden',
			array(
				'label'     => 'Hidden',
				'protected' => true,
			)
		);

		$this->assertTrue( is_post_status_viewable( 'cw_viewable' ), 'Precondition: the viewable status should be publicly viewable.' );
		$this->assertFalse( get_post_status_object( 'cw_viewable' )->internal, 'Precondition: the viewable status should not be internal.' );
		$this->assertFalse( get_post_status_object( 'cw_hidden' )->internal, 'Precondition: the hidden status should not be internal.' );
		$this->assertFalse( is_post_status_viewable( 'cw_hidden' ), 'Precondition: the hidden status should not be publicly viewable.' );
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
	 * Returns statuses that can never be set.
	 *
	 * @return array<string, array{0: string}> The statuses.
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
	 * @return array<string, array{0: string}> The authors, as fixture names.
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
	 * @return array<string, array{0: array<string, string>}> The dates.
	 */
	public static function data_dates_without_a_usable_value(): array {
		return array(
			'empty date'                      => array( array() ),
			'site date that cannot be parsed' => array( array( 'site' => 'not a date' ) ),
			'GMT date that cannot be parsed'  => array( array( 'gmt' => 'not a date' ) ),
		);
	}

	/**
	 * The ability is registered in the content category with its label and annotations.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_registers_the_ability(): void {
		$this->register_abilities();

		$ability = wp_get_ability( static::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The ability should be registered.' );
		$this->assertSame( static::LABEL, $ability->get_label(), 'The ability should have its label.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should use the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );
		$this->assertSameSetsWithIndex( static::ANNOTATIONS, $ability->get_meta_item( 'annotations', array() ), 'The ability should have its annotations.' );
	}

	/**
	 * The ability is not registered when no post type is exposed to abilities.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_is_not_registered_without_exposed_post_types(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			get_post_type_object( $post_type )->show_in_abilities = false;
		}

		$this->register_abilities();

		$ability = static::ABILITY;

		$this->assertFalse( wp_has_ability( $ability ), "The {$ability} ability should not be registered without an exposed post type." );
	}

	/**
	 * Returns inputs for which the permission callback returns false, even for an administrator.
	 *
	 * @return array<string, array{0: mixed}> The inputs.
	 */
	abstract public static function data_inputs_refused_by_the_permission_callback(): array;

	/**
	 * The permission callback returns false for missing or malformed input.
	 *
	 * @ticket 66706
	 * @dataProvider data_inputs_refused_by_the_permission_callback
	 * @since 7.3.0
	 *
	 * @param mixed $input The input.
	 */
	public function test_permission_callback_returns_false_for_malformed_input( $input ): void {
		$this->login_as( 'administrator' );

		$permission = $this->get_ability_callbacks()['permission_callback'];

		$this->assertFalse( $permission( $input ), 'The permission callback should return false.' );
	}
}
