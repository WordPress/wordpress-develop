<?php

/**
 * @group comment
 * @ticket 38027
 *
 * @covers ::get_lastcommentmodified
 */
class Tests_Comment_GetLastCommentModified extends WP_UnitTestCase {
	public function test_no_comments() {
		$this->assertFalse( get_lastcommentmodified() );
	}

	public function test_default_timezone() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		$this->assertSame( strtotime( '2000-01-01 10:00:00' ), strtotime( get_lastcommentmodified() ) );
	}

	public function test_server_timezone() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		$this->assertSame( strtotime( '2000-01-01 10:00:00' ), strtotime( get_lastcommentmodified() ) );
	}

	public function test_blog_timezone() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		$this->assertSame( '2000-01-01 11:00:00', get_lastcommentmodified( 'blog' ) );
	}

	public function test_gmt_timezone() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		$this->assertSame( strtotime( '2000-01-01 10:00:00' ), strtotime( get_lastcommentmodified( 'GMT' ) ) );
	}

	public function test_unknown_timezone() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		$this->assertFalse( get_lastcommentmodified( 'foo' ) );
	}

	public function test_data_is_cached() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2015-04-01 11:00:00',
				'comment_date_gmt' => '2015-04-01 10:00:00',
			)
		);

		get_lastcommentmodified();
		$this->assertSame( strtotime( '2015-04-01 10:00:00' ), strtotime( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) ) );
	}

	public function test_cache_is_cleared() {
		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		get_lastcommentmodified();

		$this->assertSame( strtotime( '2000-01-01 10:00:00' ), strtotime( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) ) );

		self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-02 11:00:00',
				'comment_date_gmt' => '2000-01-02 10:00:00',
			)
		);

		$this->assertFalse( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) );
		$this->assertSame( strtotime( '2000-01-02 10:00:00' ), strtotime( get_lastcommentmodified() ) );
		$this->assertSame( strtotime( '2000-01-02 10:00:00' ), strtotime( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) ) );
	}

	public function test_cache_is_cleared_when_comment_is_trashed() {
		$comment_1 = self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '1998-01-01 11:00:00',
				'comment_date_gmt' => '1998-01-01 10:00:00',
			)
		);

		$comment_2 = self::factory()->comment->create_and_get(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-02 11:00:00',
				'comment_date_gmt' => '2000-01-02 10:00:00',
			)
		);

		get_lastcommentmodified();

		$this->assertSame( strtotime( '2000-01-02 10:00:00' ), strtotime( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) ) );

		wp_trash_comment( $comment_2->comment_ID );

		$this->assertFalse( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) );
		$this->assertSame( strtotime( '1998-01-01 10:00:00' ), strtotime( get_lastcommentmodified() ) );
		$this->assertSame( strtotime( '1998-01-01 10:00:00' ), strtotime( wp_cache_get( 'lastcommentmodified:server', 'timeinfo' ) ) );
	}
	/**
	 * Internal comment types are not user-facing discussion, so they must not
	 * move the last comment modified date.
	 *
	 * @ticket 63191
	 *
	 * @dataProvider data_internal_comment_types_are_excluded
	 *
	 * @param string $timezone Timezone argument to pass to get_lastcommentmodified().
	 * @param string $expected Expected date.
	 */
	public function test_internal_comment_types_are_excluded( $timezone, $expected ) {
		self::factory()->comment->create(
			array(
				'comment_status'   => 1,
				'comment_date'     => '2000-01-01 11:00:00',
				'comment_date_gmt' => '2000-01-01 10:00:00',
			)
		);

		foreach ( wp_get_internal_comment_types() as $comment_type ) {
			self::factory()->comment->create(
				array(
					'comment_status'   => 1,
					'comment_type'     => $comment_type,
					'comment_date'     => '2020-01-01 11:00:00',
					'comment_date_gmt' => '2020-01-01 10:00:00',
				)
			);
		}

		$this->assertSame( strtotime( $expected ), strtotime( get_lastcommentmodified( $timezone ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ timezone: string, expected: string }>
	 */
	public function data_internal_comment_types_are_excluded() {
		return array(
			'server timezone' => array(
				'timezone' => 'server',
				'expected' => '2000-01-01 10:00:00',
			),
			'blog timezone'   => array(
				'timezone' => 'blog',
				'expected' => '2000-01-01 11:00:00',
			),
			'gmt timezone'    => array(
				'timezone' => 'gmt',
				'expected' => '2000-01-01 10:00:00',
			),
		);
	}

	/**
	 * With nothing but internal comment types stored there is no last modified date.
	 *
	 * @ticket 63191
	 */
	public function test_only_internal_comment_types_returns_false() {
		foreach ( wp_get_internal_comment_types() as $comment_type ) {
			self::factory()->comment->create(
				array(
					'comment_status'   => 1,
					'comment_type'     => $comment_type,
					'comment_date'     => '2020-01-01 11:00:00',
					'comment_date_gmt' => '2020-01-01 10:00:00',
				)
			);
		}

		$this->assertFalse( get_lastcommentmodified() );
	}
}
