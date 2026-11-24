<?php

declare( strict_types=1 );

require_once __DIR__ . '/testcase-content-existing-post-ability.php';

/**
 * Tests for the core/content-update ability shipped with the Abilities API.
 *
 * @covers WP_Abilities_Content
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreContentUpdateAbility extends WP_Abilities_Content_Existing_Post_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const ABILITY = 'core/content-update';

	/**
	 * The label of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var string
	 */
	protected const LABEL = 'Update Content';

	/**
	 * The annotations of the ability under test.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, bool>
	 */
	protected const ANNOTATIONS = array(
		'readonly'    => false,
		'destructive' => false,
		'idempotent'  => true,
	);

	/**
	 * The input that the shared tests send next to `where`.
	 *
	 * @since 7.3.0
	 *
	 * @var array<string, mixed>
	 */
	protected const OTHER_INPUT = array( 'data' => array( 'title' => 'x' ) );

	/**
	 * Creates shared users and posts.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::create_shared_fixtures( $factory );

		self::$user_ids['page_writer'] = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$user_ids['publisher']   = $factory->user->create( array( 'role' => 'contributor' ) );

		// A user who may edit their own draft pages but not read the drafts of other users.
		get_userdata( self::$user_ids['page_writer'] )->add_cap( 'edit_pages' );

		// A user who may publish posts but not edit published posts.
		get_userdata( self::$user_ids['publisher'] )->add_cap( 'publish_posts' );

		self::$post_ids['editor_draft_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);

		self::$post_ids['writer_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['page_writer'],
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Writer page',
			)
		);

		self::$post_ids['writer_child'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => self::$post_ids['writer_page'],
			)
		);

		self::$post_ids['writer_grandchild'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => self::$post_ids['writer_child'],
			)
		);

		// A page of the writer whose stored parent the writer may not read.
		self::$post_ids['writer_page_with_hidden_parent'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['page_writer'],
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Hidden parent',
				'post_parent' => self::$post_ids['editor_draft_page'],
			)
		);

		// An author whose role grants publish_posts, denied it for this user.
		self::$user_ids['restricted_author'] = $factory->user->create( array( 'role' => 'author' ) );
		get_userdata( self::$user_ids['restricted_author'] )->add_cap( 'publish_posts', false );

		self::$post_ids['restricted_author:draft'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['restricted_author'],
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Builds the input that updates a post with the given data.
	 *
	 * @since 7.3.0
	 *
	 * @param int                  $post_id The post ID.
	 * @param array<string, mixed> $data    The data.
	 * @param list<string>|null    $fields  Optional. The fields, or null for the default. Default null.
	 * @return array<string, mixed> The input.
	 */
	private function update_input( int $post_id, array $data, ?array $fields = null ): array {
		$input = array(
			'where' => array(
				'type' => get_post_type( $post_id ),
				'id'   => $post_id,
			),
			'data'  => $data,
		);
		if ( null !== $fields ) {
			$input['fields'] = $fields;
		}

		return $input;
	}

	/**
	 * Runs the update ability on a post with the given data.
	 *
	 * @since 7.3.0
	 *
	 * @param int                  $post_id The post ID.
	 * @param array<string, mixed> $data    The data.
	 * @param list<string>|null    $fields  The fields, or null for the default.
	 * @return mixed The ability result.
	 */
	private function update_post( int $post_id, array $data, ?array $fields = null ) {
		return $this->run_ability( $this->update_input( $post_id, $data, $fields ) );
	}

	/**
	 * Asserts that a result is the post item of a successful update and returns the stored post.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $result  The ability result.
	 * @param int   $post_id The ID of the updated post.
	 * @return WP_Post The stored post.
	 */
	private function assert_updated( $result, int $post_id ): WP_Post {
		$this->assertIsArray( $result, 'The update should succeed.' );
		$this->assertSame( $post_id, $result['id'] ?? null, 'The updated post should be returned with its ID.' );

		clean_post_cache( $post_id );

		return get_post( $post_id );
	}

	/**
	 * Asserts that a result is the 403 edit error.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 */
	protected function assert_cannot_write( $result, string $context ): void {
		$this->assert_error( $result, 'content_cannot_edit', 403, $context );
		$this->assertSame( 'Sorry, you are not allowed to edit this post.', $result->get_error_message(), "{$context}: the message should be the edit message." );
	}

	/**
	 * Creates a draft with a floating date, stored in the site's timezone with no GMT date.
	 *
	 * @since 7.3.0
	 *
	 * @param string $local The local date, as `Y-m-d H:i:s`.
	 * @return int The post ID.
	 */
	private function create_floating_draft( string $local ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_status' => 'draft',
				'post_title'  => 'Floating',
				'post_date'   => $local,
			)
		);

		$this->assertSame( '0000-00-00 00:00:00', get_post( $post_id )->post_date_gmt, 'Precondition: the draft should have a floating date.' );

		return $post_id;
	}

	/**
	 * Asserts that a stored GMT date is the current time, give or take a minute.
	 *
	 * @since 7.3.0
	 *
	 * @param string $gmt     The stored GMT date.
	 * @param string $context What the date is, for the assertion message.
	 */
	private function assert_is_now( string $gmt, string $context ): void {
		$this->assertEqualsWithDelta( time(), strtotime( $gmt . ' UTC' ), 60, "{$context} should be the current time." );
	}

	/**
	 * The input schema takes `where`, with the post type and ID, `data`, with at least one
	 * attribute, and `fields`.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_input_schema(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The update ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_input_schema();

		$this->assertSame( 'object', $schema['type'], 'The input should be an object.' );
		$this->assertFalse( $schema['additionalProperties'], 'The input should reject unknown keys.' );
		$this->assertSame( array( 'where', 'data' ), $schema['required'], 'The input should require where and data.' );
		$this->assertSame( array( 'where', 'data', 'fields' ), array_keys( $schema['properties'] ), 'The input should take where, data and fields.' );

		$where = $schema['properties']['where'];

		$this->assertSame( 'object', $where['type'], 'The where should be an object.' );
		$this->assertFalse( $where['additionalProperties'], 'The where should reject unknown keys.' );
		$this->assertSame( array( 'type', 'id' ), $where['required'], 'The where should require the type and the ID.' );
		$this->assertSame( array( 'type', 'id' ), array_keys( $where['properties'] ), 'The where should take the type and the ID.' );
		$this->assertSame( 'string', $where['properties']['type']['type'], 'The type should be a string.' );
		$this->assertSameSets( array( 'post', 'page' ), $where['properties']['type']['enum'], 'The type should list the exposed post types.' );
		$this->assertSame( 'integer', $where['properties']['id']['type'], 'The ID should be an integer.' );
		$this->assertSame( 1, $where['properties']['id']['minimum'], 'The ID should be at least 1.' );

		$data = $schema['properties']['data'];

		$this->assertSame( 'object', $data['type'], 'The data should be an object.' );
		$this->assertFalse( $data['additionalProperties'], 'The data should reject unknown keys.' );
		$this->assertSame( 1, $data['minProperties'], 'The data should hold at least one attribute.' );
		$this->assertArrayNotHasKey( 'required', $data, 'The data should require no attribute.' );
		$this->assertSame( array( 'title', 'content', 'excerpt', 'status', 'slug', 'date', 'author', 'parent' ), array_keys( $data['properties'] ), 'The data should take the allowed attributes and no post type.' );
		$this->assertSame( array( 'publish', 'future', 'draft', 'pending', 'private' ), $data['properties']['status']['enum'], 'The status should list the statuses that are not internal.' );
		$this->assertSame( 1, $data['properties']['slug']['minLength'], 'The slug should not be empty.' );
		$this->assertSame( 1, $data['properties']['author']['minimum'], 'The author should be at least 1.' );
		$this->assertSame( 0, $data['properties']['parent']['minimum'], 'The parent should be at least 0.' );
		$this->assertSame( 1, $data['properties']['date']['minProperties'], 'The date should hold at least one value.' );
		$this->assertSame( array( 'site', 'gmt' ), array_keys( $data['properties']['date']['properties'] ), 'The date should take a site and a GMT value.' );

		$create_schema = wp_get_ability( 'core/content-create' )->get_input_schema();
		$create_data   = $create_schema['properties']['data']['properties'];
		unset( $create_data['type'] );

		$this->assertSame( $this->strip_descriptions( $create_data ), $this->strip_descriptions( $data['properties'] ), 'The attributes should have the same schemas as on create.' );

		$query_schema = wp_get_ability( 'core/content-query' )->get_input_schema();

		$this->assertSame(
			$this->strip_descriptions( $query_schema['properties']['fields'] ),
			$this->strip_descriptions( $schema['properties']['fields'] ),
			'The fields should be the same list as the fields of the query.'
		);
	}

	/**
	 * The output is one post item, the same object as an item of the query.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_output_schema_is_a_query_item(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The update ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_output_schema();
		$item   = wp_get_ability( 'core/content-query' )->get_output_schema()['properties']['items']['items'];

		$this->assertSame( array( 'id' ), $schema['required'], 'The output should always hold the ID.' );
		$this->assertSame( $this->strip_descriptions( $item ), $this->strip_descriptions( $schema ), 'The output should be the same object as a query item.' );
	}

	/**
	 * A new title and excerpt are stored, and the post is returned in the chosen fields.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_updates_the_title_and_excerpt(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'draft',
				'post_title'  => 'Launch notes',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'title'   => 'Launch notes, updated',
				'excerpt' => 'What shipped and what is next.',
			),
			array( 'modified', 'title', 'excerpt' )
		);

		$this->assert_updated( $result, $post_id );

		$this->assertSameSets( array( 'id', 'modified', 'title', 'excerpt' ), array_keys( $result ), 'The ID and the chosen fields should be returned.' );
		$this->assertSameSets( array( 'site', 'gmt' ), array_keys( $result['modified'] ), 'The modified date should be given in site time and GMT.' );
		$this->assertNotFalse( rest_parse_date( $result['modified']['gmt'] ), 'The modified GMT date should be a date-time.' );
		$this->assertSame(
			array(
				'raw'      => 'Launch notes, updated',
				'rendered' => 'Launch notes, updated',
			),
			$result['title'],
			'The new title should be returned raw and rendered.'
		);
		$this->assertSame(
			array(
				'raw'       => 'What shipped and what is next.',
				'rendered'  => "<p>What shipped and what is next.</p>\n",
				'protected' => false,
			),
			$result['excerpt'],
			'The new excerpt should be returned raw, rendered and unprotected.'
		);
	}

	/**
	 * A draft is published by setting its status.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_publishes_the_draft(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'draft',
				'post_title'  => 'Launch notes, updated',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'status' => 'publish' ), array( 'status', 'slug', 'link' ) );

		$this->assertSame(
			array(
				'id'     => $post_id,
				'status' => 'publish',
				'slug'   => 'launch-notes-updated',
				'link'   => home_url( '/launch-notes-updated/' ),
			),
			$result,
			'The published post should be returned in the chosen fields.'
		);
	}

	/**
	 * The lookup comes before everything else: a missing post with invalid data is not found.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_lookup_comes_before_the_attribute_rules(): void {
		$this->login_as( 'subscriber' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$input         = $this->build_post_input( self::MISSING_ID, 'post' );
		$input['data'] = array(
			'status' => 'trash',
			'parent' => 1,
			'title'  => array( 'x' ),
		);

		$before = $this->snapshot( self::$post_ids['administrator:draft'] );
		$result = $execute( $input );

		$this->assert_not_found( $result, 'A missing post with invalid data' );
		$this->assertSame( $before, $this->snapshot( self::$post_ids['administrator:draft'] ), 'Nothing should be written for a missing post.' );
	}

	/**
	 * Each role may update the posts it may edit, in any status, and is refused for the others,
	 * whatever their status.
	 *
	 * @ticket 66706
	 * @dataProvider data_capability_matrix
	 * @since 7.3.0
	 *
	 * @param string $role    The role to update as.
	 * @param string $owner   The role of the author of the post, or `other`.
	 * @param string $status  The matrix status of the post.
	 * @param bool   $allowed Whether the update is allowed.
	 */
	public function test_update_matrix( string $role, string $owner, string $status, bool $allowed ): void {
		$post_id       = self::$post_ids[ "{$owner}:{$status}" ];
		$stored_status = get_post_status( $post_id );

		$this->login_as( $role );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'title' => 'Matrix update' ), array( 'status', 'title.rendered' ) );

		if ( ! $allowed ) {
			$this->assert_refused( $result, 'Updating a post the role may not edit' );
			$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
			return;
		}

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'Matrix update', $post->post_title, 'The title should be stored.' );
		$this->assertSame( $stored_status, $post->post_status, 'The status should not change.' );
		$this->assertSame( $stored_status, $result['status'], 'The stored status should be returned.' );
		$this->assertSame( array( 'rendered' => 'Matrix update' ), $result['title'], 'The new title should be returned.' );
	}

	/**
	 * Returns `data` values that are invalid in some way.
	 *
	 * @return array<string, array{0: array<string, mixed>}> The data.
	 */
	public static function data_invalid_data(): array {
		return array(
			'parent on a post'               => array( array( 'parent' => 1 ) ),
			'trash'                          => array( array( 'status' => 'trash' ) ),
			'private without publish_posts'  => array( array( 'status' => 'private' ) ),
			'slug that sanitizes to nothing' => array( array( 'slug' => '!!!' ) ),
			'missing author'                 => array( array( 'author' => self::MISSING_ID ) ),
			'title that is a list'           => array( array( 'title' => array( 'x' ) ) ),
			'invalid date'                   => array( array( 'date' => array( 'site' => 'not a date' ) ) ),
			'empty data'                     => array( array() ),
		);
	}

	/**
	 * Called directly by a user who may not edit the post, the call fails on the capability before
	 * anything about the attributes is checked.
	 *
	 * @ticket 66706
	 * @dataProvider data_invalid_data
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The data.
	 */
	public function test_direct_call_without_edit_post_fails_before_the_attributes( array $data ): void {
		$post_id = self::$post_ids['admin_post'];

		$this->login_as( 'contributor' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );

		$this->assert_cannot_write( $execute( $this->update_input( $post_id, $data ) ), 'A direct call without edit_post' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns keys that are not part of the input, and where they are sent.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}> The cases.
	 */
	public static function data_keys_outside_the_allow_list(): array {
		return array(
			'data ID'                => array( 'data', 'ID', 1 ),
			'data type'              => array( 'data', 'type', 'page' ),
			'data post_type'         => array( 'data', 'post_type', 'page' ),
			'data post_password'     => array( 'data', 'post_password', 'secret' ),
			'data meta_input'        => array( 'data', 'meta_input', array( 'cw_key' => 'value' ) ),
			'data tax_input'         => array( 'data', 'tax_input', array( 'category' => array( 1 ) ) ),
			'data comment_status'    => array( 'data', 'comment_status', 'closed' ),
			'data post_author'       => array( 'data', 'post_author', 1 ),
			'data import_id'         => array( 'data', 'import_id', 7654321 ),
			'data guid'              => array( 'data', 'guid', 'https://example.org/forged' ),
			'data page_template'     => array( 'data', 'page_template', 'cw-template.php' ),
			'data modified'          => array( 'data', 'modified', '2001-01-01T00:00:00' ),
			'data post_modified'     => array( 'data', 'post_modified', '2001-01-01 00:00:00' ),
			'data post_modified_gmt' => array( 'data', 'post_modified_gmt', '2001-01-01 00:00:00' ),
			'data sticky'            => array( 'data', 'sticky', true ),
			'data tags_input'        => array( 'data', 'tags_input', array( 'cw-tag' ) ),
			'data post_category'     => array( 'data', 'post_category', array( 2 ) ),
			'data menu_order'        => array( 'data', 'menu_order', 5 ),
			'data post_mime_type'    => array( 'data', 'post_mime_type', 'image/jpeg' ),
			'where status'           => array( 'where', 'status', 'draft' ),
			'where ID'               => array( 'where', 'ID', 1 ),
		);
	}

	/**
	 * An internal or unregistered status fails input validation.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_that_can_never_be_set
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_status_that_can_never_be_set_fails_input_validation( string $status ): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->assert_invalid_input( $this->update_input( $post_id, array( 'status' => $status ) ), $post_id, 'A status that can never be set' );
	}

	/**
	 * Called directly, an internal or unregistered status is an invalid status for a post that
	 * is not stored in it.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_that_can_never_be_set
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_direct_call_rejects_a_status_that_can_never_be_set( string $status ): void {
		$post_id = self::$post_ids['administrator:publish'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute( $this->update_input( $post_id, array( 'status' => $status ) ) );

		$this->assert_invalid_attribute( $result, 'status', 'A direct call with a status that can never be set' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns status changes that a contributor, and an author denied `publish_posts`, may not
	 * make on their own draft.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3?: string}> The cases.
	 */
	public static function data_status_changes_without_publish_posts(): array {
		$publish_message = 'Sorry, you are not allowed to publish posts in this post type.';
		$private_message = 'Sorry, you are not allowed to create private posts in this post type.';

		return array(
			'publish by a contributor'                  => array( 'contributor', 'publish', $publish_message ),
			'future by a contributor'                   => array( 'contributor', 'future', $publish_message ),
			'private by a contributor'                  => array( 'contributor', 'private', $private_message ),
			'publish by an author denied publish_posts' => array( 'restricted_author', 'publish', $publish_message ),
			'future by an author denied publish_posts'  => array( 'restricted_author', 'future', $publish_message ),
			'private by an author denied publish_posts' => array( 'restricted_author', 'private', $private_message ),
			'publish from the Trash by a contributor'   => array( 'contributor', 'publish', $publish_message, 'trash_draft' ),
		);
	}

	/**
	 * A status that makes a post visible needs `publish_posts`, also to move a post out of the
	 * Trash.
	 *
	 * @ticket 66706
	 * @dataProvider data_status_changes_without_publish_posts
	 * @since 7.3.0
	 *
	 * @param string $role    The role or fixture name of the user, who owns the post.
	 * @param string $status  The status.
	 * @param string $message The error message.
	 * @param string $stored  Optional. The matrix status of the post. Default 'draft'.
	 */
	public function test_status_that_makes_a_post_visible_needs_publish_posts( string $role, string $status, string $message, string $stored = 'draft' ): void {
		$post_id = self::$post_ids[ "{$role}:{$stored}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$this->assertFalse( current_user_can( 'publish_posts' ), 'Precondition: the user should not be able to publish posts.' );

		$data = array( 'status' => $status );
		if ( 'future' === $status ) {
			$data['date'] = array( 'gmt' => self::FUTURE_GMT );
		}

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, $data );

		$this->assert_error( $result, 'content_cannot_publish', 403, 'A status that makes a post visible without publish_posts' );
		$this->assertSame( $message, $result->get_error_message(), 'The message should name what the role may not do.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A status equal to the stored status changes nothing and passes with no check.
	 *
	 * A contributor may edit their own private post, but may not make a post private.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_status_equal_to_the_stored_status_passes_with_no_check(): void {
		$post_id = self::$post_ids['contributor:private'];

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'status' => 'private',
				'title'  => 'Still private',
			)
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'private', $post->post_status, 'The post should stay private.' );
		$this->assertSame( 'Still private', $post->post_title, 'The title should be stored.' );
	}

	/**
	 * A draft is scheduled with `future` and a date in the future.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_schedules_a_draft(): void {
		$post_id = self::$post_ids['author:draft'];

		$this->login_as( 'author' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'status' => 'future',
				'date'   => array( 'gmt' => self::FUTURE_GMT ),
			),
			array( 'status', 'date' )
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'future', $post->post_status, 'The post should be scheduled.' );
		$this->assertSame( '2099-06-01 12:00:00', $post->post_date_gmt, 'The post should be scheduled for the sent date.' );
		$this->assertSame( 'future', $result['status'], 'The scheduled status should be returned.' );
	}

	/**
	 * Returns status changes that unpublish a post or move a post out of the Trash.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}> The role, which owns the post,
	 *                                                               the matrix status of the post,
	 *                                                               and the sent status.
	 */
	public static function data_status_changes(): array {
		return array(
			'draft from publish by an editor'       => array( 'editor', 'publish', 'draft' ),
			'pending from publish by an editor'     => array( 'editor', 'publish', 'pending' ),
			'draft from the Trash by a contributor' => array( 'contributor', 'trash_draft', 'draft' ),
			'pending from the Trash by an author'   => array( 'author', 'trash_draft', 'pending' ),
			'publish from the Trash by an author'   => array( 'author', 'trash_draft', 'publish' ),
		);
	}

	/**
	 * A published post can be unpublished, and sending a status moves a post out of the Trash
	 * through a plain update.
	 *
	 * @ticket 66706
	 * @dataProvider data_status_changes
	 * @since 7.3.0
	 *
	 * @param string $role   The role, which owns the post.
	 * @param string $stored The matrix status of the post.
	 * @param string $status The sent status.
	 */
	public function test_status_change_is_stored( string $role, string $stored, string $status ): void {
		$post_id = self::$post_ids[ "{$role}:{$stored}" ];

		$this->login_as( $role );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'status' => $status ), array( 'status' ) );

		$this->assertSame( $status, $this->assert_updated( $result, $post_id )->post_status, 'The post should be stored in the sent status.' );
		$this->assertSame( $status, $result['status'], 'The new status should be returned.' );
	}

	/**
	 * Returns dates that change the stored status of a post.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}> The cases.
	 */
	public static function data_statuses_reported_as_stored(): array {
		return array(
			'publish with a future date' => array( 'publish', self::FUTURE_GMT, 'future' ),
			'future with a past date'    => array( 'future', '2016-12-12T19:00:00', 'publish' ),
		);
	}

	/**
	 * The regular save rules decide the stored status, and the output reports it.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_reported_as_stored
	 * @since 7.3.0
	 *
	 * @param string $status   The requested status.
	 * @param string $gmt      The requested GMT date.
	 * @param string $expected The stored status.
	 */
	public function test_output_reports_the_stored_status( string $status, string $gmt, string $expected ): void {
		$post_id = self::$post_ids['editor:draft'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'status' => $status,
				'date'   => array( 'gmt' => $gmt ),
			),
			array( 'status' )
		);

		$this->assertSame( $expected, $this->assert_updated( $result, $post_id )->post_status, 'The post should be stored in the expected status.' );
		$this->assertSame( $expected, $result['status'], 'The output should report the stored status.' );
	}

	/**
	 * A user without `edit_others_posts` gets the same error for an existing and a missing
	 * author, so the error never tells whether a user exists.
	 *
	 * @ticket 66706
	 * @dataProvider data_authors_named_without_edit_others_posts
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_naming_another_author_without_edit_others_posts_is_refused( string $author ): void {
		$post_id = self::$post_ids['author:draft'];

		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'author' => self::$user_ids[ $author ] ?? self::MISSING_ID ) );

		$this->assertWPError( $result, 'Naming another author should fail.' );
		$this->assertSame(
			array(
				'code'    => 'content_cannot_edit_others',
				'message' => 'Sorry, you are not allowed to update posts as this user.',
				'data'    => array( 'status' => 403 ),
			),
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			),
			'The error should be the same whether the user exists or not.'
		);
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A user with `edit_others_posts` may change the author of a post.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_changes_the_author(): void {
		$post_id = self::$post_ids['editor:draft'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'author' => self::$user_ids['author'] ), array( 'author.id' ) );

		$this->assertSame( self::$user_ids['author'], (int) $this->assert_updated( $result, $post_id )->post_author, 'The new author should be stored.' );
		$this->assertSame( array( 'id' => self::$user_ids['author'] ), $result['author'], 'The new author should be returned.' );
	}

	/**
	 * An author equal to the stored author changes nothing and passes with no check, even when
	 * that user no longer exists.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_author_equal_to_the_stored_author_passes_with_no_check(): void {
		global $wpdb;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_status' => 'draft',
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_author' => self::MISSING_ID ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'author' => self::MISSING_ID,
				'title'  => 'Orphan',
			)
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'Orphan', $post->post_title, 'The title should be stored.' );
		$this->assertSame( self::MISSING_ID, (int) $post->post_author, 'The stored author should not change.' );
	}

	/**
	 * A user with `edit_others_posts` who names a missing user gets the invalid author error.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_naming_a_missing_author_with_edit_others_posts_is_an_invalid_author(): void {
		$post_id = self::$post_ids['editor:draft'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'author' => self::MISSING_ID ) );

		$this->assert_invalid_attribute( $result, 'author', 'A missing author' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * On multisite, a user who is not a member of the site cannot be the author.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_naming_an_author_who_is_not_a_member_of_the_site_is_an_invalid_author(): void {
		$non_member = self::factory()->user->create( array( 'role' => 'author' ) );
		remove_user_from_blog( $non_member, get_current_blog_id() );

		$post_id = self::$post_ids['editor:draft'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'author' => $non_member ) );

		$this->assert_invalid_attribute( $result, 'author', 'An author who is not a member of the site' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns parents that a page may not have.
	 *
	 * @return array<string, array{0: string}> The parents, as fixture names.
	 */
	public static function data_parents_that_cannot_be_used(): array {
		return array(
			'missing post'               => array( 'missing' ),
			'post of another type'       => array( 'admin_post' ),
			'page the user may not read' => array( 'editor_draft_page' ),
			'the page itself'            => array( 'writer_page' ),
			'a child'                    => array( 'writer_child' ),
			'a grandchild'               => array( 'writer_grandchild' ),
			'revision'                   => array( 'revision' ),
		);
	}

	/**
	 * A missing parent, a parent of another type, a parent the user may not read and a parent
	 * that would make a loop give one identical error.
	 *
	 * @ticket 66706
	 * @dataProvider data_parents_that_cannot_be_used
	 * @since 7.3.0
	 *
	 * @param string $parent_key The parent, as a fixture name.
	 */
	public function test_parent_that_cannot_be_used_gives_one_identical_error( string $parent_key ): void {
		$post_id = self::$post_ids['writer_page'];

		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'Precondition: the user should be able to edit the page.' );
		$this->assertFalse( current_user_can( 'read_post', self::$post_ids['editor_draft_page'] ), 'Precondition: the user should not be able to read the draft page of the editor.' );

		// The reference names another missing ID than the missing post row, so no row compares a call with itself.
		$before    = $this->snapshot( $post_id );
		$reference = $this->update_post( $post_id, array( 'parent' => self::MISSING_ID + 1 ) );
		$result    = $this->update_post( $post_id, array( 'parent' => self::$post_ids[ $parent_key ] ?? self::MISSING_ID ) );

		$this->assertWPError( $reference, 'The reference call with a missing parent should fail.' );
		$this->assert_invalid_attribute( $result, 'parent', 'A parent that cannot be used' );
		$this->assertSame( $reference->get_error_data(), $result->get_error_data(), 'The error data, with its reason, should be the same as for a missing parent.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A parent equal to the stored parent changes nothing and passes with no check, even when the
	 * user may not read it.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_parent_equal_to_the_stored_parent_passes_with_no_check(): void {
		$post_id = self::$post_ids['writer_page_with_hidden_parent'];

		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'parent' => self::$post_ids['editor_draft_page'],
				'title'  => 'Same parent',
			)
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'Same parent', $post->post_title, 'The title should be stored.' );
		$this->assertSame( self::$post_ids['editor_draft_page'], $post->post_parent, 'The stored parent should not change.' );
	}

	/**
	 * Returns parents a page may be moved to.
	 *
	 * @return array<string, array{0: string|null}> The parents, as fixture names, or null for 0.
	 */
	public static function data_parents_that_can_be_used(): array {
		return array(
			'readable page' => array( 'admin_page' ),
			'top level'     => array( null ),
		);
	}

	/**
	 * A readable page of the same type can be the parent, and `0` makes the page a top-level page.
	 *
	 * @ticket 66706
	 * @dataProvider data_parents_that_can_be_used
	 * @since 7.3.0
	 *
	 * @param string|null $parent_key The parent, as a fixture name, or null for 0.
	 */
	public function test_moves_a_page_to_another_parent( ?string $parent_key ): void {
		$post_id   = self::$post_ids['writer_page_with_hidden_parent'];
		$parent_id = null === $parent_key ? 0 : self::$post_ids[ $parent_key ];

		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'parent' => $parent_id ), array( 'parent' ) );

		$this->assertSame( $parent_id, $this->assert_updated( $result, $post_id )->post_parent, 'The new parent should be stored.' );
		$this->assertSame( $parent_id, $result['parent'], 'The new parent should be returned.' );
	}

	/**
	 * Returns attributes that a post type does not support.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}> The cases.
	 */
	public static function data_unsupported_attributes(): array {
		return array(
			'excerpt of a page'  => array( 'admin_page', 'excerpt', 'An excerpt' ),
			'parent of a post'   => array( 'admin_post', 'parent', 1 ),
			'parent 0 of a post' => array( 'admin_post', 'parent', 0 ),
		);
	}

	/**
	 * An attribute the post type does not support is rejected, even when it would change nothing.
	 *
	 * @ticket 66706
	 * @dataProvider data_unsupported_attributes
	 * @since 7.3.0
	 *
	 * @param string $post      The post, as a fixture name.
	 * @param string $attribute The attribute.
	 * @param mixed  $value     The value.
	 */
	public function test_unsupported_attribute_is_rejected( string $post, string $attribute, $value ): void {
		$post_id = self::$post_ids[ $post ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( $attribute => $value ) );

		$this->assert_invalid_attribute( $result, $attribute, 'An unsupported attribute' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns attribute values of the wrong type.
	 *
	 * @return array<string, array{0: string, 1: mixed}> The cases.
	 */
	public static function data_values_of_the_wrong_type(): array {
		return array(
			'title that is a number'       => array( 'title', 123 ),
			'content that is null'         => array( 'content', null ),
			'status that is a list'        => array( 'status', array( 'draft' ) ),
			'date that is a string'        => array( 'date', '2016-12-12T14:00:00' ),
			'date that cannot be parsed'   => array( 'date', array( 'site' => 'not a date' ) ),
			'date that is not a date-time' => array( 'date', array( 'gmt' => '2010-60-01T02:00:00Z' ) ),
			'author that is a fraction'    => array( 'author', 1.5 ),
			'author that is text'          => array( 'author', '1abc' ),
		);
	}

	/**
	 * A value of the wrong type fails input validation.
	 *
	 * @ticket 66706
	 * @dataProvider data_values_of_the_wrong_type
	 * @since 7.3.0
	 *
	 * @param string $attribute The attribute.
	 * @param mixed  $value     The value.
	 */
	public function test_value_of_the_wrong_type_fails_input_validation( string $attribute, $value ): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->assert_invalid_input( $this->update_input( $post_id, array( $attribute => $value ) ), $post_id, 'A value of the wrong type' );
	}

	/**
	 * Attributes that were not sent keep their stored values.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_attributes_not_sent_keep_their_stored_values(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['author'],
				'post_status'  => 'publish',
				'post_title'   => 'Kept title',
				'post_content' => 'Kept content.',
				'post_excerpt' => 'Kept excerpt.',
				'post_name'    => 'kept-slug',
				'post_date'    => '2016-12-12 14:00:00',
			)
		);
		$stored  = get_post( $post_id );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'content' => 'New content.' ) ), $post_id );

		$this->assertSame( 'New content.', $post->post_content, 'The content should be stored.' );
		$this->assertSame( $stored->post_title, $post->post_title, 'The title should be kept.' );
		$this->assertSame( $stored->post_excerpt, $post->post_excerpt, 'The excerpt should be kept.' );
		$this->assertSame( $stored->post_name, $post->post_name, 'The slug should be kept.' );
		$this->assertSame( $stored->post_status, $post->post_status, 'The status should be kept.' );
		$this->assertSame( $stored->post_author, $post->post_author, 'The author should be kept.' );
		$this->assertSame( $stored->post_date_gmt, $post->post_date_gmt, 'The date should be kept.' );
	}

	/**
	 * A slug that sanitizes to nothing is an invalid slug.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_slug_that_sanitizes_to_nothing_is_rejected(): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'slug' => '!!!' ) );

		$this->assert_invalid_attribute( $result, 'slug', 'A slug that sanitizes to nothing' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns updates that send a slug for a post that is, or becomes, pending.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}> The posts, as fixture names, and the data.
	 */
	public static function data_slugs_of_pending_posts(): array {
		return array(
			'draft moved to pending' => array(
				'contributor:draft',
				array(
					'status' => 'pending',
					'slug'   => 'pending-slug',
				),
			),
			'pending post'           => array( 'contributor:pending', array( 'slug' => 'pending-slug' ) ),
		);
	}

	/**
	 * A user who may not publish the post cannot set the slug of a pending post.
	 *
	 * Intentionally strict: the save would drop the slug, so it is an error instead of being
	 * dropped without notice.
	 *
	 * @ticket 66706
	 * @dataProvider data_slugs_of_pending_posts
	 * @since 7.3.0
	 *
	 * @param string                $post The post, as a fixture name.
	 * @param array<string, string> $data The `data` input.
	 */
	public function test_slug_of_a_pending_post_is_rejected_without_publish_posts( string $post, array $data ): void {
		$post_id = self::$post_ids[ $post ];

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, $data );

		$this->assert_invalid_attribute( $result, 'slug', 'A slug on a pending post without publish_posts' );
		$this->assertSame( 'The slug of a pending post can only be set by a user who can publish the post.', $result->get_error_message(), 'The message should say who may set the slug.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written for a slug that cannot be stored.' );
	}

	/**
	 * A user who may publish the post sets the slug of a pending post.
	 *
	 * @ticket 66706
	 * @dataProvider data_slugs_of_pending_posts
	 * @since 7.3.0
	 *
	 * @param string                $post The post, as a fixture name.
	 * @param array<string, string> $data The `data` input.
	 */
	public function test_slug_of_a_pending_post_is_stored_with_publish_posts( string $post, array $data ): void {
		$post_id = self::$post_ids[ $post ];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$stored = $this->assert_updated( $this->update_post( $post_id, $data ), $post_id );

		$this->assertSame( 'pending', $stored->post_status, 'The post should be pending.' );
		$this->assertSame( 'pending-slug', $stored->post_name, 'The slug of the pending post should be stored.' );
	}

	/**
	 * Returns posts that take the slug of another post.
	 *
	 * @return array<string, array{0: string, 1: string|null}> The cases.
	 */
	public static function data_posts_taking_a_used_slug(): array {
		return array(
			'stored draft'       => array( 'administrator:draft', null ),
			'stored pending'     => array( 'administrator:pending', null ),
			'draft from publish' => array( 'administrator:publish', 'draft' ),
			'published post'     => array( 'administrator:publish', null ),
		);
	}

	/**
	 * A post never takes the slug of another post, also as a draft or a pending post.
	 *
	 * @ticket 66706
	 * @dataProvider data_posts_taking_a_used_slug
	 * @since 7.3.0
	 *
	 * @param string      $post   The post, as a fixture name.
	 * @param string|null $status The status sent next to the slug, or null to send none.
	 */
	public function test_slug_of_another_post_is_made_unique( string $post, ?string $status ): void {
		$post_id = self::$post_ids[ $post ];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$data = array( 'slug' => 'Shared Slug' );
		if ( null !== $status ) {
			$data['status'] = $status;
		}

		$result = $this->update_post( $post_id, $data, array( 'slug' ) );

		$this->assertSame( 'shared-slug-2', $this->assert_updated( $result, $post_id )->post_name, 'The sanitized slug should be made unique.' );
	}

	/**
	 * Returns parents for a draft page that takes the slug of an existing child page.
	 *
	 * @return array<string, array{0: string, 1: string}> The cases.
	 */
	public static function data_draft_page_slugs_by_parent(): array {
		return array(
			'shared parent sent'    => array( 'writer_page', 'writer-child-2' ),
			'different parent sent' => array( 'admin_page', 'writer-child' ),
		);
	}

	/**
	 * A draft page is made unique among the pages that share the sent parent.
	 *
	 * @ticket 66706
	 * @dataProvider data_draft_page_slugs_by_parent
	 * @since 7.3.0
	 *
	 * @param string $parent_key The parent, as a fixture name.
	 * @param string $expected   The stored slug.
	 */
	public function test_draft_page_slug_is_unique_among_its_siblings( string $parent_key, string $expected ): void {
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => self::$post_ids['writer_page'],
				'post_name'   => 'writer-child',
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Moving page',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'slug'   => 'writer-child',
				'parent' => self::$post_ids[ $parent_key ],
			)
		);

		$this->assertSame( $expected, $this->assert_updated( $result, $post_id )->post_name, 'The slug should be unique among the siblings.' );
	}

	/**
	 * Returns updates without a new date of a floating draft, 2099-01-15 10:00 in
	 * America/New_York, with the status they store.
	 *
	 * The date is in winter because PHP versions before 8.1 apply no daylight saving time
	 * after 2037.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}> The data, and the stored status.
	 */
	public static function data_updates_without_a_new_date_of_a_floating_draft(): array {
		return array(
			'title'                             => array( array( 'title' => 'Still floating' ), 'draft' ),
			'pending'                           => array( array( 'status' => 'pending' ), 'pending' ),
			'title with the site date it shows' => array(
				array(
					'date'  => array( 'site' => '2099-01-15T10:00:00' ),
					'title' => 'Same date',
				),
				'draft',
			),
			'title with the GMT date it shows'  => array(
				array(
					'date'  => array( 'gmt' => '2099-01-15T15:00:00' ),
					'title' => 'Same date',
				),
				'draft',
			),
		);
	}

	/**
	 * An update without a date keeps a floating draft floating, also when it moves the draft
	 * to pending. Sending back the date the draft shows counts as not sent and never pins it.
	 *
	 * @ticket 66706
	 * @dataProvider data_updates_without_a_new_date_of_a_floating_draft
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data   The data.
	 * @param string               $status The stored status.
	 */
	public function test_update_without_a_new_date_keeps_a_draft_floating( array $data, string $status ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = $this->create_floating_draft( '2099-01-15 10:00:00' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, $data ), $post_id );

		$this->assertSame( $status, $post->post_status, "The post should be stored as {$status}." );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'The post should keep floating.' );
	}

	/**
	 * Returns statuses that do not float, reached from a floating draft without a date.
	 *
	 * @return array<string, array{0: string}> The statuses.
	 */
	public static function data_statuses_that_stamp_the_current_time(): array {
		return array(
			'publish' => array( 'publish' ),
			'future'  => array( 'future' ),
		);
	}

	/**
	 * Publishing a floating draft without a date stamps the current time, also when it is
	 * sent as scheduled, and with a past local date.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_that_stamp_the_current_time
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_publishing_a_floating_draft_without_a_date_stamps_the_current_time( string $status ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = $this->create_floating_draft( '2020-01-01 10:00:00' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'status' => $status ) ), $post_id );

		$this->assertSame( 'publish', $post->post_status, 'The post should be published.' );
		$this->assert_is_now( $post->post_date_gmt, 'The GMT date' );
	}

	/**
	 * Returns dates that differ from the stored ones, in America/New_York.
	 *
	 * @return array<string, array{0: array<string, string>}> The dates, each 2017-01-01 10:00 local.
	 */
	public static function data_new_dates(): array {
		return array(
			'site'                => array( array( 'site' => '2017-01-01T10:00:00' ) ),
			'gmt'                 => array( array( 'gmt' => '2017-01-01T15:00:00' ) ),
			'site with an offset' => array( array( 'site' => '2017-01-01T16:00:00+01:00' ) ),
			'both, the site wins' => array(
				array(
					'site' => '2017-01-01T10:00:00',
					'gmt'  => '2001-01-01T00:00:00',
				),
			),
		);
	}

	/**
	 * A date that differs from the stored one is stored as sent, local and GMT.
	 *
	 * @ticket 66706
	 * @dataProvider data_new_dates
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date The sent date.
	 */
	public function test_new_date_is_stored_local_and_gmt( array $date ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['editor'],
				'post_status'   => 'publish',
				'post_date'     => '2016-12-12 14:00:00',
				'post_date_gmt' => '2016-12-12 19:00:00',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'date' => $date ) ), $post_id );

		$this->assertSame( '2017-01-01 10:00:00', $post->post_date, 'The local date should be stored.' );
		$this->assertSame( '2017-01-01 15:00:00', $post->post_date_gmt, 'The GMT date should be stored.' );
	}

	/**
	 * A new date pins a floating draft.
	 *
	 * @ticket 66706
	 * @dataProvider data_new_dates
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date The sent date.
	 */
	public function test_new_date_pins_a_floating_draft( array $date ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = $this->create_floating_draft( '2099-01-15 10:00:00' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'date' => $date ) ), $post_id );

		$this->assertSame( 'draft', $post->post_status, 'The post should stay a draft.' );
		$this->assertSame( '2017-01-01 10:00:00', $post->post_date, 'The local date should be stored.' );
		$this->assertSame( '2017-01-01 15:00:00', $post->post_date_gmt, 'The draft should no longer float.' );
	}

	/**
	 * Returns past dates equal to the date a floating draft shows, 2020-01-01 10:00 in
	 * America/New_York.
	 *
	 * @return array<string, array{0: array<string, string>}> The dates.
	 */
	public static function data_past_dates_equal_to_the_floating_date(): array {
		return array(
			'site' => array( array( 'site' => '2020-01-01T10:00:00' ) ),
			'gmt'  => array( array( 'gmt' => '2020-01-01T15:00:00' ) ),
		);
	}

	/**
	 * Publishing a floating draft with the past date it shows publishes it at the current
	 * time, as if no date was sent.
	 *
	 * @ticket 66706
	 * @dataProvider data_past_dates_equal_to_the_floating_date
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date The sent date.
	 */
	public function test_publishing_with_the_past_floating_date_stamps_the_current_time( array $date ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = $this->create_floating_draft( '2020-01-01 10:00:00' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$post = $this->assert_updated(
			$this->update_post(
				$post_id,
				array(
					'date'   => $date,
					'status' => 'publish',
				)
			),
			$post_id
		);

		$this->assertSame( 'publish', $post->post_status, 'The post should be published.' );
		$this->assert_is_now( $post->post_date_gmt, 'The GMT date of the published post' );
	}

	/**
	 * Returns statuses that do not float, for scheduling a floating draft for the date it shows.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}> The cases.
	 */
	public static function data_schedules_a_floating_draft_for_the_date_it_shows(): array {
		return array(
			'future, site'  => array( 'future', array( 'site' => '2099-01-15T10:00:00' ) ),
			'future, gmt'   => array( 'future', array( 'gmt' => '2099-01-15T15:00:00' ) ),
			'publish, site' => array( 'publish', array( 'site' => '2099-01-15T10:00:00' ) ),
			'publish, gmt'  => array( 'publish', array( 'gmt' => '2099-01-15T15:00:00' ) ),
		);
	}

	/**
	 * Scheduling a floating draft for the future date it shows schedules it for that date.
	 *
	 * @ticket 66706
	 * @dataProvider data_schedules_a_floating_draft_for_the_date_it_shows
	 * @since 7.3.0
	 *
	 * @param string                $status The status.
	 * @param array<string, string> $date   The sent date.
	 */
	public function test_scheduling_a_floating_draft_for_the_date_it_shows( string $status, array $date ): void {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = $this->create_floating_draft( '2099-01-15 10:00:00' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'date'   => $date,
				'status' => $status,
			),
			array( 'status' )
		);
		$post   = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'future', $post->post_status, 'The post should be scheduled.' );
		$this->assertSame( '2099-01-15 10:00:00', $post->post_date, 'The local date should be the sent date.' );
		$this->assertSame( '2099-01-15 15:00:00', $post->post_date_gmt, 'The GMT date should be the sent date.' );
		$this->assertSame( 'future', $result['status'], 'The scheduled status should be returned.' );
	}

	/**
	 * The markup of an author is filtered on update.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_markup_of_an_author_is_filtered(): void {
		$post_id = self::$post_ids['author:draft'];

		$this->login_as( 'author' );
		$this->register_abilities();

		$markup = '<a href="#" target="_blank" unfiltered=true>link</a>';
		$result = $this->update_post(
			$post_id,
			array(
				'title'   => $markup,
				'content' => $markup,
			),
			array( 'title.raw', 'content.raw' )
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( '<a href="#">link</a>', $post->post_title, 'The stored title should be filtered.' );
		$this->assertSame( '<a href="#" target="_blank">link</a>', $post->post_content, 'The stored content should be filtered.' );
		$this->assertSame( '<a href="#">link</a>', $result['title']['raw'], 'The returned title should be filtered.' );
	}

	/**
	 * The markup of an administrator with `unfiltered_html` is stored unchanged on update.
	 *
	 * @group ms-excluded
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_markup_of_an_administrator_is_not_filtered(): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$markup = '<div>div</div> <script>oh noes</script>';
		$post   = $this->assert_updated( $this->update_post( $post_id, array( 'content' => $markup ) ), $post_id );

		$this->assertSame( $markup, $post->post_content, 'The stored content should not be filtered.' );
	}

	/**
	 * On multisite, the markup of a site administrator is filtered on update.
	 *
	 * @group ms-required
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_markup_of_a_site_administrator_is_filtered_on_multisite(): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'content' => '<div>div</div> <script>oh noes</script>' ) ), $post_id );

		$this->assertSame( '<div>div</div> ', $post->post_content, 'The stored content should be filtered.' );
	}

	/**
	 * Quotes, backslashes and non-ASCII text survive a round trip unchanged.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_quotes_backslashes_and_non_ascii_text_survive_a_round_trip(): void {
		$text    = 'He said "it\'s done" \\ C:\\path\\to\\file \\\\server – café, naïve, 日本語, emoji ✓';
		$post_id = self::$post_ids['editor:draft'];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'title'   => $text,
				'content' => $text,
				'excerpt' => $text,
			),
			array( 'title.raw', 'content.raw', 'excerpt.raw' )
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( $text, $post->post_title, 'The stored title should be unchanged.' );
		$this->assertSame( $text, $post->post_content, 'The stored content should be unchanged.' );
		$this->assertSame( $text, $post->post_excerpt, 'The stored excerpt should be unchanged.' );
		$this->assertSame( $text, $result['title']['raw'], 'The returned title should be unchanged.' );
		$this->assertSame( $text, $result['content']['raw'], 'The returned content should be unchanged.' );
		$this->assertSame( $text, $result['excerpt']['raw'], 'The returned excerpt should be unchanged.' );
	}

	/**
	 * Raw fields are left out when the writer can no longer edit the post after the write.
	 *
	 * The user may publish posts but not edit published posts.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_raw_fields_are_left_out_when_the_writer_can_no_longer_edit_the_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['publisher'],
				'post_status'  => 'draft',
				'post_title'   => 'Publish once',
				'post_content' => 'Body.',
			)
		);

		$this->login_as( 'publisher' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'status' => 'publish' ), array( 'title', 'content.raw', 'content.rendered' ) );

		$this->assertSame( 'publish', $this->assert_updated( $result, $post_id )->post_status, 'The post should be published.' );
		$this->assertFalse( current_user_can( 'edit_post', $post_id ), 'The user should not be able to edit the published post.' );
		$this->assertSame( array( 'rendered' => 'Publish once' ), $result['title'], 'The title should be returned without its raw value.' );
		$this->assertSame( array( 'rendered' => "<p>Body.</p>\n" ), $result['content'], 'The content should be returned without its raw value.' );
	}

	/**
	 * A password-protected post is returned with its protection flag.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_password_protected_post_is_returned_with_its_protection_flag(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_ids['editor'],
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'content' => 'Protected body.' ), array( 'content.raw', 'content.protected' ) );

		$this->assert_updated( $result, $post_id );

		$this->assertSame(
			array(
				'raw'       => 'Protected body.',
				'protected' => true,
			),
			$result['content'],
			'The content should be returned raw to the editor and flagged as protected.'
		);
		$this->assertSame( 'secret', get_post( $post_id )->post_password, 'The password should be kept.' );
	}

	/**
	 * The author visibility is checked again at each call.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_each_call_checks_the_author_visibility_again(): void {
		$writer  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $writer,
				'post_status' => 'draft',
			)
		);

		$this->login_as( 'editor' );
		$this->assertFalse( current_user_can( 'list_users' ), 'Precondition: the editor should not be able to list users.' );

		$execute = $this->get_ability_callbacks()['execute_callback'];
		$input   = array(
			'where'  => array(
				'type' => 'post',
				'id'   => $post_id,
			),
			'data'   => array( 'title' => 'Visibility' ),
			'fields' => array( 'author' ),
		);

		$hidden = $execute( $input );

		self::factory()->post->create(
			array(
				'post_author' => $writer,
				'post_status' => 'publish',
			)
		);

		$visible = $execute( $input );

		$this->assertIsArray( $hidden, 'The first call should succeed.' );
		$this->assertIsArray( $visible, 'The second call should succeed.' );
		$this->assertSame(
			array(
				'id'   => $writer,
				'name' => null,
				'slug' => null,
			),
			$hidden['author'],
			'An author without published posts should not be shown.'
		);
		$this->assertSame( get_userdata( $writer )->display_name, $visible['author']['name'], 'The author should be shown once they have a published post.' );
	}

	/**
	 * A save error keeps its own code and message, with status 400 when the post is empty.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_empty_post_is_the_save_error(): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post(
			$post_id,
			array(
				'title'   => '',
				'content' => '',
				'excerpt' => '',
			)
		);

		$this->assert_error( $result, 'empty_content', 400, 'An empty post' );
		$this->assertSame( 'Content, title, and excerpt are empty.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * A database error during the save keeps its own code and message, with status 500.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_database_error_is_the_save_error(): void {
		global $wpdb;

		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );

		$suppress = $wpdb->suppress_errors();
		add_filter(
			'query',
			static function ( $query ) {
				return str_starts_with( $query, 'UPDATE' ) ? '],' : $query;
			}
		);

		$result = $this->update_post( $post_id, array( 'title' => 'Database error' ) );

		$wpdb->suppress_errors( $suppress );

		$this->assert_error( $result, 'db_update_error', 500, 'A database error' );
		$this->assertSame( 'Could not update post in the database.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written after a database error.' );
	}

	/**
	 * A stored page template that no longer exists does not fail an unrelated update and is kept.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_stale_page_template_does_not_fail_an_update(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, '_wp_page_template', 'cw-missing-template.php' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$post = $this->assert_updated( $this->update_post( $post_id, array( 'title' => 'Template kept' ) ), $post_id );

		$this->assertSame( 'Template kept', $post->post_title, 'The title should be stored.' );
		$this->assertSame( 'cw-missing-template.php', get_post_meta( $post_id, '_wp_page_template', true ), 'The stored page template should be kept.' );
	}

	/**
	 * The after-insert hooks fire once for the updated post, as in any regular save.
	 *
	 * A save that stores a revision also fires the hooks for the revision, so only the fires
	 * for the updated post are counted, and a regular save of an identical post is the reference.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_after_insert_hooks_fire_once(): void {
		$post_id      = self::$post_ids['administrator:draft'];
		$reference_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['administrator'],
				'post_status' => 'draft',
			)
		);
		$fires        = array();
		add_action(
			'wp_after_insert_post',
			static function ( $id ) use ( &$fires ): void {
				$fires[] = (int) $id;
			}
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		wp_update_post(
			array(
				'ID'         => $reference_id,
				'post_title' => 'Hooks',
			)
		);

		$this->assertCount( 1, array_keys( $fires, $reference_id, true ), 'Precondition: a regular save should fire wp_after_insert_post once for the post.' );

		$fires = array();
		$this->assert_updated( $this->update_post( $post_id, array( 'title' => 'Hooks' ) ), $post_id );

		$this->assertCount( 1, array_keys( $fires, $post_id, true ), 'wp_after_insert_post should fire once for the updated post.' );
	}

	/**
	 * Returns internal statuses that a post can be stored in.
	 *
	 * @return array<string, array{0: string}> The statuses.
	 */
	public static function data_internal_stored_statuses(): array {
		return array(
			'trash'      => array( 'trash' ),
			'auto-draft' => array( 'auto-draft' ),
		);
	}

	/**
	 * Called directly, an internal status is rejected also for a post that is already stored in
	 * it, and the post is found whatever its stored status.
	 *
	 * @ticket 66706
	 * @dataProvider data_internal_stored_statuses
	 * @since 7.3.0
	 *
	 * @param string $status The stored status, which is also sent.
	 */
	public function test_direct_call_rejects_an_internal_status_the_post_already_has( string $status ): void {
		if ( 'trash' === $status ) {
			$post_id = self::$post_ids['administrator:trash_publish'];
		} else {
			$post_id = self::factory()->post->create(
				array(
					'post_author' => self::$user_ids['administrator'],
					'post_status' => $status,
				)
			);
		}

		$this->assertSame( $status, get_post_status( $post_id ), 'Precondition: the post should be stored in the status.' );

		$this->login_as( 'administrator' );

		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'Precondition: the user should be able to edit the post.' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute( $this->update_input( $post_id, array( 'status' => $status ) ) );

		$this->assert_invalid_attribute( $result, 'status', 'A direct call with the internal status the post already has' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Called directly, an empty date or a date that cannot be parsed is an invalid attribute.
	 *
	 * @ticket 66706
	 * @dataProvider data_dates_without_a_usable_value
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date The date.
	 */
	public function test_direct_call_rejects_a_date_without_a_usable_value( array $date ): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute( $this->update_input( $post_id, array( 'date' => $date ) ) );

		$this->assert_invalid_attribute( $result, 'date', 'A direct call with a date without a usable value' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns direct-call inputs without `data` to write.
	 *
	 * @return array<string, array{0: array<string, mixed>}> The inputs, without `where`.
	 */
	public static function data_inputs_without_usable_data(): array {
		return array(
			'data missing' => array( array() ),
			'empty data'   => array( array( 'data' => array() ) ),
		);
	}

	/**
	 * Called directly by a user who may edit the post, an update without `data` to write is an
	 * invalid attribute and writes nothing.
	 *
	 * @ticket 66706
	 * @dataProvider data_inputs_without_usable_data
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $input The input, without `where`.
	 */
	public function test_direct_call_without_usable_data_is_an_invalid_attribute( array $input ): void {
		$post_id = self::$post_ids['administrator:draft'];

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$input['where'] = array(
			'type' => 'post',
			'id'   => $post_id,
		);

		$before = $this->snapshot( $post_id );
		$result = $execute( $input );

		$this->assert_error( $result, 'content_invalid_attribute', 400, 'A direct call without usable data' );
		$this->assertSame( array( 'data' => 'The data must be an object with at least one attribute to write.' ), $result->get_error_data()['params'] ?? array(), 'The params should hold only the data path, with the reason.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * An empty excerpt and an empty title are stored on a post that keeps its content.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_stores_an_empty_excerpt_and_an_empty_title(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_ids['administrator'],
				'post_status'  => 'draft',
				'post_title'   => 'Title to clear',
				'post_content' => 'Content to keep.',
				'post_excerpt' => 'Excerpt to clear.',
			)
		);

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$post = $this->assert_updated(
			$this->update_post(
				$post_id,
				array(
					'excerpt' => '',
					'title'   => '',
				)
			),
			$post_id
		);

		$this->assertSame( '', $post->post_excerpt, 'The empty excerpt should be stored.' );
		$this->assertSame( '', $post->post_title, 'The empty title should be stored.' );
		$this->assertSame( 'Content to keep.', $post->post_content, 'The content should be kept.' );
	}

	/**
	 * An author denied `publish_posts` may still move their own draft to `pending`.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_author_denied_publish_posts_may_set_pending(): void {
		$post_id = self::$post_ids['restricted_author:draft'];

		$this->login_as( 'restricted_author' );
		$this->register_abilities();

		$this->assertFalse( current_user_can( 'publish_posts' ), 'Precondition: the user should not be able to publish posts.' );

		$result = $this->update_post( $post_id, array( 'status' => 'pending' ) );

		$this->assertSame( 'pending', $this->assert_updated( $result, $post_id )->post_status, 'The draft should be pending.' );
	}

	/**
	 * Creates a published or scheduled post owned by the author denied `publish_posts`.
	 *
	 * @since 7.3.0
	 *
	 * @param string $status The status, `publish` or `future`.
	 * @return int The post ID.
	 */
	private function create_post_of_the_restricted_author( string $status ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['restricted_author'],
				'post_status' => $status,
				'post_date'   => 'future' === $status ? '2099-01-01 00:00:00' : '2020-01-01 00:00:00',
			)
		);

		$this->assertSame( $status, get_post_status( $post_id ), "Precondition: the post should be stored as {$status}." );

		return $post_id;
	}

	/**
	 * Returns date changes on a published or scheduled post, with the status the save stores.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string, 3: string}> The cases.
	 */
	public static function data_date_changes_on_a_published_or_scheduled_post(): array {
		$ahead = '2099-01-15T12:00:00';
		$past  = '2021-06-01T12:00:00';

		return array(
			'published post, date ahead'                => array( 'publish', false, $ahead, 'future' ),
			'published post, date ahead, stored status' => array( 'publish', true, $ahead, 'future' ),
			'published post, past date'                 => array( 'publish', false, $past, 'publish' ),
			'scheduled post, past date'                 => array( 'future', false, $past, 'publish' ),
			'scheduled post, past date, stored status'  => array( 'future', true, $past, 'publish' ),
			'scheduled post, another date ahead'        => array( 'future', false, $ahead, 'future' ),
		);
	}

	/**
	 * A date written to a published or scheduled post needs no `publish_posts`.
	 *
	 * The call sets no status. The save stores `future` for a date ahead and `publish`
	 * for any other date, so the date alone can schedule or publish the post.
	 *
	 * @ticket 66706
	 * @dataProvider data_date_changes_on_a_published_or_scheduled_post
	 * @since 7.3.0
	 *
	 * @param string $stored_status   The status the post has.
	 * @param bool   $send_status     Whether the call also sends the stored status.
	 * @param string $date            The sent GMT date.
	 * @param string $expected_status The status the save stores.
	 */
	public function test_date_on_a_published_or_scheduled_post_needs_no_publish_posts( string $stored_status, bool $send_status, string $date, string $expected_status ): void {
		$post_id = $this->create_post_of_the_restricted_author( $stored_status );

		$this->login_as( 'restricted_author' );
		$this->register_abilities();

		$this->assertFalse( current_user_can( 'publish_posts' ), 'Precondition: the user should not be able to publish posts.' );

		$data = array( 'date' => array( 'gmt' => $date ) );
		if ( $send_status ) {
			$data['status'] = $stored_status;
		}

		$result = $this->update_post( $post_id, $data, array( 'status' ) );
		$post   = $this->assert_updated( $result, $post_id );

		$this->assertSame( str_replace( 'T', ' ', $date ), $post->post_date_gmt, 'The sent date should be stored.' );
		$this->assertSame( $expected_status, $post->post_status, 'The stored status should follow the date.' );
		$this->assertSame( $expected_status, $result['status'], 'The status that follows the date should be returned.' );
	}

	/**
	 * Returns changes between `publish` and `future` that name the new status.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}> The cases.
	 */
	public static function data_named_status_changes_on_a_published_or_scheduled_post(): array {
		return array(
			'published post to future, date ahead' => array( 'publish', 'future', '2099-01-15T12:00:00' ),
			'scheduled post to publish, past date' => array( 'future', 'publish', '2021-06-01T12:00:00' ),
		);
	}

	/**
	 * A call that names the other status, `future` or `publish`, needs `publish_posts`,
	 * also when the sent date alone would lead to that status.
	 *
	 * @ticket 66706
	 * @dataProvider data_named_status_changes_on_a_published_or_scheduled_post
	 * @since 7.3.0
	 *
	 * @param string $stored_status The status the post has.
	 * @param string $status        The sent status.
	 * @param string $date          The sent GMT date.
	 */
	public function test_naming_the_status_a_date_leads_to_needs_publish_posts( string $stored_status, string $status, string $date ): void {
		$post_id = $this->create_post_of_the_restricted_author( $stored_status );

		$this->login_as( 'restricted_author' );
		$this->register_abilities();

		$this->assertFalse( current_user_can( 'publish_posts' ), 'Precondition: the user should not be able to publish posts.' );

		$before = $this->snapshot( $post_id );
		$result = $this->update_post(
			$post_id,
			array(
				'status' => $status,
				'date'   => array( 'gmt' => $date ),
			)
		);

		$this->assert_error( $result, 'content_cannot_publish', 403, 'A published or scheduled post moved to the other status without publish_posts' );
		$this->assertSame( 'Sorry, you are not allowed to publish posts in this post type.', $result->get_error_message(), 'The message should be the publish message.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Grants `edit_post` and `read_post` on one post to one user through `map_meta_cap`.
	 *
	 * @since 7.3.0
	 *
	 * @param int  $user_id          The user ID.
	 * @param int  $post_id          The post ID.
	 * @param bool $only_while_draft Whether the grant holds only while the post is a draft.
	 */
	private function grant_edit_and_read_on_one_post( int $user_id, int $post_id, bool $only_while_draft ): void {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $cap_user_id, $args ) use ( $user_id, $post_id, $only_while_draft ) {
				if (
					in_array( $cap, array( 'edit_post', 'read_post' ), true )
					&& $user_id === (int) $cap_user_id
					&& isset( $args[0] ) && $post_id === (int) $args[0]
					&& ( ! $only_while_draft || 'draft' === get_post_status( $post_id ) )
				) {
					return array( 'read' );
				}

				return $caps;
			},
			10,
			4
		);
	}

	/**
	 * A user with no edit capability for the type who is granted `edit_post` on exactly one post
	 * may update that post's title, and nothing more.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_edit_post_granted_on_one_post_allows_only_editing_that_post(): void {
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id  = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['other'],
				'post_status' => 'draft',
			)
		);
		$other_id = self::$post_ids['other:draft'];

		$this->grant_edit_and_read_on_one_post( $user_id, $post_id, false );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'edit_posts' ), 'Precondition: the user should have no edit capability for the type.' );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'Precondition: the user should be able to edit the granted post.' );
		$this->assertFalse( current_user_can( 'edit_post', $other_id ), 'Precondition: the user should not be able to edit another post.' );

		$this->register_abilities();

		$updated = $this->update_post( $post_id, array( 'title' => 'Granted title' ) );

		$this->assertSame( 'Granted title', $this->assert_updated( $updated, $post_id )->post_title, 'The title of the granted post should be stored.' );

		$other_before = $this->snapshot( $other_id );
		$other        = $this->update_post( $other_id, array( 'title' => 'Not granted' ) );

		$this->assert_refused( $other, 'Updating a post that is not granted' );
		$this->assertSame( $other_before, $this->snapshot( $other_id ), 'Nothing should be written for the post that is not granted.' );

		$before  = $this->snapshot( $post_id );
		$publish = $this->update_post( $post_id, array( 'status' => 'publish' ) );

		$this->assert_error( $publish, 'content_cannot_publish', 403, 'Publishing the granted post' );
		$this->assertSame( 'Sorry, you are not allowed to publish posts in this post type.', $publish->get_error_message(), 'The message should be the publish message.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written for the refused publish.' );
	}

	/**
	 * An editor may update the title of the front page and of the posts page, but not the
	 * privacy policy page.
	 *
	 * @ticket 66706
	 * @dataProvider data_special_pages
	 * @since 7.3.0
	 *
	 * @param string $page The page, as a fixture name.
	 */
	public function test_editor_may_update_a_special_page_other_than_the_privacy_policy_page( string $page ): void {
		$this->set_special_pages();
		$post_id = self::$post_ids[ $page ];

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'title' => 'Special page' ) );

		if ( 'privacy_page' === $page ) {
			$this->assert_refused( $result, 'An editor updating the privacy policy page' );
			$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
			return;
		}

		$this->assertSame( 'Special page', $this->assert_updated( $result, $post_id )->post_title, 'The title of the special page should be stored.' );
	}

	/**
	 * Returns updates of a page whose stored parent no longer exists.
	 *
	 * @return array<string, array{0: bool}> The cases.
	 */
	public static function data_updates_of_a_page_with_a_missing_parent(): array {
		return array(
			'parent equal to the stored parent' => array( true ),
			'title only'                        => array( false ),
		);
	}

	/**
	 * A page whose stored parent no longer exists can be updated, and keeps that parent.
	 *
	 * @ticket 66706
	 * @dataProvider data_updates_of_a_page_with_a_missing_parent
	 * @since 7.3.0
	 *
	 * @param bool $send_parent Whether the stored parent is sent.
	 */
	public function test_page_with_a_missing_parent_can_be_updated( bool $send_parent ): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['page_writer'],
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_parent' => self::MISSING_ID,
			)
		);

		$this->assertSame( self::MISSING_ID, get_post( $post_id )->post_parent, 'Precondition: the page should keep the missing parent.' );
		$this->assertNull( get_post( self::MISSING_ID ), 'Precondition: the parent should not exist.' );

		$this->login_as( 'page_writer' );
		$this->register_abilities();

		$data = array( 'title' => 'Orphan' );
		if ( $send_parent ) {
			$data['parent'] = self::MISSING_ID;
		}

		$post = $this->assert_updated( $this->update_post( $post_id, $data ), $post_id );

		$this->assertSame( 'Orphan', $post->post_title, 'The title should be stored.' );
		$this->assertSame( self::MISSING_ID, $post->post_parent, 'The stored parent should not change.' );
	}

	/**
	 * A contributor may set a hidden custom status on their own draft without `publish_posts`.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_contributor_may_set_a_hidden_custom_status(): void {
		$this->register_custom_statuses();
		$post_id = self::$post_ids['contributor:draft'];

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'status' => 'cw_hidden' ) );

		$this->assertSame( 'cw_hidden', $this->assert_updated( $result, $post_id )->post_status, 'The hidden status should be stored.' );
	}

	/**
	 * Returns custom statuses that make a post visible, with the stored status of the post.
	 *
	 * @return array<string, array{0: string, 1: string}> The cases.
	 */
	public static function data_custom_visible_statuses_by_stored_status(): array {
		return array(
			'public from a draft'    => array( 'cw_public', 'draft' ),
			'private from a draft'   => array( 'cw_private', 'draft' ),
			'public from pending'    => array( 'cw_public', 'pending' ),
			'private from pending'   => array( 'cw_private', 'pending' ),
			'public from the Trash'  => array( 'cw_public', 'trash_draft' ),
			'private from the Trash' => array( 'cw_private', 'trash_draft' ),
		);
	}

	/**
	 * A contributor may not set a custom public or private status on their own post, and the
	 * refusal uses the general message.
	 *
	 * @ticket 66706
	 * @dataProvider data_custom_visible_statuses_by_stored_status
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 * @param string $stored The matrix status of the post.
	 */
	public function test_contributor_cannot_set_a_custom_visible_status( string $status, string $stored ): void {
		$this->register_custom_statuses();
		$post_id = self::$post_ids[ "contributor:{$stored}" ];

		$this->login_as( 'contributor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->update_post( $post_id, array( 'status' => $status ) );

		$this->assert_error( $result, 'content_cannot_publish', 403, 'A contributor setting a custom visible status' );
		$this->assertSame( 'Sorry, you are not allowed to publish posts in this post type.', $result->get_error_message(), 'A custom status, also a private one, should get the general message.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * When the update takes away the caller's read access, the update is stored and the output
	 * is the ID alone, whatever the fields ask for.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_output_is_the_id_alone_when_the_update_takes_away_read_access(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['other'],
				'post_status' => 'draft',
			)
		);

		$this->grant_edit_and_read_on_one_post( $user_id, $post_id, true );
		wp_set_current_user( $user_id );

		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'Precondition: the user should be able to edit the draft.' );

		$this->register_abilities();

		$result = $this->update_post(
			$post_id,
			array(
				'status' => 'pending',
				'title'  => 'Out of reach',
			),
			array( 'title.rendered', 'status', 'content.rendered' )
		);

		$post = $this->assert_updated( $result, $post_id );

		$this->assertSame( 'pending', $post->post_status, 'The new status should be stored.' );
		$this->assertSame( 'Out of reach', $post->post_title, 'The new title should be stored.' );
		$this->assertFalse( current_user_can( 'read_post', $post_id ), 'The user should no longer be able to read the post.' );
		$this->assertSame( array( 'id' => $post_id ), $result, 'The output should be the ID alone.' );
	}

	/**
	 * When the caller may still read the post after the update, the output holds the requested
	 * fields.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_output_is_complete_when_the_caller_may_still_read_the_post(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_ids['other'],
				'post_status' => 'draft',
			)
		);

		$this->grant_edit_and_read_on_one_post( $user_id, $post_id, true );
		wp_set_current_user( $user_id );
		$this->register_abilities();

		$result = $this->update_post( $post_id, array( 'title' => 'Still a draft' ), array( 'title.rendered', 'status' ) );

		$this->assert_updated( $result, $post_id );
		$this->assertTrue( current_user_can( 'read_post', $post_id ), 'The user should still be able to read the post.' );

		$this->assertSameSetsWithIndex(
			array(
				'id'     => $post_id,
				'status' => 'draft',
				'title'  => array( 'rendered' => 'Still a draft' ),
			),
			$result,
			'The output should hold the ID and the requested fields.'
		);
	}
}
