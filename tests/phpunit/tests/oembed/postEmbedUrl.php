<?php

/**
 * @group oembed
 */
class Tests_Post_Embed_URL extends WP_UnitTestCase {
	public function test_non_existent_post() {
		$embed_url = get_post_embed_url( 0 );
		$this->assertFalse( $embed_url );
	}

	public function test_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%' );

		$post_id   = self::factory()->post->create();
		$permalink = get_permalink( $post_id );
		$embed_url = get_post_embed_url( $post_id );

		$this->assertSame( $permalink . '/embed', $embed_url );
	}

	public function test_with_ugly_permalinks() {
		$post_id   = self::factory()->post->create();
		$permalink = get_permalink( $post_id );
		$embed_url = get_post_embed_url( $post_id );

		$this->assertSame( $permalink . '&embed=true', $embed_url );
	}

	/**
	 * @ticket 34971
	 */
	public function test_static_front_page() {
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$embed_url = get_post_embed_url( $post_id );

		$this->assertSame( user_trailingslashit( trailingslashit( home_url() ) . 'embed' ), $embed_url );

		update_option( 'show_on_front', 'posts' );
	}

	/**
	 * @ticket 34971
	 */
	public function test_static_front_page_with_ugly_permalinks() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$embed_url = get_post_embed_url( $post_id );

		$this->assertSame( trailingslashit( home_url() ) . '?embed=true', $embed_url );

		update_option( 'show_on_front', 'posts' );
	}

	/**
	 * @ticket 34971
	 */
	public function test_page_conflicts_with_embed_slug() {
		$this->set_permalink_structure( '/%postname%/' );

		$parent_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		add_filter( 'wp_unique_post_slug', array( $this, 'filter_unique_post_slug' ) );
		$child_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_page,
				'post_name'   => 'embed',
			)
		);
		remove_filter( 'wp_unique_post_slug', array( $this, 'filter_unique_post_slug' ) );

		$this->assertSame( get_permalink( $parent_page ) . '?embed=true', get_post_embed_url( $parent_page ) );
		$this->assertSame( get_permalink( $child_page ) . 'embed/', get_post_embed_url( $child_page ) );
	}

	/**
	 * @ticket 34971
	 */
	public function test_static_front_page_conflicts_with_embed_slug() {
		$this->set_permalink_structure( '/%postname%/' );

		// Create a post with the 'embed' post_name.
		add_filter( 'wp_unique_post_slug', array( $this, 'filter_unique_post_slug' ) );
		$post_embed_slug = self::factory()->post->create( array( 'post_name' => 'embed' ) );
		remove_filter( 'wp_unique_post_slug', array( $this, 'filter_unique_post_slug' ) );
		$page_front = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_front );

		$this->assertSame( home_url() . '/embed/embed/', get_post_embed_url( $post_embed_slug ) );
		$this->assertSame( home_url() . '/?embed=true', get_post_embed_url( $page_front ) );

		update_option( 'show_on_front', 'posts' );
	}

	public function filter_unique_post_slug() {
		return 'embed';
	}

	/**
	 * Test should return embed URL with '/embed/' for a published post.
	 */
	public function test_should_return_embed_url_with_embed_suffix_when_post_is_published() {
		$this->set_permalink_structure( '/%postname%/' );

		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );

		$embed_url = get_post_embed_url( $post );

		$this->assertStringContainsString( 'embed', $embed_url );
		$this->assertStringEndsWith( '/embed/', $embed_url );
	}

	/**
	 * Test should return embed URL with '?embed=true' for a draft post.
	 */
	public function test_should_return_embed_url_with_embed_query_when_post_is_draft() {
		$this->set_permalink_structure( '/%postname%/' );

		$post = self::factory()->post->create_and_get( array( 'post_status' => 'draft' ) );

		$embed_url = get_post_embed_url( $post );

		$this->assertStringContainsString( '&embed=true', $embed_url );
		$this->assertStringNotContainsString( '/embed/', $embed_url );
	}

	/**
	 * Test should return embed URL with '?embed=true' for a scheduled post.
	 */
	public function test_should_return_embed_url_with_embed_query_when_post_is_scheduled() {
		$this->set_permalink_structure( '/%postname%/' );

		$future_post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
			)
		);

		$embed_url = get_post_embed_url( $future_post );

		$this->assertStringContainsString( '&embed=true', $embed_url );
		$this->assertStringNotContainsString( '/embed/', $embed_url );
	}

	/**
	 * Test should return the embed URL for the current post when no post ID is provided.
	 */
	public function test_should_return_embed_url_for_current_post_when_no_post_id_is_provided() {
		$this->set_permalink_structure( '/%postname%/' );

		$custom_post = self::factory()->post->create_and_get( array( 'post_status' => 'publish' ) );

		global $post;
		$post = $custom_post;

		$embed_url = get_post_embed_url();

		$this->assertStringContainsString( 'embed', $embed_url );
		$this->assertStringEndsWith( '/embed/', $embed_url );
	}
}
