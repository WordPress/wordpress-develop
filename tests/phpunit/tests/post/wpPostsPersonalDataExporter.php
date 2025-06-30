<?php

/**
 * @group post
 * @group privacy
 *
 * @covers ::wp_posts_personal_data_exporter
 */
class Tests_Post_wpPostsPersonalDataExporter extends WP_UnitTestCase {

	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_email' => 'personal@local.host',
				'user_login' => 'user_login',
				'user_pass'  => 'password',
				'first_name' => 'First',
				'last_name'  => 'Last',
			)
		);
	}

	/**
	 * Testing the `wp_posts_personal_data_exporter()` function with standard post.
	 *
	 * @ticket 43809
	 */
	public function test_wp_posts_personal_data_exporter() {
		$args = array(
			'post_author' => self::$user_id,
			'post_title'  => 'Test Post Title',
			'post_status' => 'publish',
			'post_type'   => 'post',
		);

		$post_id = self::factory()->post->create( $args );
		$post    = get_post( $post_id );

		$actual = wp_posts_personal_data_exporter( 'personal@local.host' );

		$this->assertTrue( $actual['done'] );
		$this->assertCount( 1, $actual['data'] );
		$this->assertCount( 2, $actual['data'][0]['data'] );

		// Exported group.
		$this->assertSame( 'posts', $actual['data'][0]['group_id'] );
		$this->assertSame( 'Posts / Pages', $actual['data'][0]['group_label'] );

		// Exported post properties.
		$this->assertSame( 'Test Post Title', $actual['data'][0]['data'][0]['value'] );
		$this->assertSame( get_the_guid( $post ), $actual['data'][0]['data'][1]['value'] );

		// Verify item_id format.
		$this->assertSame( "post-{$post_id}", $actual['data'][0]['item_id'] );
	}

	/**
	 * Testing the `wp_posts_personal_data_exporter()` function with private post.
	 *
	 * @ticket 43809
	 */
	public function test_wp_posts_personal_data_exporter_private_post() {
		$args = array(
			'post_author' => self::$user_id,
			'post_title'  => 'Test Private Post',
			'post_status' => 'private',
			'post_type'   => 'post',
		);

		$post_id = self::factory()->post->create( $args );
		$post    = get_post( $post_id );

		$actual = wp_posts_personal_data_exporter( 'personal@local.host' );

		$this->assertTrue( $actual['done'] );

		$private_post_data = null;
		foreach ( $actual['data'] as $post_data ) {
			if ( "post-{$post_id}" === $post_data['item_id'] ) {
				$private_post_data = $post_data;
				break;
			}
		}

		$this->assertNotNull( $private_post_data, 'Private post not found in export data' );

		// Private posts should have title and ID but not URL.
		$this->assertCount( 2, $private_post_data['data'] );

		// Title should be prefixed with "Private:".
		$this->assertStringContainsString( 'Private:', $private_post_data['data'][0]['value'] );
		$this->assertSame( 'Post ID', $private_post_data['data'][1]['name'] );
		$this->assertSame( $post_id, $private_post_data['data'][1]['value'] );
	}

	/**
	 * Testing the `wp_posts_personal_data_exporter()` function with password protected post.
	 *
	 * @ticket 43809
	 */
	public function test_wp_posts_personal_data_exporter_password_protected_post() {
		$args = array(
			'post_author'   => self::$user_id,
			'post_title'    => 'Test Password Protected Post',
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_password' => 'password',
		);

		$post_id = self::factory()->post->create( $args );
		$post    = get_post( $post_id );

		$actual = wp_posts_personal_data_exporter( 'personal@local.host' );

		$this->assertTrue( $actual['done'] );

		$password_post_data = null;
		foreach ( $actual['data'] as $post_data ) {
			if ( "post-{$post_id}" === $post_data['item_id'] ) {
				$password_post_data = $post_data;
				break;
			}
		}

		$this->assertNotNull( $password_post_data, 'Password protected post not found in export data' );
		$this->assertCount( 2, $password_post_data['data'] );
		$this->assertStringContainsString( 'Password Protected:', $password_post_data['data'][0]['value'] );
		$this->assertSame( 'Post ID', $password_post_data['data'][1]['name'] );
		$this->assertSame( $post_id, $password_post_data['data'][1]['value'] );
	}

	/**
	 * Testing the `wp_posts_personal_data_exporter()` function for no posts found.
	 *
	 * @ticket 43809
	 */
	public function test_wp_posts_personal_data_exporter_no_posts_found() {
		$actual = wp_posts_personal_data_exporter( 'nopostsfound@local.host' );

		$expected = array(
			'data' => array(),
			'done' => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_posts_personal_data_exporter()` function with pagination.
	 *
	 * @ticket 43809
	 */
	public function test_wp_posts_personal_data_exporter_pagination() {
		$post_ids = self::factory()->post->create_many(
			3,
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		add_filter(
			'wp_privacy_personal_data_posts_batch_size',
			function () {
				return 1;
			}
		);

		$actual_page1 = wp_posts_personal_data_exporter( 'personal@local.host', 1 );

		$this->assertCount( 1, $actual_page1['data'] );
		$this->assertFalse( $actual_page1['done'] );

		$actual_page2 = wp_posts_personal_data_exporter( 'personal@local.host', 2 );

		// Second page should have another post.
		$this->assertCount( 1, $actual_page2['data'] );
		$this->assertFalse( $actual_page2['done'] );

		$actual_page3 = wp_posts_personal_data_exporter( 'personal@local.host', 3 );

		// Third page should have the last post.
		$this->assertCount( 1, $actual_page3['data'] );
		$this->assertTrue( $actual_page3['done'] );

		// Test empty page.
		$actual_page4 = wp_posts_personal_data_exporter( 'personal@local.host', 4 );
		$this->assertCount( 0, $actual_page4['data'] );
		$this->assertTrue( $actual_page4['done'] );
	}
}
