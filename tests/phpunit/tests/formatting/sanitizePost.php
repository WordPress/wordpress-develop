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
}
