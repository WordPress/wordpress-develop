<?php
/**
 * Tests for the server-side rendering of the Breadcrumbs block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_breadcrumbs
 */
class Tests_Blocks_RenderBreadcrumbs extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/breadcrumbs',
			'attrs'     => array(),
		);

		// Two posts are enough for a second page.
		update_option( 'posts_per_page', 1 );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		_unregister_post_type( 'book' );

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block that differ from the defaults.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$attributes = array_merge(
			array(
				'prefersTaxonomy' => false,
				'separator'       => '/',
				'showHomeItem'    => true,
				'showCurrentItem' => true,
				'showOnHomePage'  => false,
			),
			$attributes
		);

		$block = new WP_Block(
			array(
				'blockName' => 'core/breadcrumbs',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_breadcrumbs( $attributes, '', $block );
	}

	/**
	 * Renders the block for a post.
	 *
	 * @param WP_Post $post       The post.
	 * @param array   $attributes Optional. The attributes of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render_for_post( $post, $attributes = array() ) {
		return $this->render(
			$attributes,
			array(
				'postId'   => $post->ID,
				'postType' => $post->post_type,
			)
		);
	}

	/**
	 * Returns the items of the output.
	 *
	 * @param string $markup The markup.
	 * @return array[] The items, as the label and the URL, which is null for the current item.
	 */
	private function get_items( $markup ) {
		preg_match_all( '/<li>(?:<a href="([^"]*)">(.*?)<\/a>|<span aria-current="page">(.*?)<\/span>)<\/li>/', $markup, $matches, PREG_SET_ORDER );

		$items = array();

		foreach ( $matches as $match ) {
			$items[] = isset( $match[3] ) ? array( html_entity_decode( $match[3] ), null ) : array( html_entity_decode( $match[2] ), html_entity_decode( $match[1] ) );
		}

		return $items;
	}

	/**
	 * Returns the labels of the items of the output.
	 *
	 * @param string $markup The markup.
	 * @return string[] The labels.
	 */
	private function get_labels( $markup ) {
		return array_column( $this->get_items( $markup ), 0 );
	}

	/**
	 * Creates a static front page and goes to it.
	 *
	 * @param string $content Optional. The content of the page. Default none.
	 * @return int The ID of the page.
	 */
	private function set_static_front_page( $content = '' ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Front',
				'post_content' => $content,
			)
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		return $page_id;
	}

	/**
	 * Tests the wrapper.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_trail_in_a_navigation() {
		$this->go_to( '/?s=wp' );

		$markup = $this->render();

		$this->assertStringStartsWith( '<nav ', $markup );
		$this->assertStringEndsWith( '</ol></nav>', $markup );

		$processor = new WP_HTML_Tag_Processor( $markup );
		$processor->next_tag( 'nav' );

		$this->assertSame( 'Breadcrumbs', $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( 'wp-block-breadcrumbs', $processor->get_attribute( 'class' ) );
		$this->assertSame( '--separator: "/"', $processor->get_attribute( 'style' ) );
	}

	/**
	 * Tests the separator.
	 *
	 * @ticket 66590
	 *
	 * @dataProvider data_separators
	 *
	 * @param mixed  $separator The separator attribute.
	 * @param string $expected  The expected value of the style.
	 */
	public function test_should_set_the_separator( $separator, $expected ) {
		$this->go_to( '/?s=wp' );

		$processor = new WP_HTML_Tag_Processor( $this->render( array( 'separator' => $separator ) ) );
		$processor->next_tag( 'nav' );

		$this->assertSame( $expected, $processor->get_attribute( 'style' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_separators() {
		return array(
			'slash'        => array( '/', '--separator: "/"' ),
			'arrow'        => array( '>', '--separator: ">"' ),
			'empty'        => array( '', '--separator: ""' ),
			'not a string' => array( 5, '--separator: ""' ),
			'null'         => array( null, '--separator: ""' ),
		);
	}

	/**
	 * Tests that nothing is rendered on the front page unless it is set to.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_nothing_on_the_front_page_by_default() {
		$this->go_to( '/' );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests the front page, when it is set to be shown.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_current_home_item_on_the_front_page_when_shown() {
		$this->go_to( '/' );

		$this->assertSame( array( array( 'Home', null ) ), $this->get_items( $this->render( array( 'showOnHomePage' => true ) ) ) );
	}

	/**
	 * Tests the front page without the home item.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_nothing_on_the_front_page_without_the_home_item() {
		$this->go_to( '/' );

		$this->assertSame(
			'',
			$this->render(
				array(
					'showOnHomePage' => true,
					'showHomeItem'   => false,
				)
			)
		);
	}

	/**
	 * Tests that the home item is a link away from the front page.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_home_item_away_from_the_front_page() {
		$this->go_to( '/?s=wp' );

		$items = $this->get_items( $this->render() );

		$this->assertSame( array( 'Home', home_url( '/' ) ), $items[0] );
	}

	/**
	 * Tests that the home item can be left out.
	 *
	 * @ticket 66590
	 */
	public function test_should_leave_out_the_home_item() {
		$this->go_to( '/?s=wp' );

		$this->assertSame( array( 'Search results for: "wp"' ), $this->get_labels( $this->render( array( 'showHomeItem' => false ) ) ) );
	}

	/**
	 * Tests the paginated front page of posts.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_home_item_and_add_the_page_number_on_a_paginated_front_page_of_posts() {
		self::factory()->post->create_many( 2 );

		$this->go_to( '/?paged=2' );

		$this->assertSame(
			array(
				array( 'Home', get_pagenum_link( 1 ) ),
				array( 'Page 2', null ),
			),
			$this->get_items( $this->render( array( 'showOnHomePage' => true ) ) )
		);
	}

	/**
	 * Tests the static front page.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_current_home_item_on_a_static_front_page() {
		$page_id = $this->set_static_front_page();

		$this->go_to( get_permalink( $page_id ) );

		$this->assertSame( array( array( 'Home', null ) ), $this->get_items( $this->render( array( 'showOnHomePage' => true ) ) ) );
	}

	/**
	 * Tests the paginated static front page.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_home_item_and_add_the_page_number_on_a_paginated_static_front_page() {
		$page_id = $this->set_static_front_page( 'One<!--nextpage-->Two' );

		$this->go_to( add_query_arg( 'page', 2, get_permalink( $page_id ) ) );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Page 2', null ),
			),
			$this->get_items( $this->render( array( 'showOnHomePage' => true ) ) )
		);
	}

	/**
	 * Tests that the first page of the static front page is the current item.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_current_home_item_on_the_first_page_of_a_static_front_page() {
		$page_id = $this->set_static_front_page( 'One<!--nextpage-->Two' );

		$this->go_to( add_query_arg( 'page', 1, get_permalink( $page_id ) ) );

		$this->assertSame( array( array( 'Home', null ) ), $this->get_items( $this->render( array( 'showOnHomePage' => true ) ) ) );
	}

	/**
	 * Tests the page of the posts.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_page_of_the_posts() {
		$front_id = $this->set_static_front_page();
		$posts_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'The blog',
			)
		);
		update_option( 'page_for_posts', $posts_id );
		self::factory()->post->create_many( 2 );

		$this->go_to( get_permalink( $posts_id ) );

		$this->assertNotSame( $front_id, $posts_id );
		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'The blog', null ),
			),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests the paginated page of the posts.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_page_of_the_posts_and_add_the_page_number_when_paginated() {
		$this->set_static_front_page();
		$posts_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'The blog',
			)
		);
		update_option( 'page_for_posts', $posts_id );
		self::factory()->post->create_many( 2 );

		$this->go_to( add_query_arg( 'paged', 2, get_permalink( $posts_id ) ) );

		$items = $this->get_items( $this->render() );

		$this->assertSame( array( 'Home', 'The blog', 'Page 2' ), array_column( $items, 0 ) );
		$this->assertSame( get_pagenum_link( 1 ), $items[1][1] );
		$this->assertNull( $items[2][1] );
	}

	/**
	 * Tests the search results.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_search_results() {
		$this->go_to( '/?s=hello+world' );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Search results for: "hello world"', null ),
			),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests that a long search is trimmed to ten words, and escaped.
	 *
	 * @ticket 66590
	 */
	public function test_should_trim_and_escape_the_search_query() {
		$this->go_to( '/?s=' . rawurlencode( 'one two three four five six seven eight nine ten eleven twelve' ) );

		$this->assertSame( 'Search results for: "one two three four five six seven eight nine ten&hellip;"', html_entity_decode( $this->get_labels( $this->render() )[1], ENT_QUOTES, 'UTF-8' ) === '' ? '' : htmlentities( html_entity_decode( $this->get_labels( $this->render() )[1] ), ENT_NOQUOTES, 'UTF-8' ) );

		$this->go_to( '/?s=' . rawurlencode( '<b>bold</b>' ) );

		$markup = $this->render();

		$this->assertStringNotContainsString( '<b>', $markup );
	}

	/**
	 * Tests the paginated search results.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_search_and_add_the_page_number_when_paginated() {
		self::factory()->post->create_many( 2, array( 'post_title' => 'Findable' ) );

		$this->go_to( '/?s=Findable&paged=2' );

		$items = $this->get_items( $this->render() );

		$this->assertSame( array( 'Home', 'Search results for: "Findable"', 'Page 2' ), array_column( $items, 0 ) );
		$this->assertSame( get_pagenum_link( 1 ), $items[1][1] );
		$this->assertNull( $items[2][1] );
	}

	/**
	 * Tests the page that is not found.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_page_that_is_not_found() {
		$this->go_to( '/?p=999999' );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Page not found', null ),
			),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests the archives.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_breadcrumbs_of_an_archive() {
		$parent_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$child_id  = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent_id,
			)
		);
		self::factory()->post->create( array( 'post_category' => array( $child_id ) ) );

		$this->go_to( get_category_link( $child_id ) );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Parent', get_term_link( $parent_id, 'category' ) ),
				array( 'Child', null ),
			),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests the context needed for a single post.
	 *
	 * @ticket 66590
	 *
	 * @dataProvider data_missing_context
	 *
	 * @param array $context The context of the block.
	 */
	public function test_should_render_nothing_for_a_single_post_without_the_context( $context ) {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		if ( isset( $context['postId'] ) && 'post' === $context['postId'] ) {
			$context['postId'] = $post_id;
		}

		$this->assertSame( '', $this->render( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_missing_context() {
		return array(
			'no context'               => array( array() ),
			'no post type'             => array( array( 'postId' => 'post' ) ),
			'no post'                  => array( array( 'postType' => 'post' ) ),
			'post that does not exist' => array(
				array(
					'postId'   => 99999999,
					'postType' => 'post',
				),
			),
		);
	}

	/**
	 * Tests the single post with its category.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_a_single_post_with_its_category() {
		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id     = self::factory()->post->create(
			array(
				'post_title'    => 'A post',
				'post_category' => array( $category_id ),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'News', get_term_link( $category_id, 'category' ) ),
				array( 'A post', null ),
			),
			$this->get_items( $this->render_for_post( get_post( $post_id ) ) )
		);
	}

	/**
	 * Tests a single post without terms.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_a_single_post_without_terms() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( array( 'Home', 'A post' ), $this->get_labels( $this->render_for_post( get_post( $post_id ) ) ) );
	}

	/**
	 * Tests the label of the post type archive for posts, with a page for posts.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_page_for_posts_in_the_trail_of_a_post() {
		$posts_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'The blog',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $posts_id );
		$post_id = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$items = $this->get_items( $this->render_for_post( get_post( $post_id ) ) );

		$this->assertSame( array( 'Home', 'The blog', 'A post' ), array_column( $items, 0 ) );
		$this->assertSame( get_post_type_archive_link( 'post' ), $items[1][1] );
	}

	/**
	 * Tests the archive of a post type in the trail of a post of the type.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_archive_of_the_post_type_in_the_trail() {
		register_post_type(
			'book',
			array(
				'public'      => true,
				'has_archive' => true,
				'labels'      => array(
					'name'     => 'Books',
					'archives' => 'All books',
				),
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'book',
				'post_title' => 'A book',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$items = $this->get_items( $this->render_for_post( get_post( $post_id ) ) );

		$this->assertSame( array( 'Home', 'All books', 'A book' ), array_column( $items, 0 ) );
		$this->assertSame( get_post_type_archive_link( 'book' ), $items[1][1] );
	}

	/**
	 * Tests the ancestors of a page.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_a_page_with_its_ancestors() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $parent_id,
			)
		);

		$this->go_to( get_permalink( $child_id ) );

		$this->assertSame(
			array(
				array( 'Home', home_url( '/' ) ),
				array( 'Parent', get_permalink( $parent_id ) ),
				array( 'Child', null ),
			),
			$this->get_items( $this->render_for_post( get_post( $child_id ) ) )
		);
	}

	/**
	 * Tests that a page does not use the taxonomies when it has none, whatever the preference.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_ancestors_of_a_page_when_the_taxonomy_is_preferred() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $parent_id,
			)
		);

		$this->go_to( get_permalink( $child_id ) );

		$this->assertSame(
			array( 'Home', 'Parent', 'Child' ),
			$this->get_labels( $this->render_for_post( get_post( $child_id ), array( 'prefersTaxonomy' => true ) ) )
		);
	}

	/**
	 * Tests the terms of a post type that is hierarchical when the taxonomy is preferred.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_terms_of_a_hierarchical_post_type_when_the_taxonomy_is_preferred() {
		register_post_type(
			'guide',
			array(
				'public'       => true,
				'hierarchical' => true,
				'taxonomies'   => array( 'category' ),
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		$category_id = self::factory()->category->create( array( 'name' => 'Topic' ) );
		$parent_id   = self::factory()->post->create(
			array(
				'post_type'  => 'guide',
				'post_title' => 'Parent guide',
			)
		);
		$guide_id    = self::factory()->post->create(
			array(
				'post_type'   => 'guide',
				'post_title'  => 'A guide',
				'post_parent' => $parent_id,
			)
		);
		wp_set_object_terms( $guide_id, array( $category_id ), 'category' );

		$this->go_to( get_permalink( $guide_id ) );

		$post = get_post( $guide_id );

		$this->assertSame( array( 'Home', 'Parent guide', 'A guide' ), $this->get_labels( $this->render_for_post( $post ) ) );
		$this->assertSame( array( 'Home', 'Topic', 'A guide' ), $this->get_labels( $this->render_for_post( $post, array( 'prefersTaxonomy' => true ) ) ) );

		_unregister_post_type( 'guide' );
	}

	/**
	 * Tests the trail of an attachment, which is the trail of its parent.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_the_trail_of_the_parent_for_an_attachment() {
		$category_id   = self::factory()->category->create( array( 'name' => 'News' ) );
		$parent_id     = self::factory()->post->create(
			array(
				'post_title'    => 'The parent',
				'post_category' => array( $category_id ),
			)
		);
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_parent'    => $parent_id,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'The image',
			)
		);

		$this->go_to( get_attachment_link( $attachment_id ) );

		$items = $this->get_items( $this->render_for_post( get_post( $attachment_id ) ) );

		$this->assertSame( array( 'Home', 'News', 'The image' ), array_column( $items, 0 ) );
		$this->assertNull( $items[2][1] );
	}

	/**
	 * Tests an attachment with a parent that does not exist.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_an_attachment_whose_parent_does_not_exist() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_parent'    => 99999999,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'The image',
			)
		);

		$this->go_to( get_attachment_link( $attachment_id ) );

		$items = $this->get_items( $this->render_for_post( get_post( $attachment_id ) ) );

		$this->assertSame( 'Home', $items[0][0] );
		$this->assertSame( array( 'The image', null ), end( $items ), 'The attachment should be the current item.' );
	}

	/**
	 * Tests the title of a post, which is filtered as post content.
	 *
	 * @ticket 66590
	 */
	public function test_should_filter_the_title_of_the_post() {
		$post_id = self::factory()->post->create( array( 'post_title' => '<em>Em</em><script>alert(1)</script>' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$markup = $this->render_for_post( get_post( $post_id ) );

		$this->assertStringContainsString( '<span aria-current="page"><em>Em</em>', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
	}

	/**
	 * Tests that a post without a title gets the fallback title.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_a_post_without_a_title() {
		$post_id = self::factory()->post->create( array( 'post_title' => '' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( array( 'Home', '(no title)' ), $this->get_labels( $this->render_for_post( get_post( $post_id ) ) ) );
	}

	/**
	 * Tests a post with pages: the title is linked and the page number is added.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_post_and_add_the_page_number_when_paginated() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'A post',
				'post_content' => 'One<!--nextpage-->Two',
			)
		);
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( add_query_arg( 'page', 2, get_permalink( $post_id ) ) );

		$items = $this->get_items( $this->render_for_post( get_post( $post_id ) ) );

		$this->assertSame( array( 'Home', 'A post', 'Page 2' ), array_column( $items, 0 ) );
		$this->assertSame( get_permalink( $post_id ), $items[1][1] );
		$this->assertNull( $items[2][1] );
	}

	/**
	 * Tests a post with pages of comments: the title is linked and the comments page number is added.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_post_and_add_the_comments_page_number_when_the_comments_are_paginated() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );
		set_query_var( 'cpage', 2 );

		$items = $this->get_items( $this->render_for_post( get_post( $post_id ) ) );

		$this->assertSame( array( 'Home', 'A post', 'Comments Page 2' ), array_column( $items, 0 ) );
		$this->assertSame( get_permalink( $post_id ), $items[1][1] );
	}

	/**
	 * Tests that the current item can be left out.
	 *
	 * @ticket 66590
	 */
	public function test_should_leave_out_the_current_item() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$items = $this->get_items( $this->render_for_post( get_post( $post_id ), array( 'showCurrentItem' => false ) ) );

		$this->assertSame( array( array( 'Home', home_url( '/' ) ) ), $items );
	}

	/**
	 * Tests that nothing is rendered when the only item is the current item and it is left out.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_nothing_when_the_only_item_is_left_out() {
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array(), 'category' );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame(
			'',
			$this->render_for_post(
				get_post( $post_id ),
				array(
					'showHomeItem'    => false,
					'showCurrentItem' => false,
				)
			)
		);
	}

	/**
	 * Tests the filter of the items.
	 *
	 * @ticket 66590
	 */
	public function test_should_filter_the_items() {
		$this->go_to( '/?s=wp' );

		add_filter(
			'block_core_breadcrumbs_items',
			static function ( $items ) {
				array_splice(
					$items,
					1,
					0,
					array(
						array(
							'label'      => '<b>Extra</b>',
							'url'        => 'https://example.org/extra/?a=1&b=2',
							'allow_html' => true,
						),
						array( 'label' => '<b>Plain</b>' ),
					)
				);

				return $items;
			}
		);

		$markup = $this->render();

		$this->assertStringContainsString( '<li><a href="https://example.org/extra/?a=1&#038;b=2"><b>Extra</b></a></li>', $markup );
		$this->assertStringContainsString( '<li><span aria-current="page">&lt;b&gt;Plain&lt;/b&gt;</span></li>', $markup );
	}

	/**
	 * Tests that nothing is rendered when the filter removes the items.
	 *
	 * @ticket 66590
	 */
	public function test_should_render_nothing_when_the_filter_removes_the_items() {
		$this->go_to( '/?s=wp' );

		add_filter( 'block_core_breadcrumbs_items', '__return_empty_array' );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that the labels and the URLs are escaped.
	 *
	 * @ticket 66590
	 */
	public function test_should_escape_the_labels_and_the_urls() {
		$this->go_to( '/?s=wp' );

		add_filter(
			'block_core_breadcrumbs_items',
			static function () {
				return array(
					array(
						'label' => 'A & "B"',
						'url'   => 'javascript:alert(1)',
					),
				);
			}
		);

		$markup = $this->render();

		$this->assertStringContainsString( '>A &amp; &quot;B&quot;</a>', $markup );
		$this->assertStringNotContainsString( 'javascript:', $markup );
	}
}
