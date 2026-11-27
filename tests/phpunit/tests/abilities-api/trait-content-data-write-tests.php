<?php

declare( strict_types=1 );

/**
 * Tests shared by the abilities that write `data`: core/content-create and core/content-update.
 *
 * The test class says which post a role writes, builds the input and checks the written post.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */
trait WP_Abilities_Content_Data_Write_Tests {

	/**
	 * Returns the ID of the post that a role writes in the shared tests.
	 *
	 * @since 7.3.0
	 *
	 * @param string $role The role, as a fixture name.
	 * @return int|null The post ID, or null when the call creates a post.
	 */
	abstract protected function get_write_post_id( string $role ): ?int;

	/**
	 * Builds the input that writes the given data to a post of type `post`.
	 *
	 * @since 7.3.0
	 *
	 * @param int|null             $post_id The post ID, or null when the call creates a post.
	 * @param array<string, mixed> $data    The data, without the type.
	 * @param list<string>|null    $fields  Optional. The fields, or null for the default. Default null.
	 * @return array<string, mixed> The input.
	 */
	abstract protected function build_write_input( ?int $post_id, array $data, ?array $fields = null ): array;

	/**
	 * Asserts that a result is the post item of a successful write and returns the stored post.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed    $result  The ability result.
	 * @param int|null $post_id The post ID, or null when the call created a post.
	 * @return WP_Post The stored post.
	 */
	abstract protected function assert_data_written( $result, ?int $post_id ): WP_Post;

