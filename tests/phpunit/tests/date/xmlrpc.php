<?php

/**
 * @group date
 * @group datetime
 * @group xmlrpc
 * @covers IXR_Date
 */
class Tests_Date_XMLRPC extends WP_XMLRPC_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset the timezone option to the default value.
		update_option( 'timezone_string', '' );

		parent::tear_down();
	}

	/**
	 * @ticket 30429
	 *
	 * @covers wp_xmlrpc_server::mw_newPost
	 */
	public function test_date_new_post() {
		$timezone = 'Europe/Helsinki';
		update_option( 'timezone_string', $timezone );

		$datetime    = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$datetimeutc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->make_user_by_role( 'editor' );

		$post = get_post(
			$this->myxmlrpcserver->mw_newPost(
				array(
					1,
					'editor',
					'editor',
					array(
						'title'        => 'test',
						'post_content' => 'test',
						'dateCreated'  => new IXR_Date( $datetimeutc->format( 'Ymd\TH:i:s\Z' ) ),
					),
				)
			)
		);

		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$post->post_date,
			'UTC time with explicit time zone into mw_newPost'
		);

		$post = get_post(
			$this->myxmlrpcserver->mw_newPost(
				array(
					1,
					'editor',
					'editor',
					array(
						'title'        => 'test',
						'post_content' => 'test',
						'dateCreated'  => new IXR_Date( $datetime->format( 'Ymd\TH:i:s' ) ),
					),
				)
			)
		);

		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$post->post_date,
			'Local time w/o time zone into mw_newPost'
		);

		$post = get_post(
			$this->myxmlrpcserver->mw_newPost(
				array(
					1,
					'editor',
					'editor',
					array(
						'title'            => 'test',
						'post_content'     => 'test',
						'date_created_gmt' => new IXR_Date( $datetimeutc->format( 'Ymd\TH:i:s' ) ),
					),
				)
			)
		);

		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$post->post_date,
			'UTC time into mw_newPost'
		);

		$post = get_post(
			$this->myxmlrpcserver->wp_newPost(
				array(
					1,
					'editor',
					'editor',
					array(
						'title'        => 'test',
						'post_content' => 'test',
						'post_date'    => $datetime->format( 'Ymd\TH:i:s' ),
					),
				)
			)
		);

		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$post->post_date,
			'Local time into wp_newPost'
		);

		$post = get_post(
			$this->myxmlrpcserver->wp_newPost(
				array(
					1,
					'editor',
					'editor',
					array(
						'title'         => 'test',
						'post_content'  => 'test',
						'post_date_gmt' => $datetimeutc->format( 'Ymd\TH:i:s' ),
					),
				)
			)
		);

		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$post->post_date,
			'UTC time into wp_newPost'
		);
	}

	/**
	 * @ticket 30429
	 *
	 * @covers wp_xmlrpc_server::mw_editPost
	 */
	public function test_date_edit_post() {
		$timezone = 'Europe/Helsinki';
		update_option( 'timezone_string', $timezone );

		$datetime    = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$datetimeutc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		$editor_id = $this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $editor_id,
				'post_date'   => $datetime->modify( '-1 hour' )->format( 'Y-m-d H:i:s' ),
			)
		);

		$result = $this->myxmlrpcserver->mw_editPost(
			array(
				$post_id,
				'editor',
				'editor',
				array(
					'dateCreated' => new IXR_Date( $datetime->format( 'Ymd\TH:i:s' ) ),
				),
			)
		);

		$fetched_post = get_post( $post_id );

		$this->assertTrue( $result );
		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$fetched_post->post_date,
			'Local time into mw_editPost'
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $editor_id,
				'post_date'   => $datetime->modify( '-1 hour' )->format( 'Y-m-d H:i:s' ),
			)
		);

		$result = $this->myxmlrpcserver->mw_editPost(
			array(
				$post_id,
				'editor',
				'editor',
				array(
					'date_created_gmt' => new IXR_Date( $datetimeutc->format( 'Ymd\TH:i:s' ) ),
				),
			)
		);

		$fetched_post = get_post( $post_id );

		$this->assertTrue( $result );
		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$fetched_post->post_date,
			'UTC time into mw_editPost'
		);
	}

	/**
	 * @ticket 30429
	 *
	 * @covers wp_xmlrpc_server::wp_editComment
	 */
	public function test_date_edit_comment() {
		$timezone = 'Europe/Helsinki';
		update_option( 'timezone_string', $timezone );

		$datetime    = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		$datetime    = $datetime->modify( '-1 hour' );
		$datetimeutc = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->make_user_by_role( 'administrator' );
		$post_id = self::factory()->post->create();

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Test commenter',
			'comment_author_url'   => 'http://example.com/',
			'comment_author_email' => 'example@example.com',
			'comment_content'      => 'Hello, world!',
			'comment_approved'     => '1',
		);
		$comment_id   = wp_insert_comment( $comment_data );

		$result = $this->myxmlrpcserver->wp_editComment(
			array(
				1,
				'administrator',
				'administrator',
				$comment_id,
				array(
					'date_created_gmt' => new IXR_Date( $datetimeutc->format( 'Ymd\TH:i:s' ) ),
				),
			)
		);

		$fetched_comment = get_comment( $comment_id );

		$this->assertTrue( $result );
		$this->assertSame(
			$datetime->format( 'Y-m-d H:i:s' ),
			$fetched_comment->comment_date,
			'UTC time into wp_editComment'
		);
	}
}
