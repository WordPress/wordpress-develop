<?php
/**
 * Tests for the server-side rendering of the Home Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_home_link
 */
class Tests_Blocks_RenderHomeLink extends WP_UnitTestCase {

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
			'blockName' => 'core/home-link',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'core/home-link',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_home_link( $attributes, '', $block );
	}

	/**
	 * Returns the attributes of the first element of the output.
	 *
	 * @param string $output The output.
	 * @param string $tag    The tag name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests the link to the home page.
	 *
	 * @ticket 66581
	 */
	public function test_should_render_the_link_to_the_home_page() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$output = $this->render();
		$link   = $this->get_element( $output, 'a' );

		$this->assertStringStartsWith( '<li ', $output );
		$this->assertStringEndsWith( '</a></li>', $output );
		$this->assertSame( home_url(), $link['href'] );
		$this->assertSame( 'home', $link['rel'] );
		$this->assertSame( 'wp-block-home-link__content wp-block-navigation-item__content', $link['class'] );
		$this->assertStringContainsString( '>Home</a>', $output );
		$this->assertArrayNotHasKey( 'target', $link );
		$this->assertArrayNotHasKey( 'aria-current', $link );
	}

	/**
	 * Tests that the front page is the current page.
	 *
	 * @ticket 66581
	 */
	public function test_should_mark_the_link_as_the_current_page_on_the_front_page() {
		$this->go_to( '/' );

		$output = $this->render();

		$this->assertSame( 'page', $this->get_element( $output, 'a' )['aria-current'] );
		$this->assertStringContainsString( 'current-menu-item', $this->get_element( $output, 'li' )['class'] );
	}

	/**
	 * Tests the posts page when there is no static home page.
	 *
	 * @ticket 66581
	 */
	public function test_should_mark_the_link_as_the_current_page_on_the_blog_without_a_static_home_page() {
		$posts_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', $posts_page );

		$this->go_to( '/' );

		$this->assertSame( 'page', $this->get_element( $this->render(), 'a' )['aria-current'] );
	}

	/**
	 * Tests that the link is not the current page on the page of the posts when there is a static home page.
	 *
	 * @ticket 66581
	 */
	public function test_should_not_mark_the_link_as_the_current_page_on_the_page_of_the_posts() {
		$home_page  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_page );
		update_option( 'page_for_posts', $posts_page );

		$this->go_to( get_permalink( $posts_page ) );

		$this->assertArrayNotHasKey( 'aria-current', $this->get_element( $this->render(), 'a' ) );
	}

	/**
	 * Tests the label.
	 *
	 * @ticket 66581
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $attributes The attributes of the block.
	 * @param string $expected   The expected content of the link.
	 */
	public function test_should_render_the_label( $attributes, $expected ) {
		$this->go_to( '/' );

		$this->assertStringContainsString( '>' . $expected . '</a>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'not set'                    => array( array(), 'Home' ),
			'empty'                      => array( array( 'label' => '' ), 'Home' ),
			'a label'                    => array( array( 'label' => 'Start' ), 'Start' ),
			'markup that is allowed'     => array( array( 'label' => 'My <em>home</em>' ), 'My <em>home</em>' ),
			'markup that is not allowed' => array( array( 'label' => 'Safe<script>alert(1)</script>' ), 'Safe' ),
		);
	}

	/**
	 * Tests the target of the link.
	 *
	 * @ticket 66581
	 *
	 * @dataProvider data_targets
	 *
	 * @param mixed $opens_in_new_tab The value of the attribute.
	 * @param bool  $expected         Whether the link opens in a new tab.
	 */
	public function test_should_open_in_a_new_tab_only_for_true( $opens_in_new_tab, $expected ) {
		$this->go_to( '/' );

		$link = $this->get_element( $this->render( array( 'opensInNewTab' => $opens_in_new_tab ) ), 'a' );

		$this->assertSame( $expected, isset( $link['target'] ) && '_blank' === $link['target'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_targets() {
		return array(
			'true'        => array( true, true ),
			'false'       => array( false, false ),
			'true string' => array( 'true', false ),
			'one'         => array( 1, false ),
		);
	}

	/**
	 * Tests the description.
	 *
	 * @ticket 66581
	 */
	public function test_should_render_the_description_with_the_markup_that_is_allowed() {
		$this->go_to( '/' );

		$output = $this->render( array( 'description' => 'Back <strong>home</strong><script>x()</script>' ) );

		$this->assertStringContainsString( '<span class="wp-block-navigation-item__description">Back <strong>home</strong></span></a>', $output );
		$this->assertStringNotContainsString( '<script', $output );
	}

	/**
	 * Tests that there is no description when it is empty.
	 *
	 * @ticket 66581
	 */
	public function test_should_not_render_an_empty_description() {
		$this->go_to( '/' );

		$this->assertStringNotContainsString( 'wp-block-navigation-item__description', $this->render( array( 'description' => '' ) ) );
	}

	/**
	 * Tests the colors of the context in the wrapper.
	 *
	 * @ticket 66581
	 */
	public function test_should_add_the_colors_of_the_context_to_the_wrapper() {
		$this->go_to( '/' );

		$item = $this->get_element(
			$this->render(
				array(),
				array(
					'textColor' => 'red',
					'style'     => array( 'color' => array( 'background' => '#eeeeee' ) ),
				)
			),
			'li'
		);

		$this->assertStringContainsString( 'has-red-color', $item['class'] );
		$this->assertStringContainsString( 'has-background', $item['class'] );
		$this->assertStringContainsString( 'background-color: #eeeeee', $item['style'] );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66581
	 *
	 * @covers ::register_block_core_home_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/home-link' );

		if ( $original_block ) {
			unregister_block_type( 'core/home-link' );
		}

		try {
			register_block_core_home_link();
			$block_type = $registry->get_registered( 'core/home-link' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_home_link', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/home-link' ) ) {
				unregister_block_type( 'core/home-link' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
