<?php

/**
 * Tests for the WP_Importer class and get_cli_args().
 *
 * @group admin
 * @group import
 */
class Tests_Admin_wpImporter extends WP_UnitTestCase {

	/**
	 * Original value of `$_SERVER['argv']`, if it was set.
	 *
	 * @var array|null
	 */
	private $orig_argv;

	/**
	 * Whether `$_SERVER['argv']` was set before the test.
	 *
	 * @var bool
	 */
	private $had_argv;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	}

	public function set_up() {
		parent::set_up();

		$this->had_argv  = array_key_exists( 'argv', $_SERVER );
		$this->orig_argv = $this->had_argv ? $_SERVER['argv'] : null;
	}

	public function tear_down() {
		if ( $this->had_argv ) {
			$_SERVER['argv'] = $this->orig_argv;
		} else {
			unset( $_SERVER['argv'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that imported permalinks are mapped to their post IDs for the given importer and blog.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::get_imported_posts
	 */
	public function test_get_imported_posts_maps_permalinks_to_post_ids() {
		$first  = self::factory()->post->create();
		$second = self::factory()->post->create();

		add_post_meta( $first, 'blogger_7_permalink', '/2026/01/first.html' );
		add_post_meta( $second, 'blogger_7_permalink', '/2026/02/second.html' );
		add_post_meta( $second, 'blogger_8_permalink', '/other-blog.html' );
		add_post_meta( $second, 'other_7_permalink', '/other-importer.html' );

		$this->assertSame(
			array(
				'/2026/01/first.html'  => $first,
				'/2026/02/second.html' => $second,
			),
			( new WP_Importer() )->get_imported_posts( 'blogger', 7 )
		);
	}

	/**
	 * Tests that imported permalinks are read in chunks of 100 until none are left.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::get_imported_posts
	 */
	public function test_get_imported_posts_reads_all_chunks() {
		global $wpdb;

		$post_id = self::factory()->post->create();

		for ( $i = 1; $i <= 101; $i++ ) {
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => $post_id,
					'meta_key'   => 'blogger_7_permalink',
					'meta_value' => "/post-{$i}.html",
				)
			);
		}

		$imported = ( new WP_Importer() )->get_imported_posts( 'blogger', 7 );

		$this->assertCount( 101, $imported, 'Permalinks after the first chunk should be included.' );
		$this->assertSame( $post_id, $imported['/post-101.html'] );
	}

	/**
	 * Tests that no imported posts gives an empty array.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::get_imported_posts
	 */
	public function test_get_imported_posts_returns_empty_array_without_imports() {
		$this->assertSame( array(), ( new WP_Importer() )->get_imported_posts( 'blogger', 7 ) );
	}

	/**
	 * Tests the number of imported permalinks for the given importer and blog.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::count_imported_posts
	 */
	public function test_count_imported_posts() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'blogger_7_permalink', '/first.html' );
		add_post_meta( $post_id, 'blogger_7_permalink', '/second.html' );
		add_post_meta( $post_id, 'blogger_8_permalink', '/other-blog.html' );

		$importer = new WP_Importer();

		$this->assertSame( 2, $importer->count_imported_posts( 'blogger', 7 ), 'Permalinks for the blog should be counted.' );
		$this->assertSame( 0, $importer->count_imported_posts( 'blogger', 9 ), 'A blog without imports should have none.' );
	}

	/**
	 * Tests that imported comments are mapped from their source comment ID for the given blog.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::get_imported_comments
	 */
	public function test_get_imported_comments_maps_source_ids_for_blog() {
		$post_id = self::factory()->post->create();

		$first  = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_agent'   => '7-15',
			)
		);
		$second = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_agent'   => '7-16',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_agent'   => '8-17',
			)
		);

		$this->assertSame(
			array(
				15 => $first,
				16 => $second,
			),
			( new WP_Importer() )->get_imported_comments( '7' )
		);
	}

	/**
	 * Tests that a numeric blog ID is returned as an integer.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::set_blog
	 *
	 * @group ms-excluded
	 */
	public function test_set_blog_returns_numeric_blog_id() {
		$this->assertSame( 1, ( new WP_Importer() )->set_blog( '1' ) );
	}

	/**
	 * Tests that set_blog() switches to the blog on multisite, resolving it by ID or URL.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::set_blog
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_set_blog_on_multisite
	 *
	 * @param string $blog Blog ID placeholder or URL passed to set_blog().
	 */
	public function test_set_blog_switches_to_blog_on_multisite( $blog ) {
		$blog_id = self::factory()->blog->create(
			array(
				'domain' => 'importer.example.org',
				'path'   => '/target/',
			)
		);

		$blog = str_replace( '%id%', $blog_id, $blog );

		$result = ( new WP_Importer() )->set_blog( $blog );

		$current = get_current_blog_id();
		restore_current_blog();

		$this->assertSame( $blog_id, $result, 'The blog ID should be returned.' );
		$this->assertSame( $blog_id, $current, 'The blog should be switched to.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_set_blog_on_multisite() {
		return array(
			'numeric ID'         => array( '%id%' ),
			'URL with scheme'    => array( 'https://importer.example.org/target/' ),
			'URL without scheme' => array( 'importer.example.org/target/' ),
		);
	}

	/**
	 * Tests that set_user() sets the current user by ID or login.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::set_user
	 *
	 * @dataProvider data_set_user
	 *
	 * @param bool $by_login Whether to pass the user login instead of the ID.
	 */
	public function test_set_user( $by_login ) {
		$user_id = self::factory()->user->create( array( 'user_login' => 'importer_user' ) );

		$this->assertSame( $user_id, ( new WP_Importer() )->set_user( $by_login ? 'importer_user' : (string) $user_id ) );
		$this->assertSame( $user_id, get_current_user_id(), 'The user should be set as the current user.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_set_user() {
		return array(
			'user ID'    => array( false ),
			'user login' => array( true ),
		);
	}

	/**
	 * Tests that cmpr_strlen() sorts the longest string first.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::cmpr_strlen
	 */
	public function test_cmpr_strlen_sorts_longest_first() {
		$importer = new WP_Importer();
		$strings  = array( 'bb', 'a', 'dddd', 'ccc' );

		usort( $strings, array( $importer, 'cmpr_strlen' ) );

		$this->assertSame( array( 'dddd', 'ccc', 'bb', 'a' ), $strings, 'Strings should be sorted by length, longest first.' );
		$this->assertSame( 0, $importer->cmpr_strlen( 'ab', 'cd' ), 'Strings of the same length should compare equal.' );
		$this->assertSame( 2, $importer->cmpr_strlen( 'a', 'abc' ), 'A shorter first string should sort after the longer one.' );
	}

	/**
	 * Tests the request made by get_page().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::get_page
	 * @covers WP_Importer::bump_request_timeout
	 *
	 * @dataProvider data_get_page_requests
	 *
	 * @param string      $username         Username.
	 * @param string      $password         Password.
	 * @param bool        $head             Whether to make a HEAD request.
	 * @param string      $method           Expected request method.
	 * @param string|null $authorization    Expected Authorization header, or null for none.
	 */
	public function test_get_page_request( $username, $password, $head, $method, $authorization ) {
		$requests = array();

		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) use ( &$requests ) {
				$requests[] = array( $url, $args );

				return array(
					'headers'  => array(),
					'body'     => 'page body',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$response = ( new WP_Importer() )->get_page( 'https://example.org/feed', $username, $password, $head );

		$this->assertSame( 'page body', wp_remote_retrieve_body( $response ), 'The response should be returned.' );
		$this->assertCount( 1, $requests, 'One request should be made.' );

		list( $url, $args ) = $requests[0];

		$this->assertSame( 'https://example.org/feed', $url, 'The URL did not match.' );
		$this->assertSame( $method, $args['method'], 'The request method did not match.' );
		$this->assertSame( 60, $args['timeout'], 'The request timeout should be bumped.' );
		$this->assertTrue( $args['reject_unsafe_urls'], 'The request should be a safe request.' );

		if ( null === $authorization ) {
			$this->assertArrayNotHasKey( 'Authorization', $args['headers'], 'No Authorization header should be sent.' );
		} else {
			$this->assertSame( $authorization, $args['headers']['Authorization'], 'The Authorization header did not match.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_page_requests() {
		return array(
			'GET without credentials' => array( '', '', false, 'GET', null ),
			'HEAD request'            => array( '', '', true, 'HEAD', null ),
			'truthy head is not HEAD' => array( '', '', 1, 'GET', null ),
			'with credentials'        => array( 'admin', 'secret', false, 'GET', 'Basic YWRtaW46c2VjcmV0' ),
			'username only'           => array( 'admin', '', false, 'GET', null ),
			'password only'           => array( '', 'secret', false, 'GET', null ),
		);
	}

	/**
	 * Tests that bump_request_timeout() always returns 60 seconds.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::bump_request_timeout
	 */
	public function test_bump_request_timeout() {
		$this->assertSame( 60, ( new WP_Importer() )->bump_request_timeout( 5 ) );
	}

	/**
	 * Tests that the user is never over quota on a single site.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::is_user_over_quota
	 *
	 * @group ms-excluded
	 */
	public function test_is_user_over_quota_on_single_site() {
		$this->assertFalse( ( new WP_Importer() )->is_user_over_quota() );
	}

	/**
	 * Tests the quota check on multisite.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::is_user_over_quota
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_quota_usage
	 *
	 * @param int    $space_used Space used, in megabytes.
	 * @param bool   $expected   Whether the user should be over quota.
	 * @param string $output     Expected output.
	 */
	public function test_is_user_over_quota_on_multisite( $space_used, $expected, $output ) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';

		update_site_option( 'upload_space_check_disabled', 0 );
		add_filter(
			'get_space_allowed',
			static function () {
				return 10;
			}
		);
		add_filter(
			'pre_get_space_used',
			static function () use ( $space_used ) {
				return $space_used;
			}
		);

		$this->expectOutputString( $output );
		$this->assertSame( $expected, ( new WP_Importer() )->is_user_over_quota() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_quota_usage() {
		return array(
			'under quota' => array( 5, false, '' ),
			'over quota'  => array( 20, true, 'Sorry, you have used your space allocation of 10 MB. Please delete some files to upload more files.' ),
		);
	}

	/**
	 * Tests that runs of whitespace are replaced with a single space.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::min_whitespace
	 *
	 * @dataProvider data_min_whitespace
	 *
	 * @param string $text     Text.
	 * @param string $expected Expected text.
	 */
	public function test_min_whitespace( $text, $expected ) {
		$this->assertSame( $expected, ( new WP_Importer() )->min_whitespace( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_min_whitespace() {
		return array(
			'mixed whitespace'     => array( "one \r\n\ttwo   three", 'one two three' ),
			'single spaces'        => array( 'one two', 'one two' ),
			'leading and trailing' => array( "\n one \t", ' one ' ),
			'no whitespace'        => array( 'one', 'one' ),
			'empty string'         => array( '', '' ),
			'zero string'          => array( '0', '0' ),
		);
	}

	/**
	 * Tests that stop_the_insanity() resets the query log and the action counts.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Importer::stop_the_insanity
	 */
	public function test_stop_the_insanity_resets_queries_and_actions() {
		global $wpdb, $wp_actions;

		$orig_queries    = $wpdb->queries;
		$orig_wp_actions = $wp_actions;

		try {
			$wpdb->queries = array( array( 'SELECT 1', 0.1, 'caller' ) );
			do_action( 'test_stop_the_insanity' );

			( new WP_Importer() )->stop_the_insanity();

			$this->assertSame( array(), $wpdb->queries, 'The query log should be emptied.' );
			$this->assertSame( array(), $wp_actions, 'The action counts should be emptied.' );
		} finally {
			$wpdb->queries = $orig_queries;
			$wp_actions    = $orig_wp_actions;
		}
	}

	/**
	 * Tests the command line arguments returned by get_cli_args().
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_cli_args
	 *
	 * @dataProvider data_get_cli_args
	 *
	 * @param array             $argv     Command line arguments, including the script name.
	 * @param string            $param    Parameter name.
	 * @param string|true|null  $expected Expected value.
	 */
	public function test_get_cli_args( $argv, $param, $expected ) {
		$_SERVER['argv'] = $argv;

		$this->assertSame( $expected, get_cli_args( $param ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_cli_args() {
		return array(
			'long option with a value'    => array( array( 'import.php', '--blog=7' ), 'blog', '7' ),
			'long option without a value' => array( array( 'import.php', '--verbose' ), 'verbose', true ),
			'value after a long option'   => array( array( 'import.php', '--user', 'admin' ), 'user', 'admin' ),
			'short options'               => array( array( 'import.php', '-vq' ), 'q', true ),
			'value after short options'   => array( array( 'import.php', '-vq', 'quiet' ), 'q', 'quiet' ),
			'later option overrides'      => array( array( 'import.php', '--blog=1', '--blog=2' ), 'blog', '2' ),
			'script name is ignored'      => array( array( '--blog=7' ), 'blog', null ),
			'value without an option'     => array( array( 'import.php', 'orphan' ), 'orphan', null ),
			'missing optional parameter'  => array( array( 'import.php', '--blog=7' ), 'user', null ),
			'empty value'                 => array( array( 'import.php', '--blog=' ), 'blog', '' ),
			'zero value'                  => array( array( 'import.php', '--blog=0' ), 'blog', '0' ),
		);
	}

	/**
	 * Tests that get_cli_args() returns null when `$_SERVER['argv']` is not an array.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_cli_args
	 */
	public function test_get_cli_args_without_argv_array() {
		$_SERVER['argv'] = 'not an array';

		$this->assertNull( get_cli_args( 'blog' ) );
	}
}
