<?php
/**
 * Tests for the server-side rendering of the Post Comments Form block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group comment
 *
 * @covers ::render_block_core_post_comments_form
 */
class Tests_Blocks_RenderPostCommentsForm extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original stylesheet of the active theme.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	/**
	 * Original scripts instance.
	 *
	 * @var WP_Scripts|null
	 */
	private $original_wp_scripts;

	/**
	 * ID of a post.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post that has a password.
	 *
	 * @var int
	 */
	private static $protected_post_id;

	/**
	 * ID of a post with the comments closed.
	 *
	 * @var int
	 */
	private static $closed_post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id           = $factory->post->create();
		self::$protected_post_id = $factory->post->create( array( 'post_password' => 'secret' ) );
		self::$closed_post_id    = $factory->post->create( array( 'comment_status' => 'closed' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
		$this->original_stylesheet          = get_stylesheet();

		// Use a new scripts instance, so enqueuing the comment reply script does not leak into other tests.
		global $wp_scripts;
		$this->original_wp_scripts = $wp_scripts;
		$wp_scripts                = null;
		wp_scripts();
	}

	public function tear_down() {
		global $wp_scripts;

		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$wp_scripts                         = $this->original_wp_scripts;

		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

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
				'blockName'    => 'core/post-comments-form',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_comments_form( $attributes, '', $block );
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests that the function returns null for a post that needs a password.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_nothing_for_a_post_that_needs_a_password() {
		$this->assertNull( $this->render( array(), array( 'postId' => self::$protected_post_id ) ) );
	}

	/**
	 * Tests that nothing is rendered when the comments are closed.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_nothing_when_the_comments_are_closed() {
		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$closed_post_id ) ) );
	}

	/**
	 * Tests the form.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_the_comment_form_of_the_post() {
		$output = $this->render();

		$this->assertStringContainsString( '<form action="', $output );
		$this->assertStringContainsString( "name='comment_post_ID' value='" . self::$post_id . "'", $output, 'The form should be the one of the post.' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'div' ), 'The form should be in a wrapper.' );
		$this->assertSame( 'respond', $processor->get_attribute( 'id' ) );
		$this->assertContains( 'comment-respond', preg_split( '/\s+/', $processor->get_attribute( 'class' ) ) );
	}

	/**
	 * Tests the classes of the wrapper.
	 *
	 * @ticket 66541
	 *
	 * @dataProvider data_wrapper_classes
	 *
	 * @param array    $attributes Attributes of the block.
	 * @param string[] $expected   Expected classes.
	 */
	public function test_should_add_the_classes_of_the_attributes_to_the_wrapper( $attributes, $expected ) {
		$processor = new WP_HTML_Tag_Processor( $this->render( $attributes ) );
		$processor->next_tag( 'div' );

		$this->assertSame( $expected, preg_split( '/\s+/', $processor->get_attribute( 'class' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wrapper_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'   => array( array(), array( 'comment-respond' ) ),
			'an alignment' => array( array( 'textAlign' => 'center' ), array( 'comment-respond', 'has-text-align-center' ) ),
			'a link color' => array( array( 'style' => $link_color ), array( 'comment-respond', 'has-link-color' ) ),
			'both'         => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				array( 'comment-respond', 'has-text-align-right', 'has-link-color' ),
			),
		);
	}

	/**
	 * Tests that the script to reply to the comments is enqueued.
	 *
	 * @ticket 66541
	 */
	public function test_should_enqueue_the_script_to_reply_to_the_comments() {
		wp_dequeue_script( 'comment-reply' );

		$this->render();

		$this->assertTrue( wp_script_is( 'comment-reply', 'enqueued' ) );
	}

	/**
	 * Tests that the script is not enqueued when nothing is rendered.
	 *
	 * @ticket 66541
	 */
	public function test_should_not_enqueue_the_script_when_nothing_is_rendered() {
		wp_dequeue_script( 'comment-reply' );

		$this->render( array(), array( 'postId' => self::$protected_post_id ) );

		$this->assertFalse( wp_script_is( 'comment-reply', 'enqueued' ) );
	}

	/**
	 * Tests that the filter of the defaults of the form is removed after the form is rendered.
	 *
	 * @ticket 66541
	 */
	public function test_should_remove_the_filter_of_the_defaults_after_the_form_is_rendered() {
		$this->render();

		$this->assertFalse( has_filter( 'comment_form_defaults', 'post_comments_form_block_form_defaults' ) );
	}

	/**
	 * Tests that the defaults of the form are not changed when the theme is not a block theme.
	 *
	 * @ticket 66541
	 *
	 * @covers ::post_comments_form_block_form_defaults
	 */
	public function test_should_not_change_the_defaults_of_the_form_when_the_theme_is_not_a_block_theme() {
		$defaults = array(
			'submit_button' => 'button',
			'submit_field'  => 'field',
			'other'         => 'value',
		);

		$this->assertFalse( wp_is_block_theme(), 'The theme should not be a block theme.' );
		$this->assertSame( $defaults, post_comments_form_block_form_defaults( $defaults ) );
	}

	/**
	 * Tests the defaults of the form for a block theme.
	 *
	 * @ticket 66541
	 *
	 * @covers ::post_comments_form_block_form_defaults
	 */
	public function test_should_change_the_submit_button_and_field_of_the_form_for_a_block_theme() {
		switch_theme( 'block-theme' );

		$this->assertTrue( wp_is_block_theme(), 'The theme should be a block theme.' );

		$defaults = post_comments_form_block_form_defaults( array( 'other' => 'value' ) );

		$this->assertSame( 'value', $defaults['other'], 'The other defaults should be kept.' );
		$this->assertSame(
			'<input name="%1$s" type="submit" id="%2$s" class="wp-block-button__link ' . wp_theme_get_element_class_name( 'button' ) . '" value="%4$s" />',
			$defaults['submit_button']
		);
		$this->assertSame( '<p class="form-submit wp-block-button">%1$s %2$s</p>', $defaults['submit_field'] );
	}

	/**
	 * Tests that the form uses the button of the block theme.
	 *
	 * @ticket 66541
	 */
	public function test_should_render_the_form_with_the_button_of_a_block_theme() {
		switch_theme( 'block-theme' );

		$output = $this->render();

		$this->assertStringContainsString( '<p class="form-submit wp-block-button">', $output );
		$this->assertStringContainsString( 'type="submit"', $output );
		$this->assertMatchesRegularExpression( '/<input name="submit"[^>]* class="wp-block-button__link[^"]*"/', $output, 'The submit button should use the classes of the block theme.' );
		$this->assertDoesNotMatchRegularExpression( '/<input name="submit"[^>]* class="[^"]*\bsubmit\b[^"]*"/', $output, 'The default submit class should be replaced.' );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66541
	 *
	 * @covers ::register_block_core_post_comments_form
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/post-comments-form' );

		if ( $original_block ) {
			unregister_block_type( 'core/post-comments-form' );
		}

		try {
			register_block_core_post_comments_form();
			$block_type = $registry->get_registered( 'core/post-comments-form' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_post_comments_form', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/post-comments-form' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