	/**
	 * Creates the users and posts that both test classes read: a `page_writer` who may write
	 * pages but not read the drafts of other users, a `publisher` who may publish posts but
	 * not edit published posts, and a draft page of the editor.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	protected static function create_data_write_fixtures( WP_UnitTest_Factory $factory ): void {
		self::$user_ids['page_writer'] = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$user_ids['page_writer'] )->add_cap( 'edit_pages' );

		self::$user_ids['publisher'] = $factory->user->create( array( 'role' => 'contributor' ) );
		get_userdata( self::$user_ids['publisher'] )->add_cap( 'publish_posts' );

		self::$post_ids['editor_draft_page'] = $factory->post->create(
			array(
				'post_author' => self::$user_ids['editor'],
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Called directly, an internal or unregistered status is an invalid attribute.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_that_can_never_be_set
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_direct_call_rejects_a_status_that_can_never_be_set( string $status ): void {
		$post_id = $this->get_write_post_id( 'administrator' );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute(
			$this->build_write_input(
				$post_id,
				array(
					'title'  => 'Unusable status',
					'status' => $status,
				)
			)
		);

		$this->assert_invalid_attribute( $result, 'status', 'A direct call with a status that can never be set', 'The status must be a registered post status that is not internal.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Returns dates that change the stored status: the requested status, the requested GMT date
	 * and the stored status.
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
		$post_id = $this->get_write_post_id( 'editor' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = $this->build_write_input(
			$post_id,
			array(
				'title'  => 'Scheduled',
				'status' => $status,
				'date'   => array( 'gmt' => $gmt ),
			),
			array( 'status' )
		);
		$result = $this->run_ability( $input );

		$this->assertSame( $expected, $this->assert_data_written( $result, $post_id )->post_status, 'The post should be stored in the expected status.' );
		$this->assertSame( $expected, $result['status'], 'The output should report the stored status.' );
	}

	/**
	 * A save error keeps its own code and message, with status 400 when the post is empty.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_empty_post_is_the_save_error(): void {
		$post_id = $this->get_write_post_id( 'administrator' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability(
			$this->build_write_input(
				$post_id,
				array(
					'title'   => '',
					'content' => '',
					'excerpt' => '',
				)
			)
		);

		$this->assert_error( $result, 'empty_content', 400, 'An empty post' );
		$this->assertSame( 'Content, title, and excerpt are empty.', $result->get_error_message(), 'The message should be the message of the save error.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Quotes, backslashes and non-ASCII text survive a round trip unchanged.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_quotes_backslashes_and_non_ascii_text_survive_a_round_trip(): void {
		$text    = 'He said "it\'s done" \\ C:\\path\\to\\file \\\\server – café, naïve, 日本語, emoji ✓';
		$post_id = $this->get_write_post_id( 'editor' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = $this->build_write_input(
			$post_id,
			array(
				'title'   => $text,
				'content' => $text,
				'excerpt' => $text,
			),
			array( 'title.raw', 'content.raw', 'excerpt.raw' )
		);
		$result = $this->run_ability( $input );

		$post = $this->assert_data_written( $result, $post_id );

		$this->assertSame( $text, $post->post_title, 'The stored title should be unchanged.' );
		$this->assertSame( $text, $post->post_content, 'The stored content should be unchanged.' );
		$this->assertSame( $text, $post->post_excerpt, 'The stored excerpt should be unchanged.' );
		$this->assertSame( $text, $result['title']['raw'], 'The returned title should be unchanged.' );
		$this->assertSame( $text, $result['content']['raw'], 'The returned content should be unchanged.' );
		$this->assertSame( $text, $result['excerpt']['raw'], 'The returned excerpt should be unchanged.' );
	}

	/**
	 * The output is one post item, the same object as an item of the query.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_output_schema_is_a_query_item(): void {
		$this->register_abilities();

		$this->assertTrue( wp_has_ability( self::ABILITY ), 'The ability should be registered.' );

		$schema = wp_get_ability( self::ABILITY )->get_output_schema();
		$item   = wp_get_ability( 'core/content-query' )->get_output_schema()['properties']['items']['items'];

		$this->assertSame( array( 'id' ), $schema['required'], 'The output should always hold the ID.' );
		$this->assertSame( $this->strip_descriptions( $item ), $this->strip_descriptions( $schema ), 'The output should be the same object as a query item.' );
	}

	/**
	 * The permission callback refuses a user without `edit_others_posts` who names another
	 * author, existing or not, so the refusal never tells whether a user exists.
	 *
	 * @ticket 66706
	 * @dataProvider data_authors_named_without_edit_others_posts
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_naming_another_author_without_edit_others_posts_is_refused( string $author ): void {
		$post_id = $this->get_write_post_id( 'author' );

		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability(
			$this->build_write_input(
				$post_id,
				array(
					'title'  => 'Ghost written',
					'author' => self::$user_ids[ $author ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				)
			)
		);

		$this->assert_refused( $result, 'Naming another author' );
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

		$this->assertFalse( is_user_member_of_blog( $non_member ), 'Precondition: the user should not be a member of the site.' );

		$post_id = $this->get_write_post_id( 'editor' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability(
			$this->build_write_input(
				$post_id,
				array(
					'title'  => 'Not a member',
					'author' => $non_member,
				)
			)
		);

		$this->assert_invalid_attribute( $result, 'author', 'An author who is not a member of the site', 'The author must be an existing user of this site.' );
		$this->assertSame( $before, $this->snapshot( $post_id ), 'Nothing should be written.' );
	}

	/**
	 * Raw fields are left out when the writer cannot edit the post after the write.
	 *
	 * The user may publish posts but not edit published posts.
	 *
	 * @ticket 66706
	 * @since 7.3.0
	 */
	public function test_raw_fields_are_left_out_when_the_writer_cannot_edit_the_written_post(): void {
		$post_id = $this->get_write_post_id( 'publisher' );

		$this->login_as( 'publisher' );
		$this->register_abilities();

		$result = $this->run_ability(
			$this->build_write_input(
				$post_id,
				array(
					'title'   => 'Published once',
					'content' => 'Body.',
					'status'  => 'publish',
				),
				array( 'title', 'content.raw', 'content.rendered' )
			)
		);

		$post = $this->assert_data_written( $result, $post_id );

		$this->assertSame( 'publish', $post->post_status, 'The post should be published.' );
		$this->assertFalse( current_user_can( 'edit_post', $post->ID ), 'The user should not be able to edit the published post.' );
		$this->assertSame( array( 'rendered' => 'Published once' ), $result['title'], 'The title should be returned without its raw value.' );
		$this->assertSame( array( 'rendered' => "<p>Body.</p>\n" ), $result['content'], 'The content should be returned without its raw value.' );
	}
}
