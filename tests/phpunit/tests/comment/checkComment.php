<?php

/**
 * @group comment
 *
 * @covers ::check_comment
 */
class Tests_Comment_CheckComment extends WP_UnitTestCase {
	public function test_should_return_true_when_comment_previously_approved_is_disabled() {
		$author       = 'BobtheBuilder';
		$author_email = 'bob@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Can we fix it? Yes, we can (thanks to Wendy).';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'comment_previously_approved', 0 );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertTrue( $results );
	}

	public function test_should_return_false_when_comment_previously_approved_is_enabled_and_author_does_not_have_approved_comment() {
		$author       = 'BobtheBuilder';
		$author_email = 'bob@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Can we fix it? Yes, we can (thanks to Wendy).';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'comment_previously_approved', 1 );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertFalse( $results );
	}

	public function test_should_return_true_when_comment_previously_approved_is_enabled_and_author_has_approved_comment() {
		$post_id         = self::factory()->post->create();
		$prev_args       = array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Can we build it?',
			'comment_approved'     => 0,
			'comment_author_email' => 'bob@example.com',
			'comment_author'       => 'BobtheBuilder',
		);
		$prev_comment_id = self::factory()->comment->create( $prev_args );

		update_option( 'comment_previously_approved', 1 );

		$author       = 'BobtheBuilder';
		$author_email = 'bob@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Can we fix it? Yes, we can (thanks to Wendy).';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertFalse( $results );

		// Approve the previous comment.
		wp_update_comment(
			array(
				'comment_ID'       => $prev_comment_id,
				'comment_approved' => 1,
			)
		);
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertTrue( $results );
	}

	public function test_should_return_false_when_content_matches_moderation_keys() {
		update_option( 'comment_previously_approved', 0 );

		$author       = 'WendytheBuilder';
		$author_email = 'wendy@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Has anyone seen Scoop?';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'moderation_keys', "foo\nbar\nscoop" );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertFalse( $results );
	}

	/**
	 * @ticket 57207
	 */
	public function test_should_return_false_when_content_with_non_latin_words_matches_moderation_keys() {
		update_option( 'comment_previously_approved', 0 );

		$author       = 'Setup';
		$author_email = 'setup@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Установка';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'moderation_keys', "установка\nfoo" );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertFalse( $results );
	}

	public function test_should_return_true_when_content_does_not_match_moderation_keys() {
		update_option( 'comment_previously_approved', 0 );

		$author       = 'WendytheBuilder';
		$author_email = 'wendy@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Has anyone seen Scoop?';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'moderation_keys', "foo\nbar" );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertTrue( $results );
	}

	public function test_should_return_false_when_link_count_exceeds_comment_max_length_setting() {
		update_option( 'comment_previously_approved', 0 );

		$author       = 'BobtheBuilder';
		$author_email = 'bob@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'This is a comment with <a href="http://example.com">multiple</a> <a href="http://bob.example.com">links</a>.';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'comment_max_links', 2 );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertFalse( $results );
	}

	public function test_should_return_true_when_link_count_does_not_exceed_comment_max_length_setting() {
		update_option( 'comment_previously_approved', 0 );

		$author       = 'BobtheBuilder';
		$author_email = 'bob@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'This is a comment with <a href="http://example.com">multiple</a> <a href="http://bob.example.com">links</a>.';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';
		$comment_type = '';

		update_option( 'comment_max_links', 3 );
		$results = check_comment( $author, $author_email, $author_url, $comment, $author_ip, $user_agent, $comment_type );
		$this->assertTrue( $results );
	}

	/**
	 * @ticket 28603
	 */
	public function test_should_return_true_when_comment_previously_approved_is_enabled_and_user_has_previously_approved_comments_with_different_email() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role'  => 'subscriber',
				'email' => 'sub@example.com',
			)
		);

		// Make sure comment author has an approved comment.
		self::factory()->comment->create(
			array(
				'user_id'              => $subscriber_id,
				'comment_approved'     => '1',
				'comment_author'       => 'foo',
				'comment_author_email' => 'sub@example.com',
			)
		);

		$subscriber_user             = new WP_User( $subscriber_id );
		$subscriber_user->user_email = 'newsub@example.com';

		wp_update_user( $subscriber_user );

		update_option( 'comment_previously_approved', 1 );

		$results = check_comment( 'foo', 'newsub@example.com', 'http://example.com', 'This is a comment.', '66.155.40.249', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:35.0) Gecko/20100101 Firefox/35.0', 'comment', 4 );
		$this->assertTrue( $results );
	}

	/**
	 * @ticket 28603
	 */
	public function test_should_return_false_when_comment_previously_approved_is_enabled_and_user_does_not_have_a_previously_approved_comment_with_any_email() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role'  => 'subscriber',
				'email' => 'zig@example.com',
			)
		);

		$subscriber_user             = new WP_User( $subscriber_id );
		$subscriber_user->user_email = 'zag@example.com';

		wp_update_user( $subscriber_user );

		update_option( 'comment_previously_approved', 1 );

		$results = check_comment( 'bar', 'zag@example.com', 'http://example.com', 'This is my first comment.', '66.155.40.249', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:35.0) Gecko/20100101 Firefox/35.0', 'comment', 4 );
		$this->assertFalse( $results );
	}

	/**
	 * @ticket 65016
	 */
	public function test_should_return_true_for_a_pingback_from_this_site() {
		update_option( 'comment_previously_approved', '1' );

		$source_url = get_permalink( self::factory()->post->create() );

		$this->assertTrue( check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', 'pingback' ) );
	}

	/**
	 * Trackbacks are never approved automatically.
	 *
	 * A trackback's source URL, title, and excerpt are unverified request data. Were
	 * they trusted, anyone could POST a trackback naming a local post as its source
	 * and have arbitrary content approved without moderation.
	 *
	 * @ticket 65016
	 */
	public function test_should_return_false_for_a_trackback_claiming_a_local_source() {
		update_option( 'comment_previously_approved', '1' );

		$source_url = get_permalink( self::factory()->post->create() );

		$this->assertFalse( check_comment( 'ForgedSite', '', $source_url, 'Spam.', '192.168.0.1', '', 'trackback' ) );
	}

	/**
	 * @ticket 65016
	 *
	 * @dataProvider data_ping_types
	 *
	 * @param string $comment_type The comment type.
	 */
	public function test_should_return_false_for_a_ping_from_another_site( $comment_type ) {
		update_option( 'comment_previously_approved', '1' );

		$this->assertFalse( check_comment( 'Site Title', '', 'http://example.com/a-post/', 'Excerpt.', '192.168.0.1', '', $comment_type ) );
	}

	/**
	 * A URL is only local when its host matches, not when it merely contains the home URL.
	 *
	 * @ticket 65016
	 *
	 * @dataProvider data_ping_types
	 *
	 * @param string $comment_type The comment type.
	 */
	public function test_should_return_false_for_a_ping_from_a_url_spoofing_this_site( $comment_type ) {
		update_option( 'comment_previously_approved', '1' );

		$post_id    = self::factory()->post->create();
		$source_url = 'http://example.com/?ref=' . rawurlencode( get_permalink( $post_id ) );

		$this->assertFalse( check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', $comment_type ) );
	}

	/**
	 * Manually approving every comment must not be bypassed by a self-pingback.
	 *
	 * @ticket 65016
	 */
	public function test_should_return_false_for_a_pingback_from_this_site_when_comment_moderation_is_enabled() {
		update_option( 'comment_moderation', '1' );

		$source_url = get_permalink( self::factory()->post->create() );

		$this->assertFalse( check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', 'pingback' ) );
	}

	/**
	 * The source post must be published, not a draft that happens to resolve.
	 *
	 * @ticket 65016
	 */
	public function test_should_return_false_for_a_pingback_from_an_unpublished_post_on_this_site() {
		update_option( 'comment_previously_approved', '1' );

		$post_id    = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$source_url = add_query_arg( 'p', $post_id, home_url( '/' ) );

		$this->assertFalse( check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', 'pingback' ) );
	}

	/**
	 * Only pings are exempt. A regular comment linking to a local post is still moderated.
	 *
	 * @ticket 65016
	 */
	public function test_should_return_false_for_a_comment_whose_author_url_is_a_post_on_this_site() {
		update_option( 'comment_previously_approved', '1' );

		$source_url = get_permalink( self::factory()->post->create() );

		$this->assertFalse( check_comment( 'Bob', 'bob@example.com', $source_url, 'A comment.', '192.168.0.1', '', 'comment' ) );
	}

	/**
	 * @ticket 65016
	 */
	public function test_auto_approve_pingback_should_be_able_to_hold_a_pingback_from_this_site() {
		update_option( 'comment_previously_approved', '1' );

		$source_url = get_permalink( self::factory()->post->create() );

		add_filter( 'auto_approve_pingback', '__return_false' );

		$this->assertFalse( check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', 'pingback' ) );
	}

	/**
	 * @ticket 65016
	 */
	public function test_auto_approve_pingback_should_be_able_to_approve_a_pingback_from_another_site() {
		update_option( 'comment_previously_approved', '1' );

		add_filter( 'auto_approve_pingback', '__return_true' );

		$this->assertTrue( check_comment( 'Site Title', '', 'http://example.com/a-post/', 'Excerpt.', '192.168.0.1', '', 'pingback' ) );
	}

	/**
	 * @ticket 65016
	 */
	public function test_auto_approve_pingback_should_receive_the_source_post_id() {
		update_option( 'comment_previously_approved', '1' );

		$post_id    = self::factory()->post->create();
		$source_url = get_permalink( $post_id );

		$observed = null;
		add_filter(
			'auto_approve_pingback',
			static function ( $approve, $source_id ) use ( &$observed ) {
				$observed = $source_id;
				return $approve;
			},
			10,
			2
		);

		check_comment( 'Site Title', '', $source_url, 'Excerpt.', '192.168.0.1', '', 'pingback' );

		$this->assertSame( $post_id, $observed );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_ping_types() {
		return array(
			'pingback'  => array( 'pingback' ),
			'trackback' => array( 'trackback' ),
		);
	}
}
