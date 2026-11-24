<?php

declare( strict_types=1 );

/**
 * Base test case for the media abilities: core/media-query, core/media-upload,
 * core/media-update and core/media-delete.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
abstract class WP_Abilities_Media_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The name of the ability under test, set by each test class.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = '';

	/**
	 * The media abilities, which every test unregisters before and after it runs.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const MEDIA_ABILITIES = array( 'core/media-query', 'core/media-upload', 'core/media-update', 'core/media-delete' );

	/**
	 * An ID that no post or user has.
	 *
	 * @since 7.3.0
	 *
	 * @var int
	 */
	protected const MISSING_ID = 987654321;

	/**
	 * The top-level keys of an item with the default fields.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const DEFAULT_KEYS = array( 'id', 'status', 'date', 'slug', 'title', 'media_type', 'mime_type', 'source_url' );

	/**
	 * The values the `fields` input takes, in schema order.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected const FIELDS = array(
		'id',
		'status',
		'date',
		'date.site',
		'date.gmt',
		'modified',
		'modified.site',
		'modified.gmt',
		'slug',
		'link',
		'title',
		'title.raw',
		'title.rendered',
		'caption',
		'caption.raw',
		'caption.rendered',
		'description',
		'description.raw',
		'description.rendered',
		'alt_text',
		'media_type',
		'mime_type',
		'source_url',
		'filename',
		'filesize',
		'media_details',
		'media_details.width',
		'media_details.height',
		'media_details.length',
		'media_details.sizes',
		'post',
		'author',
		'author.id',
		'author.name',
		'author.slug',
	);

	/**
	 * The roles the shared fixtures add, keyed by role name, with their capabilities.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected const ROLES = array(
		'mdt_uploader'              => array(
			'read'         => true,
			'upload_files' => true,
		),
		'mdt_other_editor'          => array(
			'read'               => true,
			'edit_others_posts'  => true,
			'edit_private_posts' => true,
		),
		'mdt_other_editor_uploader' => array(
			'read'               => true,
			'upload_files'       => true,
			'edit_others_posts'  => true,
			'edit_private_posts' => true,
		),
		'mdt_no_caps'               => array(),
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
	 * Shared post and attachment IDs keyed by fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, int>
	 */
	protected static $post_ids = array();

	/**
	 * Creates the shared users, posts and attachments.
	 *
	 * The attachments are rows without files, so no test class leaves a file behind.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	protected static function create_shared_fixtures( WP_UnitTest_Factory $factory ): void {
		foreach ( self::ROLES as $role => $capabilities ) {
			add_role( $role, $role, $capabilities );
		}

		self::$user_ids = array(
			'administrator'         => $factory->user->create( array( 'role' => 'administrator' ) ),
			'editor'                => $factory->user->create( array( 'role' => 'editor' ) ),
			'author'                => $factory->user->create( array( 'role' => 'author' ) ),
			'author_without_posts'  => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor'           => $factory->user->create( array( 'role' => 'contributor' ) ),
			'subscriber'            => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'uploader'              => $factory->user->create( array( 'role' => 'mdt_uploader' ) ),
			'other_editor'          => $factory->user->create( array( 'role' => 'mdt_other_editor' ) ),
			'other_editor_uploader' => $factory->user->create( array( 'role' => 'mdt_other_editor_uploader' ) ),
			'no_caps'               => $factory->user->create( array( 'role' => 'mdt_no_caps' ) ),
		);

		$posts = array(
			'post'         => $factory->post->create(
				array(
					'post_author' => self::$user_ids['editor'],
					'post_status' => 'publish',
					'post_date'   => '2020-05-15 10:00:00',
				)
			),
			'page'         => $factory->post->create(
				array(
					'post_type'   => 'page',
					'post_author' => self::$user_ids['editor'],
					'post_status' => 'publish',
					'post_date'   => '2019-03-10 10:00:00',
				)
			),
			'private_post' => $factory->post->create(
				array(
					'post_author' => self::$user_ids['editor'],
					'post_status' => 'private',
				)
			),
			'author_post'  => $factory->post->create(
				array(
					'post_author' => self::$user_ids['author'],
					'post_status' => 'publish',
					'post_date'   => '2021-07-20 10:00:00',
				)
			),
		);

		$posts['revision'] = $factory->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $posts['post'],
				'post_author' => self::$user_ids['editor'],
			)
		);

		$attachments = array(
			'unattached'     => array( self::$user_ids['author'], 0, 'inherit', 'image/jpeg', 'mdt-unattached.jpg' ),
			'attached'       => array( self::$user_ids['author'], $posts['post'], 'inherit', 'image/jpeg', 'mdt-attached.jpg' ),
			'private_parent' => array( self::$user_ids['editor'], $posts['private_post'], 'inherit', 'image/jpeg', 'mdt-private-parent.jpg' ),
			'private'        => array( self::$user_ids['editor'], 0, 'private', 'image/jpeg', 'mdt-private.jpg' ),
			'trashed'        => array( self::$user_ids['editor'], 0, 'trash', 'image/jpeg', 'mdt-trashed.jpg' ),
			'pdf'            => array( self::$user_ids['author'], 0, 'inherit', 'application/pdf', 'mdt-document.pdf' ),
			'audio'          => array( self::$user_ids['author'], 0, 'inherit', 'audio/mpeg', 'mdt-sound.mp3' ),
			'editor_image'   => array( self::$user_ids['editor'], 0, 'inherit', 'image/jpeg', 'mdt-editor-image.jpg' ),
		);

		foreach ( $attachments as $name => list( $author, $parent, $status, $mime_type, $file ) ) {
			$posts[ $name ] = $factory->attachment->create_object(
				array(
					'file'           => '2026/01/' . $file,
					'post_author'    => $author,
					'post_parent'    => $parent,
					'post_status'    => $status,
					'post_mime_type' => $mime_type,
					'post_title'     => 'Fixture ' . $name,
				)
			);
		}

		update_post_meta( $posts['trashed'], '_wp_trash_meta_status', 'inherit' );

		self::$post_ids = $posts;
	}

	/**
	 * Forgets the shared fixtures and removes the roles they added.
	 *
	 * @since 7.3.0
	 */
	public static function wpTearDownAfterClass(): void {
		foreach ( array_keys( self::ROLES ) as $role ) {
			remove_role( $role );
		}

		self::$user_ids = array();
		self::$post_ids = array();
	}

	/**
	 * Unregisters the media abilities and registers the content category.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		$this->unregister_media_abilities();

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
	 * Unregisters the media abilities and the category, removes the files a test added to the
	 * uploads folder, and resets the post types.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		global $wpdb;

		$this->unregister_media_abilities();

		if ( wp_has_ability_category( 'content' ) ) {
			wp_unregister_ability_category( 'content' );
		}

		$wpdb->suppress_errors( false );

		$this->remove_added_uploads();

		/*
		 * A test may turn off `show_in_abilities` on the attachment or post type, and the next class's
		 * wpSetUpBeforeClass() runs before the next set_up() resets the post types.
		 */
		$this->reset_post_types();

		parent::tear_down();
	}

	/**
	 * Unregisters the media abilities that are registered.
	 *
	 * @since 7.3.0
	 */
	private function unregister_media_abilities(): void {
		foreach ( self::MEDIA_ABILITIES as $ability_name ) {
			if ( wp_has_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}
	}

	/**
	 * Registers the media abilities inside a faked init action.
	 *
	 * @since 7.3.0
	 */
	protected function register_abilities(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Faking the action context to register within it.
		( new WP_Abilities_Media() )->register();
	}

	/**
	 * Registers the media abilities and returns the permission callback of the ability under
	 * test.
	 *
	 * The callback calls private methods, so tests that reach the gate without the input
	 * checks capture it from the registration arguments.
	 *
	 * @since 7.3.0
	 *
	 * @return callable The permission callback.
	 */
	protected function get_permission_callback(): callable {
		$ability  = static::ABILITY;
		$callback = null;

		add_filter(
			'wp_register_ability_args',
			static function ( array $args, string $name ) use ( &$callback, $ability ): array {
				if ( $ability === $name ) {
					$callback = $args['permission_callback'];
				}

				return $args;
			},
			10,
			2
		);

		$this->register_abilities();

		$this->assertIsCallable( $callback, "The {$ability} ability should be registered with a permission callback." );

		return $callback;
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
	 * Logs in as the shared user with the given role or fixture name, or logs out for
	 * `logged_out`.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 */
	protected function login_as( string $role ): void {
		wp_set_current_user( 'logged_out' === $role ? 0 : self::$user_ids[ $role ] );
	}

	/**
	 * Makes every input pass schema validation. The input is still converted to the schema
	 * types.
	 *
	 * @since 7.3.0
	 */
	protected function skip_input_validation(): void {
		add_filter( 'wp_ability_validate_input', '__return_true' );
	}

	/**
	 * Makes the permission callback of every ability pass.
	 *
	 * @since 7.3.0
	 */
	protected function force_permission(): void {
		add_filter( 'wp_ability_permission_result', '__return_true' );
	}

	/**
	 * Grants a capability to every user.
	 *
	 * @since 7.3.0
	 *
	 * @param string $cap The capability.
	 */
	protected function grant_cap( string $cap ): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $requested ) use ( $cap ): array {
				return $cap === $requested ? array( 'exist' ) : $caps;
			},
			10,
			2
		);
	}

	/**
	 * Makes every save of a post fail, as an empty post or with a database error.
	 *
	 * @since 7.3.0
	 *
	 * @param bool $database_error Whether the save fails with a database error.
	 */
	protected function make_saves_fail( bool $database_error ): void {
		global $wpdb;

		if ( ! $database_error ) {
			add_filter( 'wp_insert_post_empty_content', '__return_true' );
			return;
		}

		$wpdb->suppress_errors( true );

		add_filter(
			'query',
			static function ( string $query ) use ( $wpdb ): string {
				if ( preg_match( '/^\s*(INSERT\s+INTO|UPDATE)\s+`?' . preg_quote( $wpdb->posts, '/' ) . '`?\s/i', $query ) ) {
					return str_replace( $wpdb->posts, $wpdb->prefix . 'mdt_missing_table', $query );
				}

				return $query;
			}
		);
	}

	/**
	 * Returns the files under the uploads folder, sorted.
	 *
	 * @since 7.3.0
	 *
	 * @return list<string> The file paths.
	 */
	protected function list_upload_files(): array {
		$basedir = wp_upload_dir()['basedir'];

		if ( ! is_dir( $basedir ) ) {
			return array();
		}

		$files = $this->files_in_dir( $basedir );
		sort( $files );

		return $files;
	}

	/**
	 * Takes a snapshot of the stored media: the number of posts per type and status, the
	 * number of meta rows, the files under the uploads folder and, when a post is given, the
	 * row and meta of that post.
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
			'files' => $this->list_upload_files(),
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
	 * Asserts that a result is an error with the given code and status and one `params`
	 * entry, keyed by the given input path, with a reason as its value.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $code    The expected error code.
	 * @param string $path    The expected input path.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_error_with_params( $result, string $code, string $path, string $context ): void {
		$this->assert_error( $result, $code, 400, $context );

		$params = $result->get_error_data()['params'] ?? null;
		$this->assertIsArray( $params, "{$context}: the error should carry params." );
		$this->assertSame( array( $path ), array_keys( $params ), "{$context}: the params should hold only the input path {$path}." );
		$this->assertIsString( $params[ $path ], "{$context}: the params entry should hold the reason." );
		$this->assertNotSame( '', $params[ $path ], "{$context}: the reason should not be empty." );
	}

	/**
	 * Asserts the message of an error result.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $message The expected message.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_error_message( $result, string $message, string $context ): void {
		$this->assertWPError( $result, "{$context}: the call should fail before its message is read." );
		$this->assertSame( $message, $result->get_error_message(), "{$context}: the error message should be the fixed message." );
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
	 * Asserts that a result is the not-found error.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_not_found( $result, string $context ): void {
		$this->assert_error( $result, 'media_not_found', 404, $context );
		$this->assertSame( 'The requested media was not found.', $result->get_error_message(), "{$context}: the message should be the not-found message." );
	}

	/**
	 * Asserts that a value is an empty object.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $value   The value.
	 * @param string $message The assertion message.
	 */
	protected function assert_empty_object( $value, string $message ): void {
		$this->assertSame( '{}', wp_json_encode( $value ), $message );
	}

	/**
	 * Removes the `description` keywords of a schema at every depth, keeping a property
	 * named `description`, turns objects into arrays and sorts the keys of every map, so
	 * that two schemas compare by content.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $schema The schema.
	 * @return mixed The normalized schema.
	 */
	protected function normalize_schema( $schema ) {
		if ( is_object( $schema ) ) {
			$schema = get_object_vars( $schema );
		}

		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		if ( ! wp_is_numeric_array( $schema ) || empty( $schema ) ) {
			if ( isset( $schema['description'] ) && is_string( $schema['description'] ) ) {
				unset( $schema['description'] );
			}
			ksort( $schema );
		}

		foreach ( $schema as $key => $value ) {
			$schema[ $key ] = $this->normalize_schema( $value );
		}

		return $schema;
	}

	/**
	 * Returns the schema of the `fields` input, which query, upload and update share.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected static function fields_schema(): array {
		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => self::FIELDS,
			),
		);
	}

	/**
	 * Returns the schema of a `{ site, gmt }` date in the output.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	private static function output_date_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'site' => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
				'gmt'  => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
			),
		);
	}

	/**
	 * Returns the schema of a `{ raw, rendered }` text in the output.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	private static function output_text_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'raw'      => array( 'type' => 'string' ),
				'rendered' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Returns the schema of a media item in the output.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected static function item_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id'            => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'string' ),
				'date'          => self::output_date_schema(),
				'modified'      => self::output_date_schema(),
				'slug'          => array( 'type' => 'string' ),
				'link'          => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'title'         => self::output_text_schema(),
				'caption'       => self::output_text_schema(),
				'description'   => self::output_text_schema(),
				'alt_text'      => array( 'type' => 'string' ),
				'media_type'    => array(
					'type' => 'string',
					'enum' => array( 'image', 'file' ),
				),
				'mime_type'     => array( 'type' => 'string' ),
				'source_url'    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'filename'      => array( 'type' => 'string' ),
				'filesize'      => array( 'type' => 'integer' ),
				'media_details' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'width'  => array( 'type' => 'integer' ),
						'height' => array( 'type' => 'integer' ),
						'length' => array( 'type' => 'integer' ),
						'sizes'  => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'width', 'height', 'mime_type', 'source_url' ),
								'properties'           => array(
									'width'      => array( 'type' => 'integer' ),
									'height'     => array( 'type' => 'integer' ),
									'mime_type'  => array( 'type' => 'string' ),
									'source_url' => array(
										'type'   => 'string',
										'format' => 'uri',
									),
								),
							),
						),
					),
				),
				'post'          => array( 'type' => 'integer' ),
				'author'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'id'   => array( 'type' => 'integer' ),
						'name' => array( 'type' => array( 'string', 'null' ) ),
						'slug' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			),
		);
	}

	/**
	 * Returns the schema of the attributes upload and update take.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schemas, keyed by attribute.
	 */
	protected static function attribute_schemas(): array {
		return array(
			'title'       => array( 'type' => 'string' ),
			'caption'     => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'alt_text'    => array( 'type' => 'string' ),
			'slug'        => array(
				'type'      => 'string',
				'minLength' => 1,
			),
			'date'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'minProperties'        => 1,
				'properties'           => array(
					'site' => array(
						'type'   => 'string',
						'format' => 'date-time',
					),
					'gmt'  => array(
						'type'   => 'string',
						'format' => 'date-time',
					),
				),
			),
			'author'      => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'post'        => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
	}

	/**
	 * Returns the schema of a `where` that names one attachment by ID.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected static function where_id_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);
	}

	/**
	 * Asserts the registration of the ability under test: label, category, `public` meta and
	 * annotations.
	 *
	 * @since 7.3.0
	 *
	 * @param string $label       The expected label.
	 * @param bool   $read_only   The expected `readonly` annotation.
	 * @param bool   $destructive The expected `destructive` annotation.
	 * @param bool   $idempotent  The expected `idempotent` annotation.
	 */
	protected function assert_registration( string $label, bool $read_only, bool $destructive, bool $idempotent ): void {
		$this->register_abilities();

		$ability = wp_get_ability( static::ABILITY );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The ability should be registered.' );
		$this->assertSame( $label, $ability->get_label(), 'The label should match.' );
		$this->assertSame( 'content', $ability->get_category(), 'The ability should be in the content category.' );
		$this->assertTrue( $ability->get_meta_item( 'public', false ), 'The ability should be marked public.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertSame( $read_only, $annotations['readonly'] ?? null, 'The readonly annotation should match.' );
		$this->assertSame( $destructive, $annotations['destructive'] ?? null, 'The destructive annotation should match.' );
		$this->assertSame( $idempotent, $annotations['idempotent'] ?? null, 'The idempotent annotation should match.' );
	}

	/**
	 * Returns authors the author may not set.
	 *
	 * @return array<string, array{author: string}> The authors, as fixture names.
	 */
	public static function data_other_authors(): array {
		return array(
			'another user' => array( 'editor' ),
			'missing user' => array( 'missing' ),
		);
	}

	/**
	 * Returns authors below 1.
	 *
	 * @return array<string, array{author: int}> The authors.
	 */
	public static function data_authors_below_one(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -1 ),
		);
	}

	/**
	 * Returns dates, with the stored local and GMT dates in New York in winter.
	 *
	 * @return array<string, array{date: array<string, string>, local: string, gmt: string}> The cases.
	 */
	public static function data_dates(): array {
		return array(
			'site date' => array( array( 'site' => '2099-01-15T10:00:00' ), '2099-01-15 10:00:00', '2099-01-15 15:00:00' ),
			'GMT date'  => array( array( 'gmt' => '2099-01-15T15:00:00' ), '2099-01-15 10:00:00', '2099-01-15 15:00:00' ),
			'both'      => array(
				array(
					'site' => '2099-01-15T10:00:00',
					'gmt'  => '2099-01-20T00:00:00',
				),
				'2099-01-15 10:00:00',
				'2099-01-15 15:00:00',
			),
		);
	}

	/**
	 * Returns save failures with the status of their error.
	 *
	 * @return array<string, array{database_error: bool, status: int}> The cases.
	 */
	public static function data_save_failures(): array {
		return array(
			'empty post'     => array( false, 400 ),
			'database error' => array( true, 500 ),
		);
	}

	/**
	 * Returns callers who may upload media and edit an attachment of the author, with whether
	 * their markup is kept. On single site the editor has `unfiltered_html`; on multisite only
	 * a super admin has it.
	 *
	 * @return array<string, array{role: string, kept: bool, super_admin?: bool}> The cases.
	 */
	public static function data_markup_roles(): array {
		if ( is_multisite() ) {
			return array(
				'editor'      => array( 'editor', false ),
				'author'      => array( 'author', false ),
				'super admin' => array( 'administrator', true, true ),
			);
		}

		return array(
			'editor' => array( 'editor', true ),
			'author' => array( 'author', false ),
		);
	}

	/**
	 * Returns texts that hold backslashes.
	 *
	 * @return array<string, array{text: string}> The texts.
	 */
	public static function data_texts_with_backslashes(): array {
		return array(
			'single backslashes' => array( 'C:\\mdt\\x' ),
			'double backslash'   => array( 'a\\\\b' ),
		);
	}

	/**
	 * Returns parents of a type that cannot hold attachments. Both belong to the editor, so
	 * the author may not edit them.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_parents_of_an_invalid_type(): array {
		return array(
			'revision'   => array( 'revision' ),
			'attachment' => array( 'editor_image' ),
		);
	}

	/**
	 * Returns keys that are not attributes of an attachment.
	 *
	 * @return array<string, array{key: string, value: mixed}> The keys and values.
	 */
	public static function data_attribute_keys_outside_the_allow_list(): array {
		return array(
			'ID'             => array( 'ID', 1 ),
			'import_id'      => array( 'import_id', 7654321 ),
			'status'         => array( 'status', 'private' ),
			'post_status'    => array( 'post_status', 'private' ),
			'post_type'      => array( 'post_type', 'post' ),
			'mime_type'      => array( 'mime_type', 'text/html' ),
			'post_mime_type' => array( 'post_mime_type', 'text/html' ),
			'guid'           => array( 'guid', 'https://example.org/forged' ),
			'password'       => array( 'password', 'secret' ),
			'post_password'  => array( 'post_password', 'secret' ),
			'comment_status' => array( 'comment_status', 'closed' ),
			'ping_status'    => array( 'ping_status', 'closed' ),
			'menu_order'     => array( 'menu_order', 5 ),
			'meta'           => array( 'meta', array( 'mdt_key' => 'value' ) ),
			'meta_input'     => array( 'meta_input', array( 'mdt_key' => 'value' ) ),
			'tax_input'      => array( 'tax_input', array( 'category' => array( 1 ) ) ),
			'post_author'    => array( 'post_author', 1 ),
			'post_parent'    => array( 'post_parent', 1 ),
			'modified'       => array( 'modified', '2001-01-01T00:00:00' ),
		);
	}
}
