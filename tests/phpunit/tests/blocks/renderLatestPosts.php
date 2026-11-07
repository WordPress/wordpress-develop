<?php
/**
 * Tests for the server-side rendering of the Latest Posts block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_latest_posts
 */
class Tests_Blocks_RenderLatestPosts extends WP_UnitTestCase {

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
			'blockName' => 'core/latest-posts',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Creates a post.
	 *
	 * @param string $title Title of the post.
	 * @param string $date  Optional. The date of the post. Default 2020-03-15.
	 * @param array  $args  Optional. The arguments of the post that differ from the defaults.
	 * @return int The ID of the post.
	 */
	private function create_post( $title, $date = '2020-03-15 10:00:00', $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'   => $title,
					'post_date'    => $date,
					'post_content' => "Content of $title",
					'post_excerpt' => '',
				),
				$args
			)
		);
	}

	/**
	 * Renders the block with the default attributes.
	 *
	 * @param array $attributes The attributes of the block that differ from the defaults.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array() ) {
		return render_block_core_latest_posts(
			array_merge(
				array(
					'postsToShow'            => 5,
					'order'                  => 'desc',
					'orderBy'                => 'date',
					'excerptLength'          => 55,
					'displayFeaturedImage'   => false,
					'addLinkToFeaturedImage' => false,
					'featuredImageSizeSlug'  => 'thumbnail',
				),
				$attributes
			)
		);
	}

	/**
	 * Returns the titles of the posts of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The titles.
	 */
	private function get_titles( $markup ) {
		preg_match_all( '/<a class="wp-block-latest-posts__post-title" href="[^"]*">(.*?)<\/a>/', $markup, $matches );

		return $matches[1];
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the classes of the first element matching the query as a sorted list.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return string[] The classes.
	 */
	private function get_classes( $markup, $query ) {
		$classes = preg_split( '/\s+/', $this->get_element( $markup, $query )['class'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		sort( $classes );

		return $classes;
	}

	/**
	 * Tests the list of the posts from the newest.
	 *
	 * @ticket 66600
	 */
	public function test_should_list_the_posts_from_the_newest() {
		$this->create_post( 'Old', '2019-01-01 10:00:00' );
		$this->create_post( 'New', '2021-01-01 10:00:00' );
		$this->create_post( 'Middle', '2020-01-01 10:00:00' );

		$markup = $this->render();

		$this->assertStringStartsWith( '<ul ', $markup );
		$this->assertStringEndsWith( "</li>\n</ul>", $markup );
		$this->assertSame( array( 'New', 'Middle', 'Old' ), $this->get_titles( $markup ) );
		$this->assertSame( 3, substr_count( $markup, '<li>' ) );
	}

	/**
	 * Tests the order and the order by.
	 *
	 * @ticket 66600
	 *
	 * @dataProvider data_orders
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected titles.
	 */
	public function test_should_order_the_posts( $attributes, $expected ) {
		$this->create_post( 'B second', '2019-01-01 10:00:00' );
		$this->create_post( 'C third', '2021-01-01 10:00:00' );
		$this->create_post( 'A first', '2020-01-01 10:00:00' );

		$this->assertSame( $expected, $this->get_titles( $this->render( $attributes ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_orders() {
		return array(
			'date ascending'   => array( array( 'order' => 'asc' ), array( 'B second', 'A first', 'C third' ) ),
			'title ascending'  => array(
				array(
					'order'   => 'asc',
					'orderBy' => 'title',
				),
				array( 'A first', 'B second', 'C third' ),
			),
			'title descending' => array(
				array(
					'order'   => 'desc',
					'orderBy' => 'title',
				),
				array( 'C third', 'B second', 'A first' ),
			),
		);
	}

	/**
	 * Tests the number of posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_limit_the_number_of_posts() {
		$this->create_post( 'Old', '2019-01-01 10:00:00' );
		$this->create_post( 'New', '2021-01-01 10:00:00' );
		$this->create_post( 'Middle', '2020-01-01 10:00:00' );

		$this->assertSame( array( 'New', 'Middle' ), $this->get_titles( $this->render( array( 'postsToShow' => 2 ) ) ) );
	}

	/**
	 * Tests that only published posts are listed, and that sticky posts are not first.
	 *
	 * @ticket 66600
	 */
	public function test_should_list_only_published_posts_and_ignore_sticky_posts() {
		$old_id = $this->create_post( 'Old sticky', '2019-01-01 10:00:00' );
		$this->create_post( 'New', '2021-01-01 10:00:00' );
		$this->create_post( 'Draft', '2022-01-01 10:00:00', array( 'post_status' => 'draft' ) );
		$this->create_post( 'Page', '2022-01-01 10:00:00', array( 'post_type' => 'page' ) );
		stick_post( $old_id );

		$this->assertSame( array( 'New', 'Old sticky' ), $this->get_titles( $this->render() ) );
	}

	/**
	 * Tests the categories.
	 *
	 * @ticket 66600
	 */
	public function test_should_list_only_the_posts_of_the_categories() {
		$first_id  = self::factory()->category->create();
		$second_id = self::factory()->category->create();
		$third_id  = self::factory()->category->create();

		$this->create_post( 'In first', '2021-01-01 10:00:00', array( 'post_category' => array( $first_id ) ) );
		$this->create_post( 'In second', '2020-01-01 10:00:00', array( 'post_category' => array( $second_id ) ) );
		$this->create_post( 'In third', '2019-01-01 10:00:00', array( 'post_category' => array( $third_id ) ) );

		$categories = array(
			array( 'id' => $first_id ),
			array( 'id' => $second_id ),
		);

		$this->assertSame( array( 'In first', 'In second' ), $this->get_titles( $this->render( array( 'categories' => $categories ) ) ) );
		$this->assertCount( 3, $this->get_titles( $this->render( array( 'categories' => array() ) ) ), 'Empty categories should list all the posts.' );
		$this->assertCount( 3, $this->get_titles( $this->render( array( 'categories' => 'not a list' ) ) ), 'Categories that are not a list should be ignored.' );
	}

	/**
	 * Tests the author.
	 *
	 * @ticket 66600
	 */
	public function test_should_list_only_the_posts_of_the_author() {
		$author_id = self::factory()->user->create();

		$this->create_post( 'By the author', '2021-01-01 10:00:00', array( 'post_author' => $author_id ) );
		$this->create_post( 'By someone else' );

		$this->assertSame( array( 'By the author' ), $this->get_titles( $this->render( array( 'selectedAuthor' => $author_id ) ) ) );
	}

	/**
	 * Tests the link and the title of a post.
	 *
	 * @ticket 66600
	 */
	public function test_should_link_the_title_of_the_post() {
		$post_id = $this->create_post( 'A post' );

		$markup = $this->render();
		$link   = $this->get_element( $markup, array( 'class_name' => 'wp-block-latest-posts__post-title' ) );

		$this->assertSame( get_permalink( $post_id ), $link['href'] );
		$this->assertSame( '<li><a class="wp-block-latest-posts__post-title" href="' . esc_url( get_permalink( $post_id ) ) . '">A post</a></li>', trim( substr( $markup, strpos( $markup, '<li>' ), strpos( $markup, '</li>' ) - strpos( $markup, '<li>' ) + 5 ) ) );
	}

	/**
	 * Tests a post without a title.
	 *
	 * @ticket 66600
	 */
	public function test_should_use_a_fallback_for_a_post_without_a_title() {
		$this->create_post( '' );

		$this->assertSame( array( '(no title)' ), $this->get_titles( $this->render() ) );
	}

	/**
	 * Creates a post with a featured image.
	 *
	 * @param string $title Title of the post.
	 * @return int The ID of the post.
	 */
	private function create_post_with_featured_image( $title ) {
		$post_id       = $this->create_post( $title );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'featured.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $post_id,
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 600,
				'height' => 400,
				'file'   => 'featured.jpg',
				'sizes'  => array(
					'thumbnail' => array(
						'file'      => 'featured-150x100.jpg',
						'width'     => 150,
						'height'    => 100,
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);
		set_post_thumbnail( $post_id, $attachment_id );

		return $post_id;
	}

	/**
	 * Tests the featured image.
	 *
	 * @ticket 66600
	 */
	public function test_should_display_the_featured_image() {
		$this->create_post_with_featured_image( 'With an image' );

		$markup = $this->render( array( 'displayFeaturedImage' => true ) );
		$figure = $this->get_element( $markup, 'div' );

		$this->assertSame( 'wp-block-latest-posts__featured-image', $figure['class'] );
		$this->assertStringContainsString( '<div class="wp-block-latest-posts__featured-image"><img ', $markup );
		$this->assertStringContainsString( 'featured.jpg', $markup );
		$this->assertNull( $this->get_element( $markup, 'a' )['aria-label'] ?? null, 'The image should not be linked.' );
		$this->assertSame( '', $this->get_element( $markup, 'img' )['style'], 'The image should have no size style.' );
		$this->assertStringNotContainsString( 'wp-block-latest-posts__featured-image"><a', $markup );
	}

	/**
	 * Tests the alignment and the size of the featured image.
	 *
	 * @ticket 66600
	 */
	public function test_should_align_and_size_the_featured_image() {
		$this->create_post_with_featured_image( 'With an image' );

		$markup = $this->render(
			array(
				'displayFeaturedImage'    => true,
				'featuredImageAlign'      => 'left',
				'featuredImageSizeWidth'  => 150,
				'featuredImageSizeHeight' => 100,
			)
		);

		$this->assertSame( 'wp-block-latest-posts__featured-image alignleft', $this->get_element( $markup, 'div' )['class'] );
		$this->assertSame( 'max-width:150px;max-height:100px;', $this->get_element( $markup, 'img' )['style'] );
	}

	/**
	 * Tests the width or the height alone.
	 *
	 * @ticket 66600
	 */
	public function test_should_size_the_featured_image_by_width_or_height() {
		$this->create_post_with_featured_image( 'With an image' );

		$width  = $this->render(
			array(
				'displayFeaturedImage'   => true,
				'featuredImageSizeWidth' => 150,
			)
		);
		$height = $this->render(
			array(
				'displayFeaturedImage'    => true,
				'featuredImageSizeHeight' => 100,
			)
		);

		$this->assertSame( 'max-width:150px;', $this->get_element( $width, 'img' )['style'] );
		$this->assertSame( 'max-height:100px;', $this->get_element( $height, 'img' )['style'] );
	}

	/**
	 * Tests the size of the featured image.
	 *
	 * @ticket 66600
	 */
	public function test_should_use_the_size_slug_of_the_featured_image() {
		$this->create_post_with_featured_image( 'With an image' );

		$thumbnail = $this->get_element( $this->render( array( 'displayFeaturedImage' => true ) ), 'img' );
		$full      = $this->get_element(
			$this->render(
				array(
					'displayFeaturedImage'  => true,
					'featuredImageSizeSlug' => 'full',
				)
			),
			'img'
		);

		$this->assertStringEndsWith( 'featured-150x100.jpg', $thumbnail['src'] );
		$this->assertStringEndsWith( 'featured.jpg', $full['src'] );
		$this->assertStringNotContainsString( '150x100', $full['src'] );
	}

	/**
	 * Tests that the alignment and the title are escaped.
	 *
	 * @ticket 66600
	 */
	public function test_should_escape_the_alignment_and_the_title_of_the_featured_image() {
		$this->create_post_with_featured_image( 'With an image' );

		add_filter(
			'the_title',
			static function () {
				return 'Say "hi"';
			}
		);

		$markup = $this->render(
			array(
				'displayFeaturedImage'   => true,
				'addLinkToFeaturedImage' => true,
				'featuredImageAlign'     => 'left" onclick="x',
			)
		);

		$figure = $this->get_element( $markup, 'div' );

		$this->assertArrayNotHasKey( 'onclick', $figure );
		$this->assertSame( 'wp-block-latest-posts__featured-image alignleft" onclick="x', $figure['class'] );
		$this->assertSame( 'Say "hi"', $this->get_element( $markup, 'a' )['aria-label'] );
	}

	/**
	 * Tests the link of the featured image.
	 *
	 * @ticket 66600
	 */
	public function test_should_link_the_featured_image() {
		$post_id = $this->create_post_with_featured_image( 'Fish & chips image' );

		$markup = $this->render(
			array(
				'displayFeaturedImage'   => true,
				'addLinkToFeaturedImage' => true,
			)
		);
		$link   = $this->get_element( $markup, 'a' );

		$this->assertSame( get_permalink( $post_id ), $link['href'] );
		$this->assertSame( 'Fish & chips image', $link['aria-label'] );
		$this->assertStringContainsString( '<div class="wp-block-latest-posts__featured-image"><a href=', $markup );
	}

	/**
	 * Tests that there is no image for a post without a featured image, or when it is off.
	 *
	 * @ticket 66600
	 */
	public function test_should_not_display_the_featured_image_unless_it_is_set() {
		$this->create_post( 'Without an image' );

		$this->assertStringNotContainsString( 'featured-image', $this->render( array( 'displayFeaturedImage' => true ) ) );

		$this->create_post_with_featured_image( 'With an image' );

		$this->assertStringNotContainsString( 'featured-image', $this->render( array( 'displayFeaturedImage' => false ) ) );
	}

	/**
	 * Tests the author of the posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_display_the_author() {
		$author_id = self::factory()->user->create( array( 'display_name' => 'Jane Writer' ) );

		$this->create_post( 'A post', '2020-03-15 10:00:00', array( 'post_author' => $author_id ) );

		$markup = $this->render( array( 'displayAuthor' => true ) );

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-author">by Jane Writer</div>', $markup );
		$this->assertContains( 'has-author', $this->get_classes( $markup, 'ul' ) );
		$this->assertStringNotContainsString( 'post-author', $this->render( array( 'displayAuthor' => false ) ) );
		$this->assertStringNotContainsString( 'post-author', $this->render() );
	}

	/**
	 * Tests the date of the posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_display_the_date() {
		$post_id = $this->create_post( 'A post' );

		$markup = $this->render( array( 'displayPostDate' => true ) );
		$time   = $this->get_element( $markup, 'time' );

		$this->assertSame( get_the_date( 'c', $post_id ), $time['datetime'] );
		$this->assertSame( 'wp-block-latest-posts__post-date', $time['class'] );
		$this->assertStringContainsString( '>March 15, 2020</time>', $markup );
		$this->assertContains( 'has-dates', $this->get_classes( $markup, 'ul' ) );
		$this->assertStringNotContainsString( '<time', $this->render( array( 'displayPostDate' => false ) ) );
		$this->assertStringNotContainsString( '<time', $this->render() );
	}

	/**
	 * Tests the excerpt of the posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_display_the_excerpt() {
		$this->create_post( 'A post', '2020-03-15 10:00:00', array( 'post_excerpt' => 'A short excerpt.' ) );

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'excerpt',
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-excerpt">A short excerpt.</div>', $markup );
		$this->assertStringNotContainsString( 'post-full-content', $markup );
	}

	/**
	 * Tests the excerpt that is trimmed, with the link to read more.
	 *
	 * @ticket 66600
	 */
	public function test_should_trim_the_excerpt_and_link_to_read_more() {
		$post_id = $this->create_post( 'A <em>long</em> post', '2020-03-15 10:00:00', array( 'post_content' => 'One two three four five six seven eight nine ten.' ) );

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'excerpt',
				'excerptLength'           => 3,
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-excerpt">One two three… <a class="wp-block-latest-posts__read-more"', $markup );
		$this->assertStringNotContainsString( 'four', $markup );
		$this->assertStringContainsString(
			'… <a class="wp-block-latest-posts__read-more" href="' . esc_url( get_permalink( $post_id ) ) . '" rel="noopener">Read more<span class="screen-reader-text">: A &lt;em&gt;long&lt;/em&gt; post</span></a></div>',
			$markup
		);
		$this->assertStringNotContainsString( '[&hellip;]', $markup );
	}

	/**
	 * Tests that the link to read more is not added when a filter makes the excerpt longer.
	 *
	 * @ticket 66600
	 */
	public function test_should_not_add_the_read_more_link_when_a_filter_changes_the_excerpt_length() {
		$this->create_post( 'A post', '2020-03-15 10:00:00', array( 'post_content' => implode( ' ', array_fill( 0, 120, 'word' ) ) ) );

		add_filter(
			'excerpt_length',
			static function () {
				return 100;
			},
			99
		);

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'excerpt',
				'excerptLength'           => 3,
			)
		);

		$this->assertStringNotContainsString( 'wp-block-latest-posts__read-more', $markup );
	}

	/**
	 * Tests that the excerpt length filter of the block is removed.
	 *
	 * @ticket 66600
	 */
	public function test_should_remove_the_excerpt_length_filter_after_rendering() {
		$this->create_post( 'A post' );

		$this->render();

		$this->assertFalse( has_filter( 'excerpt_length', 'block_core_latest_posts_get_excerpt_length' ) );
	}

	/**
	 * Tests the excerpt of a post that is protected by a password.
	 *
	 * @ticket 66600
	 */
	public function test_should_hide_the_excerpt_of_a_protected_post() {
		$this->create_post( 'Protected', '2020-03-15 10:00:00', array( 'post_password' => 'secret' ) );

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'excerpt',
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-excerpt">This content is password protected.</div>', $markup );
		$this->assertStringNotContainsString( 'Content of Protected', $markup );
	}

	/**
	 * Tests the full content of the posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_display_the_full_content() {
		$this->create_post( 'A post', '2020-03-15 10:00:00', array( 'post_content' => "First paragraph.\n\nSecond paragraph with a ]]> marker." ) );

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'full_post',
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-full-content">First paragraph.', $markup );
		$this->assertStringContainsString( 'Second paragraph with a ]]&gt; marker.</div>', $markup );
		$this->assertStringNotContainsString( ']]>', $markup );
		$this->assertStringNotContainsString( 'post-excerpt', $markup );
	}

	/**
	 * Tests the full content of a post that is protected by a password.
	 *
	 * @ticket 66600
	 */
	public function test_should_hide_the_full_content_of_a_protected_post() {
		$this->create_post( 'Protected', '2020-03-15 10:00:00', array( 'post_password' => 'secret' ) );

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'full_post',
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-full-content">This content is password protected.</div>', $markup );
		$this->assertStringNotContainsString( 'Content of Protected', $markup );
	}

	/**
	 * Tests that no content is displayed unless it is set.
	 *
	 * @ticket 66600
	 *
	 * @dataProvider data_no_content
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_not_display_the_content_unless_it_is_set( $attributes ) {
		$this->create_post( 'A post' );

		$markup = $this->render( $attributes );

		$this->assertStringNotContainsString( 'post-excerpt', $markup );
		$this->assertStringNotContainsString( 'post-full-content', $markup );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_content() {
		return array(
			'no attributes'      => array( array() ),
			'content is off'     => array(
				array(
					'displayPostContent'      => false,
					'displayPostContentRadio' => 'excerpt',
				),
			),
			'no radio'           => array( array( 'displayPostContent' => true ) ),
			'radio is not known' => array(
				array(
					'displayPostContent'      => true,
					'displayPostContentRadio' => 'summary',
				),
			),
		);
	}

	/**
	 * Tests that a post that contains the block is not rendered inside itself.
	 *
	 * @ticket 66600
	 */
	public function test_should_not_render_a_post_inside_itself() {
		$post_id = $this->create_post(
			'Recursive',
			'2020-03-15 10:00:00',
			array( 'post_content' => '<!-- wp:latest-posts {"displayPostContent":true,"displayPostContentRadio":"full_post"} /-->' )
		);

		$markup = $this->render(
			array(
				'displayPostContent'      => true,
				'displayPostContentRadio' => 'full_post',
			)
		);

		$this->assertSame( array( 'Recursive', 'Recursive' ), $this->get_titles( $markup ), 'The post should be listed in the block and once in its own content.' );
		$this->assertStringContainsString( '<div class="wp-block-latest-posts__post-full-content"></div>', $markup, 'The content of the post should be empty the second time.' );
		$this->assertSame( 1, substr_count( $markup, 'wp-block-latest-posts__post-full-content"></div>' ) );
		$this->assertNotSame( 0, $post_id );
	}

	/**
	 * Tests that a post can be rendered again after its full content was rendered.
	 *
	 * @ticket 66600
	 */
	public function test_should_render_the_full_content_of_a_post_again() {
		$this->create_post( 'A post', '2020-03-15 10:00:00', array( 'post_content' => 'The content.' ) );

		$attributes = array(
			'displayPostContent'      => true,
			'displayPostContentRadio' => 'full_post',
		);

		$this->assertStringContainsString( 'post-full-content">The content.</div>', $this->render( $attributes ) );
		$this->assertStringContainsString( 'post-full-content">The content.</div>', $this->render( $attributes ) );
	}

	/**
	 * Tests the layout classes.
	 *
	 * @ticket 66600
	 *
	 * @dataProvider data_layouts
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected classes beside the common ones.
	 */
	public function test_should_add_the_classes_of_the_layout( $attributes, $expected ) {
		$this->create_post( 'A post' );

		$classes = $this->get_classes( $this->render( $attributes ), 'ul' );
		$common  = array( 'wp-block-latest-posts', 'wp-block-latest-posts__list' );

		sort( $expected );

		$this->assertSame( $expected, array_values( array_diff( $classes, $common ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_layouts() {
		return array(
			'default'                         => array( array(), array() ),
			'grid layout'                     => array( array( 'layout' => array( 'type' => 'grid' ) ), array( 'is-grid' ) ),
			'grid with columns'               => array(
				array(
					'layout' => array(
						'type'        => 'grid',
						'columnCount' => 3,
					),
				),
				array( 'is-grid', 'columns-3' ),
			),
			'grid with the old columns'       => array(
				array(
					'layout'  => array( 'type' => 'grid' ),
					'columns' => 4,
				),
				array( 'is-grid', 'columns-4' ),
			),
			'grid with a minimum width'       => array(
				array(
					'layout' => array(
						'type'               => 'grid',
						'columnCount'        => 2,
						'minimumColumnWidth' => '12rem',
					),
				),
				array( 'is-grid', 'columns-2', 'has-native-responsive-grid' ),
			),
			'minimum width without columns'   => array(
				array(
					'layout' => array(
						'type'               => 'grid',
						'minimumColumnWidth' => '12rem',
					),
				),
				array( 'is-grid' ),
			),
			'default layout with columns'     => array(
				array(
					'layout' => array(
						'type'        => 'default',
						'columnCount' => 3,
					),
				),
				array(),
			),
			'old grid layout'                 => array( array( 'postLayout' => 'grid' ), array( 'is-grid' ) ),
			'old grid layout with columns'    => array(
				array(
					'postLayout' => 'grid',
					'columns'    => 2,
				),
				array( 'is-grid', 'columns-2' ),
			),
			'old list layout'                 => array(
				array(
					'postLayout' => 'list',
					'columns'    => 2,
				),
				array(),
			),
			'layout wins over the old layout' => array(
				array(
					'layout'     => array( 'type' => 'default' ),
					'postLayout' => 'grid',
				),
				array(),
			),
			'link color'                      => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#112233' ) ) ) ) ), array( 'has-link-color' ) ),
		);
	}

	/**
	 * Tests the list when there are no posts.
	 *
	 * @ticket 66600
	 */
	public function test_should_render_an_empty_list_without_posts() {
		$markup = $this->render();

		$this->assertStringStartsWith( '<ul ', $markup );
		$this->assertStringEndsWith( '></ul>', $markup );
		$this->assertStringNotContainsString( '<li>', $markup );
	}

	/**
	 * Tests that the global post is restored.
	 *
	 * @ticket 66600
	 */
	public function test_should_restore_the_global_post() {
		$outer_id = $this->create_post( 'Outer', '2019-01-01 10:00:00' );
		$this->create_post( 'Inner', '2021-01-01 10:00:00' );

		$GLOBALS['post'] = get_post( $outer_id );
		setup_postdata( $GLOBALS['post'] );

		$this->render( array( 'postsToShow' => 1 ) );

		$this->assertSame( $outer_id, $GLOBALS['post']->ID );
		$this->assertSame( $outer_id, get_the_ID() );
		$this->assertSame( $outer_id, $GLOBALS['id'], 'The post data should be set up again.' );
	}

	/**
	 * Tests that there is no global post to restore when there was none.
	 *
	 * @ticket 66600
	 */
	public function test_should_leave_no_global_post_when_there_was_none() {
		$this->create_post( 'A post' );

		unset( $GLOBALS['post'] );

		$this->render();

		$this->assertEmpty( $GLOBALS['post'] ?? null );
	}
}
