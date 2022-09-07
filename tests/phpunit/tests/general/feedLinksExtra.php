<?php
/**
 * Test feed_links_extra().
 *
 * @ticket 54713
 *
 * @group general
 * @group template
 *
 * @covers ::feed_links_extra
 */
class Tests_General_FeedLinksExtra extends WP_UnitTestCase {
	/**
	 * Author ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Category ID.
	 *
	 * @var int
	 */
	protected static $category_id;

	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	protected static $tag_id;

	/**
	 * Taxonomy ID.
	 *
	 * @var int
	 */
	protected static $tax_id;

	/**
	 * Post Type.
	 *
	 * @var string
	 */
	protected static $post_type;

	/**
	 * The ID of a post with no comment.
	 *
	 * @var int
	 */
	protected static $post_no_comment_id;

	/**
	 * The ID of a post with a comment.
	 *
	 * @var int
	 */
	protected static $post_with_comment_id;

	/**
	 * The ID of a post with a custom post type.
	 *
	 * @var int
	 */
	protected static $post_with_cpt_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Author.
		self::$author_id = $factory->user->create(
			array(
				'user_login' => 'author_feed_links_extra',
				'role'       => 'administrator',
			)
		);

		// Category.
		self::$category_id = $factory->category->create(
			array( 'name' => 'cat_feed_links_extra' )
		);

		// Tag.
		self::$tag_id = $factory->tag->create(
			array( 'name' => 'tag_feed_links_extra' )
		);

		// Taxonomy.
		self::$tax_id = 'tax_feed_links_extra';

		// Post type.
		self::$post_type = 'cpt_feed_links_extra';

		register_taxonomy(
			self::$tax_id,
			self::$post_type,
			array(
				'labels' => array(
					'name'          => 'Taxonomy Terms',
					'singular_name' => 'Taxonomy Term',
				),
			)
		);

		register_post_type(
			self::$post_type,
			array(
				'public'      => true,
				'has_archive' => true,
				'taxonomies'  => array( self::$tax_id ),
				'labels'      => array( 'name' => 'CPT for feed_links_extra()' ),
			)
		);

		// Posts.
		self::$post_no_comment_id = $factory->post->create(
			array( 'post_title' => 'Post with no comments' )
		);

		self::$post_with_comment_id = $factory->post->create(
			array( 'post_title' => 'Post with a comment' )
		);

		$factory->comment->create(
			array(
				'comment_author'  => self::$author_id,
				'comment_post_ID' => self::$post_with_comment_id,
			)
		);

		self::$post_with_cpt_id = $factory->post->create(
			array(
				'post_title' => 'Post with a custom post type',
				'post_type'  => self::$post_type,
			)
		);

