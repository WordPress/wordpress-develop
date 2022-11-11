<?php

/**
 * @group comment
 *
 * @covers WP_Comment::get_instance
 */
class Tests_Comment_WpComment extends WP_UnitTestCase {
	protected static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		// Ensure that there is a comment with ID 1.
		$comment_1 = WP_Comment::get_instance( 1 );
		if ( ! $comment_1 ) {
			$wpdb->insert(
				$wpdb->comments,
				array(
					'comment_ID' => 1,
				)
			);

			clean_comment_cache( 1 );
		}

		self::$comment_id = $factory->comment->create();
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Comment::get_instance( (string) self::$comment_id );

		$this->assertEquals( self::$comment_id, $found->comment_ID );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Comment::get_instance( -self::$comment_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Comment::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Comment::get_instance( 1.0 );

		$this->assertEquals( 1, $found->comment_ID );
	}


	/**
	 * @dataProvider data_test_wp_comment_null_byte
	 * @ticket 52738
	 */
	public function test_wp_comment_null_byte( $comment_data, $expected ) {
		$comment = new WP_Comment( $comment_data );

		$this->assertSame( $comment->comment_content, $expected );
	}

	public function data_test_wp_comment_null_byte() {
		return array(
			array(
				(object) array(
					'comment_content' => 'Foo1',
					chr( 0 )          => 'null-byte',
				),
				'Foo1',
			),
			array(
				(object) array(
					'comment_content' => 'Foo2',
					chr( 0 ) . 'prop' => 'Starts with null-byte',
				),
				'Foo2',
			),
			array(
				(object) array(
					'comment_content' => 'Foo3',
					'prop' . chr( 0 ) => 'Ends with null-byte',
				),
				'Foo3',
			),
		);
	}
}
