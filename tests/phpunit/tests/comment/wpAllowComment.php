<?php

/**
 * @group comment
 *
 * @covers ::wp_allow_comment
 */
class Tests_Comment_WpAllowComment extends WP_UnitTestCase {
	protected static $post_id;
	protected static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id    = $factory->post->create();
		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_approved'     => '1',
				'comment_author'       => 'Bob',
				'comment_author_email' => 'bobthebuilder@example.com',
				'comment_author_url'   => 'http://example.com',
				'comment_content'      => 'Yes, we can!',
			)
		);

		update_option( 'comment_previously_approved', 0 );
	}

	public static function wpTeardownAfterClass() {
		wp_delete_post( self::$post_id, true );
		wp_delete_comment( self::$comment_id, true );

		update_option( 'comment_previously_approved', 1 );
	}

	public function test_allow_comment_if_comment_author_emails_differ() {
		$now          = time();
		$comment_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Bob',
			'comment_author_email' => 'sideshowbob@example.com',
			'comment_author_url'   => 'http://example.com',
			'comment_content'      => 'Yes, we can!',
			'comment_author_IP'    => '192.168.0.1',
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now ),
			'comment_agent'        => 'Bobbot/2.1',
			'comment_type'         => '',
		);

		$result = wp_allow_comment( $comment_data );

		$this->assertSame( 1, $result );
	}

	public function test_die_as_duplicate_if_comment_author_name_and_emails_match() {
		$this->expectException( 'WPDieException' );

		$now          = time();
		$comment_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Bob',
			'comment_author_email' => 'bobthebuilder@example.com',
			'comment_author_url'   => 'http://example.com',
			'comment_content'      => 'Yes, we can!',
			'comment_author_IP'    => '192.168.0.1',
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now ),
			'comment_agent'        => 'Bobbot/2.1',
			'comment_type'         => '',
		);

		$result = wp_allow_comment( $comment_data );
	}

	/**
	 * Tests that the duplicate comment check does not trigger a database error
	 * when the comment contains four-byte characters, whatever the character set
	 * of the `comment_content` column.
	 *
	 * @ticket 46947
	 *
	 * @dataProvider data_comment_content_column_charsets
	 *
	 * @param string $charset   Character set to convert the comments table to.
	 * @param string $collation Collation to convert the comments table to.
	 */
	public function test_four_byte_characters_do_not_cause_a_database_error_in_the_duplicate_check( $charset, $collation ) {
		global $wpdb;

		$query_error = null;
		$capture     = static function ( $dupe_id ) use ( $wpdb, &$query_error ) {
			$query_error = $wpdb->last_error;
			return $dupe_id;
		};

		add_filter( 'duplicate_comment_id', $capture );

		$result = $this->with_comments_table_charset(
			$charset,
			$collation,
			function () {
				return wp_allow_comment( $this->get_four_byte_comment_data() );
			}
		);

		remove_filter( 'duplicate_comment_id', $capture );

		$this->assertSame( '', $query_error, 'The duplicate check query should not produce a database error.' );
		$this->assertSame( 1, $result, 'The comment should be allowed.' );
	}

	/**
	 * Tests that a duplicate comment containing four-byte characters is still detected,
	 * whatever the character set of the `comment_content` column.
	 *
	 * @ticket 46947
	 *
	 * @dataProvider data_comment_content_column_charsets
	 *
	 * @param string $charset   Character set to convert the comments table to.
	 * @param string $collation Collation to convert the comments table to.
	 */
	public function test_duplicate_comment_with_four_byte_characters_is_detected( $charset, $collation ) {
		$result = $this->with_comments_table_charset(
			$charset,
			$collation,
			function () {
				global $wpdb;

				$comment_data = $this->get_four_byte_comment_data();

				// Store the comment as it is saved when the column cannot hold four-byte characters.
				$stored_data                    = $comment_data;
				$stored_data['comment_content'] = $wpdb->strip_invalid_text_for_column( $wpdb->comments, 'comment_content', $comment_data['comment_content'] );

				$this->assertNotEmpty( wp_insert_comment( wp_slash( $stored_data ) ), 'The existing comment should be stored.' );

				return wp_allow_comment( $comment_data, true );
			}
		);

		$this->assertWPError( $result, 'The duplicate comment should be rejected.' );
		$this->assertSame( 'comment_duplicate', $result->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comment_content_column_charsets() {
		return array(
			'legacy utf8 column' => array( 'utf8', 'utf8_general_ci' ),
			'utf8mb4 column'     => array( 'utf8mb4', 'utf8mb4_unicode_ci' ),
		);
	}

	/**
	 * Gets comment data containing four-byte characters.
	 *
	 * @return array Comment data.
	 */
	private function get_four_byte_comment_data() {
		return array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Rhonda',
			'comment_author_email' => 'rhonda@example.com',
			'comment_author_url'   => '',
			'comment_content'      => "Amen And Amen! Thank you Rabbi!\u{2764}\u{1F525}\u{1F4AF}\u{1F64C}",
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			'comment_author_IP'    => '192.168.0.1',
			'comment_agent'        => 'WordPress PHPUnit',
			'comment_type'         => 'comment',
		);
	}

	/**
	 * Runs a callback with a temporary comments table that uses the given character set.
	 *
	 * Skips the test if the database or the collation is not available.
	 *
	 * @param string   $charset   Character set to convert the temporary table to.
	 * @param string   $collation Collation to convert the temporary table to.
	 * @param callable $callback  Callback to run while the temporary table is in use.
	 * @return mixed The value returned by the callback.
	 */
	private function with_comments_table_charset( $charset, $collation, $callback ) {
		global $wpdb;

		if ( ! $wpdb->is_mysql ) {
			$this->markTestSkipped( 'This test requires MySQL or MariaDB.' );
		}

		if ( 'utf8mb4' !== $wpdb->charset ) {
			$this->markTestSkipped( 'This test requires a utf8mb4 database connection.' );
		}

		$comments_table = $wpdb->comments;
		$test_table     = $wpdb->prefix . 'comments_46947_' . $charset; // A unique name per character set avoids stale cached column metadata.

		$wpdb->query( "CREATE TEMPORARY TABLE `$test_table` LIKE `$comments_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and character set names come from the test itself.
		$wpdb->query( "ALTER TABLE `$test_table` CONVERT TO CHARACTER SET $charset COLLATE $collation" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and character set names come from the test itself.

		if ( $wpdb->last_error ) {
			$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `$test_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and character set names come from the test itself.
			$this->markTestSkipped( "The $collation collation is not available." );
		}

		$wpdb->comments = $test_table;

		try {
			return $callback();
		} finally {
			$wpdb->comments = $comments_table;
			$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `$test_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and character set names come from the test itself.
		}
	}

	/**
	 * @ticket 65016
	 *
	 * @dataProvider data_should_approve_a_pingback_only_when_it_comes_from_this_site
	 *
	 * @param bool $is_self_ping Whether the pingback should come from this site.
	 * @param int  $expected     The expected approval status.
	 */
	public function test_should_approve_a_pingback_only_when_it_comes_from_this_site( $is_self_ping, $expected ) {
		update_option( 'comment_previously_approved', '1' );

		$source_url = $is_self_ping
			? get_permalink( self::factory()->post->create() )
			: 'http://example.com/their-post/';

		$comment_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'The Linking Post',
			'comment_author_email' => '',
			'comment_author_url'   => $source_url,
			'comment_content'      => '[&#8230;] an earlier post of mine [&#8230;]',
			'comment_author_IP'    => '192.168.0.1',
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			'comment_agent'        => 'WordPress/6.8',
			'comment_type'         => 'pingback',
		);

		$this->assertSame( $expected, wp_allow_comment( $comment_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_approve_a_pingback_only_when_it_comes_from_this_site() {
		return array(
			'a pingback from this site'    => array( true, 1 ),
			'a pingback from another site' => array( false, 0 ),
		);
	}
}
