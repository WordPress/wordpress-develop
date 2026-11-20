<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';

/**
 * Tests for the core/media-upload ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaUploadAbility extends WP_Abilities_Media_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-upload';

	/**
	 * The message of a file that is too big for a maximum of 2 KB.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const TOO_BIG_2_KB = 'This file is too big. Files must be less than 2 KB in size.';

	/**
	 * The message of a URL that names no image.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const ONLY_IMAGES = 'Only images can be uploaded from a URL.';

	/**
	 * The message of a URL the URL check refuses.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const INVALID_URL = 'Invalid URL. Provide a valid, publicly reachable HTTP or HTTPS image URL.';

	/**
	 * Core's message for a refused file type.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	private const REFUSED_TYPE = 'Sorry, you are not allowed to upload this file type.';

	/**
	 * Creates the shared fixtures.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::create_shared_fixtures( $factory );
	}

	/**
	 * Returns the path of a test data file.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file The path inside the test data folder.
	 * @return string The full path.
	 */
	private static function fixture( string $file ): string {
		return DIR_TESTDATA . '/' . $file;
	}

	/**
	 * Returns an upload input for a test data file sent as base64.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $file     The path inside the test data folder.
	 * @param string               $filename The file name to send.
	 * @param array<string, mixed> $data     Optional. More attributes. Default empty.
	 * @return array<string, mixed> The input.
	 */
	private function base64_input( string $file, string $filename, array $data = array() ): array {
		$data['file'] = array(
			'base64'   => base64_encode( file_get_contents( self::fixture( $file ) ) ),
			'filename' => $filename,
		);

		return array( 'data' => $data );
	}

	/**
	 * Returns an upload input for a URL.
	 *
	 * @since 7.3.0
	 *
	 * @param string               $url  The URL.
	 * @param array<string, mixed> $data Optional. More attributes. Default empty.
	 * @return array<string, mixed> The input.
	 */
	private function url_input( string $url, array $data = array() ): array {
		$data['file'] = array( 'url' => $url );

		return array( 'data' => $data );
	}

	/**
	 * Asserts that a call succeeded and returns the ID of the new attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 * @return int The attachment ID.
	 */
	private function assert_uploaded( $result, string $context ): int {
		$this->assertNotWPError( $result, "{$context}: the upload should succeed." );
		$this->assertIsArray( $result, "{$context}: the upload should return the item." );
		$this->assertSame( 'attachment', get_post_type( $result['id'] ), "{$context}: the item should be a new attachment." );

		return $result['id'];
	}

	/**
	 * Asserts that a refused call left no temporary file and changed no stored media.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $before  The snapshot before the call.
	 * @param string               $context What the call was, for the assertion messages.
	 */
	private function assert_nothing_changed( array $before, string $context ): void {
		$after = $this->snapshot( null, $before['temp_name'] );

		$this->assertSame( $before['posts'], $after['posts'], "{$context}: no post should be added." );
		$this->assertSame( $before['meta'], $after['meta'], "{$context}: no meta should be added." );
		$this->assertSame( $before['files'], $after['files'], "{$context}: no file should be left in the uploads folder." );
		$this->assertSame( array(), $after['temp'], "{$context}: no temporary file should be left." );
	}

	/**
	 * Takes a snapshot before a call that sends the given file name.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file_name The file name of the call.
	 * @return array<string, mixed> The snapshot.
	 */
	private function snapshot_before( string $file_name ): array {
		$snapshot              = $this->snapshot( null, $file_name );
		$snapshot['temp_name'] = $file_name;

		return $snapshot;
	}

	/**
	 * The ability is registered with its label, category, meta and annotations.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_registers_the_upload_ability(): void {
		$this->assert_registration( 'Upload Media', false, false, false );
	}

	/**
	 * The input and output schemas are the published ones.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );

		$file = array(
			'anyOf' => array(
				array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'url' ),
					'properties'           => array(
						'url' => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'base64', 'filename' ),
					'properties'           => array(
						'base64'   => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'filename' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
				),
			),
		);

		$expected_input = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'file' ),
					'properties'           => array_merge( array( 'file' => $file ), self::attribute_schemas( 1 ) ),
				),
				'fields' => self::fields_schema(),
			),
		);

		$this->assertSame( $this->normalize_schema( $expected_input ), $this->normalize_schema( $ability->get_input_schema() ), 'The input schema should match.' );
		$this->assertSame( $this->normalize_schema( self::item_schema() ), $this->normalize_schema( $ability->get_output_schema() ), 'The output schema should match.' );
	}

	/**
	 * The permission callback passes for a caller with `upload_files`.
	 *
	 * @ticket 66719
	 * @dataProvider data_roles_with_upload_files
	 * @since 7.3.0
	 *
	 * @param string $role The role.
	 */
	public function test_permission_callback_passes_with_upload_files( string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$this->assertTrue( wp_get_ability( self::ABILITY )->check_permissions( $this->url_input( $this->remote_url( 'mdt-gate.jpg' ) ) ), 'A caller with upload_files should pass.' );
	}

	/**
	 * The permission callback refuses a caller without `upload_files`.
	 *
	 * @ticket 66719
	 * @dataProvider data_roles_without_upload_files
	 * @since 7.3.0
	 *
	 * @param string $role The role.
	 */
	public function test_permission_callback_refuses_without_upload_files( string $role ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$this->assertFalse( wp_get_ability( self::ABILITY )->check_permissions( $this->url_input( $this->remote_url( 'mdt-gate.jpg' ) ) ), 'A caller without upload_files should be refused.' );
	}

	/**
	 * The permission callback refuses an administrator when the attachment type has
	 * `show_in_rest` off, and the call is refused with the generic error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_when_attachments_are_hidden(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();
		$this->fake_http();

		get_post_type_object( 'attachment' )->show_in_rest = false;

		$this->assertFalse( wp_get_ability( self::ABILITY )->check_permissions( $this->url_input( $this->remote_url( 'mdt-hidden.jpg' ) ) ), 'The administrator should be refused.' );
		$this->assert_refused( $this->run_ability( $this->url_input( $this->remote_url( 'mdt-hidden.jpg' ) ) ), 'An upload while attachments are hidden' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * A refused caller gets the generic error, and the server requests nothing.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_refused_caller_gets_the_generic_error(): void {
		$this->login_as( 'subscriber' );
		$this->register_abilities();
		$this->fake_http();

		$this->assert_refused( $this->run_ability( $this->url_input( $this->remote_url( 'mdt-refused.jpg' ) ) ), 'An upload by a subscriber' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Returns callers without `upload_files`, with the status they get.
	 *
	 * @return array<string, array{role: string, status: int}> The cases.
	 */
	public static function data_callers_without_upload_files(): array {
		$cases = array();

		foreach ( self::data_roles_without_upload_files() as $name => $row ) {
			$cases[ $name ] = array( $row[0], 'logged_out' === $row[0] ? 401 : 403 );
		}

		return $cases;
	}

	/**
	 * Without `upload_files` the callback refuses the call before anything else and before
	 * any request.
	 *
	 * @ticket 66719
	 * @dataProvider data_callers_without_upload_files
	 * @since 7.3.0
	 *
	 * @param string $role   The role.
	 * @param int    $status The expected status.
	 */
	public function test_callback_refuses_without_upload_files( string $role, int $status ): void {
		$this->login_as( $role );
		$this->register_abilities();
		$this->force_permission();
		$this->skip_input_validation();
		$this->fake_http();

		$before = $this->snapshot_before( 'mdt-no-upload-files.jpg' );
		$result = $this->run_ability( $this->url_input( $this->remote_url( 'mdt-no-upload-files.jpg' ), array( 'author' => 0 ) ) );

		$this->assert_error( $result, 'media_cannot_upload', $status, 'An upload without upload_files' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to upload media on this site.', 'An upload without upload_files' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
		$this->assert_nothing_changed( $before, 'An upload without upload_files' );
	}

	/**
	 * The attributes are checked in the order author, post, date, slug, before the file.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attribute_checks_run_in_order_before_the_file(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();
		$this->fake_http();

		$data = array(
			'author' => 0,
			'post'   => 0,
			'date'   => array(),
			'slug'   => '!!!',
		);
		$url  = $this->remote_url( 'mdt-order.jpg' );

		$this->assert_error_with_params( $this->run_ability( $this->url_input( $url, $data ) ), 'media_invalid_attribute', 'data[author]', 'Every attribute invalid' );

		unset( $data['author'] );
		$this->assert_error_with_params( $this->run_ability( $this->url_input( $url, $data ) ), 'media_invalid_attribute', 'data[post]', 'Post, date and slug invalid' );

		unset( $data['post'] );
		$this->assert_error_with_params( $this->run_ability( $this->url_input( $url, $data ) ), 'media_invalid_attribute', 'data[date]', 'Date and slug invalid' );

		unset( $data['date'] );
		$this->assert_error_with_params( $this->run_ability( $this->url_input( $url, $data ) ), 'media_invalid_attribute', 'data[slug]', 'Slug invalid' );

		$this->assertSame( array(), $this->requests, 'No request should be made while an attribute is invalid.' );
	}

	/**
	 * A missing file is an invalid file, checked after the attributes.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_missing_file_is_checked_after_the_attributes(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$this->assert_error_with_params( $this->run_ability( array( 'data' => array( 'author' => 0 ) ) ), 'media_invalid_attribute', 'data[author]', 'A missing file next to an invalid author' );
		$this->assert_error_with_params( $this->run_ability( array( 'data' => array( 'title' => 'MDT no file' ) ) ), 'media_invalid_file', 'data[file]', 'A missing file' );
	}

	/**
	 * The caller's own ID is allowed as the author.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_own_author_id_is_allowed(): void {
		$this->login_as( 'uploader' );
		$this->register_abilities();

		$id = $this->assert_uploaded(
			$this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-own-author.png', array( 'author' => self::$user_ids['uploader'] ) ) ),
			'An upload as the caller'
		);

		$this->assertSame( self::$user_ids['uploader'], (int) get_post_field( 'post_author', $id ), 'The caller should be the author.' );
	}

	/**
	 * A listener on the new attachment sees the caller as the current user, and a callback
	 * on the attachment data runs during the upload and stays registered.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_upload_runs_as_the_caller(): void {
		$added_by   = array();
		$data_calls = 0;
		$callback   = static function ( array $data ) use ( &$data_calls ): array {
			++$data_calls;
			return $data;
		};
		add_action(
			'add_attachment',
			static function () use ( &$added_by ): void {
				$added_by[] = get_current_user_id();
			}
		);
		add_filter( 'wp_insert_attachment_data', $callback );

		$this->login_as( 'uploader' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-caller-upload.png' ) ), 'An upload by the uploader' );

		$this->assertSame( array( self::$user_ids['uploader'] ), $added_by, 'The listener should see the caller as the current user.' );
		$this->assertGreaterThan( 0, $data_calls, 'The attachment data callback should run during the upload.' );
		$this->assertNotFalse( has_filter( 'wp_insert_attachment_data', $callback ), 'The attachment data callback should stay registered.' );
	}

	/**
	 * Returns authors a caller without `edit_others_posts` may not set.
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
	 * Another author needs `edit_others_posts`, checked before the user is looked up.
	 *
	 * @ticket 66719
	 * @dataProvider data_other_authors
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_other_author_needs_edit_others_posts( string $author ): void {
		$this->login_as( 'uploader' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-other-author.png' );
		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-other-author.png', array( 'author' => self::$user_ids[ $author ] ?? self::MISSING_ID ) ) );

		$this->assert_error( $result, 'media_cannot_edit_others', 403, 'An upload as another author' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to create posts as this user.', 'An upload as another author' );
		$this->assert_nothing_changed( $before, 'An upload as another author' );
	}

	/**
	 * An author that names no user is an invalid author for a caller who may set authors.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_missing_author_is_invalid(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-missing-author.png', array( 'author' => self::MISSING_ID ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'A missing author' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'A missing author' );
	}

	/**
	 * Returns parents the uploader may not attach to.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_parents_the_uploader_may_not_use(): array {
		return array(
			'post of another user' => array( 'post' ),
			'missing post'         => array( 'missing' ),
		);
	}

	/**
	 * A parent needs `edit_post` on it, checked before it is looked up.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_the_uploader_may_not_use
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_needs_edit_post( string $post ): void {
		$this->login_as( 'uploader' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-forbidden-parent.png' );
		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-forbidden-parent.png', array( 'post' => self::$post_ids[ $post ] ?? self::MISSING_ID ) ) );

		$this->assert_error( $result, 'media_cannot_attach', 403, 'An upload to a forbidden parent' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to upload media to this post.', 'An upload to a forbidden parent' );
		$this->assert_nothing_changed( $before, 'An upload to a forbidden parent' );
	}

	/**
	 * Returns parents of a type that cannot hold attachments.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_parents_of_an_invalid_type(): array {
		return array(
			'revision'   => array( 'revision' ),
			'attachment' => array( 'unattached' ),
		);
	}

	/**
	 * A revision or an attachment cannot be the parent.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_of_an_invalid_type( string $post ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-invalid-parent.png', array( 'post' => self::$post_ids[ $post ] ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[post]', 'A parent of an invalid type' );
		$this->assert_error_message( $result, 'Invalid parent type.', 'A parent of an invalid type' );
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
	 * An author below 1 is invalid before the capability to set another author is checked.
	 *
	 * @ticket 66719
	 * @dataProvider data_authors_below_one
	 * @since 7.3.0
	 *
	 * @param int $author The author.
	 */
	public function test_author_below_one_is_invalid_before_the_capability_check( int $author ): void {
		$this->login_as( 'author' );
		$this->register_abilities();
		$this->skip_input_validation();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-author-below-one.png', array( 'author' => $author ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'An author below 1' );
	}

	/**
	 * Returns posts of another user of a type that cannot hold attachments.
	 *
	 * @return array<string, array{post: string}> The posts, as fixture names.
	 */
	public static function data_other_users_parents_of_an_invalid_type(): array {
		return array(
			'revision of a post of another user' => array( 'revision' ),
			'attachment of another user'         => array( 'editor_image' ),
		);
	}

	/**
	 * A parent of an invalid type that the caller may not edit is refused as a missing post
	 * is, before its type is checked.
	 *
	 * @ticket 66719
	 * @dataProvider data_other_users_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_capability_is_checked_before_the_type( string $post ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$missing = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-parent-order-missing.png', array( 'post' => self::MISSING_ID ) ) );
		$result  = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-parent-order.png', array( 'post' => self::$post_ids[ $post ] ) ) );

		$this->assert_error( $missing, 'media_cannot_attach', 403, 'An upload to a missing post' );
		$this->assert_error( $result, 'media_cannot_attach', 403, 'An upload to a forbidden parent of an invalid type' );
		$this->assert_error_message( $result, 'Sorry, you are not allowed to upload media to this post.', 'An upload to a forbidden parent of an invalid type' );
		$this->assertSame( $missing->get_error_message(), $result->get_error_message(), 'The message should be the one of a missing post.' );
		$this->assertSame( $missing->get_error_data(), $result->get_error_data(), 'The error data should be the one of a missing post.' );
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
	 * The site date is read in the site's time zone and the GMT date in UTC; the site date
	 * wins when both are sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_dates
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date  The date input.
	 * @param string                $local The expected stored local date.
	 * @param string                $gmt   The expected stored GMT date.
	 */
	public function test_date( array $date, string $local, string $gmt ): void {
		update_option( 'timezone_string', 'America/New_York' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-date.png', array( 'date' => $date ) ) ), 'An upload with a date' );

		$this->assertSame( $local, get_post_field( 'post_date', $id ), 'The local date should be stored.' );
		$this->assertSame( $gmt, get_post_field( 'post_date_gmt', $id ), 'The GMT date should be stored.' );
	}

	/**
	 * A date Core cannot parse is an invalid date.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unparsable_date_is_invalid(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-bad-date.png', array( 'date' => array( 'site' => 'not a date' ) ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[date]', 'An unparsable date' );
	}

	/**
	 * A slug with nothing left after sanitizing is invalid; a taken slug is made unique.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_slug(): void {
		self::factory()->post->create( array( 'post_name' => 'mdt-taken-slug' ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_error_with_params(
			$this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-empty-slug.png', array( 'slug' => '!!!' ) ) ),
			'media_invalid_attribute',
			'data[slug]',
			'A slug with nothing left'
		);

		$id   = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-taken.png', array( 'slug' => 'MDT-Taken-Slug' ) ) ), 'An upload with a taken slug' );
		$slug = get_post_field( 'post_name', $id );

		$this->assertNotSame( 'mdt-taken-slug', $slug, 'The taken slug should be made unique.' );
		$this->assertStringStartsWith( 'mdt-taken-slug', $slug, 'The unique slug should start with the sanitized slug.' );
	}

	/**
	 * An image from a URL is stored with the attributes sent, and the request is the one
	 * the download should make.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_upload_from_a_url(): void {
		$url = $this->remote_url( 'mdt-team-photo.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );
		$this->set_max_upload_size( MB_IN_BYTES );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'file'     => array( 'url' => $url ),
					'title'    => 'Team photo',
					'alt_text' => 'The team on the roof',
					'post'     => self::$post_ids['post'],
				),
				'fields' => array( 'title.rendered', 'alt_text', 'mime_type', 'source_url', 'post' ),
			)
		);

		$id = $this->assert_uploaded( $result, 'An upload from a URL' );

		$this->assertSame( 'Team photo', $result['title']['rendered'], 'The title should be the one sent.' );
		$this->assertSame( 'The team on the roof', $result['alt_text'], 'The alt text should be the one sent.' );
		$this->assertSame( 'image/jpeg', $result['mime_type'], 'The MIME type should be the stored one.' );
		$this->assertStringEndsWith( '/mdt-team-photo.jpg', $result['source_url'], 'The file should be stored under its name.' );
		$this->assertSame( self::$post_ids['post'], $result['post'], 'The parent should be the post sent.' );
		$this->assertSame( array(), $this->list_temp_files( 'mdt-team-photo.jpg' ), 'No temporary file should be left.' );

		$requests = $this->requests_for( $url );
		$this->assertCount( 1, $requests, 'The URL should be requested once.' );
		$this->assertSame( MB_IN_BYTES + 1, $requests[0]['limit_response_size'], 'The response should be limited to the maximum size plus one byte.' );
		$this->assertSame( 300, $requests[0]['timeout'], 'The request should have a timeout of 300 seconds.' );
		$this->assertTrue( $requests[0]['reject_unsafe_urls'], 'The request should refuse unsafe URLs.' );
		$this->assertSame( $id, attachment_url_to_postid( $result['source_url'] ), 'The stored file should belong to the attachment.' );
	}

	/**
	 * A request for another URL never has the limit, during or after the download, also after
	 * a failed one.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_request_limit_applies_to_the_url_only(): void {
		$url     = $this->remote_url( 'mdt-limit.jpg' );
		$failing = $this->remote_url( 'mdt-limit-failing.jpg' );
		$other   = $this->remote_url( 'mdt-limit-other' );

		$this->fake_http(
			array(
				$url     => array(
					'file'   => self::fixture( 'images/canola.jpg' ),
					'during' => static function () use ( $other ): void {
						wp_remote_get( $other );
					},
				),
				$failing => new WP_Error( 'http_request_failed', 'MDT failure' ),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->set_max_upload_size( MB_IN_BYTES );
		$this->assert_uploaded( $this->run_ability( $this->url_input( $url ) ), 'An upload with a maximum size' );
		wp_remote_get( $other );

		$this->assert_error( $this->run_ability( $this->url_input( $failing ) ), 'media_download_failed', 500, 'A failed download' );
		wp_remote_get( $other );

		$other_requests = $this->requests_for( $other );
		$this->assertCount( 3, $other_requests, 'The other URL should be requested three times.' );
		$this->assertNull( $other_requests[0]['limit_response_size'], 'A request for another URL during the download should have no limit.' );
		$this->assertNull( $other_requests[1]['limit_response_size'], 'A request for another URL after the download should have no limit.' );
		$this->assertNull( $other_requests[2]['limit_response_size'], 'A request for another URL after a failed download should have no limit.' );
		$this->assertSame( MB_IN_BYTES + 1, $this->requests_for( $failing )[0]['limit_response_size'], 'The failing request should have the limit.' );
	}

	/**
	 * Returns downloads that return and that fail.
	 *
	 * @return array<string, array{fails: bool}> The cases.
	 */
	public static function data_returned_and_failed_downloads(): array {
		return array(
			'returned' => array( false ),
			'failed'   => array( true ),
		);
	}

	/**
	 * Nothing of the limit is left after the call: a later request for the very same URL has
	 * no limit, and the request arguments filter has the callbacks it had before.
	 *
	 * @ticket 66719
	 * @dataProvider data_returned_and_failed_downloads
	 * @since 7.3.0
	 *
	 * @param bool $fails Whether the download fails.
	 */
	public function test_no_limit_is_left_for_the_same_url( bool $fails ): void {
		$name = $fails ? 'mdt-limit-same-failed.jpg' : 'mdt-limit-same.jpg';
		$url  = $this->remote_url( $name );
		$this->fake_http( array( $url => $fails ? new WP_Error( 'http_request_failed', 'MDT failure' ) : array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );
		$this->set_max_upload_size( MB_IN_BYTES );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$filter_before = has_filter( 'http_request_args' );
		$result        = $this->run_ability( $this->url_input( $url ) );
		$filter_after  = has_filter( 'http_request_args' );

		wp_remote_get( $url );
		$requests = $this->requests_for( $url );

		if ( $fails ) {
			$this->assert_error( $result, 'media_download_failed', 500, 'A failed download' );
		} else {
			$this->assert_uploaded( $result, 'A download that returned' );
		}

		$this->assertSame( $filter_before, $filter_after, 'The request arguments filter should have the callbacks it had before the call.' );
		$this->assertCount( 2, $requests, 'The URL should be requested twice.' );
		$this->assertSame( MB_IN_BYTES + 1, $requests[0]['limit_response_size'], 'The request of the call should have the limit.' );
		$this->assertNull( $requests[1]['limit_response_size'], 'A later request for the same URL should have no limit.' );
	}

	/**
	 * Returns how a file is sent, with each maximum size that means no limit.
	 *
	 * @return array<string, array{path: string, maximum: int}> The cases.
	 */
	public static function data_file_paths_without_a_maximum(): array {
		return array(
			'URL, maximum of 0'     => array( 'url', 0 ),
			'URL, maximum of -1'    => array( 'url', -1 ),
			'base64, maximum of 0'  => array( 'base64', 0 ),
			'base64, maximum of -1' => array( 'base64', -1 ),
		);
	}

	/**
	 * With a maximum size of 0 or less the request has no limit and a file of any size is
	 * stored.
	 *
	 * @ticket 66719
	 * @dataProvider data_file_paths_without_a_maximum
	 * @since 7.3.0
	 *
	 * @param string $path    How the file is sent.
	 * @param int    $maximum The maximum size.
	 */
	public function test_maximum_of_zero_or_less_sets_no_limit( string $path, int $maximum ): void {
		$name = "mdt-unlimited-{$path}.jpg";
		$url  = $this->remote_url( $name );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );
		$this->set_max_upload_size( $maximum );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( 'url' === $path ? $this->url_input( $url ) : $this->base64_input( 'images/canola.jpg', $name ) ), 'An upload without a maximum size' );

		if ( 'url' === $path ) {
			$this->assertNull( $this->requests_for( $url )[0]['limit_response_size'], 'The request should have no limit.' );
		}
	}

	/**
	 * Returns URL forms that arrive repaired, as the text in front of the host and the file
	 * name of the URL.
	 *
	 * @return array<string, array{prefix: string, file_name: string}> The cases.
	 */
	public static function data_repaired_url_forms(): array {
		return array(
			'no scheme'      => array( '', 'mdt-schemeless.jpg' ),
			'space in front' => array( ' http://', 'mdt-repaired-space.jpg' ),
			'http;//'        => array( 'http;//', 'mdt-repaired-semicolon.jpg' ),
		);
	}

	/**
	 * A URL without a scheme, with a space in front or with `http;//` is requested as the
	 * HTTP URL, and only that URL is requested.
	 *
	 * @ticket 66719
	 * @dataProvider data_repaired_url_forms
	 * @since 7.3.0
	 *
	 * @param string $prefix    The text sent in front of the host.
	 * @param string $file_name The file name of the URL.
	 */
	public function test_repaired_url_form_is_requested_over_http( string $prefix, string $file_name ): void {
		$url = $this->remote_url( $file_name );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->url_input( $prefix . substr( $url, strlen( 'http://' ) ) ) ), 'An upload from a repaired URL form' );
		$this->assertCount( 1, $this->requests, 'Exactly one request should be made.' );
		$this->assertSame( $url, $this->requests[0]['url'], 'The request should be for the HTTP URL.' );
	}

	/**
	 * The file name is taken from the URL's path without percent-decoding.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_file_name_is_not_percent_decoded(): void {
		$url = $this->remote_url( 'mdt%20encoded.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array( 'file' => array( 'url' => $url ) ),
				'fields' => array( 'source_url' ),
			)
		);

		$this->assert_uploaded( $result, 'An upload from a URL with an encoded name' );
		$this->assertStringEndsWith( '/mdt20encoded.jpg', $result['source_url'], 'The encoded name should be sanitized, not decoded.' );
	}

	/**
	 * Returns URL paths without a file name.
	 *
	 * @return array<string, array{path: string}> The paths.
	 */
	public static function data_url_paths_without_a_name(): array {
		return array(
			'empty path' => array( '' ),
			'slash'      => array( '/' ),
		);
	}

	/**
	 * A URL without a file name is an invalid file, and nothing is requested.
	 *
	 * @ticket 66719
	 * @dataProvider data_url_paths_without_a_name
	 * @since 7.3.0
	 *
	 * @param string $path The URL's path.
	 */
	public function test_url_without_a_file_name( string $path ): void {
		$this->fake_http();
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->url_input( 'http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . $path ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A URL without a file name' );
		$this->assert_error_message( $result, 'Could not determine a filename from the provided URL.', 'A URL without a file name' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Returns URL file names that name no image.
	 *
	 * @return array<string, array{name: string}> The names.
	 */
	public static function data_url_names_that_are_not_images(): array {
		return array(
			'PDF'               => array( 'mdt-document.pdf' ),
			'PHP'               => array( 'mdt-script.php' ),
			'SVG'               => array( 'mdt-drawing.svg' ),
			'HTML'              => array( 'mdt-page.html' ),
			'no extension'      => array( 'mdt-no-extension' ),
			'folder with slash' => array( 'mdt-folder/' ),
		);
	}

	/**
	 * A URL whose name does not end in an image extension is refused before any request.
	 *
	 * @ticket 66719
	 * @dataProvider data_url_names_that_are_not_images
	 * @since 7.3.0
	 *
	 * @param string $name The file name.
	 */
	public function test_url_name_that_is_not_an_image( string $name ): void {
		$this->fake_http();
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->url_input( $this->remote_url( $name ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A URL that names no image' );
		$this->assert_error_message( $result, self::ONLY_IMAGES, 'A URL that names no image' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Returns URLs the URL check refuses.
	 *
	 * @return array<string, array{url: string}> The URLs, with `{host}` for the site's host.
	 */
	public static function data_refused_urls(): array {
		return array(
			'loopback'          => array( 'http://127.0.0.1/mdt-refused.jpg' ),
			'private 10'        => array( 'http://10.0.0.1/mdt-refused.jpg' ),
			'private 192.168'   => array( 'http://192.168.1.1/mdt-refused.jpg' ),
			'private 172.16'    => array( 'http://172.16.0.1/mdt-refused.jpg' ),
			'link-local'        => array( 'http://169.254.169.254/mdt-refused.jpg' ),
			'user and password' => array( 'http://user:pass@{host}/mdt-refused.jpg' ),
			'port 22'           => array( 'http://{host}:22/mdt-refused.jpg' ),
			'file scheme'       => array( 'file:///etc/passwd' ),
			'FTP scheme'        => array( 'ftp://{host}/mdt-refused.jpg' ),
		);
	}

	/**
	 * A URL the URL check refuses is an invalid file, and nothing is requested.
	 *
	 * @ticket 66719
	 * @dataProvider data_refused_urls
	 * @since 7.3.0
	 *
	 * @param string $url The URL, with `{host}` for the site's host.
	 */
	public function test_refused_url( string $url ): void {
		$this->fake_http();
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->url_input( str_replace( '{host}', wp_parse_url( home_url(), PHP_URL_HOST ), $url ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A refused URL' );
		$this->assert_error_message( $result, self::INVALID_URL, 'A refused URL' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Returns failed downloads.
	 *
	 * @return array<string, array{response: string}> The responses, as case names.
	 */
	public static function data_failed_downloads(): array {
		return array(
			'request error'           => array( 'error' ),
			'request refused, unsafe' => array( 'unsafe' ),
			'not found'               => array( 'not_found' ),
			'server error'            => array( 'server_error' ),
		);
	}

	/**
	 * A failed download gives the fixed error, with nothing of the answer in it, and leaves
	 * no temporary file.
	 *
	 * @ticket 66719
	 * @dataProvider data_failed_downloads
	 * @since 7.3.0
	 *
	 * @param string $response The response, as a case name.
	 */
	public function test_failed_download( string $response ): void {
		$responses = array(
			'error'        => new WP_Error( 'http_request_failed', 'MDT secret error' ),
			'unsafe'       => new WP_Error( 'http_request_failed', 'A valid URL was not provided.' ),
			'not_found'    => array(
				'code'    => 404,
				'body'    => 'MDT secret body',
				'headers' => array( 'x-mdt-secret' => 'MDT secret header' ),
			),
			'server_error' => array(
				'code' => 500,
				'body' => 'MDT secret body',
			),
		);

		$url = $this->remote_url( 'mdt-failed-download.jpg' );
		$this->fake_http( array( $url => $responses[ $response ] ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-failed-download.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error( $result, 'media_download_failed', 500, 'A failed download' );
		$this->assert_error_message( $result, 'Download failed.', 'A failed download' );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data(), 'The error data should hold only the status.' );
		$this->assertStringNotContainsString( 'MDT secret', wp_json_encode( $result->get_error_messages() ), 'No message should hold anything of the answer.' );
		$this->assert_nothing_changed( $before, 'A failed download' );
	}

	/**
	 * Returns headers of a downloaded answer, with the URL's file name and the file name the
	 * headers give, if any.
	 *
	 * @return array<string, array{file_name: string, headers: array<string, string>, header_name: string}> The cases.
	 */
	public static function data_answer_headers(): array {
		return array(
			'content type'        => array(
				'mdt-headers-type.jpg',
				array( 'content-type' => 'image/png' ),
				'',
			),
			'content disposition' => array(
				'mdt-headers-disposition.jpg',
				array( 'Content-Disposition' => 'attachment; filename=mdt-headers-disposition-answer.png' ),
				'mdt-headers-disposition-answer.png',
			),
			'both'                => array(
				'mdt-headers-both.jpg',
				array(
					'Content-Disposition' => 'attachment; filename=mdt-headers-both-answer.png',
					'content-type'        => 'image/png',
				),
				'mdt-headers-both-answer.png',
			),
		);
	}

	/**
	 * The file downloaded from a URL is stored under the URL's file name and with the type
	 * of its content, whatever the headers of the answer say, and no temporary file is left.
	 *
	 * @ticket 66719
	 * @dataProvider data_answer_headers
	 * @since 7.3.0
	 *
	 * @param string                $file_name   The file name at the end of the URL.
	 * @param array<string, string> $headers     The headers of the answer.
	 * @param string                $header_name The file name the headers give, or empty.
	 */
	public function test_download_is_named_after_the_url( string $file_name, array $headers, string $header_name ): void {
		$header_file = '' === $header_name ? '' : get_temp_dir() . $header_name;

		if ( '' !== $header_file ) {
			$this->temp_files_to_remove[] = $header_file;

			if ( file_exists( $header_file ) ) {
				unlink( $header_file );
			}

			$this->assertFileDoesNotExist( $header_file, 'No file with the name from the headers should be in the temporary folder before the call.' );
		}

		$url = $this->remote_url( $file_name );
		$this->fake_http(
			array(
				$url => array(
					'file'    => self::fixture( 'images/canola.jpg' ),
					'headers' => $headers,
				),
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array( 'file' => array( 'url' => $url ) ),
				'fields' => array( 'id', 'filename', 'mime_type' ),
			)
		);

		$this->assert_uploaded( $result, 'An upload from a URL whose answer has headers' );
		$this->assertSame( $file_name, $result['filename'], 'The stored file should be named after the URL.' );
		$this->assertSame( 'image/jpeg', $result['mime_type'], 'The type should come from the file and the URL.' );
		$this->assertSame( array(), $this->list_temp_files( $file_name ), 'No temporary file should be left.' );

		if ( '' !== $header_file ) {
			$this->assertFileDoesNotExist( $header_file, 'No file with the name from the headers should be left in the temporary folder.' );
		}
	}

	/**
	 * A downloaded file larger than the maximum size is refused and removed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_downloaded_file_larger_than_the_maximum(): void {
		$url = $this->remote_url( 'mdt-too-big.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );
		$this->set_max_upload_size( 2048 );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-too-big.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A downloaded file that is too big' );
		$this->assert_error_message( $result, self::TOO_BIG_2_KB, 'A downloaded file that is too big' );
		$this->assert_nothing_changed( $before, 'A downloaded file that is too big' );
	}

	/**
	 * Returns downloads under an image name whose content is no image.
	 *
	 * @return array<string, array{name: string, content: string, unfiltered: bool}> The cases.
	 */
	public static function data_downloads_that_are_not_images(): array {
		return array(
			'text'                         => array( 'mdt-text.jpg', 'text', false ),
			'SVG'                          => array( 'mdt-svg.jpg', 'svg', false ),
			'HTML'                         => array( 'mdt-html.jpg', 'html', false ),
			'text, with unfiltered_upload' => array( 'mdt-text-unfiltered.jpg', 'text', true ),
			'SVG, with unfiltered_upload'  => array( 'mdt-svg-unfiltered.jpg', 'svg', true ),
		);
	}

	/**
	 * A downloaded file whose content is no image is refused, also for a caller with
	 * `unfiltered_upload`, and removed.
	 *
	 * @ticket 66719
	 * @requires extension fileinfo
	 * @dataProvider data_downloads_that_are_not_images
	 * @since 7.3.0
	 *
	 * @param string $name       The file name.
	 * @param string $content    The content, as a case name.
	 * @param bool   $unfiltered Whether the caller has `unfiltered_upload`.
	 */
	public function test_download_that_is_not_an_image( string $name, string $content, bool $unfiltered ): void {
		$contents = array(
			'text' => array( 'body' => "MDT plain text that is not an image.\n" ),
			'svg'  => array( 'file' => self::fixture( 'uploads/video-play.svg' ) ),
			'html' => array( 'body' => "<!DOCTYPE html>\n<html><head><title>MDT</title></head><body><p>MDT page</p></body></html>\n" ),
		);

		$url = $this->remote_url( $name );
		$this->fake_http( array( $url => $contents[ $content ] ) );

		if ( $unfiltered ) {
			$this->grant_unfiltered_upload();
		}

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( $name );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A download that is not an image' );
		$this->assert_error_message( $result, self::ONLY_IMAGES, 'A download that is not an image' );
		$this->assert_nothing_changed( $before, 'A download that is not an image' );
	}

	/**
	 * A downloaded file that Core's file check answers with a type that is not an image is
	 * refused and removed.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_download_the_file_check_types_as_no_image(): void {
		$url = $this->remote_url( 'mdt-checked-as-pdf.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		add_filter(
			'wp_check_filetype_and_ext',
			static function ( array $data, $file, $filename ): array {
				if ( 'mdt-checked-as-pdf.jpg' === $filename ) {
					$data['ext']  = 'pdf';
					$data['type'] = 'application/pdf';
				}

				return $data;
			},
			10,
			3
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-checked-as-pdf.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A download the file check types as a PDF' );
		$this->assert_error_message( $result, self::ONLY_IMAGES, 'A download the file check types as a PDF' );
		$this->assert_nothing_changed( $before, 'A download the file check types as a PDF' );
	}

	/**
	 * An image whose content is another image type than its name says is stored under the
	 * corrected extension.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_download_with_a_mismatched_extension_is_stored_under_the_corrected_one(): void {
		$url = $this->remote_url( 'mdt-mismatch.png' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/test-image-mime-jpg.png' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array( 'file' => array( 'url' => $url ) ),
				'fields' => array( 'mime_type', 'source_url' ),
			)
		);

		$this->assert_uploaded( $result, 'A download with a mismatched extension' );
		$this->assertSame( 'image/jpeg', $result['mime_type'], 'The MIME type should be the one of the content.' );
		$this->assertStringEndsWith( '/mdt-mismatch.jpg', $result['source_url'], 'The file should be stored under the corrected extension.' );
	}

	/**
	 * A type Core's upload handling refuses for a downloaded file is an invalid file, and
	 * the file Core's handling gets has an empty type.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_type_refused_by_the_upload_handling_of_a_download(): void {
		$url = $this->remote_url( 'mdt-refused-type.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$calls = 0;
		add_filter(
			'wp_check_filetype_and_ext',
			static function ( array $data ) use ( &$calls ): array {
				++$calls;

				if ( 1 === $calls ) {
					return $data;
				}

				return array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				);
			}
		);

		$files = array();
		add_filter(
			'wp_handle_sideload_prefilter',
			static function ( array $file ) use ( &$files ): array {
				$files[] = $file;
				return $file;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-refused-type.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A type refused by the upload handling' );
		$this->assert_error_message( $result, self::REFUSED_TYPE, 'A type refused by the upload handling' );
		$this->assert_nothing_changed( $before, 'A type refused by the upload handling' );
		$this->assertCount( 1, $files, 'The upload handling should get the file once.' );
		$this->assertSame( 'mdt-refused-type.jpg', $files[0]['name'], 'The upload handling should get the name from the URL.' );
		$this->assertSame( '', $files[0]['type'], 'The upload handling should get an empty type.' );
	}

	/**
	 * Returns how a file is sent.
	 *
	 * @return array<string, array{path: string}> The paths.
	 */
	public static function data_file_paths(): array {
		return array(
			'URL'    => array( 'url' ),
			'base64' => array( 'base64' ),
		);
	}

	/**
	 * Another error of Core's upload handling is an upload failure with Core's message, and
	 * the temporary file is removed.
	 *
	 * @ticket 66719
	 * @dataProvider data_file_paths
	 * @since 7.3.0
	 *
	 * @param string $path How the file is sent.
	 */
	public function test_other_error_of_the_upload_handling( string $path ): void {
		$name = "mdt-prefilter-{$path}.jpg";
		$url  = $this->remote_url( $name );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		add_filter(
			'wp_handle_sideload_prefilter',
			static function ( array $file ): array {
				$file['error'] = 'MDT prefilter failure.';
				return $file;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = 'url' === $path ? $this->url_input( $url ) : $this->base64_input( 'images/canola.jpg', $name );
		$before = $this->snapshot_before( $name );
		$result = $this->run_ability( $input );

		$this->assert_error( $result, 'media_upload_failed', 500, 'An error of the upload handling' );
		$this->assert_error_message( $result, 'MDT prefilter failure.', 'An error of the upload handling' );
		$this->assert_nothing_changed( $before, 'An error of the upload handling' );
	}

	/**
	 * A file from base64 is stored, and the file Core's handling gets has the name as sent
	 * and an empty type.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_upload_from_base64(): void {
		$files = array();
		add_filter(
			'wp_handle_sideload_prefilter',
			static function ( array $file ) use ( &$files ): array {
				$files[] = $file;
				return $file;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( 'images/test-alpha.pdf', 'mdt-press-kit.pdf', array( 'title' => 'Press kit' ) ) );
		$id     = $this->assert_uploaded( $result, 'An upload of a PDF' );

		$this->assertSameSets( self::DEFAULT_KEYS, array_keys( $result ), 'The item should hold the default fields.' );
		$this->assertSame( 'inherit', $result['status'], 'The status should be inherit.' );
		$this->assertSame( 'Press kit', $result['title']['rendered'], 'The title should be the one sent.' );
		$this->assertSame( 'file', $result['media_type'], 'A PDF should have the media type file.' );
		$this->assertSame( 'application/pdf', $result['mime_type'], 'The MIME type should be the one of the PDF.' );
		$this->assertSame( wp_get_attachment_url( $id ), $result['source_url'], 'The source URL should be the attachment URL.' );
		$this->assertSame( filesize( self::fixture( 'images/test-alpha.pdf' ) ), filesize( get_attached_file( $id ) ), 'The stored file should hold the decoded bytes.' );
		$this->assertCount( 1, $files, 'The upload handling should get the file once.' );
		$this->assertSame( 'mdt-press-kit.pdf', $files[0]['name'], 'The upload handling should get the name as sent.' );
		$this->assertSame( '', $files[0]['type'], 'The upload handling should get an empty type.' );
		$this->assertSame( array(), $this->list_temp_files( 'mdt-press-kit.pdf' ), 'No temporary file should be left.' );
	}

	/**
	 * Whitespace in the data is skipped and missing padding is accepted.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_base64_with_whitespace_and_without_padding(): void {
		$bytes = file_get_contents( self::fixture( 'images/codeispoetry.png' ) );
		$data  = rtrim( chunk_split( base64_encode( $bytes ), 76, "\n" ), "=\n" );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data' => array(
					'file' => array(
						'base64'   => $data,
						'filename' => 'mdt-whitespace.png',
					),
				),
			)
		);

		$id = $this->assert_uploaded( $result, 'Data with whitespace and without padding' );
		$this->assertSame( $bytes, file_get_contents( get_attached_file( $id ) ), 'The stored file should hold the decoded bytes.' );
	}

	/**
	 * Returns data that is not valid base64.
	 *
	 * @return array<string, array{data: string}> The data.
	 */
	public static function data_invalid_base64(): array {
		return array(
			'characters outside the alphabet' => array( 'iVBOR!!!w0KGgo' ),
			'data URL prefix'                 => array( 'data:image/png;base64,iVBORw0KGgo=' ),
			'lone character'                  => array( 'A' ),
		);
	}

	/**
	 * Data that is not valid base64 is refused, and nothing is written.
	 *
	 * @ticket 66719
	 * @dataProvider data_invalid_base64
	 * @since 7.3.0
	 *
	 * @param string $data The data.
	 */
	public function test_invalid_base64( string $data ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-invalid-base64.png' );
		$result = $this->run_ability(
			array(
				'data' => array(
					'file' => array(
						'base64'   => $data,
						'filename' => 'mdt-invalid-base64.png',
					),
				),
			)
		);

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][base64]', 'Data that is not valid base64' );
		$this->assert_error_message( $result, 'The file data is not valid base64.', 'Data that is not valid base64' );
		$this->assert_nothing_changed( $before, 'Data that is not valid base64' );
	}

	/**
	 * Data of whitespace only decodes to nothing and is an empty file.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_base64_of_whitespace_only_is_an_empty_file(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-empty.png' );
		$result = $this->run_ability(
			array(
				'data' => array(
					'file' => array(
						'base64'   => " \n\t ",
						'filename' => 'mdt-empty.png',
					),
				),
			)
		);

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][base64]', 'Data of whitespace only' );
		$this->assert_error_message( $result, 'File is empty. Please upload something more substantial.', 'Data of whitespace only' );
		$this->assert_nothing_changed( $before, 'Data of whitespace only' );
	}

	/**
	 * Decoded data larger than the maximum size is refused before anything is written.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_base64_larger_than_the_maximum(): void {
		$this->set_max_upload_size( 2048 );
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-big-base64.jpg' );
		$result = $this->run_ability( $this->base64_input( 'images/canola.jpg', 'mdt-big-base64.jpg' ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][base64]', 'Decoded data that is too big' );
		$this->assert_error_message( $result, self::TOO_BIG_2_KB, 'Decoded data that is too big' );
		$this->assert_nothing_changed( $before, 'Decoded data that is too big' );
	}

	/**
	 * Returns file names with path separators or control characters.
	 *
	 * @return array<string, array{filename: string}> The names.
	 */
	public static function data_file_names_with_paths(): array {
		return array(
			'parent folders'     => array( '../../mdt-escape.png' ),
			'absolute path'      => array( '/tmp/mdt-absolute.png' ),
			'backslashes'        => array( '..\\..\\mdt-backslash.png' ),
			'control characters' => array( "mdt-control\x01\x1f.png" ),
		);
	}

	/**
	 * A file name with path separators or control characters is no error and the file is
	 * stored inside the uploads folder.
	 *
	 * @ticket 66719
	 * @dataProvider data_file_names_with_paths
	 * @since 7.3.0
	 *
	 * @param string $filename The file name.
	 */
	public function test_file_name_with_paths_stays_inside_the_uploads_folder( string $filename ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$id   = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', $filename ) ), 'A file name with a path' );
		$file = get_attached_file( $id );

		$this->assertFileExists( $file, 'The stored file should exist.' );
		$this->assertStringStartsWith( realpath( wp_upload_dir()['basedir'] ) . DIRECTORY_SEPARATOR, realpath( $file ), 'The stored file should be inside the uploads folder.' );
	}

	/**
	 * Returns file names Core refuses for a caller without `unfiltered_upload`.
	 *
	 * @return array<string, array{filename: string}> The names.
	 */
	public static function data_refused_file_names(): array {
		return array(
			'PHP file'     => array( 'mdt-evil.php' ),
			'no extension' => array( 'mdt-no-extension' ),
		);
	}

	/**
	 * A type Core refuses for the caller is an invalid file name, and nothing is left.
	 *
	 * @ticket 66719
	 * @dataProvider data_refused_file_names
	 * @since 7.3.0
	 *
	 * @param string $filename The file name.
	 */
	public function test_refused_file_type( string $filename ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot_before( $filename );
		$result = $this->run_ability(
			array(
				'data' => array(
					'file' => array(
						'base64'   => base64_encode( "<?php echo 'MDT';\n" ),
						'filename' => $filename,
					),
				),
			)
		);

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][filename]', 'A refused file type' );
		$this->assert_error_message( $result, self::REFUSED_TYPE, 'A refused file type' );
		$this->assert_nothing_changed( $before, 'A refused file type' );
	}

	/**
	 * An image sent under a name with a double extension is stored under Core's sanitized name.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_double_extension_is_sanitized(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/canola.jpg', 'mdt-evil.php.jpg' ) ), 'An image with a double extension' );

		$this->assertSame( 'mdt-evil.php_.jpg', wp_basename( get_attached_file( $id ) ), 'The name should be sanitized.' );
	}

	/**
	 * Returns files of types other than images.
	 *
	 * @return array<string, array{file: string, filename: string, mime_type: string}> The files.
	 */
	public static function data_files_that_are_not_images(): array {
		return array(
			'PDF'   => array( 'images/test-alpha.pdf', 'mdt-allowed.pdf', 'application/pdf' ),
			'audio' => array( 'uploads/small-audio.mp3', 'mdt-allowed.mp3', 'audio/mpeg' ),
		);
	}

	/**
	 * Any type the caller may upload is accepted from base64, not only images.
	 *
	 * @ticket 66719
	 * @dataProvider data_files_that_are_not_images
	 * @since 7.3.0
	 *
	 * @param string $file      The path inside the test data folder.
	 * @param string $filename  The file name.
	 * @param string $mime_type The expected MIME type.
	 */
	public function test_types_other_than_images_are_accepted( string $file, string $filename, string $mime_type ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( $file, $filename ) );

		$this->assert_uploaded( $result, 'A file that is not an image' );
		$this->assertSame( $mime_type, $result['mime_type'], 'The MIME type should be the stored one.' );
		$this->assertSame( 'file', $result['media_type'], 'The media type should be file.' );
	}

	/**
	 * A file of a type Core cannot tell is stored for a caller with `unfiltered_upload`, with
	 * an empty MIME type.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unknown_type_is_stored_with_unfiltered_upload(): void {
		$this->grant_unfiltered_upload();
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'file' => array(
						'base64'   => base64_encode( "<?php echo 'MDT';\n" ),
						'filename' => 'mdt-unfiltered.php',
					),
				),
				'fields' => array( 'mime_type' ),
			)
		);

		$this->assert_uploaded( $result, 'An unknown type with unfiltered_upload' );
		$this->assertSame( '', $result['mime_type'], 'The MIME type should be empty.' );
	}

	/**
	 * Returns parents with the folder the file goes to.
	 *
	 * @return array<string, array{post: string|null, folder: string}> The cases, with `now` for the current folder.
	 */
	public static function data_upload_folders(): array {
		return array(
			'post'      => array( 'post', '2020/05' ),
			'page'      => array( 'page', 'now' ),
			'no parent' => array( null, 'now' ),
		);
	}

	/**
	 * The file goes to the folder of its parent's date when the parent is not a page, and to
	 * the current folder otherwise; the date sent plays no part.
	 *
	 * @ticket 66719
	 * @dataProvider data_upload_folders
	 * @since 7.3.0
	 *
	 * @param string|null $post   The parent, as a fixture name.
	 * @param string      $folder The expected folder.
	 */
	public function test_upload_folder( ?string $post, string $folder ): void {
		update_option( 'uploads_use_yearmonth_folders', 1 );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$data = array( 'date' => array( 'site' => '2099-01-15T10:00:00' ) );
		if ( null !== $post ) {
			$data['post'] = self::$post_ids[ $post ];
		}

		$id       = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-folder.png', $data ) ), 'An upload' );
		$expected = 'now' === $folder ? current_time( 'Y/m' ) : $folder;

		$this->assertStringStartsWith( $expected . '/', get_post_meta( $id, '_wp_attached_file', true ), 'The file should be in the expected folder.' );
	}

	/**
	 * Without a title or caption the ones in the image's metadata are used.
	 *
	 * @ticket 66719
	 * @requires extension gd
	 * @requires extension exif
	 * @since 7.3.0
	 */
	public function test_title_and_caption_from_the_metadata(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'file'  => array(
						'base64'   => base64_encode( file_get_contents( self::fixture( 'images/2004-07-22-DSC_0007.jpg' ) ) ),
						'filename' => 'mdt-metadata.jpg',
					),
					'title' => '',
				),
				'fields' => array( 'title.raw', 'caption.raw' ),
			)
		);

		$this->assert_uploaded( $result, 'An image with metadata' );
		$this->assertSame( 'IPTC Headline', $result['title']['raw'], 'The title should come from the metadata.' );
		$this->assertSame( 'IPTC Caption', $result['caption']['raw'], 'The caption should come from the metadata.' );
	}

	/**
	 * Values that are sent win over the metadata.
	 *
	 * @ticket 66719
	 * @requires extension gd
	 * @requires extension exif
	 * @since 7.3.0
	 */
	public function test_sent_values_win_over_the_metadata(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'file'     => array(
						'base64'   => base64_encode( file_get_contents( self::fixture( 'images/IPTC-PhotometadataRef-Std2025.1.jpg' ) ) ),
						'filename' => 'mdt-metadata-sent.jpg',
					),
					'title'    => 'MDT title',
					'caption'  => 'MDT caption',
					'alt_text' => 'MDT alt',
				),
				'fields' => array( 'title.raw', 'caption.raw', 'alt_text' ),
			)
		);

		$this->assert_uploaded( $result, 'An image with metadata and sent values' );
		$this->assertSame( 'MDT title', $result['title']['raw'], 'The title sent should win.' );
		$this->assertSame( 'MDT caption', $result['caption']['raw'], 'The caption sent should win.' );
		$this->assertSame( 'MDT alt', $result['alt_text'], 'The alt text sent should win.' );
	}

	/**
	 * Without an alt text the one in the image's metadata is used.
	 *
	 * @ticket 66719
	 * @requires extension gd
	 * @requires extension exif
	 * @since 7.3.0
	 */
	public function test_alt_text_from_the_metadata(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array(
				'data'   => array(
					'file' => array(
						'base64'   => base64_encode( file_get_contents( self::fixture( 'images/IPTC-PhotometadataRef-Std2025.1.jpg' ) ) ),
						'filename' => 'mdt-metadata-alt.jpg',
					),
				),
				'fields' => array( 'alt_text' ),
			)
		);

		$this->assert_uploaded( $result, 'An image with an alt text in its metadata' );
		$this->assertSame( 'This is the Alt Text description to support accessibility in 2025.1', $result['alt_text'], 'The alt text should come from the metadata.' );
	}

	/**
	 * A title in the metadata that is a number, and a file without metadata, give the file
	 * name without its extension as the title.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_title_falls_back_to_the_file_name(): void {
		add_filter(
			'wp_read_image_metadata',
			static function ( $meta, $file ) {
				if ( str_contains( (string) $file, 'mdt-numeric-title' ) ) {
					$meta['title'] = '12345';
				}

				return $meta;
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$numeric = $this->run_ability(
			array_merge(
				$this->base64_input( 'images/canola.jpg', 'mdt-numeric-title.jpg' ),
				array( 'fields' => array( 'title.raw' ) )
			)
		);
		$plain   = $this->run_ability(
			array_merge(
				$this->base64_input( 'images/test-alpha.pdf', 'mdt-plain-title.pdf' ),
				array( 'fields' => array( 'title.raw' ) )
			)
		);

		$this->assert_uploaded( $numeric, 'An image with a numeric title' );
		$this->assert_uploaded( $plain, 'A file without metadata' );
		$this->assertSame( 'mdt-numeric-title', $numeric['title']['raw'], 'A numeric title should give the file name.' );
		$this->assertSame( 'mdt-plain-title', $plain['title']['raw'], 'A file without metadata should give the file name.' );
	}

	/**
	 * The attachment is saved as `inherit`, with the author and parent sent.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_saved_attachment(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$id   = $this->assert_uploaded(
			$this->run_ability(
				$this->base64_input(
					'images/codeispoetry.png',
					'mdt-saved.png',
					array(
						'author'      => self::$user_ids['author'],
						'post'        => self::$post_ids['author_post'],
						'description' => 'MDT description',
					)
				)
			),
			'An upload with an author and a parent'
		);
		$post = get_post( $id );

		$this->assertSame( 'inherit', $post->post_status, 'The status should be inherit.' );
		$this->assertSame( self::$user_ids['author'], (int) $post->post_author, 'The author should be the one sent.' );
		$this->assertSame( self::$post_ids['author_post'], $post->post_parent, 'The parent should be the one sent.' );
		$this->assertSame( 'image/png', $post->post_mime_type, 'The MIME type should be the stored one.' );
		$this->assertSame( 'MDT description', $post->post_content, 'The description should be saved.' );

		$own = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-saved-own.png' ) ), 'An upload without an author or parent' );
		$this->assertSame( self::$user_ids['editor'], (int) get_post_field( 'post_author', $own ), 'The caller should be the author.' );
		$this->assertSame( 0, get_post( $own )->post_parent, 'The attachment should be unattached.' );
	}

	/**
	 * Returns keys that are not part of the input.
	 *
	 * @return array<string, array{key: string, value: mixed}> The keys and values.
	 */
	public static function data_keys_outside_the_allow_list(): array {
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

	/**
	 * A key outside the allow-list fails input validation, and nothing is requested or
	 * written.
	 *
	 * @ticket 66719
	 * @dataProvider data_keys_outside_the_allow_list
	 * @since 7.3.0
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value.
	 */
	public function test_key_outside_the_allow_list_fails_input_validation( string $key, $value ): void {
		$url = $this->remote_url( 'mdt-extra-key.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-extra-key.jpg' );
		$result = $this->run_ability( $this->url_input( $url, array( $key => $value ) ) );

		$this->assertWPError( $result, 'The call should fail.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The schema should reject the key.' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
		$this->assertSame( $before, $this->snapshot_before( 'mdt-extra-key.jpg' ), 'Nothing should be written.' );
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
	 * When the save fails no attachment exists and the stored file is removed.
	 *
	 * @ticket 66719
	 * @dataProvider data_save_failures
	 * @since 7.3.0
	 *
	 * @param bool $database_error Whether the save fails with a database error.
	 * @param int  $status         The expected status.
	 */
	public function test_failed_save_removes_the_stored_file( bool $database_error, int $status ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-failed-save.png' );
		$this->make_saves_fail( $database_error );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-failed-save.png' ) );

		$this->assert_error( $result, 'media_save_failed', $status, 'A failed save' );
		$this->assert_nothing_changed( $before, 'A failed save' );
	}

	/**
	 * Returns existing files another hook may swap the stored file for.
	 *
	 * @return array<string, array{place: string}> The places, as case names.
	 */
	public static function data_swapped_files(): array {
		return array(
			'inside the uploads folder'  => array( 'inside' ),
			'outside the uploads folder' => array( 'outside' ),
			'with the very same bytes'   => array( 'same_bytes' ),
		);
	}

	/**
	 * When another hook swapped the stored file for an existing one, nothing is removed after
	 * a failed save.
	 *
	 * @ticket 66719
	 * @dataProvider data_swapped_files
	 * @since 7.3.0
	 *
	 * @param string $place Where the existing file is, as a case name.
	 */
	public function test_failed_save_never_removes_a_swapped_file( string $place ): void {
		$bytes    = file_get_contents( self::fixture( 'images/codeispoetry.png' ) );
		$basedir  = wp_upload_dir()['basedir'];
		$paths    = array(
			'inside'     => $basedir . '/mdt-swapped-inside.png',
			'outside'    => get_temp_dir() . 'mdt-swapped-outside-' . wp_generate_password( 8, false ) . '.png',
			'same_bytes' => $basedir . '/mdt-swapped-same.png',
		);
		$existing = $paths[ $place ];
		$contents = 'same_bytes' === $place ? $bytes : 'MDT existing file';

		wp_mkdir_p( dirname( $existing ) );
		file_put_contents( $existing, $contents );

		add_filter(
			'wp_handle_upload',
			static function ( array $upload ) use ( $existing ): array {
				$upload['file'] = $existing;
				return $upload;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-swapped.png' ) );
		$kept   = file_exists( $existing ) ? file_get_contents( $existing ) : null;

		if ( 'outside' === $place && file_exists( $existing ) ) {
			unlink( $existing );
		}

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save after a swap' );
		$this->assertSame( $contents, $kept, 'The existing file should be kept unchanged.' );
	}

	/**
	 * When another callback took over the move of the file, nothing is removed after a failed
	 * save, also when the file appears at the path only after the take-over and holds exactly
	 * the uploaded bytes.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_failed_save_keeps_a_file_another_callback_moved(): void {
		$moved          = null;
		$existed_before = null;
		add_filter(
			'pre_move_uploaded_file',
			static function ( $move, array $file, string $new_file ) use ( &$moved, &$existed_before ) {
				$existed_before = file_exists( $new_file );

				add_filter(
					'pre_move_uploaded_file',
					static function ( $move, array $file, string $new_file ) use ( &$moved ) {
						copy( $file['tmp_name'], $new_file );
						$moved = $new_file;
						return $move;
					},
					PHP_INT_MAX,
					3
				);

				return true;
			},
			10,
			3
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-moved-elsewhere.png' ) );

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save after another callback moved the file' );
		$this->assertFalse( $existed_before, 'The path should not exist when the move is taken over.' );
		$this->assertIsString( $moved, 'The other callback should have moved the file.' );
		$this->assertFileExists( $moved, 'The file the other callback moved should be kept.' );
		$this->assertSame( file_get_contents( self::fixture( 'images/codeispoetry.png' ) ), file_get_contents( $moved ), 'The kept file should hold exactly the uploaded bytes.' );
	}

	/**
	 * When the chosen path already held a file, that path is not removed after a failed save.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_failed_save_keeps_a_path_that_existed(): void {
		$folder   = wp_upload_dir()['path'];
		$existing = $folder . '/mdt-path-existed-target.png';
		wp_mkdir_p( $folder );
		file_put_contents( $existing, 'MDT existing file' );

		add_filter(
			'wp_unique_filename',
			static function ( string $filename ): string {
				return 'mdt-path-existed.png' === $filename ? 'mdt-path-existed-target.png' : $filename;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-path-existed.png' ) );

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save to a path that existed' );
		$this->assertFileExists( $existing, 'The path that existed should still hold a file.' );
	}

	/**
	 * When the stored file holds other bytes than the call stored, it is not removed after a
	 * failed save.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_failed_save_keeps_a_file_with_other_bytes(): void {
		$stored = null;
		add_filter(
			'wp_handle_upload',
			static function ( array $upload ) use ( &$stored ): array {
				file_put_contents( $upload['file'], 'MDT other bytes' );
				$stored = $upload['file'];
				return $upload;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-other-bytes.png' ) );

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save of a file with other bytes' );
		$this->assertIsString( $stored, 'The upload handling should return the stored file.' );
		$this->assertFileExists( $stored, 'The file with other bytes should be kept.' );
	}

	/**
	 * When the stored file is gone, a failed save is still the save error, without a warning
	 * or notice.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_failed_save_of_a_file_that_is_gone(): void {
		add_filter(
			'wp_handle_upload',
			static function ( array $upload ): array {
				unlink( $upload['file'] );
				return $upload;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-gone.png' ) );

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save of a file that is gone' );
	}

	/**
	 * When the stored file lies outside the uploads folder, it is not removed after a failed
	 * save.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_failed_save_keeps_a_file_outside_the_uploads_folder(): void {
		$outside = get_temp_dir() . 'mdt-outside-uploads-' . wp_generate_password( 8, false );
		add_filter(
			'upload_dir',
			static function ( array $uploads ) use ( $outside ): array {
				$uploads['path'] = $outside;
				return $uploads;
			}
		);

		$stored = null;
		add_filter(
			'wp_handle_upload',
			static function ( array $upload ) use ( &$stored ): array {
				$stored = $upload['file'];
				return $upload;
			}
		);

		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->make_saves_fail( false );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-outside-uploads.png' ) );
		$kept   = is_string( $stored ) && file_exists( $stored );

		if ( $kept ) {
			unlink( $stored );
		}
		if ( is_dir( $outside ) ) {
			rmdir( $outside );
		}

		$this->assert_error( $result, 'media_save_failed', 400, 'A failed save of a file outside the uploads folder' );
		$this->assertIsString( $stored, 'The upload handling should return the stored file.' );
		$this->assertStringStartsWith( $outside, $stored, 'The stored file should lie outside the uploads folder.' );
		$this->assertTrue( $kept, 'The file outside the uploads folder should be kept.' );
	}

	/**
	 * When the metadata cannot be generated the attachment stays and is returned, with empty
	 * media details.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attachment_stays_when_the_metadata_cannot_be_generated(): void {
		add_filter( 'wp_generate_attachment_metadata', '__return_empty_array' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->base64_input( 'images/canola.jpg', 'mdt-no-metadata.jpg' ),
				array( 'fields' => array( 'media_details' ) )
			)
		);

		$this->assert_uploaded( $result, 'An upload without metadata' );
		$this->assert_empty_object( $result['media_details'], 'The media details should be an empty object.' );
	}

	/**
	 * The stored metadata and sub-sizes are returned in the media details.
	 *
	 * @ticket 66719
	 * @requires extension gd
	 * @since 7.3.0
	 */
	public function test_metadata_and_sub_sizes_are_generated(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->base64_input( 'images/canola.jpg', 'mdt-sub-sizes.jpg' ),
				array( 'fields' => array( 'media_details' ) )
			)
		);

		$this->assert_uploaded( $result, 'An upload of an image' );
		$this->assertSame( 640, $result['media_details']['width'], 'The width should be the image width.' );
		$this->assertSame( 480, $result['media_details']['height'], 'The height should be the image height.' );
		$this->assertArrayHasKey( 'thumbnail', (array) $result['media_details']['sizes'], 'The sub-sizes should be generated.' );
		$this->assertArrayHasKey( 'full', (array) $result['media_details']['sizes'], 'The full size should be listed.' );
	}

	/**
	 * A listener after the save sees the alt text.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_listener_after_the_save_sees_the_alt_text(): void {
		$seen = array();
		add_action(
			'wp_after_insert_post',
			static function ( int $post_id, WP_Post $post ) use ( &$seen ): void {
				if ( 'attachment' === $post->post_type ) {
					$seen[] = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
				}
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-listener.png', array( 'alt_text' => 'MDT listener alt' ) ) ), 'An upload with an alt text' );

		$this->assertNotEmpty( $seen, 'The listener should run.' );
		$this->assertSame( 'MDT listener alt', end( $seen ), 'The last call of the listener should see the alt text.' );
	}

	/**
	 * The alt text is sanitized as a text field.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_alt_text_is_sanitized(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-alt-sanitized.png', array( 'alt_text' => "<b>MDT</b>\n alt " ) ) ), 'An upload with markup in the alt text' );

		$this->assertSame( sanitize_text_field( "<b>MDT</b>\n alt " ), get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The alt text should be sanitized as a text field.' );
	}

	/**
	 * Two identical calls create two attachments.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_two_identical_calls_create_two_attachments(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = $this->base64_input( 'images/codeispoetry.png', 'mdt-twice.png' );
		$first  = $this->assert_uploaded( $this->run_ability( $input ), 'The first call' );
		$second = $this->assert_uploaded( $this->run_ability( $input ), 'The second call' );

		$this->assertNotSame( $first, $second, 'The calls should create two attachments.' );
	}

	/**
	 * `fields` selects the keys of the output.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_fields_select_the_output(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->base64_input( 'images/codeispoetry.png', 'mdt-fields.png', array( 'title' => 'MDT fields' ) ),
				array( 'fields' => array( 'title.raw', 'author.id' ) )
			)
		);

		$expected = array(
			'author' => array( 'id' => self::$user_ids['editor'] ),
			'id'     => $this->assert_uploaded( $result, 'An upload with fields' ),
			'title'  => array( 'raw' => 'MDT fields' ),
		);
		ksort( $result );

		$this->assertSame( $expected, $result, 'The output should hold exactly the selected fields.' );
	}

	/**
	 * When the caller cannot read the new attachment the upload succeeds and returns only
	 * the ID.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unreadable_upload_returns_only_the_id(): void {
		$this->login_as( 'other_editor_uploader' );
		$this->register_abilities();

		$result = $this->run_ability(
			array_merge(
				$this->base64_input(
					'images/codeispoetry.png',
					'mdt-unreadable.png',
					array(
						'author' => self::$user_ids['editor'],
						'post'   => self::$post_ids['private_post'],
					)
				),
				array( 'fields' => array( 'title', 'status' ) )
			)
		);

		$id = $this->assert_uploaded( $result, 'An upload the caller cannot read' );
		$this->assertSame( array( 'id' => $id ), $result, 'The output should hold only the ID.' );
	}

	/**
	 * Returns callers who may upload, with whether their markup is kept. On single site the
	 * editor has `unfiltered_html`; on multisite only a super admin has it.
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
	 * Markup in the title, caption and description is kept for a caller with
	 * `unfiltered_html` and filtered for one without it.
	 *
	 * @ticket 66719
	 * @dataProvider data_markup_roles
	 * @since 7.3.0
	 *
	 * @param string $role        The role.
	 * @param bool   $kept        Whether the markup is kept.
	 * @param bool   $super_admin Optional. Whether the caller is a super admin. Default false.
	 */
	public function test_markup_follows_unfiltered_html( string $role, bool $kept, bool $super_admin = false ): void {
		if ( $super_admin ) {
			grant_super_admin( self::$user_ids[ $role ] );
		}

		$this->login_as( $role );
		$this->register_abilities();

		$markup = 'MDT <script>alert(1)</script>';
		$id     = $this->assert_uploaded(
			$this->run_ability(
				$this->base64_input(
					'images/codeispoetry.png',
					"mdt-markup-{$role}.png",
					array(
						'title'       => $markup,
						'caption'     => $markup,
						'description' => $markup,
					)
				)
			),
			'An upload with markup'
		);

		if ( $super_admin ) {
			revoke_super_admin( self::$user_ids[ $role ] );
		}

		$post = get_post( $id );

		foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $field ) {
			$this->assertSame( $kept, str_contains( $post->$field, '<script>' ), $kept ? "The markup in {$field} should be kept." : "The markup in {$field} should be filtered." );
		}
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
	 * Backslashes in the title, caption, description and alt text are stored as they were
	 * sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_texts_with_backslashes
	 * @since 7.3.0
	 *
	 * @param string $text The text.
	 */
	public function test_backslashes_are_stored_as_sent( string $text ): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ): array {
				return 'unfiltered_html' === $cap ? array( 'exist' ) : $caps;
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_uploaded(
			$this->run_ability(
				$this->base64_input(
					'images/codeispoetry.png',
					'mdt-backslashes.png',
					array(
						'title'       => $text,
						'caption'     => $text,
						'description' => $text,
						'alt_text'    => $text,
					)
				)
			),
			'An upload with backslashes'
		);

		$this->assertSame( $text, get_post_field( 'post_title', $id, 'raw' ), 'The title should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_excerpt', $id, 'raw' ), 'The caption should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_content', $id, 'raw' ), 'The description should be stored as sent.' );
		$this->assertSame( $text, get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The alt text should be stored as sent.' );
	}

	/**
	 * On multisite a site without space left is refused on the URL path before any request,
	 * also for a super admin.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_site_without_space_is_refused_before_the_download(): void {
		update_site_option( 'upload_space_check_disabled', false );
		add_filter( 'get_space_allowed', static fn() => 1 );
		add_filter( 'pre_get_space_used', static fn() => 1 );
		grant_super_admin( self::$user_ids['administrator'] );

		$url = $this->remote_url( 'mdt-no-space.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->run_ability( $this->url_input( $url ) );
		revoke_super_admin( self::$user_ids['administrator'] );

		$this->assert_error( $result, 'media_quota_exceeded', 400, 'An upload to a site without space' );
		$this->assert_error_message( $result, 'You have used your space quota. Please delete files before uploading.', 'An upload to a site without space' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * On multisite a file that needs more space than is left is refused on both paths.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @dataProvider data_file_paths_without_a_maximum
	 * @since 7.3.0
	 *
	 * @param string $path    How the file is sent.
	 * @param int    $maximum The maximum size the test sets.
	 */
	public function test_file_larger_than_the_space_left( string $path, int $maximum ): void {
		update_site_option( 'upload_space_check_disabled', false );
		add_filter( 'get_space_allowed', static fn() => 1 );
		add_filter( 'pre_get_space_used', static fn() => 0.9 );
		$this->set_max_upload_size( $maximum );

		$name = "mdt-space-left-{$path}{$maximum}.jpg";
		$url  = $this->remote_url( $name );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( $name );
		$result = $this->run_ability( 'url' === $path ? $this->url_input( $url ) : $this->base64_input( 'images/canola.jpg', $name ) );

		$this->assert_error( $result, 'media_quota_exceeded', 400, 'A file larger than the space left' );
		$this->assert_nothing_changed( $before, 'A file larger than the space left' );
	}

	/**
	 * On multisite a file above the network's maximum file size is refused on both paths,
	 * with the stored maximum in the message.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @dataProvider data_file_paths_without_a_maximum
	 * @since 7.3.0
	 *
	 * @param string $path    How the file is sent.
	 * @param int    $maximum The maximum size the test sets.
	 */
	public function test_file_above_the_network_maximum( string $path, int $maximum ): void {
		update_site_option( 'upload_space_check_disabled', false );
		update_site_option( 'fileupload_maxk', 2 );
		add_filter( 'get_space_allowed', static fn() => 100 );
		add_filter( 'pre_get_space_used', static fn() => 0 );
		$this->set_max_upload_size( $maximum );

		$name = "mdt-network-maximum-{$path}{$maximum}.jpg";
		$url  = $this->remote_url( $name );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( $name );
		$result = $this->run_ability( 'url' === $path ? $this->url_input( $url ) : $this->base64_input( 'images/canola.jpg', $name ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'url' === $path ? 'data[file][url]' : 'data[file][base64]', 'A file above the network maximum' );
		$this->assert_error_message( $result, self::TOO_BIG_2_KB, 'A file above the network maximum' );
		$this->assert_nothing_changed( $before, 'A file above the network maximum' );
	}

	/**
	 * On multisite a network maximum file size below 1 refuses a URL upload before any
	 * request, with the stored maximum in the message.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_network_maximum_below_one_is_refused_before_the_download(): void {
		update_site_option( 'upload_space_check_disabled', false );
		update_site_option( 'fileupload_maxk', 0 );
		add_filter( 'get_space_allowed', static fn() => 100 );
		add_filter( 'pre_get_space_used', static fn() => 0 );

		$url = $this->remote_url( 'mdt-network-maximum-zero.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-network-maximum-zero.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A URL upload with a network maximum of 0' );
		$this->assert_error_message( $result, 'This file is too big. Files must be less than 0 KB in size.', 'A URL upload with a network maximum of 0' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
		$this->assert_nothing_changed( $before, 'A URL upload with a network maximum of 0' );
	}

	/**
	 * On multisite, with the space check turned off, a file from base64 above the network
	 * maximum file size is stored when the maximum size allows it.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_space_check_turned_off_on_the_base64_path(): void {
		update_site_option( 'upload_space_check_disabled', true );
		update_site_option( 'fileupload_maxk', 2 );
		add_filter( 'get_space_allowed', static fn() => 1 );
		add_filter( 'pre_get_space_used', static fn() => 1 );
		$this->set_max_upload_size( MB_IN_BYTES );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/canola.jpg', 'mdt-check-off-base64.jpg' ) ), 'A base64 upload with the space check off' );
	}

	/**
	 * On multisite, with the space check turned off, a URL upload to a site without allowed
	 * space is stored.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_space_check_turned_off_on_the_url_path(): void {
		update_site_option( 'upload_space_check_disabled', true );
		add_filter( 'get_space_allowed', static fn() => 0 );

		$url = $this->remote_url( 'mdt-check-off-url.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->url_input( $url ) ), 'A URL upload with the space check off' );
		$this->assertCount( 1, $this->requests_for( $url ), 'The URL should be requested.' );
	}

	/**
	 * On multisite, with the space check turned off and a network maximum file size of 0, a
	 * URL upload is requested without a limit and stored.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_space_check_turned_off_with_a_network_maximum_of_zero(): void {
		update_site_option( 'upload_space_check_disabled', true );
		update_site_option( 'fileupload_maxk', 0 );

		$url = $this->remote_url( 'mdt-check-off-maximum-zero.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_uploaded( $this->run_ability( $this->url_input( $url ) ), 'A URL upload with a network maximum of 0 and the space check off' );

		$requests = $this->requests_for( $url );
		$this->assertCount( 1, $requests, 'The URL should be requested once.' );
		$this->assertNull( $requests[0]['limit_response_size'], 'The request should have no limit.' );
	}

	/**
	 * On multisite an author who is not a member of the site is an invalid author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_author_of_another_site_is_invalid(): void {
		$outsider = self::factory()->user->create();
		remove_user_from_blog( $outsider, get_current_blog_id() );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-outsider.png', array( 'author' => $outsider ) ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'An author of another site' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'An author of another site' );
	}

	/**
	 * On multisite a super admin who is not a member of the site may send their own ID as
	 * the author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_outside_the_site_may_send_the_own_id(): void {
		$super_admin = self::factory()->user->create();
		remove_user_from_blog( $super_admin, get_current_blog_id() );
		grant_super_admin( $super_admin );

		wp_set_current_user( $super_admin );
		$this->register_abilities();

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-super-admin-author.png', array( 'author' => $super_admin ) ) );
		revoke_super_admin( $super_admin );

		$id = $this->assert_uploaded( $result, 'An upload by a super admin outside the site' );
		$this->assertSame( (string) $super_admin, get_post_field( 'post_author', $id ), 'The stored author should be the super admin.' );
	}

	/**
	 * On multisite the allowed types are the network's.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_network_file_types(): void {
		update_site_option( 'upload_filetypes', 'jpg jpeg png' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_error_with_params(
			$this->run_ability( $this->base64_input( 'images/test-alpha.pdf', 'mdt-network-type.pdf' ) ),
			'media_invalid_file',
			'data[file][filename]',
			'A type the network does not allow'
		);
	}
}
