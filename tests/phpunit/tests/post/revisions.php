<?php

/**
 * @group post
 * @group revision
 */
class Tests_Post_Revisions extends WP_UnitTestCase {

	const POST_TYPE = 'test-revision';

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $author_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_user_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Note: Test needs reviewing when #16215 is fixed because I'm not sure the test current tests the "correct" behavior
	 *
	 * @ticket 20982
	 * @ticket 16215
	 */
	public function test_revision_restore_updates_edit_last_post_meta() {
		// Create a post as Author.
		wp_set_current_user( self::$author_user_id );
		$post    = get_default_post_to_edit( 'post', true );
		$post_id = $post->ID;

		wp_update_post(
			array(
				'post_status'  => 'draft',
				'post_content' => 'I cant spel werds.',
				'ID'           => $post_id,
			)
		);

		// Update post as Editor.
		wp_set_current_user( self::$editor_user_id );
		wp_update_post(
			array(
				'post_content' => 'The Editor was in fixing your typos.',
				'ID'           => $post_id,
			)
		);

		// Restore back as Admin.
		wp_set_current_user( self::$admin_user_id );
		$revisions = wp_get_post_revisions( $post->ID );
		$this->assertCount( 2, $revisions );

		$lastrevision = end( $revisions );
		$this->assertSame( 'I cant spel werds.', $lastrevision->post_content );
		// #16215
		$this->assertEquals( self::$author_user_id, $lastrevision->post_author );

		wp_restore_post_revision( $lastrevision->ID );

		// Is post_meta correctly set to revision author after restoring user?
		$this->assertEquals( self::$admin_user_id, get_post_meta( $post_id, '_edit_last', true ) );
	}

