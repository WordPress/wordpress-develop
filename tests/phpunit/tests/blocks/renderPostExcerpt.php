<?php
/**
 * Tests for the server-side rendering of the Post Excerpt block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_post_excerpt
 */
class Tests_Blocks_RenderPostExcerpt extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * ID of a post without an excerpt, with twelve words.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post with an excerpt.
	 *
	 * @var int
	 */
	private static $post_with_excerpt_id;

	/**
	 * ID of a post without an excerpt, with 120 words.
	 *
	 * @var int
	 */
	private static $long_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id              = $factory->post->create(
			array(
				'post_excerpt' => '',
				'post_content' => 'One two three four five six seven eight nine ten eleven twelve.',
			)
		);
		self::$long_post_id         = $factory->post->create(
			array(
				'post_excerpt' => '',
				'post_content' => implode(
					' ',
					array_map(
						static function ( $number ) {
							return 'word' . $number;
						},
						range( 1, 120 )
					)
				),
			)
		);
		self::$post_with_excerpt_id = $factory->post->create(
			array(
				'post_excerpt' => 'A written summary of the post that has more than five words.',
				'post_content' => 'Different content.',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'timezone_string', 'UTC' );
		update_option( 'date_format', 'Y-m-d' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block, with the post by default.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-excerpt',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		// The block has a default for the length, and the function reads it directly.
		$attributes = array_merge( array( 'excerptLength' => 55 ), $attributes );

		return render_block_core_post_excerpt( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests the excerpt that is made from the content.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_excerpt_from_the_content() {
		$this->assertSame(
			'<div ><p class="wp-block-post-excerpt__excerpt">One two three four five six seven eight nine ten eleven twelve.</p></div>',
			$this->render()
		);
	}

	/**
	 * Tests the excerpt of the post.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_excerpt_that_was_written() {
		$this->assertSame(
			'<div ><p class="wp-block-post-excerpt__excerpt">A written summary of the post that has more than five words.</p></div>',
			$this->render( array(), array( 'postId' => self::$post_with_excerpt_id ) )
		);
	}

	/**
	 * Tests the length of the excerpt.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_lengths
	 *
	 * @param int    $length   Length of the excerpt.
	 * @param string $expected Expected text of the excerpt.
	 */
	public function test_should_trim_the_excerpt_to_the_length( $length, $expected ) {
		$this->assertStringContainsString( '>' . $expected . '</p></div>', $this->render( array( 'excerptLength' => $length ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_lengths() {
		return array(
			'five words'               => array( 5, 'One two three four five&hellip;' ),
			'three words'              => array( 3, 'One two three&hellip;' ),
			'more words than the text' => array( 50, 'One two three four five six seven eight nine ten eleven twelve.' ),
		);
	}

	/**
	 * Tests that the length is also applied to a written excerpt.
	 *
	 * @ticket 66543
	 */
	public function test_should_trim_a_written_excerpt_to_the_length() {
		$this->assertStringContainsString(
			'>A written summary of&hellip;</p>',
			$this->render( array( 'excerptLength' => 4 ), array( 'postId' => self::$post_with_excerpt_id ) )
		);
	}

	/**
	 * Tests the link to read more, on a new line.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_more_link_on_a_new_line_by_default() {
		$link = '<a class="wp-block-post-excerpt__more-link" href="' . esc_url( get_permalink( self::$post_id ) ) . '">Read more</a>';

		$this->assertSame(
			'<div ><p class="wp-block-post-excerpt__excerpt">One two three&hellip;</p><p class="wp-block-post-excerpt__more-text">' . $link . '</p></div>',
			$this->render(
				array(
					'excerptLength' => 3,
					'moreText'      => 'Read more',
				)
			)
		);
	}

	/**
	 * Tests the link to read more, in the same paragraph.
	 *
	 * @ticket 66543
	 */
	public function test_should_render_the_more_link_in_the_same_paragraph_when_it_is_requested() {
		$link = '<a class="wp-block-post-excerpt__more-link" href="' . esc_url( get_permalink( self::$post_id ) ) . '">Read more</a>';

		$this->assertSame(
			'<div ><p class="wp-block-post-excerpt__excerpt">One two three&hellip; ' . $link . '</p></div>',
			$this->render(
				array(
					'excerptLength'     => 3,
					'moreText'          => 'Read more',
					'showMoreOnNewLine' => false,
				)
			)
		);
	}

	/**
	 * Tests the link to read more of a post without an excerpt.
	 *
	 * @ticket 66543
	 */
	public function test_should_not_add_a_space_before_the_more_link_for_an_empty_excerpt() {
		$post_id = self::factory()->post->create(
			array(
				'post_excerpt' => '',
				'post_content' => '',
			)
		);

		$output = $this->render(
			array(
				'moreText'          => 'Read more',
				'showMoreOnNewLine' => false,
			),
			array( 'postId' => $post_id )
		);

		$this->assertStringContainsString( '<p class="wp-block-post-excerpt__excerpt"><a class="wp-block-post-excerpt__more-link"', $output );
	}

	/**
	 * Tests the text of the link to read more.
	 *
	 * @ticket 66543
	 */
	public function test_should_clean_the_text_of_the_more_link() {
		$output = $this->render(
			array(
				'excerptLength' => 3,
				'moreText'      => 'Read <strong>more</strong><script>alert( 1 );</script>',
			)
		);

		// Only the tag of the script is checked, because what happens to its text depends on the filter of the content.
		$this->assertStringContainsString( '">Read <strong>more</strong>', $output, 'The allowed markup should be kept.' );
		$this->assertStringNotContainsString( '<script', $output, 'The script should be removed.' );
		$this->assertStringEndsWith( '</a></p></div>', $output, 'The link should be closed.' );
	}

	/**
	 * Tests that the URL of the link to read more is escaped.
	 *
	 * @ticket 66543
	 */
	public function test_should_escape_the_url_of_the_more_link() {
		add_filter(
			'post_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( 'href="https://example.org/?a=1&#038;b=2">Read more</a>', $this->render( array( 'moreText' => 'Read more' ) ) );
	}

	/**
	 * Tests that the filter of the end of the excerpt is used without a link.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_empty_more_texts
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_keep_the_default_end_of_the_excerpt_without_a_more_link( $attributes ) {
		add_filter(
			'excerpt_more',
			static function () {
				return ' [more]';
			}
		);

		$output = $this->render(
			array_merge( array( 'excerptLength' => 150 ), $attributes ),
			array( 'postId' => self::$long_post_id )
		);

		$this->assertStringContainsString( ' word100 word101 [more]</p>', $output );
		$this->assertStringNotContainsString( 'word102', $output, 'The excerpt should be trimmed to the length that the block uses.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_more_texts() {
		return array(
			'no text'       => array( array() ),
			'an empty text' => array( array( 'moreText' => '' ) ),
		);
	}

	/**
	 * Tests that the end of the excerpt is not added when there is a link.
	 *
	 * @ticket 66543
	 */
	public function test_should_replace_the_end_of_the_excerpt_with_the_more_link() {
		add_filter(
			'excerpt_more',
			static function () {
				return ' [more]';
			}
		);

		$output = $this->render(
			array(
				'excerptLength' => 150,
				'moreText'      => 'Read more',
			),
			array( 'postId' => self::$long_post_id )
		);

		$this->assertStringContainsString( ' word100 word101</p>', $output, 'The excerpt should have no ending.' );
		$this->assertStringNotContainsString( '[more]', $output );
	}

	/**
	 * Tests that the filter of the end of the excerpt is removed after the block is rendered.
	 *
	 * @ticket 66543
	 */
	public function test_should_remove_the_filters_after_the_block_is_rendered() {
		global $wp_filter;

		$count = static function ( $hook ) use ( &$wp_filter ) {
			$total = 0;

			if ( isset( $wp_filter[ $hook ] ) ) {
				foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
					$total += count( $callbacks );
				}
			}

			return $total;
		};

		$more_before   = $count( 'excerpt_more' );
		$length_before = $count( 'excerpt_length' );

		$this->render( array( 'moreText' => 'Read more' ) );

		$this->assertSame( $more_before, $count( 'excerpt_more' ), 'The filter of the end of the excerpt should be removed.' );
		$this->assertSame( $length_before, $count( 'excerpt_length' ), 'The filter of the length should be removed.' );
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66543
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                  => array( array(), '<div >' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
			'a link color'                => array( array( 'style' => $link_color ), '<div class="has-link-color">' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'<div class="has-text-align-right has-link-color">',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '<div >' ),
		);
	}

	/**
	 * Tests the length that is used for the excerpt in the editor.
	 *
	 * @ticket 66543
	 *
	 * @covers ::block_core_post_excerpt_excerpt_length
	 */
	public function test_should_use_a_length_that_is_long_in_the_editor() {
		$this->assertSame( 101, block_core_post_excerpt_excerpt_length() );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66543
	 *
	 * @covers ::register_block_core_post_excerpt
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-excerpt' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-excerpt' );
		}

		register_block_core_post_excerpt();
		$block_type = $registry->get_registered( 'core/post-excerpt' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-excerpt' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_excerpt', $block_type->render_callback );
	}
}
