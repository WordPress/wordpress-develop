<?php
/**
 * Tests for the server-side rendering of the Latest Comments block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderLatestComments extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of the post of the comments.
	 *
	 * @var int
	 */
	private $post_id;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/latest-comments',
			'attrs'     => array(),
		);

		$this->post_id = self::factory()->post->create( array( 'post_title' => 'Hello post' ) );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Creates a comment.
	 *
	 * @param string $author Author of the comment.
	 * @param array  $args   Optional. The arguments of the comment that differ from the defaults.
	 * @return int The ID of the comment.
	 */
	private function create_comment( $author, $args = array() ) {
		return self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'      => $this->post_id,
					'comment_author'       => $author,
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_content'      => "$author says hello",
					'comment_date'         => '2020-03-15 10:00:00',
					'comment_date_gmt'     => '2020-03-15 10:00:00',
					'comment_approved'     => '1',
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
		return render_block_core_latest_comments(
			array_merge(
				array(
					'commentsToShow' => 5,
					'displayAvatar'  => true,
					'displayDate'    => true,
					'displayContent' => 'excerpt',
				),
				$attributes
			)
		);
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
	 * Returns the authors of the comments of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The authors.
	 */
	private function get_authors( $markup ) {
		preg_match_all( '/<(?:a|span) class="wp-block-latest-comments__comment-author"[^>]*>(.*?)<\/(?:a|span)>/', $markup, $matches );

		return $matches[1];
	}

	/**
	 * Tests the list of the comments.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_list_the_comments_from_the_newest() {
		$this->create_comment( 'Oldest', array( 'comment_date_gmt' => '2020-01-01 10:00:00' ) );
		$this->create_comment( 'Newest', array( 'comment_date_gmt' => '2021-01-01 10:00:00' ) );
		$this->create_comment( 'Middle', array( 'comment_date_gmt' => '2020-06-01 10:00:00' ) );

		$markup = $this->render();

		$this->assertStringStartsWith( '<ol ', $markup );
		$this->assertStringEndsWith( '</ol>', $markup );
		$this->assertSame( array( 'Newest', 'Middle', 'Oldest' ), $this->get_authors( $markup ) );
		$this->assertSame( 3, substr_count( $markup, '<li class="wp-block-latest-comments__comment">' ) );
	}

	/**
	 * Tests the number of comments.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_limit_the_number_of_comments() {
		$this->create_comment( 'Oldest', array( 'comment_date_gmt' => '2020-01-01 10:00:00' ) );
		$this->create_comment( 'Newest', array( 'comment_date_gmt' => '2021-01-01 10:00:00' ) );
		$this->create_comment( 'Middle', array( 'comment_date_gmt' => '2020-06-01 10:00:00' ) );

		$this->assertSame( array( 'Newest', 'Middle' ), $this->get_authors( $this->render( array( 'commentsToShow' => 2 ) ) ) );
	}

	/**
	 * Tests that only approved comments of published posts are listed.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_list_only_approved_comments_of_published_posts() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->create_comment( 'Approved' );
		$this->create_comment( 'Pending', array( 'comment_approved' => '0' ) );
		$this->create_comment( 'Spam', array( 'comment_approved' => 'spam' ) );
		$this->create_comment( 'On a draft', array( 'comment_post_ID' => $draft_id ) );

		$this->assertSame( array( 'Approved' ), $this->get_authors( $this->render() ) );
	}

	/**
	 * Tests the filter of the arguments of the comments.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_filter_the_arguments_of_the_comments() {
		$this->create_comment( 'Oldest', array( 'comment_date_gmt' => '2020-01-01 10:00:00' ) );
		$this->create_comment( 'Newest', array( 'comment_date_gmt' => '2021-01-01 10:00:00' ) );

		$received = null;

		add_filter(
			'widget_comments_args',
			static function ( $args, $instance ) use ( &$received ) {
				$received = array( $args, $instance );

				$args['number'] = 1;

				return $args;
			},
			10,
			2
		);

		$markup = $this->render( array( 'commentsToShow' => 4 ) );

		$this->assertSame(
			array(
				array(
					'number'      => 4,
					'status'      => 'approve',
					'post_status' => 'publish',
				),
				array(),
			),
			$received
		);
		$this->assertSame( array( 'Newest' ), $this->get_authors( $markup ) );
	}

	/**
	 * Tests the message when there are no comments.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_show_a_message_when_there_are_no_comments() {
		$markup = $this->render();

		$this->assertStringStartsWith( '<div ', $markup );
		$this->assertStringEndsWith( '>No comments to show.</div>', $markup );
		$this->assertContains( 'no-comments', $this->get_classes( $markup, 'div' ) );
	}

	/**
	 * Tests the classes of the wrapper.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 *
	 * @dataProvider data_wrapper_classes
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected classes beside the common ones.
	 */
	public function test_should_add_the_classes_to_the_wrapper( $attributes, $expected ) {
		$this->create_comment( 'Someone' );

		$classes = $this->get_classes( $this->render( $attributes ), 'ol' );
		$classes = array_values( array_diff( $classes, array( 'wp-block-latest-comments' ) ) );

		sort( $expected );

		$this->assertSame( $expected, $classes );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wrapper_classes() {
		return array(
			'defaults'     => array( array(), array( 'has-avatars', 'has-dates', 'has-excerpts' ) ),
			'no avatars'   => array( array( 'displayAvatar' => false ), array( 'has-dates', 'has-excerpts' ) ),
			'no dates'     => array( array( 'displayDate' => false ), array( 'has-avatars', 'has-excerpts' ) ),
			'no content'   => array( array( 'displayContent' => 'none' ), array( 'has-avatars', 'has-dates' ) ),
			'full content' => array( array( 'displayContent' => 'full' ), array( 'has-avatars', 'has-dates', 'has-excerpts' ) ),
			'nothing'      => array(
				array(
					'displayAvatar'  => false,
					'displayDate'    => false,
					'displayContent' => 'none',
				),
				array(),
			),
		);
	}

	/**
	 * Tests the avatar.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_render_the_avatar() {
		$this->create_comment( 'Someone', array( 'comment_author_email' => 'someone@example.org' ) );

		$markup = $this->render();
		$image  = $this->get_element( $markup, 'img' );

		$this->assertStringContainsString( '<li class="wp-block-latest-comments__comment"><img ', $markup );
		$this->assertContains( 'wp-block-latest-comments__comment-avatar', explode( ' ', $image['class'] ) );
		$this->assertSame( '48', $image['width'] );
		$this->assertStringContainsString( hash( 'sha256', 'someone@example.org' ), $image['src'] );
	}

	/**
	 * Tests that there is no avatar unless it is set, or when avatars are turned off.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_not_render_the_avatar_unless_it_is_set() {
		$this->create_comment( 'Someone' );

		$this->assertStringNotContainsString( '<img', $this->render( array( 'displayAvatar' => false ) ) );

		update_option( 'show_avatars', 0 );

		$markup = $this->render();

		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertStringContainsString( '<li class="wp-block-latest-comments__comment"><article>', $markup );
	}

	/**
	 * Tests the author without a website.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_render_the_author_without_a_link() {
		$this->create_comment( 'Someone' );

		$markup = $this->render();

		$this->assertStringContainsString( '<span class="wp-block-latest-comments__comment-author">Someone</span>', $markup );
		$this->assertNull(
			$this->get_element(
				$markup,
				array(
					'class_name' => 'wp-block-latest-comments__comment-author',
					'tag_name'   => 'a',
				)
			)
		);
	}

	/**
	 * Tests the author with a website.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_link_the_author_to_the_website() {
		$this->create_comment( 'Someone', array( 'comment_author_url' => 'https://example.org/someone/?a=1&b=2' ) );

		$markup = $this->render();

		$this->assertStringContainsString( '<a class="wp-block-latest-comments__comment-author" href="https://example.org/someone/?a=1&#038;b=2">Someone</a>', $markup );
	}

	/**
	 * Tests the author that is a user without a website.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_link_a_user_to_the_author_archive() {
		$user_id = self::factory()->user->create();

		$this->create_comment(
			'A user',
			array(
				'user_id'            => $user_id,
				'comment_author_url' => '',
			)
		);

		$this->assertStringContainsString(
			'<a class="wp-block-latest-comments__comment-author" href="' . esc_url( get_author_posts_url( $user_id ) ) . '">A user</a>',
			$this->render()
		);
	}

	/**
	 * Tests that the website wins over the author archive.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_prefer_the_website_to_the_author_archive() {
		$user_id = self::factory()->user->create();

		$this->create_comment(
			'A user',
			array(
				'user_id'            => $user_id,
				'comment_author_url' => 'https://example.org/site/',
			)
		);

		$markup = $this->render();

		$this->assertStringContainsString( 'href="https://example.org/site/">A user</a>', $markup );
		$this->assertStringNotContainsString( get_author_posts_url( $user_id ), $markup );
	}

	/**
	 * Tests the link to the comment and the title of the post.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_link_the_comment_with_the_title_of_the_post() {
		$comment_id = $this->create_comment( 'Someone' );

		$markup = $this->render();
		$link   = $this->get_element( $markup, array( 'class_name' => 'wp-block-latest-comments__comment-link' ) );

		$this->assertSame( get_comment_link( $comment_id ), $link['href'] );
		$this->assertStringContainsString( '">Hello post</a>', $markup );
		$this->assertStringContainsString( 'Someone</span> on <a class="wp-block-latest-comments__comment-link"', $markup );
	}

	/**
	 * Tests the title of a post without a title.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_use_the_fallback_title_for_a_post_without_a_title() {
		$this->post_id = self::factory()->post->create( array( 'post_title' => '' ) );

		$this->create_comment( 'Someone' );

		$this->assertStringContainsString( '">(no title)</a>', $this->render() );
	}

	/**
	 * Tests the date.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_render_the_date() {
		$comment_id = $this->create_comment( 'Someone' );

		$markup = $this->render();
		$time   = $this->get_element( $markup, 'time' );

		$this->assertSame( get_comment_date( 'c', $comment_id ), $time['datetime'] );
		$this->assertSame( 'wp-block-latest-comments__comment-date', $time['class'] );
		$this->assertStringContainsString( '>March 15, 2020</time></footer>', $markup );

		update_option( 'date_format', 'Y/m/d' );

		$this->assertStringContainsString( '>2020/03/15</time>', $this->render() );
		$this->assertStringNotContainsString( '<time', $this->render( array( 'displayDate' => false ) ) );
	}

	/**
	 * Tests the excerpt of the content.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_render_the_excerpt_of_the_comment() {
		$this->create_comment( 'Someone', array( 'comment_content' => 'One two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty twenty-one twenty-two' ) );

		$markup = $this->render();

		$this->assertMatchesRegularExpression( '/<div class="wp-block-latest-comments__comment-excerpt"><p>One two .* twenty&hellip;<\/p>\s*<\/div><\/article><\/li>/s', $markup );
		$this->assertStringNotContainsString( 'twenty-one', $markup );
	}

	/**
	 * Tests the full content.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_render_the_full_comment() {
		$this->create_comment( 'Someone', array( 'comment_content' => 'One two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty twenty-one twenty-two' ) );

		$markup = $this->render( array( 'displayContent' => 'full' ) );

		$this->assertStringContainsString( 'twenty twenty-one twenty-two</p>', $markup );
		$this->assertStringNotContainsString( '&hellip;', $markup );
	}

	/**
	 * Tests the full content of a comment on a post that is protected by a password.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_hide_the_full_comment_of_a_protected_post() {
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Protected',
				'post_password' => 'secret',
			)
		);

		$this->create_comment( 'Someone', array( 'comment_content' => 'A private remark' ) );

		$markup = $this->render( array( 'displayContent' => 'full' ) );

		$this->assertStringContainsString( '<p>Password protected</p>', $markup );
		$this->assertStringNotContainsString( 'A private remark', $markup );
	}

	/**
	 * Tests that there is no content unless it is set.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_not_render_the_content_when_it_is_off() {
		$this->create_comment( 'Someone' );

		$this->assertStringNotContainsString( 'comment-excerpt', $this->render( array( 'displayContent' => 'none' ) ) );
		$this->assertStringEndsWith( '</footer></article></li></ol>', $this->render( array( 'displayContent' => 'none' ) ) );
	}

	/**
	 * Tests that the content is the excerpt when the attribute is missing.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_show_the_excerpt_when_the_content_attribute_is_missing() {
		$this->create_comment( 'Someone', array( 'comment_content' => 'Remark' ) );

		$markup = render_block_core_latest_comments(
			array(
				'commentsToShow' => 5,
				'displayAvatar'  => false,
				'displayDate'    => false,
			)
		);

		$this->assertStringContainsString( 'comment-excerpt"><p>Remark</p>', $markup );
		$this->assertContains( 'has-excerpts', $this->get_classes( $markup, 'ol' ) );
	}

	/**
	 * Tests the old attribute for the excerpt.
	 *
	 * @ticket 66595
	 *
	 * @covers ::render_block_core_latest_comments
	 */
	public function test_should_use_the_old_excerpt_attribute() {
		$this->create_comment( 'Someone', array( 'comment_content' => 'Remark' ) );

		$on  = $this->render(
			array(
				'displayExcerpt' => true,
				'displayContent' => 'none',
			)
		);
		$off = $this->render(
			array(
				'displayExcerpt' => false,
				'displayContent' => 'full',
			)
		);

		$this->assertStringContainsString( 'comment-excerpt"><p>Remark</p>', $on );
		$this->assertContains( 'has-excerpts', $this->get_classes( $on, 'ol' ) );
		$this->assertStringNotContainsString( 'comment-excerpt', $off );
		$this->assertNotContains( 'has-excerpts', $this->get_classes( $off, 'ol' ) );
	}

	/**
	 * Tests the title of a post.
	 *
	 * @ticket 66595
	 *
	 * @covers ::wp_latest_comments_draft_or_post_title
	 */
	public function test_draft_or_post_title() {
		$titled   = self::factory()->post->create( array( 'post_title' => 'A title' ) );
		$untitled = self::factory()->post->create( array( 'post_title' => '' ) );

		$this->assertSame( 'A title', wp_latest_comments_draft_or_post_title( $titled ) );
		$this->assertSame( 'A title', wp_latest_comments_draft_or_post_title( get_post( $titled ) ) );
		$this->assertSame( '(no title)', wp_latest_comments_draft_or_post_title( $untitled ) );
		$this->assertSame( '(no title)', wp_latest_comments_draft_or_post_title( 99999999 ) );
	}

	/**
	 * Tests the title of the global post.
	 *
	 * @ticket 66595
	 *
	 * @covers ::wp_latest_comments_draft_or_post_title
	 */
	public function test_draft_or_post_title_of_the_global_post() {
		$GLOBALS['post'] = get_post( $this->post_id );

		$this->assertSame( 'Hello post', wp_latest_comments_draft_or_post_title() );
	}
}
