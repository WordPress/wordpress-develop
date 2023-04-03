<?php

/**
 * @group post
 * @group template
 *
 * @covers ::get_page_template_slug
 */
class Tests_Post_GetPageTemplateSlug extends WP_UnitTestCase {

	/**
	 * @ticket 31389
	 */
	public function test_get_page_template_slug_by_id() {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$this->assertSame( '', get_page_template_slug( $page_id ) );

		update_post_meta( $page_id, '_wp_page_template', 'default' );
		$this->assertSame( '', get_page_template_slug( $page_id ) );

		update_post_meta( $page_id, '_wp_page_template', 'example.php' );
		$this->assertSame( 'example.php', get_page_template_slug( $page_id ) );
	}

	/**
	 * @ticket 31389
	 */
	public function test_get_page_template_slug_from_loop() {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		update_post_meta( $page_id, '_wp_page_template', 'example.php' );
		$this->go_to( get_permalink( $page_id ) );

		$this->assertSame( 'example.php', get_page_template_slug() );
	}

	/**
	 * @ticket 31389
	 * @ticket 18375
	 */
	public function test_get_page_template_slug_non_page() {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', get_page_template_slug( $post_id ) );

		update_post_meta( $post_id, '_wp_page_template', 'default' );

		$this->assertSame( '', get_page_template_slug( $post_id ) );

		update_post_meta( $post_id, '_wp_page_template', 'example.php' );
		$this->assertSame( 'example.php', get_page_template_slug( $post_id ) );
	}

	/**
	 * @ticket 18375
	 */
	public function test_get_page_template_slug_non_page_from_loop() {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, '_wp_page_template', 'example.php' );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( 'example.php', get_page_template_slug() );
	}
}
