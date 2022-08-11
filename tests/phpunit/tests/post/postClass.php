<?php

/**
 * @group post
 * @covers ::post_class
 */
class Tests_Post_PostClass extends WP_UnitTestCase {
	protected $post_id;

	public function set_up() {
		parent::set_up();
		$this->post_id = self::factory()->post->create();
	}

	public function test_post_class() {
		$expected = 'class="' . implode( ' ', get_post_class( '', $this->post_id ) ) . '"';
		$this->expectOutputString( $expected );
		post_class( '', $this->post_id );
	}

	public function test_post_class_extra_esc_attr() {
		$classes              = get_post_class( '', $this->post_id );
		$escaped_again        = array_map( 'esc_attr', $classes );
		$escaped_another_time = 'class="' . esc_attr( implode( ' ', $escaped_again ) ) . '"';

		$this->expectOutputString( $escaped_another_time );
		post_class( '', $this->post_id );
	}
}
