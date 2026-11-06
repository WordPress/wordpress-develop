<?php
/**
 * Tests for the server-side rendering of the Post Navigation Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group post
 */
class Tests_Blocks_RenderPostNavigationLink extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * IDs of three posts in a row, oldest first. The two oldest are in a category.
	 *
	 * @var int[]
	 */
	private static $posts = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$category = $factory->category->create( array( 'name' => 'Shared' ) );

		foreach ( array( 'Older post', 'Middle post', 'Newer post' ) as $index => $title ) {
			self::$posts[] = $factory->post->create(
				array(
					'post_title' => $title,
					'post_date'  => '2023-02-0' . ( $index + 1 ) . ' 10:00:00',
				)
			);
		}

		wp_set_object_terms( self::$posts[0], array( $category ), 'category' );
		wp_set_object_terms( self::$posts[1], array( $category ), 'category' );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Goes to the middle post.
	 */
	private function go_to_middle_post() {
		$this->go_to( get_permalink( self::$posts[1] ) );
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array() ) {
		// The block has a default for this attribute, and the function reads it directly.
		$attributes = array_merge( array( 'linkLabel' => false ), $attributes );

		return render_block_core_post_navigation_link( $attributes, '' );
	}

	/**
	 * Tests that nothing is rendered outside a single post.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_render_nothing_outside_a_single_post() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that nothing is rendered for a type that is not valid.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 *
	 * @dataProvider data_types_that_are_not_valid
	 *
	 * @param mixed $type Type of the block.
	 */
	public function test_should_render_nothing_for_a_type_that_is_not_valid( $type ) {
		$this->go_to_middle_post();

		$this->assertSame( '', $this->render( array( 'type' => $type ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_types_that_are_not_valid() {
		return array(
			'another type'  => array( 'first' ),
			'an empty type' => array( '' ),
			'in capitals'   => array( 'NEXT' ),
		);
	}

	/**
	 * Tests the link to the next and to the previous post.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 *
	 * @dataProvider data_types
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param int    $index      Index of the post that is linked.
	 * @param string $rel        Expected rel of the link.
	 * @param string $label      Expected text of the link.
	 * @param string $class_name Expected class of the wrapper.
	 */
	public function test_should_link_to_the_adjacent_post( $attributes, $index, $rel, $label, $class_name ) {
		$this->go_to_middle_post();

		$this->assertSame(
			'<div class="' . $class_name . '"><a href="' . get_permalink( self::$posts[ $index ] ) . '" rel="' . $rel . '">' . $label . '</a></div>',
			$this->render( $attributes )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_types() {
		return array(
			'the default is the next one' => array( array(), 2, 'next', 'Next', 'post-navigation-link-next' ),
			'the next one'                => array( array( 'type' => 'next' ), 2, 'next', 'Next', 'post-navigation-link-next' ),
			'the previous one'            => array( array( 'type' => 'previous' ), 0, 'prev', 'Previous', 'post-navigation-link-previous' ),
		);
	}

	/**
	 * Tests that the wrapper is rendered without a link when there is no adjacent post.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_render_an_empty_wrapper_without_an_adjacent_post() {
		$this->go_to( get_permalink( self::$posts[2] ) );

		$this->assertSame( '<div class="post-navigation-link-next"></div>', $this->render( array( 'type' => 'next' ) ) );
	}

	/**
	 * Tests that the styles are not added to an empty wrapper.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_not_add_the_styles_to_an_empty_wrapper() {
		$this->go_to( get_permalink( self::$posts[2] ) );

		$this->assertSame(
			'<div class="post-navigation-link-next"></div>',
			$this->render(
				array(
					'type'  => 'next',
					'style' => array( 'border' => array( 'radius' => '5px' ) ),
				)
			)
		);
	}

	/**
	 * Tests the label of the link.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected content of the wrapper.
	 */
	public function test_should_use_the_label_and_the_title_in_the_link( $attributes, $expected ) {
		$this->go_to_middle_post();

		$url = get_permalink( self::$posts[2] );

		$this->assertSame(
			'<div class="post-navigation-link-next">' . str_replace( '{url}', $url, $expected ) . '</div>',
			$this->render( $attributes )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'a label'                                  => array( array( 'label' => 'Go on' ), '<a href="{url}" rel="next">Go on</a>' ),
			'a label with allowed markup'              => array( array( 'label' => 'Go <em>on</em><script>alert( 1 );</script>' ), '<a href="{url}" rel="next">Go <em>on</em></a>' ),
			'an empty label'                           => array( array( 'label' => '' ), '<a href="{url}" rel="next">Next</a>' ),
			'the title'                                => array( array( 'showTitle' => true ), '<a href="{url}" rel="next">Newer post</a>' ),
			'the title and a label that links'         => array(
				array(
					'showTitle' => true,
					'linkLabel' => true,
					'label'     => 'Next:',
				),
				'<a href="{url}" rel="next"><span class="post-navigation-link__label">Next:</span> <span class="post-navigation-link__title">Newer post</span></a>',
			),
			'the title and a label that is not linked' => array(
				array(
					'showTitle' => true,
					'linkLabel' => false,
					'label'     => 'Read',
				),
				'<span class="post-navigation-link__label">Read</span> <a href="{url}" rel="next">Newer post</a>',
			),
			'the title and a link without a label'     => array(
				array(
					'showTitle' => true,
					'linkLabel' => true,
				),
				'<a href="{url}" rel="next"><span class="post-navigation-link__label">Next:</span> <span class="post-navigation-link__title">Newer post</span></a>',
			),
			'a title that is not shown'                => array( array( 'showTitle' => false ), '<a href="{url}" rel="next">Next</a>' ),
		);
	}

	/**
	 * Tests the label of the previous post.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_use_the_label_before_the_title_of_the_previous_post() {
		$this->go_to_middle_post();

		$this->assertStringContainsString(
			'<span class="post-navigation-link__label">Previous:</span> <span class="post-navigation-link__title">Older post</span>',
			$this->render(
				array(
					'type'      => 'previous',
					'showTitle' => true,
					'linkLabel' => true,
				)
			)
		);
	}

	/**
	 * Tests the arrows.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 *
	 * @dataProvider data_arrows
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected content of the wrapper, with the link as {link}.
	 */
	public function test_should_add_the_arrow( $attributes, $expected ) {
		$this->go_to_middle_post();

		$is_previous = 'previous' === ( $attributes['type'] ?? 'next' );
		$index       = $is_previous ? 0 : 2;
		$rel         = $is_previous ? 'prev' : 'next';
		$label       = $is_previous ? 'Previous' : 'Next';
		$link        = '<a href="' . get_permalink( self::$posts[ $index ] ) . '" rel="' . $rel . '">' . $label . '</a>';

		$this->assertStringContainsString( str_replace( '{link}', $link, $expected ), $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_arrows() {
		return array(
			'an arrow after the next link'       => array( array( 'arrow' => 'arrow' ), '{link}<span class="wp-block-post-navigation-link__arrow-next is-arrow-arrow" aria-hidden="true">→</span>' ),
			'a chevron after the next link'      => array( array( 'arrow' => 'chevron' ), '{link}<span class="wp-block-post-navigation-link__arrow-next is-arrow-chevron" aria-hidden="true">»</span>' ),
			'an arrow before the previous link'  => array(
				array(
					'type'  => 'previous',
					'arrow' => 'arrow',
				),
				'<span class="wp-block-post-navigation-link__arrow-previous is-arrow-arrow" aria-hidden="true">←</span>{link}',
			),
			'a chevron before the previous link' => array(
				array(
					'type'  => 'previous',
					'arrow' => 'chevron',
				),
				'<span class="wp-block-post-navigation-link__arrow-previous is-arrow-chevron" aria-hidden="true">«</span>{link}',
			),
			'no arrow'                           => array( array( 'arrow' => 'none' ), '>{link}</div>' ),
			'an arrow that is not known'         => array( array( 'arrow' => 'other' ), '>{link}</div>' ),
		);
	}

	/**
	 * Tests that the posts of the same taxonomy are used.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_only_link_to_the_posts_of_the_same_term_with_a_taxonomy() {
		$this->go_to_middle_post();

		$this->assertStringContainsString(
			'href="' . get_permalink( self::$posts[0] ) . '"',
			$this->render(
				array(
					'type'     => 'previous',
					'taxonomy' => 'category',
				)
			),
			'The previous post of the category should be linked.'
		);
		$this->assertSame(
			'<div class="post-navigation-link-next"></div>',
			$this->render(
				array(
					'type'     => 'next',
					'taxonomy' => 'category',
				)
			),
			'The next post is not in the category, so nothing should be linked.'
		);
	}

	/**
	 * Tests that the taxonomy of the block is used.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_use_the_taxonomy_of_the_block() {
		wp_set_object_terms( self::$posts[1], array( 'shared-tag' ), 'post_tag' );
		wp_set_object_terms( self::$posts[2], array( 'shared-tag' ), 'post_tag' );

		$this->go_to_middle_post();

		$this->assertStringContainsString(
			'href="' . get_permalink( self::$posts[2] ) . '"',
			$this->render(
				array(
					'type'     => 'next',
					'taxonomy' => 'post_tag',
				)
			),
			'The next post of the tag should be linked.'
		);
		$this->assertSame(
			'<div class="post-navigation-link-previous"></div>',
			$this->render(
				array(
					'type'     => 'previous',
					'taxonomy' => 'post_tag',
				)
			),
			'The previous post is not in the tag, so nothing should be linked.'
		);
	}

	/**
	 * Tests that a link that is null by a filter renders an empty wrapper without the styles.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_render_an_empty_wrapper_when_a_filter_returns_null() {
		add_filter( 'next_post_link', '__return_null' );

		$this->go_to_middle_post();

		$this->assertSame(
			'<div class="post-navigation-link-next"></div>',
			$this->render(
				array(
					'type'  => 'next',
					'style' => array( 'border' => array( 'radius' => '5px' ) ),
				)
			)
		);
	}

	/**
	 * Tests the classes of the wrapper.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 *
	 * @dataProvider data_wrapper_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->go_to_middle_post();

		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wrapper_classes() {
		return array(
			'the type'          => array( array(), '<div class="post-navigation-link-next">' ),
			'the previous type' => array( array( 'type' => 'previous' ), '<div class="post-navigation-link-previous">' ),
			'a text alignment'  => array( array( 'textAlign' => 'center' ), '<div class="post-navigation-link-next has-text-align-center">' ),
			'a border color'    => array( array( 'borderColor' => 'cyan' ), '<div class="post-navigation-link-next has-border-color has-cyan-border-color">' ),
		);
	}

	/**
	 * Tests the styles of the wrapper.
	 *
	 * @ticket 66547
	 *
	 * @covers ::render_block_core_post_navigation_link
	 */
	public function test_should_add_the_support_styles_to_the_wrapper() {
		$this->go_to_middle_post();

		$processor = new WP_HTML_Tag_Processor(
			$this->render(
				array(
					'textAlign' => 'right',
					'style'     => array( 'border' => array( 'radius' => 5 ) ),
				)
			)
		);
		$processor->next_tag( 'div' );

		$this->assertSame( 'post-navigation-link-next has-text-align-right', $processor->get_attribute( 'class' ) );
		$this->assertSame( 'border-radius:5px', $processor->get_attribute( 'style' ) );
	}

	/**
	 * Tests the styles of the support.
	 *
	 * @ticket 66547
	 *
	 * @covers ::block_core_post_navigation_link_get_support_styles
	 *
	 * @dataProvider data_support_styles
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $class_names Expected classes.
	 * @param string $style      Expected styles.
	 */
	public function test_should_build_the_support_styles( $attributes, $class_names, $style ) {
		$this->assertSame(
			array(
				'class' => $class_names,
				'style' => $style,
			),
			block_core_post_navigation_link_get_support_styles( $attributes )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_support_styles() {
		$image = array( 'url' => 'https://example.org/a.jpg' );

		return array(
			'nothing'                                      => array( array(), '', '' ),
			'a radius and a width of numbers'              => array(
				array(
					'style' => array(
						'border' => array(
							'radius' => 5,
							'width'  => 2,
						),
					),
				),
				'',
				'border-radius:5px;border-width:2px;',
			),
			'a radius and a width of lengths'              => array(
				array(
					'style' => array(
						'border' => array(
							'radius' => '1em',
							'width'  => '3px',
						),
					),
				),
				'',
				'border-radius:1em;border-width:3px;',
			),
			'a border style'                               => array( array( 'style' => array( 'border' => array( 'style' => 'dotted' ) ) ), '', 'border-style:dotted;' ),
			'a named border color'                         => array( array( 'borderColor' => 'cyan' ), 'has-border-color has-cyan-border-color', '' ),
			'a custom border color'                        => array( array( 'style' => array( 'border' => array( 'color' => '#ff0000' ) ) ), 'has-border-color', 'border-color:#ff0000;' ),
			'a named and a custom border color'            => array(
				array(
					'borderColor' => 'cyan',
					'style'       => array( 'border' => array( 'color' => '#ff0000' ) ),
				),
				'has-border-color has-cyan-border-color',
				'',
			),
			'a side of the border'                         => array( array( 'style' => array( 'border' => array( 'top' => array( 'width' => '1px' ) ) ) ), '', 'border-top-width:1px;' ),
			'a background image'                           => array(
				array( 'style' => array( 'background' => array( 'backgroundImage' => $image ) ) ),
				'has-background',
				"background-image:url('https://example.org/a.jpg');background-size:cover;",
			),
			'a background image to contain'                => array(
				array(
					'style' => array(
						'background' => array(
							'backgroundImage' => $image,
							'backgroundSize'  => 'contain',
						),
					),
				),
				'has-background',
				"background-image:url('https://example.org/a.jpg');background-position:50% 50%;background-size:contain;",
			),
			'a background image to contain and a position' => array(
				array(
					'style' => array(
						'background' => array(
							'backgroundImage'    => $image,
							'backgroundSize'     => 'contain',
							'backgroundPosition' => '10% 20%',
						),
					),
				),
				'has-background',
				"background-image:url('https://example.org/a.jpg');background-position:10% 20%;background-size:contain;",
			),
			'a gradient'                                   => array( array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(red,blue)' ) ) ), 'has-background', 'background-image:linear-gradient(red,blue);' ),
			'the minimum dimensions'                       => array(
				array(
					'style' => array(
						'dimensions' => array(
							'minHeight' => '10px',
							'minWidth'  => '20px',
						),
					),
				),
				'',
				'min-height:10px;min-width:20px;',
			),
			'a shadow'                                     => array( array( 'style' => array( 'shadow' => '1px 1px 2px #000' ) ), '', 'box-shadow:1px 1px 2px #000;' ),
			'the spacing'                                  => array(
				array(
					'style' => array(
						'spacing' => array(
							'margin'  => array( 'top' => '1px' ),
							'padding' => array( 'left' => '2px' ),
						),
					),
				),
				'',
				'padding-left:2px;margin-top:1px;',
			),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66547
	 *
	 * @covers ::register_block_core_post_navigation_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-navigation-link' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-navigation-link' );
		}

		register_block_core_post_navigation_link();
		$block_type = $registry->get_registered( 'core/post-navigation-link' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-navigation-link' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_navigation_link', $block_type->render_callback );
	}
}
