<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-media-ability.php';
require_once __DIR__ . '/trait-media-attribute-tests.php';

/**
 * Tests for the core/media-upload ability.
 *
 * @covers WP_Abilities_Media
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreMediaUploadAbility extends WP_Abilities_Media_UnitTestCase {

	use WP_Abilities_Media_Attribute_Tests;

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/media-upload';

	/**
	 * The message of a write as another author.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const OTHER_AUTHOR_MESSAGE = 'Sorry, you are not allowed to create posts as this user.';

	/**
	 * The message of a write to a parent the caller may not edit.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ATTACH_MESSAGE = 'Sorry, you are not allowed to upload media to this post.';

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
	 * The requests the faked HTTP transport received, each with its URL and arguments.
	 *
	 * @since 7.3.0
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	private $requests = array();

	/**
	 * The files in the temporary folder when the test started.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private $temp_files_at_start = array();

	/**
	 * Files and empty folders in the temporary folder that tear_down() removes, in order,
	 * should a test leave them.
	 *
	 * @since 7.3.0
	 *
	 * @var list<string>
	 */
	private $temp_files_to_remove = array();

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
	 * Notes the files in the temporary folder.
	 *
	 * @since 7.3.0
	 */
	public function set_up(): void {
		parent::set_up();

		$temp_files                = glob( get_temp_dir() . '*' );
		$this->temp_files_at_start = false === $temp_files ? array() : $temp_files;
	}

	/**
	 * Removes the files and folders a test left in the temporary folder.
	 *
	 * @since 7.3.0
	 */
	public function tear_down(): void {
		foreach ( $this->temp_files_to_remove as $path ) {
			if ( is_dir( $path ) ) {
				rmdir( $path );
			} elseif ( file_exists( $path ) ) {
				unlink( $path );
			}
		}

		parent::tear_down();
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
	 * Sets the maximum upload size, after Core's own callbacks on the filter.
	 *
	 * @since 7.3.0
	 *
	 * @param int $bytes The maximum size in bytes; 0 means no limit.
	 */
	private function set_max_upload_size( int $bytes ): void {
		add_filter(
			'upload_size_limit',
			static function () use ( $bytes ): int {
				return $bytes;
			},
			20
		);
	}

	/**
	 * Returns the temporary files made for a file name since the test started.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file_name The file name a test sends, with or without its extension.
	 * @return list<string> The temporary file paths.
	 */
	private function list_temp_files( string $file_name ): array {
		$base  = preg_replace( '/\.[^.]*$/', '', wp_basename( $file_name ) );
		$files = glob( get_temp_dir() . $base . '-*' );

		return false === $files ? array() : array_values( array_diff( $files, $this->temp_files_at_start ) );
	}

	/**
	 * Returns a URL on the site's own host, which the URL check accepts without a lookup.
	 *
	 * @since 7.3.0
	 *
	 * @param string $file_name The file name at the end of the path.
	 * @return string The URL.
	 */
	private function remote_url( string $file_name ): string {
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
	private function fake_http( array $responses = array() ): void {
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
	private function requests_for( string $url ): array {
		$found = array();

		foreach ( $this->requests as $request ) {
			if ( $url === $request['url'] ) {
				$found[] = $request['args'];
			}
		}

		return $found;
	}

	/**
	 * Asserts that a refused call left no temporary file and changed no stored media.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $before  The snapshot before the call.
	 * @param string               $context What the call was, for the assertion messages.
	 */
	protected function assert_unchanged( array $before, string $context ): void {
		$after = $this->snapshot();

		$this->assertSame( $before['posts'], $after['posts'], "{$context}: no post should be added." );
		$this->assertSame( $before['meta'], $after['meta'], "{$context}: no meta should be added." );
		$this->assertSame( $before['files'], $after['files'], "{$context}: no file should be left in the uploads folder." );
		$this->assertSame( array(), $this->list_temp_files( $before['temp_name'] ), "{$context}: no temporary file should be left." );
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
		$snapshot              = $this->snapshot();
		$snapshot['temp_name'] = $file_name;

		return $snapshot;
	}

	/**
	 * Uploads an image as base64 with the given attributes.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data   The attributes.
	 * @param string[]|null        $fields Optional. The fields. Default null.
	 * @return mixed The ability result.
	 */
	protected function write( array $data, ?array $fields = null ) {
		$input = $this->base64_input( 'images/codeispoetry.png', 'mdt-attributes.png', $data );

		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $this->run_ability( $input );
	}

	/**
	 * Asserts that an upload succeeded and returns the ID of the new attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 * @return int The attachment ID.
	 */
	protected function assert_written( $result, string $context ): int {
		return $this->assert_uploaded( $result, $context );
	}

	/**
	 * Takes a snapshot before an upload of the attributes image.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	protected function snapshot_write(): array {
		return $this->snapshot_before( 'mdt-attributes.png' );
	}

	/**
	 * Returns roles, with whether they have `upload_files`.
	 *
	 * @return array<string, array{role: string, expected: bool}> The roles.
	 */
	public static function data_gate_roles(): array {
		return array(
			'logged out'      => array( 'logged_out', false ),
			'subscriber'      => array( 'subscriber', false ),
			'contributor'     => array( 'contributor', false ),
			'no caps'         => array( 'no_caps', false ),
			'author'          => array( 'author', true ),
			'editor'          => array( 'editor', true ),
			'administrator'   => array( 'administrator', true ),
			'read and upload' => array( 'uploader', true ),
		);
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
	 * The input takes the file, the attributes and the fields, and the output is one
	 * attachment.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_schemas(): void {
		$this->register_abilities();
		$ability = wp_get_ability( self::ABILITY );
		$input   = $ability->get_input_schema();
		$file    = $input['properties']['data']['properties']['file'] ?? array();

		$this->assert_object_schema( $input, array( 'data' ), array( 'data', 'fields' ), 'The input schema' );
		$this->assert_attributes_schema( $input['properties']['data'], array( 'file' ) );
		$this->assertCount( 2, $file['anyOf'] ?? array(), 'The file should be a URL or base64 content.' );
		$this->assert_object_schema( $file['anyOf'][0], array( 'url' ), array( 'url' ), 'The URL file schema' );
		$this->assertSame( 'uri', $file['anyOf'][0]['properties']['url']['format'] ?? null, 'The URL should be a URI.' );
		$this->assert_object_schema( $file['anyOf'][1], array( 'base64', 'filename' ), array( 'base64', 'filename' ), 'The base64 file schema' );
		$this->assert_fields_schema( $input['properties']['fields'] );
		$this->assert_item_schema( $ability->get_output_schema() );
	}

	/**
	 * The permission callback follows `upload_files`.
	 *
	 * @ticket 66719
	 * @dataProvider data_gate_roles
	 * @since 7.3.0
	 *
	 * @param string $role     The role.
	 * @param bool   $expected Whether the gate passes.
	 */
	public function test_permission_callback_follows_upload_files( string $role, bool $expected ): void {
		$this->login_as( $role );
		$this->register_abilities();

		$this->assertSame( $expected, wp_get_ability( self::ABILITY )->check_permissions( $this->url_input( $this->remote_url( 'mdt-gate.jpg' ) ) ), 'The gate should follow upload_files.' );
	}

	/**
	 * The permission callback refuses an administrator when the attachment type has
	 * `show_in_abilities` off, and the call is refused with the generic error.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_permission_callback_refuses_when_attachments_are_hidden(): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();
		$this->fake_http();

		get_post_type_object( 'attachment' )->show_in_abilities = false;

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

		foreach ( self::data_gate_roles() as $name => $row ) {
			if ( ! $row[1] ) {
				$cases[ $name ] = array( $row[0], 'logged_out' === $row[0] ? 401 : 403 );
			}
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
		$this->assert_unchanged( $before, 'An upload without upload_files' );
	}

	/**
	 * An invalid attribute is reported before the file is requested.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attributes_are_checked_before_the_file(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->fake_http();

		$this->assert_error_with_params( $this->run_ability( $this->url_input( $this->remote_url( 'mdt-order.jpg' ), array( 'slug' => '!!!' ) ) ), 'media_invalid_attribute', 'data[slug]', 'An invalid slug' );
		$this->assertSame( array(), $this->requests, 'No request should be made while an attribute is invalid.' );
	}

	/**
	 * A missing file is invalid input.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_missing_file_is_invalid_input(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$before = $this->snapshot_before( 'mdt-no-file.png' );

		$result = $this->run_ability( array( 'data' => array( 'title' => 'MDT no file' ) ) );

		$this->assert_invalid_input( $result, 'A missing file' );
		$this->assert_unchanged( $before, 'A missing file' );
	}

	/**
	 * The new attachment hook fires once for the attachment, and a callback on the attachment
	 * data runs during the upload and stays registered.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_upload_runs_the_attachment_hooks(): void {
		$added      = array();
		$data_calls = 0;
		$callback   = static function ( array $data ) use ( &$data_calls ): array {
			++$data_calls;
			return $data;
		};
		add_action(
			'add_attachment',
			static function ( int $post_id ) use ( &$added ): void {
				$added[] = $post_id;
			}
		);
		add_filter( 'wp_insert_attachment_data', $callback );

		$this->login_as( 'uploader' );
		$this->register_abilities();

		$id = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-caller-upload.png' ) ), 'An upload by the uploader' );

		$this->assertSame( array( $id ), $added, 'The new attachment hook should fire once for the attachment.' );
		$this->assertGreaterThan( 0, $data_calls, 'The attachment data callback should run during the upload.' );
		$this->assertNotFalse( has_filter( 'wp_insert_attachment_data', $callback ), 'The attachment data callback should stay registered.' );
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
	 * Returns how a file is sent, with each upload size limit of 0 or less, which sets no limit
	 * of its own.
	 *
	 * @return array<string, array{path: string, maximum: int}> The cases.
	 */
	public static function data_file_paths_and_upload_size_limits_of_zero_or_less(): array {
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
	 * @dataProvider data_file_paths_and_upload_size_limits_of_zero_or_less
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
			'request error' => array( 'error' ),
			'not found'     => array( 'not_found' ),
			'server error'  => array( 'server_error' ),
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
		$this->assert_unchanged( $before, 'A failed download' );
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
		// A file with that name left by something else in the shared temporary folder is kept.
		$header_file = '' === $header_name || file_exists( get_temp_dir() . $header_name ) ? '' : get_temp_dir() . $header_name;

		if ( '' !== $header_file ) {
			$this->temp_files_to_remove[] = $header_file;
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
		$this->assert_unchanged( $before, 'A downloaded file that is too big' );
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
			$this->grant_cap( 'unfiltered_upload' );
		}

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( $name );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error_with_params( $result, 'media_invalid_file', 'data[file][url]', 'A download that is not an image' );
		$this->assert_error_message( $result, self::ONLY_IMAGES, 'A download that is not an image' );
		$this->assert_unchanged( $before, 'A download that is not an image' );
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
		$this->assert_unchanged( $before, 'A download the file check types as a PDF' );
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
	 * A type Core's upload rule refuses for a downloaded file is an invalid file, reported
	 * before Core's upload handling gets the file.
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
		$this->assert_unchanged( $before, 'A type refused by the upload handling' );
		$this->assertSame( array(), $files, 'The upload handling should not get the file.' );
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
		$this->assert_unchanged( $before, 'An error of the upload handling' );
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
		$this->assert_unchanged( $before, 'Data that is not valid base64' );
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
		$this->assert_unchanged( $before, 'Data of whitespace only' );
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
		$this->assert_unchanged( $before, 'Decoded data that is too big' );
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
		$this->assert_unchanged( $before, 'A refused file type' );
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
		$this->grant_cap( 'unfiltered_upload' );
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

		// The month is read before and after the call, so a call across a month boundary passes.
		$month    = current_time( 'Y/m' );
		$id       = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-folder.png', $data ) ), 'An upload' );
		$expected = 'now' === $folder ? array_unique( array( $month, current_time( 'Y/m' ) ) ) : array( $folder );

		$this->assertContains( dirname( get_post_meta( $id, '_wp_attached_file', true ) ), $expected, 'The file should be in the expected folder.' );
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

		$unattached = $this->assert_uploaded( $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-saved-unattached.png', array( 'post' => 0 ) ) ), 'An upload with the post 0' );
		$this->assertSame( 0, get_post( $unattached )->post_parent, 'The attachment sent with the post 0 should be unattached.' );
	}

	/**
	 * When the save fails no attachment exists and no temporary file is left.
	 *
	 * @ticket 66719
	 * @dataProvider data_save_failures
	 * @since 7.3.0
	 *
	 * @param bool $database_error Whether the save fails with a database error.
	 * @param int  $status         The expected status.
	 */
	public function test_failed_save_creates_no_attachment( bool $database_error, int $status ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-failed-save.png' );
		$this->make_saves_fail( $database_error );

		$result = $this->run_ability( $this->base64_input( 'images/codeispoetry.png', 'mdt-failed-save.png' ) );
		$after  = $this->snapshot();

		$this->assert_error( $result, 'media_save_failed', $status, 'A failed save' );
		$this->assertSame( $before['posts'], $after['posts'], 'A failed save should add no post.' );
		$this->assertSame( $before['meta'], $after['meta'], 'A failed save should add no meta.' );
		$this->assertSame( array(), $this->list_temp_files( 'mdt-failed-save.png' ), 'A failed save should leave no temporary file.' );
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
	 * On multisite a site without space left is refused on the URL path once the file is
	 * downloaded, also for a super admin, and the temporary file is removed.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_site_without_space_is_refused_on_the_url_path(): void {
		update_site_option( 'upload_space_check_disabled', false );
		add_filter( 'get_space_allowed', static fn() => 1 );
		add_filter( 'pre_get_space_used', static fn() => 1 );
		grant_super_admin( self::$user_ids['administrator'] );

		$url = $this->remote_url( 'mdt-no-space.jpg' );
		$this->fake_http( array( $url => array( 'file' => self::fixture( 'images/canola.jpg' ) ) ) );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot_before( 'mdt-no-space.jpg' );
		$result = $this->run_ability( $this->url_input( $url ) );

		$this->assert_error( $result, 'media_quota_exceeded', 400, 'An upload to a site without space' );
		$this->assertCount( 1, $this->requests_for( $url ), 'The file should be downloaded once.' );
		$this->assert_unchanged( $before, 'An upload to a site without space' );
	}

	/**
	 * On multisite a file that needs more space than is left is refused on both paths.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @dataProvider data_file_paths_and_upload_size_limits_of_zero_or_less
	 * @since 7.3.0
	 *
	 * @param string $path    How the file is sent.
	 * @param int    $maximum The upload size limit the test sets, which sets no limit of its own.
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
		$this->assert_unchanged( $before, 'A file larger than the space left' );
	}

	/**
	 * On multisite a file above the network's maximum file size is refused on both paths,
	 * with the stored maximum in the message.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @dataProvider data_file_paths_and_upload_size_limits_of_zero_or_less
	 * @since 7.3.0
	 *
	 * @param string $path    How the file is sent.
	 * @param int    $maximum The upload size limit the test sets, which sets no limit of its own.
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
		$this->assert_unchanged( $before, 'A file above the network maximum' );
	}

	/**
	 * On multisite a network maximum file size below 1 refuses a URL upload once the file
	 * is downloaded, with the stored maximum in the message.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_network_maximum_below_one_is_refused_on_the_url_path(): void {
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
		$this->assertCount( 1, $this->requests_for( $url ), 'The file should be downloaded once.' );
		$this->assert_unchanged( $before, 'A URL upload with a network maximum of 0' );
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
