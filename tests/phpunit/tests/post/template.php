<?php

/**
 * @group template
 */
class Tests_Post_Template extends WP_UnitTestCase {

	public function test_wp_link_pages() {
		$contents = array( 'One', 'Two', 'Three' );
		$content  = implode( '<!--nextpage-->', $contents );
		$post_id  = self::factory()->post->create( array( 'post_content' => $content ) );

		$this->go_to( '?p=' . $post_id );

		setup_postdata( get_post( $post_id ) );

		$permalink = sprintf( '<a href="%s" class="post-page-numbers">', get_permalink() );
		$page2     = _wp_link_page( 2 );
		$page3     = _wp_link_page( 3 );

		$expected = '<p class="post-nav-links">Pages: <span class="post-page-numbers current" aria-current="page">1</span> ' . $page2 . '2</a> ' . $page3 . '3</a></p>';
		$output   = wp_link_pages( array( 'echo' => 0 ) );

		$this->assertSame( $expected, $output );

		$before_after = " <span class=\"post-page-numbers current\" aria-current=\"page\">1</span> {$page2}2</a> {$page3}3</a>";
		$output       = wp_link_pages(
			array(
				'echo'   => 0,
				'before' => '',
				'after'  => '',
			)
		);

		$this->assertSame( $before_after, $output );

		$separator = " <span class=\"post-page-numbers current\" aria-current=\"page\">1</span>{$page2}2</a>{$page3}3</a>";
		$output    = wp_link_pages(
			array(
				'echo'      => 0,
				'before'    => '',
				'after'     => '',
				'separator' => '',
			)
		);

		$this->assertSame( $separator, $output );

		$link   = " <span class=\"post-page-numbers current\" aria-current=\"page\"><em>1</em></span>{$page2}<em>2</em></a>{$page3}<em>3</em></a>";
		$output = wp_link_pages(
			array(
				'echo'        => 0,
				'before'      => '',
				'after'       => '',
				'separator'   => '',
				'link_before' => '<em>',
				'link_after'  => '</em>',
			)
		);

		$this->assertSame( $link, $output );

		$next   = "{$page2}<em>Next page</em></a>";
		$output = wp_link_pages(
			array(
				'echo'           => 0,
				'before'         => '',
				'after'          => '',
				'separator'      => '',
				'link_before'    => '<em>',
				'link_after'     => '</em>',
				'next_or_number' => 'next',
			)
		);

		$this->assertSame( $next, $output );

		$GLOBALS['page'] = 2;
		$next_prev       = "{$permalink}<em>Previous page</em></a>{$page3}<em>Next page</em></a>";
		$output          = wp_link_pages(
			array(
				'echo'           => 0,
				'before'         => '',
				'after'          => '',
				'separator'      => '',
				'link_before'    => '<em>',
				'link_after'     => '</em>',
				'next_or_number' => 'next',
			)
		);

		$this->assertSame( $next_prev, $output );

		$next_prev_link = "{$permalink}Woo page</a>{$page3}Hoo page</a>";
		$output         = wp_link_pages(
			array(
				'echo'             => 0,
				'before'           => '',
				'after'            => '',
				'separator'        => '',
				'next_or_number'   => 'next',
				'nextpagelink'     => 'Hoo page',
				'previouspagelink' => 'Woo page',
			)
		);

		$this->assertSame( $next_prev_link, $output );

		$GLOBALS['page'] = 1;
		$separator       = "<p class=\"post-nav-links\">Pages: <span class=\"post-page-numbers current\" aria-current=\"page\">1</span> | {$page2}2</a> | {$page3}3</a></p>";
		$output          = wp_link_pages(
			array(
				'echo'      => 0,
				'separator' => ' | ',
			)
		);

		$this->assertSame( $separator, $output );

		$pagelink = " <span class=\"post-page-numbers current\" aria-current=\"page\">Page 1</span> | {$page2}Page 2</a> | {$page3}Page 3</a>";
		$output   = wp_link_pages(
			array(
				'echo'      => 0,
				'separator' => ' | ',
				'before'    => '',
				'after'     => '',
				'pagelink'  => 'Page %',
			)
		);

		$this->assertSame( $pagelink, $output );
	}

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

	/**
	 * @ticket 11095
	 * @ticket 33974
	 */
	public function test_wp_page_menu_wp_nav_menu_fallback() {
		$pages = self::factory()->post->create_many( 3, array( 'post_type' => 'page' ) );

		// No menus + wp_nav_menu() falls back to wp_page_menu().
		$menu = wp_nav_menu( array( 'echo' => false ) );

		// After falling back, the 'before' argument should be set and output as '<ul>'.
		$this->assertMatchesRegularExpression( '/<div class="menu"><ul>/', $menu );

		// After falling back, the 'after' argument should be set and output as '</ul>'.
		$this->assertMatchesRegularExpression( '/<\/ul><\/div>/', $menu );

		// After falling back, the markup should include whitespace around <li>'s.
		$this->assertMatchesRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertDoesNotMatchRegularExpression( '/><li.*>|<\/li></U', $menu );

		// No menus + wp_nav_menu() falls back to wp_page_menu(), this time without a container.
		$menu = wp_nav_menu(
			array(
				'echo'      => false,
				'container' => false,
			)
		);

		// After falling back, the empty 'container' argument should still return a container element.
		$this->assertMatchesRegularExpression( '/<div class="menu">/', $menu );

		// No menus + wp_nav_menu() falls back to wp_page_menu(), this time without white-space.
		$menu = wp_nav_menu(
			array(
				'echo'         => false,
				'item_spacing' => 'discard',
			)
		);

		// After falling back, the markup should not include whitespace around <li>'s.
		$this->assertDoesNotMatchRegularExpression( '/\s<li.*>|<\/li>\s/U', $menu );
		$this->assertMatchesRegularExpression( '/><li.*>|<\/li></U', $menu );

	}

	/**
	 * @ticket 33045
	 */
	public function test_get_parent_post() {
		$post = array(
			'post_status' => 'publish',
			'post_type'   => 'page',
		);

		// Insert two initial posts.
		$parent_id = self::factory()->post->create( $post );
		$child_id  = self::factory()->post->create( $post );

		// Test if child get_parent_post() post returns Null by default.
		$parent = get_post_parent( $child_id );
		$this->assertNull( $parent );

		// Update child post with a parent.
		wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			)
		);

		// Test if child get_parent_post() post returns the parent object.
		$parent = get_post_parent( $child_id );
		$this->assertNotNull( $parent );
		$this->assertSame( $parent_id, $parent->ID );
	}

	/**
	 * @ticket 33045
	 */
	public function test_has_parent_post() {
		$post = array(
			'post_status' => 'publish',
			'post_type'   => 'page',
		);

		// Insert two initial posts.
		$parent_id = self::factory()->post->create( $post );
		$child_id  = self::factory()->post->create( $post );

		// Test if child has_parent_post() post returns False by default.
		$parent = has_post_parent( $child_id );
		$this->assertFalse( $parent );

		// Update child post with a parent.
		wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			)
		);

		// Test if child has_parent_post() returns True.
		$parent = has_post_parent( $child_id );
		$this->assertTrue( $parent );
	}
}
