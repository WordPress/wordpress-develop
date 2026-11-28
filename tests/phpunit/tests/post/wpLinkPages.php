<?php

/**
 * @group post
 * @group template
 *
 * @covers ::wp_link_pages
 */
class Tests_Post_wpLinkPages extends WP_UnitTestCase {

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
	 * Tests the URL of a subpage of a post that is split with the nextpage tag.
	 *
	 * The permalink of a post that is not published is a plain `?p=ID` URL,
	 * so the page number must be added as a query argument as well.
	 *
	 * @ticket 32295
	 *
	 * @dataProvider data_post_statuses
	 *
	 * @param string $post_status    Post status.
	 * @param bool   $uses_query_arg Whether the page number is expected as a query argument.
	 */
	public function test_subpage_url_with_pretty_permalinks( $post_status, $uses_query_arg ) {
		$this->set_permalink_structure( '/%postname%/' );

		$post_args = array(
			'post_status'  => $post_status,
			'post_content' => 'One<!--nextpage-->Two<!--nextpage-->Three',
		);

		// A post is only scheduled when its date is in the future.
		if ( 'future' === $post_status ) {
			$post_args['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
			$post_args['post_date_gmt'] = $post_args['post_date'];
		}

		$post_id = self::factory()->post->create( $post_args );

		$this->assertSame( $post_status, get_post_status( $post_id ), 'The post should have the expected status.' );

		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$permalink = get_permalink( $post_id );

		if ( $uses_query_arg ) {
			$expected_url = add_query_arg( 'page', 2, $permalink );
		} else {
			$expected_url = trailingslashit( $permalink ) . user_trailingslashit( 2, 'single_paged' );
		}

		$this->assertSame( '<a href="' . esc_url( $expected_url ) . '" class="post-page-numbers">', _wp_link_page( 2 ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_post_statuses() {
		return array(
			'draft'     => array( 'draft', true ),
			'pending'   => array( 'pending', true ),
			'scheduled' => array( 'future', true ),
			'published' => array( 'publish', false ),
		);
	}
}
