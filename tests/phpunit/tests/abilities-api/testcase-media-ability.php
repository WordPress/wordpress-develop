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
	 * The requests the faked HTTP transport received, each with its URL and arguments.
	 *
	 * @since 7.3.0
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	protected $requests = array();

	/**
	 * The files in the temporary folder when the test started.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private $temp_files_at_start = array();

	/**
	 * Files in the temporary folder that tear_down() removes should a call leave them.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	protected $temp_files_to_remove = array();

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

		$this->requests             = array();
		$this->temp_files_to_remove = array();

		$temp_files                = glob( get_temp_dir() . '*' );
		$this->temp_files_at_start = false === $temp_files ? array() : $temp_files;

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
	 * Unregisters the media abilities and the category, removes the files a test added or
	 * left in the temporary folder, and resets the post types.
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

		foreach ( $this->temp_files_to_remove as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		/*
		 * A test may turn off `show_in_rest` on the attachment or post type, and the next class's
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
	 * Registers the media abilities and returns the callbacks of the ability under test.
	 *
	 * The callbacks call private methods, so tests that reach a check behind the permission
	 * callback capture them from the registration arguments.
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
	 * Creates an attachment row without a file whose author is the shared user with the
	 * given role or fixture name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role or fixture name.
	 * @return int The attachment ID.
	 */
	protected function create_attachment_of( string $role ): int {
		return self::factory()->attachment->create_object(
			array(
				'file'           => '2026/01/mdt-own-' . $role . '.jpg',
				'post_author'    => self::$user_ids[ $role ],
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Own ' . $role,
			)
		);
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
	 * Grants `unfiltered_upload` to every user.
	 *
	 * @since 7.3.0
	 */
	protected function grant_unfiltered_upload(): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ): array {
				return 'unfiltered_upload' === $cap ? array( 'exist' ) : $caps;
			},
			10,
			2
		);
	}

	/**
	 * Sets the maximum upload size, after Core's own callbacks on the filter.
	 *
	 * @since 7.3.0
	 *
	 * @param int $bytes The maximum size in bytes; 0 means no limit.
	 */
	protected function set_max_upload_size( int $bytes ): void {
		add_filter(
			'upload_size_limit',
			static function () use ( $bytes ): int {
				return $bytes;
			},
			20
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
	 * Returns the temporary files made for a file name since the test started.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file_name The file name a test sends, with or without its extension.
	 * @return list<string> The temporary file paths.
	 */
	protected function list_temp_files( string $file_name ): array {
		$base  = preg_replace( '/\.[^.]*$/', '', wp_basename( $file_name ) );
		$files = glob( get_temp_dir() . $base . '-*' );

		return false === $files ? array() : array_values( array_diff( $files, $this->temp_files_at_start ) );
	}

	/**
	 * Takes a snapshot of the stored media: the number of posts per type and status, the
	 * number of meta rows, the files under the uploads folder, the temporary files of a file
	 * name and, when a post is given, the row and meta of that post.
	 *
	 * @since 7.3.0
	 *
	 * @param int|null $post_id   Optional. The post ID. Default null.
	 * @param string   $file_name Optional. The file name of the call. Default empty.
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot( ?int $post_id = null, string $file_name = '' ): array {
		global $wpdb;

		$snapshot = array(
			'posts' => $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS total FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A ),
			'meta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'files' => $this->list_upload_files(),
		);

		if ( '' !== $file_name ) {
			$snapshot['temp'] = $this->list_temp_files( $file_name );
		}

		if ( null !== $post_id ) {
			$snapshot['post']      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
			$snapshot['post_meta'] = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A );
		}

		return $snapshot;
	}

	/**
	 * Returns a URL on the site's own host, which the URL check accepts without a lookup.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file_name The file name at the end of the path.
	 * @return string The URL.
	 */
	protected function remote_url( string $file_name ): string {
		return 'http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/mdt-remote/' . $file_name;
	}

	/**
	 * Fakes the HTTP transport. Every request is recorded and answered without the network.
	 *
	 * Each response is keyed by URL and is a `WP_Error`, or an array with any of `file` (a
	 * fixture path written to the download), `body` (a string written to the download),
	 * `code` (default 200), `headers` (default none) and `during` (a callable run while the
	 * download runs). A request for any other URL gets an empty 200 response.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, WP_Error|array<string, mixed>> $responses The responses, keyed by URL.
	 */
	protected function fake_http( array $responses = array() ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $responses ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				$response = $responses[ $url ] ?? array();

				if ( $response instanceof WP_Error ) {
					return $response;
				}

				if ( isset( $response['during'] ) ) {
					call_user_func( $response['during'] );
				}

				$body = null;
				if ( isset( $response['file'] ) ) {
					$body = file_get_contents( $response['file'] );
				} elseif ( isset( $response['body'] ) ) {
					$body = $response['body'];
				}

				if ( null !== $body && ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
					if ( ! empty( $args['limit_response_size'] ) ) {
						$body = substr( $body, 0, (int) $args['limit_response_size'] );
					}

					file_put_contents( $args['filename'], $body );
				}

				$code = $response['code'] ?? 200;

				return array(
					'headers'  => $response['headers'] ?? array(),
					'body'     => '',
					'response' => array(
						'code'    => $code,
						'message' => get_status_header_desc( $code ),
					),
					'cookies'  => array(),
					'filename' => $args['filename'] ?? null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Returns the requests the faked transport received for a URL.
	 *
	 * @since 7.3.0
	 *
	 * @param string $url The URL.
	 * @return list<array<string, mixed>> The arguments of each request.
	 */
	protected function requests_for( string $url ): array {
		$found = array();

		foreach ( $this->requests as $request ) {
			if ( $url === $request['url'] ) {
				$found[] = $request['args'];
			}
		}

		return $found;
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

		if ( ! wp_is_numeric_array( $schema ) || array() === $schema ) {
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
				'media_type'    => array( 'type' => 'string' ),
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
	 * @param int $post_minimum The minimum of `post`.
	 * @return array<string, mixed> The schemas, keyed by attribute.
	 */
	protected static function attribute_schemas( int $post_minimum ): array {
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
				'minimum' => $post_minimum,
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
	 * Returns roles without `upload_files`.
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_roles_without_upload_files(): array {
		return array(
			'logged out'  => array( 'logged_out' ),
			'subscriber'  => array( 'subscriber' ),
			'contributor' => array( 'contributor' ),
			'no caps'     => array( 'no_caps' ),
		);
	}

	/**
	 * Returns roles with `upload_files`.
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_roles_with_upload_files(): array {
		return array(
			'author'          => array( 'author' ),
			'editor'          => array( 'editor' ),
			'administrator'   => array( 'administrator' ),
			'read and upload' => array( 'uploader' ),
		);
	}

	/**
	 * Returns every role of the matrix tests.
	 *
	 * @return array<string, array{role: string}> The roles.
	 */
	public static function data_all_roles(): array {
		return array(
			'logged out'      => array( 'logged_out' ),
			'subscriber'      => array( 'subscriber' ),
			'contributor'     => array( 'contributor' ),
			'author'          => array( 'author' ),
			'editor'          => array( 'editor' ),
			'administrator'   => array( 'administrator' ),
			'read and upload' => array( 'uploader' ),
		);
	}

	/**
	 * Returns posts that are not attachments, and an ID that names nothing.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_ids_that_name_no_attachment(): array {
		return array(
			'post'     => array( 'post' ),
			'page'     => array( 'page' ),
			'revision' => array( 'revision' ),
			'missing'  => array( 'missing' ),
		);
	}

	/**
	 * Returns `where.id` values in forms the integer type accepts, as names for id_in_form().
	 *
	 * @return array<string, array{form: string}> The forms.
	 */
	public static function data_accepted_id_forms(): array {
		return array(
			'integer'              => array( 'integer' ),
			'integer string'       => array( 'string' ),
			'zero-padded string'   => array( 'padded' ),
			'float without a part' => array( 'float' ),
		);
	}

	/**
	 * Returns an ID in one of the forms of data_accepted_id_forms().
	 *
	 * @since 7.3.0
	 *
	 * @param int    $id   The ID.
	 * @param string $form The form.
	 * @return int|string|float The ID in that form.
	 */
	protected function id_in_form( int $id, string $form ) {
		switch ( $form ) {
			case 'string':
				return (string) $id;
			case 'padded':
				return '00' . $id;
			case 'float':
				return (float) $id;
			default:
				return $id;
		}
	}

	/**
	 * Returns `where` values the update and delete gates return false for.
	 *
	 * @return array<string, array{input: mixed}> The inputs.
	 */
	public static function data_inputs_without_a_usable_id(): array {
		return array(
			'no input'                 => array( null ),
			'no where'                 => array( array() ),
			'where string'             => array( array( 'where' => 'x' ) ),
			'no ID'                    => array( array( 'where' => array() ) ),
			'true'                     => array( array( 'where' => array( 'id' => true ) ) ),
			'letters'                  => array( array( 'where' => array( 'id' => 'abc' ) ) ),
			'fraction'                 => array( array( 'where' => array( 'id' => 1.5 ) ) ),
			'fraction string'          => array( array( 'where' => array( 'id' => '1.5' ) ) ),
			'list'                     => array( array( 'where' => array( 'id' => array( 1 ) ) ) ),
			'zero'                     => array( array( 'where' => array( 'id' => 0 ) ) ),
			'zero string'              => array( array( 'where' => array( 'id' => '0' ) ) ),
			'zero-padded zero string'  => array( array( 'where' => array( 'id' => '000' ) ) ),
			'zero float'               => array( array( 'where' => array( 'id' => 0.0 ) ) ),
			'negative'                 => array( array( 'where' => array( 'id' => -1 ) ) ),
			'negative float'           => array( array( 'where' => array( 'id' => -1.0 ) ) ),
			'float above 2 to the 53'  => array( array( 'where' => array( 'id' => 9007199254740994.0 ) ) ),
			'float in exponent format' => array( array( 'where' => array( 'id' => 1e20 ) ) ),
		);
	}

	/**
	 * Returns whether the site can move attachments to the Trash.
	 *
	 * @since 7.3.0
	 *
	 * @return bool Whether attachments can be trashed.
	 */
	protected static function site_can_trash_attachments(): bool {
		return EMPTY_TRASH_DAYS >= 1 && MEDIA_TRASH;
	}
}