		wp_set_object_terms( self::$post_with_cpt_id, 'tax_term', self::$tax_id );
	}

	public function set_up() {
		parent::set_up();

		register_taxonomy(
			self::$tax_id,
			self::$post_type,
			array(
				'labels' => array(
					'name'          => 'Taxonomy Terms',
					'singular_name' => 'Taxonomy Term',
				),
			)
		);

		register_post_type(
			self::$post_type,
			array(
				'public'      => true,
				'has_archive' => true,
				'taxonomies'  => array( self::$tax_id ),
				'labels'      => array( 'name' => 'CPT for feed_links_extra()' ),
			)
		);
	}

	/**
	 * @dataProvider data_feed_links_extra
	 * @ticket 54713
	 *
	 * @param string $title     The expected title.
	 * @param string $type      The name of the test class property containing the object ID.
	 * @param array  $args {
	 *        Optional arguments. Default empty.
	 *
	 *        @type string $separator     The separator between blog name and feed type.
	 *        @type string $singletitle   The title of the comments feed.
	 *        @type string $cattitle      The title of the category feed.
	 *        @type string $tagtitle      The title of the tag feed.
	 *        @type string $taxtitle      The title of the taxonomy feed.
	 *        @type string $authortitle   The title of the author feed.
	 *        @type string $searchtitle   The title of the search feed.
	 *        @type string $posttypetitle The title of the post type feed.
	 * }
	 */
	public function test_feed_links_extra( $title, $type, array $args = array() ) {
		$permalink = $this->helper_get_the_permalink( $type );
		$this->go_to( $permalink );

		$expected = '';

		if ( '' !== $title ) {
			if ( 'post_type' === $type || 'search' === $type ) {
				$feed_link = $permalink . '&#038;feed=rss2';
			} else {
				$feed_link = str_replace( '?', '?feed=rss2&#038;', $permalink );
			}

			$expected = sprintf(
				'<link rel="alternate" type="application/rss+xml" title="%s" href="%s" />' . "\n",
				esc_attr( $title ),
				esc_url( $feed_link )
			);
		}

		$this->assertSame( $expected, get_echo( 'feed_links_extra', array( $args ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_feed_links_extra() {
		return array(
			'a post with a comment'                        => array(
				'title' => 'Test Blog &raquo; Post with a comment Comments Feed',
				'type'  => 'post_with_comment',
			),
			'a post with a comment and a custom separator' => array(
				'title' => 'Test Blog // Post with a comment Comments Feed',
				'type'  => 'post_with_comment',
				'args'  => array(
					'separator' => '//',
				),
			),
			'a post with a comment and a custom title'     => array(
				'title' => 'Custom Title for Singular Feed',
				'type'  => 'post_with_comment',
				'args'  => array(
					'singletitle' => 'Custom Title for Singular Feed',
				),
			),
			'a post with a comment, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Singular Feed',
				'type'  => 'post_with_comment',
				'args'  => array(
					'separator'   => '//',
					'singletitle' => '%1$s %2$s Custom Title for Singular Feed',
				),
			),
			'a custom post type'                           => array(
				'title' => 'Test Blog &raquo; CPT for feed_links_extra() Feed',
				'type'  => 'post_type',
			),
			'a custom post type and a custom separator'    => array(
				'title' => 'Test Blog // CPT for feed_links_extra() Feed',
				'type'  => 'post_type',
				'args'  => array(
					'separator' => '//',
				),
			),
			'a custom post type and a custom title'        => array(
				'title' => 'Custom Title for CPT Feed',
				'type'  => 'post_type',
				'args'  => array(
					'posttypetitle' => 'Custom Title for CPT Feed',
				),
			),
			'a custom post type, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for CPT Feed',
				'type'  => 'post_type',
				'args'  => array(
					'separator'     => '//',
					'posttypetitle' => '%1$s %2$s Custom Title for CPT Feed',
				),
			),
			'a category'                                   => array(
				'title' => 'Test Blog &raquo; cat_feed_links_extra Category Feed',
				'type'  => 'category',
			),
			'a category and a custom separator'            => array(
				'title' => 'Test Blog // cat_feed_links_extra Category Feed',
				'type'  => 'category',
				'args'  => array(
					'separator' => '//',
				),
			),
			'a category and a custom title'                => array(
				'title' => 'Custom Title for Category Feed',
				'type'  => 'category',
				'args'  => array(
					'cattitle' => 'Custom Title for Category Feed',
				),
			),
			'a category, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Category Feed',
				'type'  => 'category',
				'args'  => array(
					'separator' => '//',
					'cattitle'  => '%1$s %2$s Custom Title for Category Feed',
				),
			),
			'a tag'                                        => array(
				'title' => 'Test Blog &raquo; tag_feed_links_extra Tag Feed',
				'type'  => 'tag',
			),
			'a tag and a custom separator'                 => array(
				'title' => 'Test Blog // tag_feed_links_extra Tag Feed',
				'type'  => 'tag',
				'args'  => array(
					'separator' => '//',
				),
			),
			'a tag and a custom title'                     => array(
				'title' => 'Custom Title for Tag Feed',
				'type'  => 'tag',
				'args'  => array(
					'tagtitle' => 'Custom Title for Tag Feed',
				),
			),
			'a tag, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Tag Feed',
				'type'  => 'tag',
				'args'  => array(
					'separator' => '//',
					'tagtitle'  => '%1$s %2$s Custom Title for Tag Feed',
				),
			),
			'a taxonomy'                                   => array(
				'title' => 'Test Blog &raquo; tax_term Taxonomy Term Feed',
				'type'  => 'tax',
			),
			'a taxonomy and a custom separator'            => array(
				'title' => 'Test Blog // tax_term Taxonomy Term Feed',
				'type'  => 'tax',
				'args'  => array(
					'separator' => '//',
				),
			),
			'a taxonomy and a custom title'                => array(
				'title' => 'Custom Title for Taxonomy Feed',
				'type'  => 'tax',
				'args'  => array(
					'taxtitle' => 'Custom Title for Taxonomy Feed',
				),
			),
			'a taxonomy, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Taxonomy Feed',
				'type'  => 'tax',
				'args'  => array(
					'separator' => '//',
					'taxtitle'  => '%1$s %2$s Custom Title for Taxonomy Feed',
				),
			),
			'an author'                                    => array(
				'title' => 'Test Blog &raquo; Posts by author_feed_links_extra Feed',
				'type'  => 'author',
			),
			'an author and a custom separator'             => array(
				'title' => 'Test Blog // Posts by author_feed_links_extra Feed',
				'type'  => 'author',
				'args'  => array(
					'separator' => '//',
				),
			),
			'an author and a custom title'                 => array(
				'title' => 'Custom Title for Author Feed',
				'type'  => 'author',
				'args'  => array(
					'authortitle' => 'Custom Title for Author Feed',
				),
			),
			'an author, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Author Feed',
				'type'  => 'author',
				'args'  => array(
					'separator'   => '//',
					'authortitle' => '%1$s %2$s Custom Title for Author Feed',
				),
			),
			'search results'                               => array(
				'title' => 'Test Blog &raquo; Search Results for &#8220;Search&#8221; Feed',
				'type'  => 'search',
			),
			'search results and a custom separator'        => array(
				'title' => 'Test Blog // Search Results for &#8220;Search&#8221; Feed',
				'type'  => 'search',
				'args'  => array(
					'separator' => '//',
				),
			),
			'search results and a custom title'            => array(
				'title' => 'Custom Title for Search Feed',
				'type'  => 'search',
				'args'  => array(
					'searchtitle' => 'Custom Title for Search Feed',
				),
			),
			'search results, a custom separator and a custom title' => array(
				'title' => 'Test Blog // Custom Title for Search Feed',
				'type'  => 'search',
				'args'  => array(
					'separator'   => '//',
					'searchtitle' => '%1$s %2$s Custom Title for Search Feed',
				),
			),
		);
	}

	/**
	 * Helper function to get the permalink based on type.
	 *
	 * @ticket 54713
	 *
	 * @param string $type The name of the test class property containing the object ID.
	 * @return string The permalink.
	 */
	private function helper_get_the_permalink( $type ) {
		if ( 'category' === $type || 'tag' === $type ) {
			return get_term_link( self::${$type . '_id'} );
		}

		if ( 'tax' === $type ) {
			return get_term_link( 'tax_term', self::$tax_id );
		}

		if ( 'post_type' === $type ) {
			return get_post_type_archive_link( self::$post_type );
		}

		if ( 'author' === $type ) {
			return get_author_posts_url( self::$author_id );
		}

		if ( 'search' === $type ) {
			return home_url( '?s=Search' );
		}

		return get_the_permalink( self::${$type . '_id'} );
	}

	/**
	 * @ticket 54713
	 */
	public function test_feed_links_extra_should_respect_comments_open() {
		add_filter( 'comments_open', '__return_true' );
		add_filter( 'pings_open', '__return_false' );

		$this->go_to( get_the_permalink( self::$post_no_comment_id ) );

		$expected  = '<link rel="alternate" type="application/rss+xml"';
		$expected .= ' title="Test Blog &raquo; Post with no comments Comments Feed"';
		$expected .= ' href="http://example.org/?feed=rss2&#038;p=' . self::$post_no_comment_id . '" />' . "\n";
		$this->assertSame( $expected, get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @ticket 54713
	 */
	public function test_feed_links_extra_should_respect_pings_open() {
		add_filter( 'pings_open', '__return_true' );
		add_filter( 'comments_open', '__return_false' );

		$this->go_to( get_the_permalink( self::$post_no_comment_id ) );

		$expected  = '<link rel="alternate" type="application/rss+xml"';
		$expected .= ' title="Test Blog &raquo; Post with no comments Comments Feed"';
		$expected .= ' href="http://example.org/?feed=rss2&#038;p=' . self::$post_no_comment_id . '" />' . "\n";
		$this->assertSame( $expected, get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @ticket 54713
	 */
	public function test_feed_links_extra_should_respect_post_comment_count() {
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'comments_open', '__return_false' );

		$this->go_to( get_the_permalink( self::$post_with_comment_id ) );

		$expected  = '<link rel="alternate" type="application/rss+xml"';
		$expected .= ' title="Test Blog &raquo; Post with a comment Comments Feed"';
		$expected .= ' href="http://example.org/?feed=rss2&#038;p=' . self::$post_with_comment_id . '" />' . "\n";
		$this->assertSame( $expected, get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @ticket 54713
	 */
	public function test_feed_links_extra_should_return_empty_when_comments_and_pings_are_closed_and_post_has_no_comments() {
		add_filter( 'comments_open', '__return_false' );
		add_filter( 'pings_open', '__return_false' );

		$this->go_to( get_the_permalink( self::$post_no_comment_id ) );
		$this->assertEmpty( get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @ticket 54713
	 */
	public function test_feed_links_extra_should_respect_feed_type() {
		add_filter(
			'default_feed',
			static function() {
				return 'foo';
			}
		);

		add_filter(
			'feed_content_type',
			static function() {
				return 'testing/foo';
			}
		);

		$this->go_to( get_the_permalink( self::$post_with_comment_id ) );

		$expected  = '<link rel="alternate" type="testing/foo"';
		$expected .= ' title="Test Blog &raquo; Post with a comment Comments Feed"';
		$expected .= ' href="http://example.org/?feed=foo&#038;p=' . self::$post_with_comment_id . '" />' . "\n";
		$this->assertSame( $expected, get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @ticket 54703
	 */
	public function test_feed_links_extra_should_output_nothing_when_show_comments_feed_filter_returns_false() {
		add_filter( 'feed_links_show_comments_feed', '__return_false' );

		$this->go_to( get_the_permalink( self::$post_with_comment_id ) );
		$this->assertEmpty( get_echo( 'feed_links_extra' ) );
	}

	/**
	 * @dataProvider data_feed_links_extra_should_output_nothing_when_post_comments_feed_link_is_falsy
	 *
	 * @ticket 54703
	 *
	 * @param string $callback The callback to use for the 'post_comments_feed_link' filter.
	 */
	public function test_feed_links_extra_should_output_nothing_when_post_comments_feed_link_is_falsy( $callback ) {
		add_filter( 'post_comments_feed_link', $callback );

		$this->go_to( get_the_permalink( self::$post_with_comment_id ) );
		$this->assertEmpty( get_echo( 'feed_links_extra' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_feed_links_extra_should_output_nothing_when_post_comments_feed_link_is_falsy() {
		return array(
			'empty string' => array( 'callback' => '__return_empty_string' ),
			'empty array'  => array( 'callback' => '__return_empty_array' ),
			'zero int'     => array( 'callback' => '__return_zero' ),
			'zero float'   => array( 'callback' => array( $this, 'cb_return_zero_float' ) ),
			'zero string'  => array( 'callback' => array( $this, 'cb_return_zero_string' ) ),
			'null'         => array( 'callback' => '__return_null' ),
			'false'        => array( 'callback' => '__return_false' ),
		);
	}

	/**
	 * Callback that returns 0.0.
	 *
	 * @return float 0.0.
	 */
	public function cb_return_zero_float() {
		return 0.0;
	}

	/**
	 * Callback that returns '0'.
	 *
	 * @return string '0'.
	 */
	public function cb_return_zero_string() {
		return '0';
	}

	/**
	 * @ticket 54703
	 */
	public function test_feed_links_extra_should_output_the_comments_feed_link_when_show_comments_feed_filter_returns_true() {
		add_filter( 'feed_links_show_comments_feed', '__return_true' );

		$this->go_to( get_the_permalink( self::$post_with_comment_id ) );
		$this->assertNotEmpty( get_echo( 'feed_links_extra' ) );
	}
}
