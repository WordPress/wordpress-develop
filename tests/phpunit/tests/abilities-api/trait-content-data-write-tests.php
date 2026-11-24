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
	abstract protected function data_write_post_id( string $role ): ?int;

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
	abstract protected function data_write_input( ?int $post_id, array $data, ?array $fields = null ): array;

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
	 * Called directly, an internal or unregistered status is an invalid attribute.
	 *
	 * @ticket 66706
	 * @dataProvider data_statuses_that_can_never_be_set
	 * @since 7.3.0
	 *
	 * @param string $status The status.
	 */
	public function test_direct_call_rejects_a_status_that_can_never_be_set( string $status ): void {
		$post_id = $this->data_write_post_id( 'administrator' );

		$this->login_as( 'administrator' );
		$execute = $this->get_ability_callbacks()['execute_callback'];

		$before = $this->snapshot( $post_id );
		$result = $execute(
			$this->data_write_input(
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
		$post_id = $this->data_write_post_id( 'editor' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = $this->data_write_input(
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
		$post_id = $this->data_write_post_id( 'administrator' );

		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot( $post_id );
		$result = $this->run_ability(
			$this->data_write_input(
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
		$post_id = $this->data_write_post_id( 'editor' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$input  = $this->data_write_input(
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
}
