<?php

/**
 *
 * Tests to the Status Query that consider user permission
 *
 * @group query
 */
class Tests_Query_PostStatus_Perm extends WP_UnitTestCase {

	static $subscriber;
	static $editor;

	static public function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$role = get_role( 'subscriber' );
		$role->add_cap( 'read_private_pages' );

		self::$subscriber = $factory->user->create(
			array(
				'user_login' => 'subscriber',
				'user_role'  => 'subscriber',
				'user_pass'  => '123',
			)
		);

		self::$editor = $factory->user->create(
			array(
				'user_login' => 'editor',
				'user_role'  => 'editor',
				'user_pass'  => '123',
			)
		);

		$factory->post->create(
			array(
				'post_title'  => 'public post',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_author' => self::$editor,
			)
		);

		$factory->post->create(
			array(
				'post_title'  => 'public page',
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_author' => self::$editor,
			)
		);

		$factory->post->create(
			array(
				'post_title'  => 'private post',
				'post_status' => 'private',
				'post_type'   => 'post',
				'post_author' => self::$editor,
			)
		);

		$factory->post->create(
			array(
				'post_title'  => 'private page',
				'post_status' => 'private',
				'post_type'   => 'page',
				'post_author' => self::$editor,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		$role->remove_cap( 'read_private_pages' );
	}

	public function test_perm_readable() {
		wp_set_current_user( 1 ); // admin.
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'private',
				'perm'        => 'readable',
			)
		);
		$this->assertSame( 2, $query->found_posts, 'admin can read private posts and pages and should see both editor\'s posts' );
	}

	public function test_perm_readable_multiple_statuses() {
		wp_set_current_user( 1 ); // admin.
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => array( 'private', 'publish' ),
				'perm'        => 'readable',
			)
		);
		$this->assertSame( 4, $query->found_posts, 'admin can read private posts and pages and should see both editor\'s posts' );
	}

	public function test_perm_editable() {
		wp_set_current_user( 1 ); // admin.
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'private',
				'perm'        => 'editable',
			)
		);
		$this->assertSame( 2, $query->found_posts, 'admin can edit private posts and pages and should see both editor\'s posts' );
	}

	public function test_perm_readable_not_admin() {
		wp_set_current_user( self::$subscriber );
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'private',
				'perm'        => 'readable',
			)
		);

		$this->assertSame( 1, $query->found_posts, 'subscriber in this test can read private pages (but not posts) and should see editor\'s page' );
	}

	public function test_perm_editable_not_admin() {
		wp_set_current_user( self::$subscriber );
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => array( 'private', 'publish' ),
				'perm'        => 'editable',
			)
		);

		$this->assertSame( 0, $query->found_posts, 'subscriber cant edit any of editor\'s content and should get empty results' );
	}

	public function test_perm_readable_custom_status() {
		wp_set_current_user( self::$subscriber );

		register_post_status(
			'private_test',
			array(
				'private' => true,
			)
		);

		wp_insert_post(
			array(
				'post_title'  => 'private custom post status',
				'post_status' => 'private_test',
				'post_type'   => 'post',
				'post_author' => self::$editor,
			)
		);

		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'private_test',
				'perm'        => 'readable',
			)
		);
		$this->assertSame( 0, $query->found_posts, 'subscriber cant read private posts of this custom status and should not get any result' );
	}

	public function test_invalid_status() {
		wp_set_current_user( 1 );
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'invalid_status',
				'perm'        => 'readable',
			)
		);
		$this->assertSame( 0, $query->found_posts, 'querying only for an invalid status should return no results' );
	}

	public function test_any_status() {
		wp_set_current_user( self::$subscriber );
		$query = new WP_Query(
			array(
				'post_type'   => array(
					'post',
					'page',
				),
				'post_status' => 'any',
				'perm'        => 'readable',
			)
		);
		$this->assertSame( 0, $query->found_posts, 'querying only for an invalid status should return no results' );
	}

}
