<?php

/**
 * @group post
 */
class Tests_Post_wpPost extends WP_UnitTestCase {
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		// Ensure that there is a post with ID 1.
		if ( ! get_post( 1 ) ) {
			$wpdb->insert(
				$wpdb->posts,
				array(
					'ID'         => 1,
					'post_title' => 'Post 1',
				)
			);
		}

		self::$post_id = $factory->post->create();
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Post::get_instance( (string) self::$post_id );

		$this->assertSame( self::$post_id, $found->ID );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Post::get_instance( -self::$post_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Post::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Post::get_instance( 1.0 );

		$this->assertSame( 1, $found->ID );
	}

	/**
	 * @dataProvider data_test_wp_post_null_byte
	 * @ticket 52738
	 */
	public function test_wp_post_null_byte_php_7_or_greater( $post_data, $expected ) {
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			$this->markTestSkipped( 'This test can only run on PHP 7.0 or greater due to illegal member variable name.' );
		}

		$post = new WP_Post( $post_data );

		$this->assertSame( $post->post_title, $expected );
	}

	public function data_test_wp_post_null_byte() {
		return array(
			array(
				(object) array(
					'post_title' => 'Foo1',
					chr( 0 )     => 'null-byte',
				),
				'Foo1',
			),
			array(
				(object) array(
					'post_title'      => 'Foo2',
					chr( 0 ) . 'prop' => 'Starts with null-byte',
				),
				'Foo2',
			),
			array(
				(object) array(
					'post_title'      => 'Foo3',
					'prop' . chr( 0 ) => 'Ends with null-byte',
				),
				'Foo3',
			),
		);
	}
}
