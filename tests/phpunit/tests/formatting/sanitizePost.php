<?php
/**
 * @group formatting
 * @group post
 *
 * @covers ::sanitize_post
 * @covers WP_Post::__construct
 */
class Tests_Formatting_SanitizePost extends WP_UnitTestCase {

	/**
	 * @ticket 22324
	 */
	public function test_int_fields() {
		$post       = self::factory()->post->create_and_get();
		$int_fields = array(
			'ID'            => 'integer',
			'post_parent'   => 'integer',
			'menu_order'    => 'integer',
			'post_author'   => 'string',
			'comment_count' => 'string',
		);

		foreach ( $int_fields as $field => $type ) {
			switch ( $type ) {
				case 'integer':
					$this->assertIsInt( $post->$field, "field $field" );
					break;
				case 'string':
					$this->assertIsString( $post->$field, "field $field" );
					break;
			}
		}
	}

	/**
	 * @dataProvider data_test_null_byte
	 * @ticket 52738
	 */
	public function test_null_byte_php_7_or_greater( $post_data ) {
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			$this->markTestSkipped( 'This test can only run on PHP 7.0 or greater due to illegal member variable name.' );
		}

		$post = sanitize_post( $post_data );

		$this->assertSame( $post->post_type, $post_data->post_type );
	}

	public function data_test_null_byte() {
		return array(
			array(
				(object) array(
					'post_status' => 'publish',
					'post_title'  => new WP_UnitTest_Generator_Sequence( 'Post title %s' ),
					'post_type'   => 'post',
					chr( 0 )      => 'Null-byte',
				),
			),
			array(
				(object) array(
					'post_status'     => 'publish',
					'post_title'      => new WP_UnitTest_Generator_Sequence( 'Post title %s' ),
					'post_type'       => 'post',
					chr( 0 ) . 'prop' => 'Starts with null-byte',
				),
			),
			array(
				(object) array(
					'post_status'     => 'publish',
					'post_title'      => new WP_UnitTest_Generator_Sequence( 'Post title %s' ),
					'post_type'       => 'post',
					'prop' . chr( 0 ) => 'Ends with null-byte',
				),
			),
		);
	}
}