	/**
	 * @ticket 7392
	 * @ticket 9843
	 */
	public function test_revision_dont_save_revision_if_unchanged() {
		$post    = get_default_post_to_edit( 'post', true );
		$post_id = $post->ID;

		$this->assertCount( 0, wp_get_post_revisions( $post_id ) ); // No revisions on auto-draft creation.

		wp_update_post(
			array(
				'post_status'  => 'draft',
				'post_title'   => 'some-post',
				'post_content' => 'some_content',
				'ID'           => $post_id,
			)
		);

		$this->assertCount( 1, wp_get_post_revisions( $post_id ) ); // Just the initial revision.

		// First update.
		wp_update_post(
			array(
				'post_content' => 'some updated content',
				'ID'           => $post_id,
			)
		); // First revision.

		$this->assertCount( 2, wp_get_post_revisions( $post_id ) ); // Should be 2 revisions so far.

		// Update the post.
		wp_update_post(
			array(
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Second revision.
		$this->assertCount( 3, wp_get_post_revisions( $post_id ) ); // Should be 3 revisions so far.

		// Next, try to save another identical update, tests for patch that prevents storing duplicates.
		wp_update_post(
			array(
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Content unchanged, shouldn't save.
		$this->assertCount( 3, wp_get_post_revisions( $post_id ) ); // Should still be 3 revisions.

		// Next, try to save another update, same content, but new title, should save revision.
		wp_update_post(
			array(
				'post_title'   => 'some-post-changed',
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		);
		$this->assertCount( 4, wp_get_post_revisions( $post_id ) ); // Should be 4 revisions.

		// Next, try to save another identical update.
		wp_update_post(
			array(
				'post_title'   => 'some-post-changed',
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Content unchanged, shouldn't save.
		$this->assertCount( 4, wp_get_post_revisions( $post_id ) ); // Should still be 4 revisions.
	}

	/**
	 * @ticket 7392
	 * @ticket 9843
	 */
	public function test_revision_force_save_revision_even_if_unchanged() {
		add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );

		$post    = get_default_post_to_edit( 'post', true );
		$post_id = $post->ID;

		$this->assertCount( 0, wp_get_post_revisions( $post_id ) ); // No revisions on auto-draft creation.

		wp_update_post(
			array(
				'post_status'  => 'draft',
				'post_title'   => 'some-post',
				'post_type'    => 'post',
				'post_content' => 'some_content',
				'ID'           => $post_id,
			)
		);

		$this->assertCount( 1, wp_get_post_revisions( $post_id ) );

		// First update.
		wp_update_post(
			array(
				'post_content' => 'some updated content',
				'ID'           => $post_id,
			)
		); // First revision.
		$this->assertCount( 2, wp_get_post_revisions( $post_id ) );

		// Update the post.
		wp_update_post(
			array(
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Second revision.
		$this->assertCount( 3, wp_get_post_revisions( $post_id ) );

		// Next, try to save another identical update, tests for patch that prevents storing duplicates.
		wp_update_post(
			array(
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Content unchanged, shouldn't save.
		$this->assertCount( 4, wp_get_post_revisions( $post_id ) );

		// Next, try to save another update, same content, but new title, should save revision.
		wp_update_post(
			array(
				'post_title'   => 'some-post-changed',
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		);
		$this->assertCount( 5, wp_get_post_revisions( $post_id ) );

		// Next, try to save another identical update.
		wp_update_post(
			array(
				'post_title'   => 'some-post-changed',
				'post_content' => 'new update for some updated content',
				'ID'           => $post_id,
			)
		); // Content unchanged, shouldn't save.
		$this->assertCount( 6, wp_get_post_revisions( $post_id ) );

		remove_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
	}

	/**
	 * Tests the Caps used in the action=view case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_view_caps_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $post_id ) );

		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $revision->ID ) );
		}

		// Author should be able to view the revisions fine.
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$author_user_id, 'read_post', $revision->ID ) );
		}
	}

	/**
	 * Tests the Caps used in the action=restore case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_restore_caps_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'edit_post', $revision->post_parent ) );
		}

		// Author shouldn't be able to restore the revisions.
		foreach ( $revisions as $revision ) {
			$this->assertFalse( user_can( self::$author_user_id, 'edit_post', $revision->post_parent ) );
		}
	}

	/**
	 * Tests the Caps used in the action=diff case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_diff_caps_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is even better',
				'ID'           => $post_id,
			)
		);

		// Diff checks if you can read both left and right revisions.
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $revision->ID ) );
		}

		// Author should be able to diff the revisions fine.
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$author_user_id, 'read_post', $revision->ID ) );
		}
	}

	/**
	 * Tests the Caps used in the action=view case of wp-admin/revision.php with a CPT with Custom Capabilities
	 *
	 * @ticket 16847
	 */
	public function test_revision_view_caps_cpt() {
		register_post_type(
			self::POST_TYPE,
			array(
				'capability_type' => 'event',
				'map_meta_cap'    => true,
				'supports'        => array( 'revisions' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::POST_TYPE,
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $post_id ) );

		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $revision->ID ) );
		}

		// Author should be able to view the revisions fine.
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$author_user_id, 'read_post', $revision->ID ) );
		}
	}

	/**
	 * Tests the Caps used in the action=restore case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_restore_caps_cpt() {
		register_post_type(
			self::POST_TYPE,
			array(
				'capability_type' => 'event',
				'map_meta_cap'    => true,
				'supports'        => array( 'revisions' ),
			)
		);

		// The minimum extra caps needed for this test normally you would give the role all the relevant caps.
		$editor_user = new WP_User( self::$editor_user_id );
		$editor_user->add_cap( 'edit_published_events' );

		// Create a post as Editor.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::POST_TYPE,
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'edit_post', $revision->post_parent ) );
		}

		// Author shouldn't be able to restore the revisions.
		wp_set_current_user( self::$author_user_id );
		foreach ( $revisions as $revision ) {
			$this->assertFalse( user_can( self::$author_user_id, 'edit_post', $revision->post_parent ) );
		}
	}

	/**
	 * Tests the Caps used in the action=restore case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_restore_caps_before_publish() {
		register_post_type(
			self::POST_TYPE,
			array(
				'capability_type' => 'post',
				'capabilities'    => array(
					// No one can edit this post type once published.
					// So, revisions cannot be restored, either.
					'edit_published_posts' => 'do_not_allow',
				),
				'map_meta_cap'    => true,
				'supports'        => array( 'revisions' ),
			)
		);

		$old_id = get_current_user_id();
		wp_set_current_user( self::$editor_user_id );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertTrue( current_user_can( 'edit_post', $revision->post_parent ) );
			$this->assertTrue( current_user_can( 'edit_post', $revision->ID ) );
		}

		wp_update_post(
			array(
				'post_status'  => 'publish',
				'ID'           => $post_id,
				'post_content' => 'content',
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertFalse( current_user_can( 'edit_post', $revision->post_parent ) );
			$this->assertFalse( current_user_can( 'edit_post', $revision->ID ) );
		}
		wp_set_current_user( $old_id );
	}

	/**
	 * Tests the Caps used in the action=diff case of wp-admin/revision.php
	 *
	 * @ticket 16847
	 */
	public function test_revision_diff_caps_cpt() {
		register_post_type(
			self::POST_TYPE,
			array(
				'capability_type' => 'event',
				'map_meta_cap'    => true,
				'supports'        => array( 'revisions' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => self::POST_TYPE,
				'post_author' => self::$editor_user_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is much better',
				'ID'           => $post_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is even better',
				'ID'           => $post_id,
			)
		);

		// Diff checks if you can read both left and right revisions.
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $revisions );
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$editor_user_id, 'read_post', $revision->ID ) );
		}

		// Author should be able to diff the revisions fine.
		foreach ( $revisions as $revision ) {
			$this->assertTrue( user_can( self::$author_user_id, 'read_post', $revision->ID ) );
		}
	}

	/**
	 * @ticket 26042
	 */
	public function test_wp_get_post_revisions_should_order_by_post_date() {
		global $wpdb;

		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'some-post',
				'post_type'    => 'post',
				'post_content' => 'some_content',
			)
		);

		$post                 = (array) $post;
		$post_revision_fields = _wp_post_revision_data( $post );
		$post_revision_fields = wp_slash( $post_revision_fields );

		$revision_ids = array();
		$now          = time();
		for ( $j = 1; $j < 3; $j++ ) {
			// Manually modify dates to ensure they're different.
			$date                                  = gmdate( 'Y-m-d H:i:s', $now - ( $j * 10 ) );
			$post_revision_fields['post_date']     = $date;
			$post_revision_fields['post_date_gmt'] = $date;

			$revision_id = wp_insert_post( $post_revision_fields );

			$revision_ids[] = $revision_id;
		}

		$revisions = wp_get_post_revisions( $post['ID'] );

		$this->assertSame( $revision_ids, array_values( wp_list_pluck( $revisions, 'ID' ) ) );
	}

	/**
	 * @ticket 26042
	 */
	public function test_wp_get_post_revisions_should_order_by_ID_when_post_date_matches() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'some-post',
				'post_type'    => 'post',
				'post_content' => 'some_content',
			)
		);

		$post                 = (array) $post;
		$post_revision_fields = _wp_post_revision_data( $post );
		$post_revision_fields = wp_slash( $post_revision_fields );

		$revision_ids = array();
		$date         = gmdate( 'Y-m-d H:i:s', time() - 10 );
		for ( $j = 1; $j < 3; $j++ ) {
			// Manually modify dates to ensure they're the same.
			$post_revision_fields['post_date']     = $date;
			$post_revision_fields['post_date_gmt'] = $date;

			$revision_id = wp_insert_post( $post_revision_fields );

			$revision_ids[] = $revision_id;
		}

		rsort( $revision_ids );

		$revisions = wp_get_post_revisions( $post['ID'] );

		$this->assertSame( $revision_ids, array_values( wp_list_pluck( $revisions, 'ID' ) ) );
	}

	/**
	 * @ticket 51550
	 */
	public function test_wp_revisions_to_keep_filter() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'some-post',
				'post_type'    => 'post',
				'post_content' => 'some_content',
			)
		);

		$default  = wp_revisions_to_keep( $post );
		$expected = $default + 1;

		add_filter(
			'wp_revisions_to_keep',
			static function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, wp_revisions_to_keep( $post ) );
	}

	/**
	 * @ticket 51550
	 */
	public function test_wp_post_type_revisions_to_keep_filter() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'some-post',
				'post_type'    => 'post',
				'post_content' => 'some_content',
			)
		);

		$default = wp_revisions_to_keep( $post );
		$generic = $default + 1;

		add_filter(
			'wp_revisions_to_keep',
			static function () use ( $generic ) {
				return $generic;
			}
		);

		$this->assertSame( $generic, wp_revisions_to_keep( $post ) );

		$expected = $generic + 1;

		add_filter(
			"wp_{$post->post_type}_revisions_to_keep",
			static function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, wp_revisions_to_keep( $post ) );
	}

	/**
	 * Verifies that trying to create a revision with an invalid ID returns a WP_Error.
	 *
	 * @ticket 30009
	 */
	public function test_wp_save_post_revision_error() {
		$post = self::factory()->post->create_and_get(
			array(
				'ID' => PHP_INT_MAX,
			)
		);

		$revision = _wp_put_post_revision( $post );

		$this->assertWPError( $revision );
	}

	/**
	 * Tests that wp_get_latest_revision_id_and_total_count() returns the latest revision ID and total count.
	 *
	 * @covers ::wp_get_latest_revision_id_and_total_count
	 * @ticket 55857
	 * @dataProvider data_wp_get_post_revisions_url
	 */
	public function test_wp_get_latest_revision_id_and_total_count( $revisions ) {
		$post_id = self::factory()->post->create();
		for ( $i = 0; $i < $revisions; ++$i ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => 'Some Post',
				)
			);
		}

		$post_revisions       = wp_get_post_revisions( $post_id );
		$latest_post_revision = current( $post_revisions );
		$revisions            = wp_get_latest_revision_id_and_total_count( $post_id );

		$this->assertSame(
			$latest_post_revision->ID,
			$revisions['latest_id'],
			'The latest revision ID does not match.'
		);

		$this->assertSame(
			count( $post_revisions ),
			$revisions['count'],
			'The total count of revisions does not match.'
		);
	}

	/**
	 * Tests that wp_get_latest_revision_id_and_total_count() returns a WP_Error when no revisions exist.
	 *
	 * @covers ::wp_get_latest_revision_id_and_total_count
	 * @ticket 55857
	 */
	public function test_wp_get_latest_revision_id_and_total_count_no_revisions() {
		$revision = wp_get_latest_revision_id_and_total_count( null );

		$this->assertWPError( $revision, 'Invalid post, no revisions should exist.' );
		$this->assertSame( $revision->get_error_code(), 'invalid_post' );

		add_filter( 'wp_revisions_to_keep', '__return_zero' );
		$post_id  = self::factory()->post->create();
		$revision = wp_get_latest_revision_id_and_total_count( $post_id );

		$this->assertWPError( $revision, 'Revisions should not be enabled.' );
		$this->assertSame( $revision->get_error_code(), 'revisions_not_enabled' );
	}

	/**
	 * Tests that wp_get_post_revisions_url() returns the revisions URL.
	 *
	 * @ticket 39062
	 *
	 * @dataProvider data_wp_get_post_revisions_url
	 *
	 * @covers ::wp_get_post_revisions_url
	 *
	 * @param int $revisions The number of revisions to create.
	 */
	public function test_wp_get_post_revisions_url( $revisions ) {
		wp_set_current_user( self::$admin_user_id );

		$post_id            = self::factory()->post->create( array( 'post_title' => 'Some Post' ) );
		$latest_revision_id = null;

		if ( 0 !== $revisions ) {
			$latest_revision_id = $post_id;

			for ( $i = 0; $i < $revisions; ++$i ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => 'Some Post ' . $i,
					)
				);

				$latest_revision_id++;
			}
		}

		$expected = admin_url( 'revision.php?revision=' . $latest_revision_id );

		$this->assertSame(
			$expected,
			wp_get_post_revisions_url( $post_id ),
			'Failed when passed the Post ID'
		);

		$this->assertSame(
			$expected,
			wp_get_post_revisions_url( $latest_revision_id ),
			'Failed when passed the latest revision ID'
		);
	}

	/**
	 * Tests that wp_get_post_revisions_url() returns the revisions URL
	 * when passed a WP_Post object.
	 *
	 * @ticket 39062
	 *
	 * @dataProvider data_wp_get_post_revisions_url
	 *
	 * @covers ::wp_get_post_revisions_url
	 *
	 * @param int $revisions The number of revisions to create.
	 */
	public function test_wp_get_post_revisions_url_with_post_object( $revisions ) {
		wp_set_current_user( self::$admin_user_id );

		$post               = self::factory()->post->create_and_get( array( 'post_title' => 'Some Post' ) );
		$latest_revision_id = null;

		if ( 0 !== $revisions ) {
			$latest_revision_id = $post->ID;

			for ( $i = 0; $i < $revisions; ++$i ) {
				wp_update_post(
					array(
						'ID'         => $post->ID,
						'post_title' => 'Some Post ' . $i,
					)
				);

				$latest_revision_id++;
			}
		}

		$expected = admin_url( 'revision.php?revision=' . $latest_revision_id );

		$this->assertSame(
			$expected,
			wp_get_post_revisions_url( $post ),
			'Failed when passed the Post Object'
		);

		$this->assertSame(
			$expected,
			wp_get_post_revisions_url( $latest_revision_id ),
			'Failed when passed the latest revision ID'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_get_post_revisions_url() {
		return array(
			'one revision'       => array( 'revisions' => 1 ),
			'multiple revisions' => array( 'revisions' => 2 ),
		);
	}

	/**
	 * Tests that wp_get_post_revisions_url() returns NULL when a post does not exist.
	 *
	 * @ticket 39062
	 *
	 * @covers ::wp_get_post_revisions_url
	 */
	public function test_wp_get_post_revisions_url_returns_null_when_post_does_not_exist() {
		wp_set_current_user( self::$admin_user_id );
		$post_id = 99999;
		$this->assertNull( wp_get_post_revisions_url( $post_id ) );
	}

	/**
	 * Tests that wp_get_post_revisions_url() returns NULL when there are no revisions.
	 *
	 * @ticket 39062
	 *
	 * @covers ::wp_get_post_revisions_url
	 */
	public function test_wp_get_post_revisions_url_returns_null_with_no_revisions() {
		wp_set_current_user( self::$admin_user_id );
		$post_id = self::factory()->post->create( array( 'post_title' => 'Some Post' ) );
		$this->assertNull( wp_get_post_revisions_url( $post_id ) );
	}

	/**
	 * Tests that wp_get_post_revisions_url() returns NULL when revisions are disabled.
	 *
	 * @ticket 39062
	 *
	 * @covers ::wp_get_post_revisions_url
	 */
	public function test_wp_get_post_revisions_url_returns_null_with_revisions_disabled() {
		wp_set_current_user( self::$admin_user_id );

		remove_post_type_support( 'post', 'revisions' );

		$post_id = self::factory()->post->create( array( 'post_title' => 'Some Post' ) );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Some Post 2',
			)
		);

		$this->assertNull( wp_get_post_revisions_url( $post_id ) );

		add_post_type_support( 'post', 'revisions' );
	}
}
