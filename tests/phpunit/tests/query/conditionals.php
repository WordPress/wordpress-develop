<?php

/**
 * Test the is_*() functions in query.php across the URL structure
 *
 * This exercises both query.php and rewrite.php: urls are fed through the rewrite code,
 * then we test the effects of each url on the wp_query object.
 *
 * @group query
 * @group rewrite
 */
class Tests_Query_Conditionals extends WP_UnitTestCase {

	protected $page_ids;
	protected $post_ids;

	public function set_up() {
		parent::set_up();

		update_option( 'comments_per_page', 5 );
		update_option( 'posts_per_page', 5 );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		create_initial_taxonomies();
	}

	public function test_home() {
		$this->go_to( '/' );
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_page_on_front() {
		$page_on_front  = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$page_for_posts = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_on_front );
		update_option( 'page_for_posts', $page_for_posts );

		$this->go_to( '/' );
		$this->assertQueryTrue( 'is_front_page', 'is_page', 'is_singular' );

		$this->go_to( get_permalink( $page_for_posts ) );
		$this->assertQueryTrue( 'is_home', 'is_posts_page' );

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );
	}

	public function test_404() {
		$this->go_to( '/notapage' );
		$this->assertQueryTrue( 'is_404' );
	}

	public function test_permalink() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'hello-world' ) );
		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
	}

	public function test_post_comments_feed() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'hello-world' ) );
		self::factory()->comment->create_post_comments( $post_id, 2 );
		$this->go_to( get_post_comments_feed_link( $post_id ) );
		$this->assertQueryTrue( 'is_feed', 'is_single', 'is_singular', 'is_comment_feed' );
	}


	public function test_post_comments_feed_with_no_comments() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'hello-world' ) );
		$this->go_to( get_post_comments_feed_link( $post_id ) );
		$this->assertQueryTrue( 'is_feed', 'is_single', 'is_singular', 'is_comment_feed' );
	}

	public function test_attachment_comments_feed() {
		$attachment_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		self::factory()->comment->create_post_comments( $attachment_id, 2 );
		$this->go_to( get_post_comments_feed_link( $attachment_id ) );
		$this->assertQueryTrue( 'is_feed', 'is_attachment', 'is_single', 'is_singular', 'is_comment_feed' );
	}

	public function test_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'about',
			)
		);
		$this->go_to( get_permalink( $page_id ) );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_parent_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_child_page_1() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_child_page_2() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	// '(about)/trackback/?$' => 'index.php?pagename=$matches[1]&tb=1'
	public function test_page_trackback() {
		$page_ids   = array();
		$page_id    = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_ids[] = $page_id;
		$page_id    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_ids[] = $page_id;
		$page_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		foreach ( $page_ids as $page_id ) {
			$url = get_permalink( $page_id );
			$this->go_to( "{$url}trackback/" );

			// Make sure the correct WP_Query flags are set.
			$this->assertQueryTrue( 'is_page', 'is_singular', 'is_trackback' );

			// Make sure the correct page was fetched.
			global $wp_query;
			$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	// '(about)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]'
	public function test_page_feed() {
		$page_ids   = array();
		$page_id    = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_ids[] = $page_id;
		$page_id    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_ids[] = $page_id;
		$page_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		foreach ( $page_ids as $page_id ) {
			self::factory()->comment->create_post_comments( $page_id, 2 );
			$url = get_permalink( $page_id );
			$this->go_to( "{$url}feed/" );

			// Make sure the correct WP_Query flags are set.
			$this->assertQueryTrue( 'is_page', 'is_singular', 'is_feed', 'is_comment_feed' );

			// Make sure the correct page was fetched.
			global $wp_query;
			$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	public function test_page_feed_with_no_comments() {
		$page_ids   = array();
		$page_id    = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_ids[] = $page_id;
		$page_id    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_ids[] = $page_id;
		$page_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		foreach ( $page_ids as $page_id ) {
			$url = get_permalink( $page_id );
			$this->go_to( "{$url}feed/" );

			// Make sure the correct WP_Query flags are set.
			$this->assertQueryTrue( 'is_page', 'is_singular', 'is_feed', 'is_comment_feed' );

			// Make sure the correct page was fetched.
			global $wp_query;
			$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	// '(about)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]'
	public function test_page_feed_atom() {
		$page_ids   = array();
		$page_id    = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_ids[] = $page_id;
		$page_id    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_ids[] = $page_id;
		$page_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		foreach ( $page_ids as $page_id ) {
			self::factory()->comment->create_post_comments( $page_id, 2 );

			$url = get_permalink( $page_id );
			$this->go_to( "{$url}feed/atom/" );

			// Make sure the correct WP_Query flags are set.
			$this->assertQueryTrue( 'is_page', 'is_singular', 'is_feed', 'is_comment_feed' );

			// Make sure the correct page was fetched.
			global $wp_query;
			$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	// '(about)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'
	public function test_page_page_2() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		$this->go_to( '/about/page/2/' );

		// Make sure the correct WP_Query flags are set.
		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_paged' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
	}

	// '(about)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'
	public function test_page_page_2_no_slash() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		$this->go_to( '/about/page2/' );

		// Make sure the correct WP_Query flags are set.
		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_paged' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
	}

	// '(about)(/[0-9]+)?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]'
	public function test_pagination_of_posts_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $page_id );

		$this->go_to( '/about/2/' );

		$this->assertQueryTrue( 'is_home', 'is_posts_page' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_for_posts' );
	}

	// FIXME: no tests for these yet:
	// 'about/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
	// 'about/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
	// 'about/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
	// 'about/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',

	// 'feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]',
	// '(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]',
	public function test_main_feed_2() {
		self::factory()->post->create(); // @test_404
		$feeds = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );

		// Long version.
		foreach ( $feeds as $feed ) {
			$this->go_to( "/feed/{$feed}/" );
			$this->assertQueryTrue( 'is_feed' );
		}

		// Short version.
		foreach ( $feeds as $feed ) {
			$this->go_to( "/{$feed}/" );
			$this->assertQueryTrue( 'is_feed' );
		}
	}

	public function test_main_feed() {
		self::factory()->post->create(); // @test_404
		$types = array( 'rss2', 'rss', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( get_feed_link( $type ) );
			$this->assertQueryTrue( 'is_feed' );
		}
	}

	// 'page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
	public function test_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 5 );
		for ( $i = 2; $i <= 3; $i++ ) {
			$this->go_to( "/page/{$i}/" );
			$this->assertQueryTrue( 'is_home', 'is_front_page', 'is_paged' );
		}
	}

	// 'comments/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
	// 'comments/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
	public function test_main_comments_feed() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'hello-world' ) );
		self::factory()->comment->create_post_comments( $post_id, 2 );

		// Check the URL as generated by get_post_comments_feed_link().
		$this->go_to( get_post_comments_feed_link( $post_id ) );
		$this->assertQueryTrue( 'is_feed', 'is_single', 'is_singular', 'is_comment_feed' );

		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/comments/feed/{$type}" );
				$this->assertQueryTrue( 'is_feed', 'is_comment_feed' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/comments/{$type}" );
				$this->assertQueryTrue( 'is_feed', 'is_comment_feed' );
		}
	}

	// 'category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
	// 'category/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
	public function test_category_feed() {
		self::factory()->term->create(
			array(
				'name'     => 'cat-a',
				'taxonomy' => 'category',
			)
		);

		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( "/category/cat-a/feed/{$type}" );
			$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_category' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( "/category/cat-a/{$type}" );
			$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_category' );
		}
	}

	// 'category/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?category_name=$matches[1]&paged=$matches[2]',
	public function test_category_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3 );
		$this->go_to( '/category/uncategorized/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_category', 'is_paged' );
	}

	// 'category/(.+?)/?$' => 'index.php?category_name=$matches[1]',
	public function test_category() {
		self::factory()->term->create(
			array(
				'name'     => 'cat-a',
				'taxonomy' => 'category',
			)
		);
		$this->go_to( '/category/cat-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_category' );
	}

	// 'tag/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
	// 'tag/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
	public function test_tag_feed() {
		self::factory()->term->create(
			array(
				'name'     => 'tag-a',
				'taxonomy' => 'post_tag',
			)
		);
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/tag/tag-a/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_tag' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/tag/tag-a/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_tag' );
		}
	}

	// 'tag/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?tag=$matches[1]&paged=$matches[2]',
	public function test_tag_paged() {
		update_option( 'posts_per_page', 2 );
		$post_ids = self::factory()->post->create_many( 3 );
		foreach ( $post_ids as $post_id ) {
			self::factory()->term->add_post_terms( $post_id, 'tag-a', 'post_tag' );
		}
		$this->go_to( '/tag/tag-a/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_tag', 'is_paged' );
	}

	// 'tag/(.+?)/?$' => 'index.php?tag=$matches[1]',
	public function test_tag() {
		$term_id = self::factory()->term->create(
			array(
				'name'     => 'Tag Named A',
				'slug'     => 'tag-a',
				'taxonomy' => 'post_tag',
			)
		);
		$this->go_to( '/tag/tag-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_tag' );

		$tag = get_term( $term_id, 'post_tag' );

		$this->assertTrue( is_tag() );
		$this->assertTrue( is_tag( $tag->name ) );
		$this->assertTrue( is_tag( $tag->slug ) );
		$this->assertTrue( is_tag( $tag->term_id ) );
		$this->assertTrue( is_tag( array() ) );
		$this->assertTrue( is_tag( array( $tag->name ) ) );
		$this->assertTrue( is_tag( array( $tag->slug ) ) );
		$this->assertTrue( is_tag( array( $tag->term_id ) ) );
	}

	// 'author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
	// 'author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
	public function test_author_feed() {
		self::factory()->user->create( array( 'user_login' => 'user-a' ) );
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/author/user-a/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_author' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/author/user-a/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_author' );
		}
	}

	// 'author/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?author_name=$matches[1]&paged=$matches[2]',
	public function test_author_paged() {
		update_option( 'posts_per_page', 2 );
		$user_id = self::factory()->user->create( array( 'user_login' => 'user-a' ) );
		self::factory()->post->create_many( 3, array( 'post_author' => $user_id ) );
		$this->go_to( '/author/user-a/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_author', 'is_paged' );
	}

	// 'author/([^/]+)/?$' => 'index.php?author_name=$matches[1]',
	public function test_author() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'user-a' ) );
		self::factory()->post->create( array( 'post_author' => $user_id ) );
		$this->go_to( '/author/user-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_author' );
	}

	public function test_author_with_no_posts() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'user-a' ) );
		$this->go_to( '/author/user-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_author' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
	public function test_ymd_feed() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/09/04/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_day', 'is_date' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/09/04/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_day', 'is_date' );
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
	public function test_ymd_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/04/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_day', 'is_date', 'is_paged' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
	public function test_ymd() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/04/' );
		$this->assertQueryTrue( 'is_archive', 'is_day', 'is_date' );
	}

	// '([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
	// '([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
	public function test_ym_feed() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/09/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_month', 'is_date' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/09/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_month', 'is_date' );
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
	public function test_ym_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_month', 'is_paged' );
	}

	// '([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
	public function test_ym() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_month' );
	}

	// '([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
	// '([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
	public function test_y_feed() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_year', 'is_date' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/2007/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_year', 'is_date' );
		}
	}

	// '([0-9]{4})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&paged=$matches[2]',
	public function test_y_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_year', 'is_paged' );
	}

	// '([0-9]{4})/?$' => 'index.php?year=$matches[1]',
	public function test_y() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_year' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/trackback/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&tb=1',
	public function test_post_trackback() {
		$post_id   = self::factory()->post->create();
		$permalink = get_permalink( $post_id );
		$this->go_to( "{$permalink}trackback/" );
		$this->assertQueryTrue( 'is_single', 'is_singular', 'is_trackback' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
	public function test_post_comment_feed() {
		$post_id   = self::factory()->post->create();
		$permalink = get_permalink( $post_id );
		// Check the long form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "{$permalink}feed/{$type}" );
				$this->assertQueryTrue( 'is_single', 'is_singular', 'is_feed', 'is_comment_feed' );
		}

		// Check the short form.
		$types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "{$permalink}{$type}" );
				$this->assertQueryTrue( 'is_single', 'is_singular', 'is_feed', 'is_comment_feed' );
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)(/[0-9]+)?/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&page=$matches[5]',
	public function test_post_paged_short() {
		$post_id = self::factory()->post->create(
			array(
				'post_date'    => '2007-09-04 00:00:00',
				'post_title'   => 'a-post-with-multiple-pages',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		$this->go_to( get_permalink( $post_id ) . '2/' );
		// Should is_paged be true also?
		$this->assertQueryTrue( 'is_single', 'is_singular' );
	}

	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
	public function test_post_attachment() {
		$post_id   = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		$permalink = get_attachment_link( $post_id );
		$this->go_to( $permalink );
		$this->assertQueryTrue( 'is_single', 'is_attachment', 'is_singular' );
	}

	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
	// '[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',

	/**
	 * @expectedIncorrectUsage WP_Date_Query
	 */
	public function test_bad_dates() {
		$this->go_to( '/2013/13/13/' );
		$this->assertQueryTrue( 'is_404' );

		$this->go_to( '/2013/11/41/' );
		$this->assertQueryTrue( 'is_404' );
	}

	public function test_post_type_archive_with_tax_query() {
		delete_option( 'rewrite_rules' );

		$cpt_name = 'ptawtq';
		register_post_type(
			$cpt_name,
			array(
				'taxonomies'  => array( 'post_tag', 'category' ),
				'rewrite'     => true,
				'has_archive' => true,
				'public'      => true,
			)
		);

		$tag_id  = self::factory()->tag->create( array( 'slug' => 'tag-slug' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => $cpt_name ) );
		wp_set_object_terms( $post_id, $tag_id, 'post_tag' );

		$this->go_to( '/ptawtq/' );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_tax_query' ) );

		$this->go_to( '/ptawtq/' );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_tax_query' ) );
	}

	public function pre_get_posts_with_tax_query( &$query ) {
		$term = get_term_by( 'slug', 'tag-slug', 'post_tag' );
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			)
		);
	}

	public function test_post_type_array() {
		delete_option( 'rewrite_rules' );

		$cpt_name = 'thearray';
		register_post_type(
			$cpt_name,
			array(
				'taxonomies'  => array( 'post_tag', 'category' ),
				'rewrite'     => true,
				'has_archive' => true,
				'public'      => true,
			)
		);
		self::factory()->post->create( array( 'post_type' => $cpt_name ) );

		$this->go_to( "/$cpt_name/" );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_type_array' ) );

		$this->go_to( "/$cpt_name/" );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( 'post' ) );

		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_type_array' ) );
	}

	public function pre_get_posts_with_type_array( &$query ) {
		$query->set( 'post_type', array( 'post', 'thearray' ) );
	}

	public function test_is_single() {
		$post_id = self::factory()->post->create();
		$this->go_to( "/?p=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_single() );
		$this->assertTrue( $q->is_single );
		$this->assertFalse( $q->is_page );
		$this->assertFalse( $q->is_attachment );
		$this->assertTrue( is_single( $post ) );
		$this->assertTrue( is_single( $post->ID ) );
		$this->assertTrue( is_single( $post->post_title ) );
		$this->assertTrue( is_single( $post->post_name ) );
	}

	/**
	 * @ticket 16802
	 */
	public function test_is_single_with_parent() {
		// Use custom hierarchical post type.
		$post_type = 'test_hierarchical';

		register_post_type(
			$post_type,
			array(
				'hierarchical' => true,
				'rewrite'      => true,
				'has_archive'  => true,
				'public'       => true,
			)
		);

		// Create parent and child posts.
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => $post_type,
				'post_name' => 'foo',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_name'   => 'bar',
				'post_parent' => $parent_id,
			)
		);

		// Tests.
		$this->go_to( "/?p=$post_id&post_type=$post_type" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_single() );
		$this->assertFalse( $q->is_page );
		$this->assertTrue( $q->is_single );
		$this->assertFalse( $q->is_attachment );
		$this->assertTrue( is_single( $post ) );
		$this->assertTrue( is_single( $post->ID ) );
		$this->assertTrue( is_single( $post->post_title ) );
		$this->assertTrue( is_single( $post->post_name ) );
		$this->assertTrue( is_single( 'foo/bar' ) );
		$this->assertFalse( is_single( $parent_id ) );
		$this->assertFalse( is_single( 'foo/bar/baz' ) );
		$this->assertFalse( is_single( 'bar/bar' ) );
		$this->assertFalse( is_single( 'foo' ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_single_with_slug_that_begins_with_a_number_that_clashes_with_another_post_id() {
		$p1 = self::factory()->post->create();

		$p2_name = $p1 . '-post';
		$p2      = self::factory()->post->create(
			array(
				'slug' => $p2_name,
			)
		);

		$this->go_to( "/?p=$p1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_single() );
		$this->assertTrue( $q->is_single( $p1 ) );
		$this->assertFalse( $q->is_single( $p2_name ) );
		$this->assertFalse( $q->is_single( $p2 ) );
	}

	/**
	 * @ticket 24612
	 */
	public function test_is_single_with_slug_that_clashes_with_attachment() {
		$this->set_permalink_structure( '/%postname%/' );

		$attachment_id = self::factory()->post->create(
			array(
				'post_type' => 'attachment',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title' => get_post( $attachment_id )->post_title,
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_single() );
		$this->assertTrue( $q->is_single( $post_id ) );
		$this->assertFalse( $q->is_attachment() );
		$this->assertFalse( $q->is_404() );

		$this->set_permalink_structure();
	}

	/**
	 * @ticket 38225
	 */
	public function test_is_single_with_attachment() {
		$post_id = self::factory()->post->create();

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->go_to( get_permalink( $attachment_id ) );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( is_single() );
		$this->assertTrue( $q->is_single );
		$this->assertTrue( $q->is_attachment );
	}

	public function test_is_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( "/?page_id=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_page() );
		$this->assertFalse( $q->is_single );
		$this->assertTrue( $q->is_page );
		$this->assertFalse( $q->is_attachment );
		$this->assertTrue( is_page( $post ) );
		$this->assertTrue( is_page( $post->ID ) );
		$this->assertTrue( is_page( $post->post_title ) );
		$this->assertTrue( is_page( $post->post_name ) );
	}

	/**
	 * @ticket 16802
	 */
	public function test_is_page_with_parent() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'bar',
				'post_parent' => $parent_id,
			)
		);
		$this->go_to( "/?page_id=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_page() );
		$this->assertFalse( $q->is_single );
		$this->assertTrue( $q->is_page );
		$this->assertFalse( $q->is_attachment );
		$this->assertTrue( is_page( $post ) );
		$this->assertTrue( is_page( $post->ID ) );
		$this->assertTrue( is_page( $post->post_title ) );
		$this->assertTrue( is_page( $post->post_name ) );
		$this->assertTrue( is_page( 'foo/bar' ) );
		$this->assertFalse( is_page( $parent_id ) );
		$this->assertFalse( is_page( 'foo/bar/baz' ) );
		$this->assertFalse( is_page( 'bar/bar' ) );
		$this->assertFalse( is_page( 'foo' ) );
	}

	public function test_is_attachment() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		$this->go_to( "/?attachment_id=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_attachment() );
		$this->assertTrue( is_single() );
		$this->assertTrue( $q->is_attachment );
		$this->assertTrue( $q->is_single );
		$this->assertFalse( $q->is_page );
		$this->assertTrue( is_attachment( $post ) );
		$this->assertTrue( is_attachment( $post->ID ) );
		$this->assertTrue( is_attachment( $post->post_title ) );
		$this->assertTrue( is_attachment( $post->post_name ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_attachment_with_slug_that_begins_with_a_number_that_clashes_with_a_page_ID() {
		$p1 = self::factory()->post->create( array( 'post_type' => 'attachment' ) );

		$p2_name = $p1 . '-attachment';
		$p2      = self::factory()->post->create(
			array(
				'post_type' => 'attachment',
				'post_name' => $p2_name,
			)
		);

		$this->go_to( "/?attachment_id=$p1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_attachment() );
		$this->assertTrue( $q->is_attachment( $p1 ) );
		$this->assertFalse( $q->is_attachment( $p2_name ) );
		$this->assertFalse( $q->is_attachment( $p2 ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_author_with_nicename_that_begins_with_a_number_that_clashes_with_another_author_id() {
		$u1 = self::factory()->user->create();

		$u2_name = $u1 . '_user';
		$u2      = self::factory()->user->create(
			array(
				'user_nicename' => $u2_name,
			)
		);

		$this->go_to( "/?author=$u1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_author() );
		$this->assertTrue( $q->is_author( $u1 ) );
		$this->assertFalse( $q->is_author( $u2_name ) );
		$this->assertFalse( $q->is_author( $u2 ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_category_with_slug_that_begins_with_a_number_that_clashes_with_another_category_id() {
		$c1 = self::factory()->category->create();

		$c2_name = $c1 . '-category';
		$c2      = self::factory()->category->create(
			array(
				'slug' => $c2_name,
			)
		);

		$this->go_to( "/?cat=$c1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_category() );
		$this->assertTrue( $q->is_category( $c1 ) );
		$this->assertFalse( $q->is_category( $c2_name ) );
		$this->assertFalse( $q->is_category( $c2 ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_tag_with_slug_that_begins_with_a_number_that_clashes_with_another_tag_id() {
		$t1 = self::factory()->tag->create();

		$t2_name = $t1 . '-tag';
		$t2      = self::factory()->tag->create(
			array(
				'slug' => $t2_name,
			)
		);

		$this->go_to( "/?tag_id=$t1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_tag() );
		$this->assertTrue( $q->is_tag( $t1 ) );
		$this->assertFalse( $q->is_tag( $t2_name ) );
		$this->assertFalse( $q->is_tag( $t2 ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_page_with_page_id_zero_and_random_page_slug() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( "/?page_id=$post_id" );

		// Override post ID to 0 temporarily for testing.
		$_id                           = $GLOBALS['wp_query']->post->ID;
		$GLOBALS['wp_query']->post->ID = 0;

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_page() );
		$this->assertFalse( $q->is_page( 'sample-page' ) );
		$this->assertFalse( $q->is_page( 'random-page-slug' ) );

		// Revert $wp_query global change.
		$GLOBALS['wp_query']->post->ID = $_id;
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_page_with_page_slug_that_begins_with_a_number_that_clashes_with_a_page_ID() {
		$p1 = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$p2_name = $p1 . '-page';
		$p2      = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => $p2_name,
			)
		);

		$this->go_to( "/?page_id=$p1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_page() );
		$this->assertTrue( $q->is_page( $p1 ) );
		$this->assertFalse( $q->is_page( $p2_name ) );
		$this->assertFalse( $q->is_page( $p2 ) );
	}

	public function test_is_page_template() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $post_id, '_wp_page_template', 'example.php' );
		$this->go_to( "/?page_id=$post_id" );
		$this->assertTrue( is_page_template( 'example.php' ) );
	}

	/**
	 * @ticket 31271
	 */
	public function test_is_page_template_default() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( "/?page_id=$post_id" );
		$this->assertTrue( is_page_template( 'default' ) );
		$this->assertTrue( is_page_template( array( 'random', 'default' ) ) );
	}

	/**
	 * @ticket 31271
	 */
	public function test_is_page_template_array() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $post_id, '_wp_page_template', 'example.php' );
		$this->go_to( "/?page_id=$post_id" );
		$this->assertFalse( is_page_template( array( 'test.php' ) ) );
		$this->assertTrue( is_page_template( array( 'test.php', 'example.php' ) ) );
	}

	/**
	 * @ticket 18375
	 */
	public function test_is_page_template_other_post_type() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $post_id, '_wp_page_template', 'example.php' );
		$this->go_to( get_post_permalink( $post_id ) );
		$this->assertFalse( is_page_template( array( 'test.php' ) ) );
		$this->assertTrue( is_page_template( array( 'test.php', 'example.php' ) ) );
	}

	/**
	 * @ticket 39211
	 */
	public function test_is_page_template_not_singular() {
		global $wpdb;

		// We need a non-post that shares an ID with a post assigned a template.
		$user_id = self::factory()->user->create();
		if ( ! get_post( $user_id ) ) {
			$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
			$wpdb->update( $wpdb->posts, array( 'ID' => $user_id ), array( 'ID' => $post_id ), array( '%d' ) );
		}

		update_post_meta( $user_id, '_wp_page_template', 'example.php' );

		// Verify that the post correctly reports having a template.
		$this->go_to( get_post_permalink( $user_id ) );
		$this->assertInstanceOf( 'WP_Post', get_queried_object() );
		$this->assertTrue( is_page_template( 'example.php' ) );

		// Verify that the non-post with a matching ID does not report having a template.
		$this->go_to( get_author_posts_url( $user_id ) );
		$this->assertInstanceOf( 'WP_User', get_queried_object() );
		$this->assertFalse( is_page_template( 'example.php' ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_attachment_should_not_match_numeric_id_to_post_title_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'attachment',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'attachment',
				'post_title' => "$p1 Foo",
				'post_name'  => 'foo-2',
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_attachment( $p2 ) );
		$this->assertFalse( is_attachment( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_attachment_should_not_match_numeric_id_to_post_name_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'attachment',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'attachment',
				'post_title' => 'Foo',
				'post_name'  => "$p1-foo",
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_attachment( $p2 ) );
		$this->assertFalse( is_attachment( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_author_should_not_match_numeric_id_to_nickname_beginning_with_id() {
		$u1 = self::factory()->user->create(
			array(
				'nickname'      => 'Foo',
				'user_nicename' => 'foo',
			)
		);
		$u2 = self::factory()->user->create(
			array(
				'nickname'      => "$u1 Foo",
				'user_nicename' => 'foo-2',
			)
		);

		$this->go_to( get_author_posts_url( $u2 ) );

		$this->assertTrue( is_author( $u2 ) );
		$this->assertFalse( is_author( $u1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_author_should_not_match_numeric_id_to_user_nicename_beginning_with_id() {
		$u1 = self::factory()->user->create(
			array(
				'nickname'      => 'Foo',
				'user_nicename' => 'foo',
			)
		);
		$u2 = self::factory()->user->create(
			array(
				'nickname'      => 'Foo',
				'user_nicename' => "$u1-foo",
			)
		);

		$this->go_to( get_author_posts_url( $u2 ) );

		$this->assertTrue( is_author( $u2 ) );
		$this->assertFalse( is_author( $u1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_category_should_not_match_numeric_id_to_name_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => "$t1-foo",
				'name'     => 'foo 2',
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_category( $t2 ) );
		$this->assertFalse( is_category( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_category_should_not_match_numeric_id_to_slug_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo-2',
				'name'     => "$t1 foo",
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_category( $t2 ) );
		$this->assertFalse( is_category( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_tag_should_not_match_numeric_id_to_name_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => "$t1-foo",
				'name'     => 'foo 2',
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_tag( $t2 ) );
		$this->assertFalse( is_tag( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_tag_should_not_match_numeric_id_to_slug_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo-2',
				'name'     => "$t1 foo",
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_tag( $t2 ) );
		$this->assertFalse( is_tag( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_page_should_not_match_numeric_id_to_post_title_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => "$p1 Foo",
				'post_name'  => 'foo-2',
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_page( $p2 ) );
		$this->assertFalse( is_page( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_page_should_not_match_numeric_id_to_post_name_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => "$p1-foo",
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_page( $p2 ) );
		$this->assertFalse( is_page( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_single_should_not_match_numeric_id_to_post_title_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => "$p1 Foo",
				'post_name'  => 'foo-2',
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_single( $p2 ) );
		$this->assertFalse( is_single( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_single_should_not_match_numeric_id_to_post_name_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => "$p1-foo",
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_single( $p2 ) );
		$this->assertFalse( is_single( $p1 ) );
	}

	/**
	 * @ticket 44005
	 * @group privacy
	 */
	public function test_is_privacy_policy() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Privacy Policy',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $page_id );

		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_privacy_policy' );
	}

	/**
	 * @ticket 55104
	 *
	 * @dataProvider data_conditional_tags_trigger_doing_it_wrong_and_return_false_if_wp_query_is_not_set
	 *
	 * @param string $function_name The name of the function to test.
	 */
	public function test_conditional_tags_trigger_doing_it_wrong_and_return_false_if_wp_query_is_not_set( $function_name ) {
		unset( $GLOBALS['wp_query'] );

		if ( 'is_comments_popup' === $function_name ) {
			// `is_comments_popup()` is deprecated as of WP 4.5.
			$this->setExpectedDeprecated( $function_name );
		} else {
			// All the other functions should throw a `_doing_it_wrong()` notice.
			$this->setExpectedIncorrectUsage( $function_name );
		}

		$this->assertFalse( call_user_func( $function_name ) );
	}

	/**
	 * Data provider.
	 */
	public function data_conditional_tags_trigger_doing_it_wrong_and_return_false_if_wp_query_is_not_set() {
		// Get the list of `is_*()` conditional tags.
		$functions = array_filter(
			get_class_methods( 'WP_Query' ),
			static function ( $function_name ) {
				return str_starts_with( $function_name, 'is_' );
			}
		);

		// Wrap each function name in an array.
		$functions = array_map(
			static function ( $function_name ) {
				return array( $function_name );
			},
			$functions
		);

		return $functions;
	}

	/**
	 * @ticket 55722
	 *
	 * @dataProvider data_loop_functions_do_not_trigger_a_fatal_error_if_wp_query_is_not_set
	 *
	 * @param string     $function_name The name of the function to test.
	 * @param false|null $expected      Expected return value.
	 */
	public function test_loop_functions_do_not_trigger_a_fatal_error_if_wp_query_is_not_set( $function_name, $expected ) {
		unset( $GLOBALS['wp_query'] );

		$this->assertSame( $expected, call_user_func( $function_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string     $function_name The name of the function to test.
	 *     @type false|null $expected      Expected return value.
	 * }
	 */
	public function data_loop_functions_do_not_trigger_a_fatal_error_if_wp_query_is_not_set() {
		return array(
			array( 'have_posts', false ),
			array( 'in_the_loop', false ),
			array( 'rewind_posts', null ),
			array( 'the_post', null ),
			array( 'have_comments', false ),
			array( 'the_comment', null ),
		);
	}
}
