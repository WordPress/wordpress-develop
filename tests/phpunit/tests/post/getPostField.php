<?php
/**
 * Tests for get_post_field().
 *
 * @group post
 *
 * @covers ::get_post_field
 */
class Tests_Post_GetPostField extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_forwards_context_to_sanitize_post_field() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Example Title',
			)
		);

		add_filter(
			'post_title',
			static function ( $value ) {
				return $value . '|post_title';
			}
		);
		add_filter(
			'edit_post_title',
			static function ( $value ) {
				return $value . '|edit_post_title';
			}
		);

		$this->assertSame( 'Example Title', get_post_field( 'post_title', $post_id, 'raw' ) );
		$this->assertSame( 'Example Title|post_title', get_post_field( 'post_title', $post_id, 'display' ) );
		$this->assertSame( 'Example Title|post_title', get_post_field( 'post_title', $post_id, 'attribute' ) );
		$this->assertSame( 'Example Title|edit_post_title', get_post_field( 'post_title', $post_id, 'edit' ) );
	}
}
