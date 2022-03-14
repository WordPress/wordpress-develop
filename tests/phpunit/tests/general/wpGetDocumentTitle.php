<?php

/**
 * A set of unit tests for functions in wp-includes/general-template.php
 *
 * @group general
 * @group template
 * @group document-title
 * @covers ::wp_get_document_title
 * @covers ::_wp_render_title_tag
 */
class Tests_General_wpGetDocumentTitle extends WP_UnitTestCase {

	public $blog_name;
	public static $category_id;
	public static $author_id;
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id = $factory->category->create(
			array(
				'name' => 'test_category',
			)
		);

		self::$author_id = $factory->user->create(
			array(
				'role'        => 'author',
				'user_login'  => 'test_author',
				'description' => 'test_author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
				'post_title'  => 'test_title',
				'post_type'   => 'post',
				'post_date'   => '2015-09-22 18:52:17',
				'category'    => self::$category_id,
			)
		);
	}

	public function set_up() {
		parent::set_up();

		add_action( 'after_setup_theme', array( $this, 'add_title_tag_support' ) );

		$this->blog_name = get_option( 'blogname' );

		setup_postdata( get_post( self::$post_id ) );
	}

	public function add_title_tag_support() {
		add_theme_support( 'title-tag' );
	}

	public function test__wp_render_title_tag() {
		$this->go_to( '/' );

		$this->expectOutputString( sprintf( "<title>%s &#8211; %s</title>\n", $this->blog_name, get_option( 'blogdescription' ) ) );
		_wp_render_title_tag();
	}

	public function test__wp_render_title_no_theme_support() {
		$this->go_to( '/' );

		remove_theme_support( 'title-tag' );

		$this->expectOutputString( '' );
		_wp_render_title_tag();
	}

	public function test_short_circuiting_title() {
		$this->go_to( '/' );

		add_filter( 'pre_get_document_title', array( $this, 'short_circuit_title' ) );

		$this->assertSame( 'A Wild Title', wp_get_document_title() );
	}

	public function short_circuit_title( $title ) {
		return 'A Wild Title';
	}

	public function test_front_page_title() {
		update_option( 'show_on_front', 'page' );
		update_option(
			'page_on_front',
			$this->factory->post->create(
				array(
					'post_title' => 'front-page',
					'post_type'  => 'page',
				)
			)
		);
		add_filter( 'document_title_parts', array( $this, 'front_page_title_parts' ) );

		$this->go_to( '/' );
		$this->assertSame( sprintf( '%s &#8211; Just another WordPress site', $this->blog_name ), wp_get_document_title() );

		update_option( 'show_on_front', 'posts' );

		$this->go_to( '/' );
		$this->assertSame( sprintf( '%s &#8211; Just another WordPress site', $this->blog_name ), wp_get_document_title() );
	}

	public function front_page_title_parts( $parts ) {
		$this->assertArrayHasKey( 'title', $parts );
		$this->assertArrayHasKey( 'tagline', $parts );
		$this->assertArrayNotHasKey( 'site', $parts );

		return $parts;
	}

	public function test_home_title() {
		$blog_page_id = $this->factory->post->create(
			array(
				'post_title' => 'blog-page',
				'post_type'  => 'page',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $blog_page_id );

		// Show page name on home page if it's not the front page.
		$this->go_to( get_permalink( $blog_page_id ) );
		$this->assertSame( sprintf( 'blog-page &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_paged_title() {
		$this->go_to( '?page=4' );

		add_filter( 'document_title_parts', array( $this, 'paged_title_parts' ) );

		$this->assertSame( sprintf( '%s &#8211; Page 4 &#8211; Just another WordPress site', $this->blog_name ), wp_get_document_title() );
	}

	public function paged_title_parts( $parts ) {
		$this->assertArrayHasKey( 'page', $parts );
		$this->assertArrayHasKey( 'title', $parts );
		$this->assertArrayHasKey( 'tagline', $parts );
		$this->assertArrayNotHasKey( 'site', $parts );

		return $parts;
	}

	public function test_singular_title() {
		$this->go_to( '?p=' . self::$post_id );

		add_filter( 'document_title_parts', array( $this, 'singular_title_parts' ) );

		$this->assertSame( sprintf( 'test_title &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function singular_title_parts( $parts ) {
		$this->assertArrayHasKey( 'site', $parts );
		$this->assertArrayHasKey( 'title', $parts );
		$this->assertArrayNotHasKey( 'tagline', $parts );

		return $parts;
	}

	public function test_category_title() {
		$this->go_to( '?cat=' . self::$category_id );

		$this->assertSame( sprintf( 'test_category &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_search_title() {
		$this->go_to( '?s=test_title' );

		$this->assertSame( sprintf( 'Search Results for &#8220;test_title&#8221; &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_author_title() {
		$this->go_to( '?author=' . self::$author_id );

		$this->assertSame( sprintf( 'test_author &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_post_type_archive_title() {
		register_post_type(
			'cpt',
			array(
				'public'      => true,
				'has_archive' => true,
				'labels'      => array(
					'name' => 'test_cpt',
				),
			)
		);

		$this->factory->post->create(
			array(
				'post_type' => 'cpt',
			)
		);

		$this->go_to( '?post_type=cpt' );

		$this->assertSame( sprintf( 'test_cpt &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_year_title() {
		$this->go_to( '?year=2015' );

		$this->assertSame( sprintf( '2015 &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_month_title() {
		$this->go_to( '?monthnum=09' );

		$this->assertSame( sprintf( 'September 2015 &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_day_title() {
		$this->go_to( '?day=22' );

		$this->assertSame( sprintf( 'September 22, 2015 &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_404_title() {
		$this->go_to( '?m=404' );

		$this->assertSame( sprintf( 'Page not found &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function test_paged_post_title() {
		$this->go_to( '?paged=4&p=' . self::$post_id );

		add_filter( 'title_tag_parts', array( $this, 'paged_post_title_parts' ) );

		$this->assertSame( sprintf( 'test_title &#8211; Page 4 &#8211; %s', $this->blog_name ), wp_get_document_title() );
	}

	public function paged_post_title_parts( $parts ) {
		$this->assertArrayHasKey( 'page', $parts );
		$this->assertArrayHasKey( 'site', $parts );
		$this->assertArrayHasKey( 'title', $parts );
		$this->assertArrayNotHasKey( 'tagline', $parts );

		return $parts;
	}

	public function test_rearrange_title_parts() {
		$this->go_to( '?p=' . self::$post_id );

		add_filter( 'document_title_parts', array( $this, 'rearrange_title_parts' ) );

		$this->assertSame( sprintf( '%s &#8211; test_title', $this->blog_name ), wp_get_document_title() );
	}

	public function rearrange_title_parts( $parts ) {
		$parts = array(
			$parts['site'],
			$parts['title'],
		);

		return $parts;
	}

	public function test_change_title_separator() {
		$this->go_to( '?p=' . self::$post_id );

		add_filter( 'document_title_separator', array( $this, 'change_title_separator' ) );

		$this->assertSame( sprintf( 'test_title %%%% %s', $this->blog_name ), wp_get_document_title() );
	}

	public function change_title_separator( $sep ) {
		return '%%';
	}
}
