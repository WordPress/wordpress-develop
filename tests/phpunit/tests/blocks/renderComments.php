<?php
/**
 * Tests for the server-side rendering of the Comments block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderComments extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * The original block type of the legacy block.
	 *
	 * @var WP_Block_Type|null
	 */
	private $original_legacy_block_type;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render = WP_Block_Supports::$block_to_render;

		$this->original_legacy_block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/post-comments' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'core/post-comments' ) ) {
			$registry->unregister( 'core/post-comments' );
		}

		if ( $this->original_legacy_block_type ) {
			$registry->register( $this->original_legacy_block_type );
		}

		wp_dequeue_script( 'comment-reply' );

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array  $attributes The attributes of the block.
	 * @param array  $context    Optional. The context of the block. Default none.
	 * @param string $name       Optional. The name of the block. Default 'core/comments'.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $context = array(), $name = 'core/comments' ) {
		WP_Block_Supports::$block_to_render = array(
			'blockName' => $name,
			'attrs'     => $attributes,
		);

		$block = new WP_Block(
			array(
				'blockName'    => $name,
				'attrs'        => $attributes,
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/html',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Inner</p>',
						'innerContent' => array( '<p>Inner</p>' ),
					),
				),
				'innerHTML'    => '<div class="wp-block-comments"></div>',
				'innerContent' => array( '<div class="wp-block-comments">', null, '</div>' ),
			),
			$context
		);

		return render_block_core_comments( $attributes, '', $block );
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
	 * Creates a post with a comment and goes to it.
	 *
	 * @return int The ID of the post.
	 */
	private function go_to_post_with_comment() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'With a comment' ) );

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'A comment of the post',
				'comment_approved' => '1',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		// The comments template needs the globals of the template loading.
		wp_set_template_globals();

		return $post_id;
	}

	/**
	 * Tests that nothing is rendered without the post ID.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_nothing_without_the_post_id() {
		$this->assertSame( '', $this->render( array( 'legacy' => true ) ) );
	}

	/**
	 * Tests that nothing is rendered for a post with closed comments and no comments.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_nothing_for_closed_comments_without_comments() {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );

		$this->assertSame( '', $this->render( array( 'legacy' => true ), array( 'postId' => $post_id ) ) );
		$this->assertSame( '', $this->render( array(), array( 'postId' => $post_id ) ) );
	}

	/**
	 * Tests that the block is rendered for closed comments when the post has comments.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_closed_comments_with_comments() {
		$post_id = $this->go_to_post_with_comment();

		wp_update_post(
			array(
				'ID'             => $post_id,
				'comment_status' => 'closed',
			)
		);

		$this->assertNotSame( '', $this->render( array(), array( 'postId' => $post_id ) ) );
	}

	/**
	 * Tests that the block is rendered for open comments without comments.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_open_comments_without_comments() {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );

		$this->assertNotSame( '', $this->render( array(), array( 'postId' => $post_id ) ) );
	}

	/**
	 * Tests that the legacy version renders the comments of the post of the context.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_the_comments_of_the_post_of_the_context() {
		$post_id  = $this->go_to_post_with_comment();
		$other_id = self::factory()->post->create( array( 'post_title' => 'Other post' ) );

		$this->go_to( get_permalink( $other_id ) );
		wp_set_template_globals();

		$output = $this->render( array( 'legacy' => true ), array( 'postId' => $post_id ) );

		$this->assertStringContainsString( 'A comment of the post', $output );
	}

	/**
	 * Tests the static version of the block.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_the_static_version_of_the_block() {
		$post_id = $this->go_to_post_with_comment();

		$output = $this->render( array(), array( 'postId' => $post_id ) );

		$this->assertStringContainsString( '<div class="wp-block-comments"><p>Inner</p></div>', $output );
		$this->assertStringNotContainsString( 'A comment of the post', $output );
	}

	/**
	 * Tests the legacy version of the block.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_the_legacy_version_with_the_comments() {
		$post_id = $this->go_to_post_with_comment();

		$output  = $this->render( array( 'legacy' => true ), array( 'postId' => $post_id ) );
		$wrapper = $this->get_element( $output, 'div' );

		$this->assertStringStartsWith( '<div ', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertStringContainsString( 'A comment of the post', $output );
		$this->assertStringNotContainsString( '<p>Inner</p>', $output, 'The inner blocks should not be rendered.' );
		$this->assertContains( 'wp-block-post-comments', explode( ' ', $wrapper['class'] ) );
		$this->assertContains( 'wp-block-comments', explode( ' ', $wrapper['class'] ) );
	}

	/**
	 * Tests the legacy classes of the wrapper.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 *
	 * @dataProvider data_legacy_attributes
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected classes beside the class of the block.
	 */
	public function test_should_add_the_classes_to_the_legacy_wrapper( $attributes, $expected ) {
		$post_id = $this->go_to_post_with_comment();

		$wrapper = $this->get_element( $this->render( $attributes, array( 'postId' => $post_id ) ), 'div' );
		$classes = array_values( array_diff( explode( ' ', $wrapper['class'] ), array( 'wp-block-comments' ) ) );

		sort( $classes );
		sort( $expected );

		$this->assertSame( $expected, $classes );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_legacy_attributes() {
		return array(
			'legacy only' => array( array( 'legacy' => true ), array( 'wp-block-post-comments' ) ),
		);
	}

	/**
	 * Tests the text alignment of the legacy block.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_add_the_text_alignment_to_the_legacy_wrapper() {
		$post_id = $this->go_to_post_with_comment();

		$wrapper = $this->get_element(
			$this->render(
				array(
					'legacy'    => true,
					'textAlign' => 'center',
				),
				array( 'postId' => $post_id )
			),
			'div'
		);

		$this->assertContains( 'has-text-align-center', explode( ' ', $wrapper['class'] ) );
	}

	/**
	 * Tests that the old post comments block is always legacy.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_render_the_post_comments_block_as_legacy() {
		$post_id = $this->go_to_post_with_comment();

		$output = $this->render( array(), array( 'postId' => $post_id ), 'core/post-comments' );

		$this->assertStringContainsString( 'A comment of the post', $output );
		$this->assertStringNotContainsString( 'wp-block-post-comments"', $this->get_element( $output, 'div' )['class'] ?? '', 'The old class is added only with the legacy attribute.' );
	}

	/**
	 * Tests the script of the reply link.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_enqueue_the_script_of_the_reply_link_for_the_legacy_version() {
		$post_id = $this->go_to_post_with_comment();

		$this->render( array(), array( 'postId' => $post_id ) );

		$this->assertFalse( wp_script_is( 'comment-reply', 'enqueued' ), 'The static version should not enqueue the script.' );

		$this->render( array( 'legacy' => true ), array( 'postId' => $post_id ) );

		$this->assertTrue( wp_script_is( 'comment-reply', 'enqueued' ) );
	}

	/**
	 * Tests that the global post is restored.
	 *
	 * @ticket 66601
	 *
	 * @covers ::render_block_core_comments
	 */
	public function test_should_restore_the_global_post_after_the_legacy_version() {
		$post_id  = $this->go_to_post_with_comment();
		$other_id = self::factory()->post->create();

		$GLOBALS['post'] = get_post( $other_id );

		$this->render( array( 'legacy' => true ), array( 'postId' => $post_id ) );

		$this->assertSame( $other_id, $GLOBALS['post']->ID );
	}

	/**
	 * Tests the form of the comment for a theme that is not a block theme.
	 *
	 * @ticket 66601
	 *
	 * @covers ::comments_block_form_defaults
	 */
	public function test_form_defaults_are_unchanged_for_a_theme_that_is_not_a_block_theme() {
		$fields = array(
			'submit_button' => 'button',
			'submit_field'  => 'field',
			'other'         => 'other',
		);

		$this->assertFalse( wp_is_block_theme() );
		$this->assertSame( $fields, comments_block_form_defaults( $fields ) );
	}

	/**
	 * Tests the form of the comment for a block theme.
	 *
	 * @ticket 66601
	 *
	 * @covers ::comments_block_form_defaults
	 */
	public function test_form_defaults_are_changed_for_a_block_theme() {
		switch_theme( 'block-theme' );

		$fields = comments_block_form_defaults( array( 'other' => 'other' ) );

		$this->assertSame( 'other', $fields['other'] );
		$this->assertSame(
			'<input name="%1$s" type="submit" id="%2$s" class="%3$s wp-block-button__link ' . wp_theme_get_element_class_name( 'button' ) . '" value="%4$s" />',
			$fields['submit_button']
		);
		$this->assertSame( '<p class="form-submit wp-block-button">%1$s %2$s</p>', $fields['submit_field'] );
		$this->assertStringContainsString( 'wp-element-button', $fields['submit_button'] );
	}

	/**
	 * Tests that the function is hooked to the defaults of the form.
	 *
	 * @ticket 66601
	 *
	 * @covers ::comments_block_form_defaults
	 */
	public function test_form_defaults_are_hooked() {
		$this->assertSame( 10, has_filter( 'comment_form_defaults', 'comments_block_form_defaults' ) );
	}

	/**
	 * Tests the legacy block that is registered again.
	 *
	 * @ticket 66601
	 *
	 * @covers ::register_legacy_post_comments_block
	 */
	public function test_register_legacy_post_comments_block() {
		$registry = WP_Block_Type_Registry::get_instance();

		register_legacy_post_comments_block();

		$block_type = $registry->get_registered( 'core/post-comments' );

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_comments', $block_type->render_callback );
		$this->assertSame( array( 'postId', 'postType' ), $block_type->uses_context );
		$this->assertArrayHasKey( 'textAlign', $block_type->attributes );
		$this->assertFalse( $block_type->supports['inserter'] );
		$this->assertFalse( $block_type->supports['html'] );
		$this->assertSame( 'theme', $block_type->category );
	}

	/**
	 * Tests that the legacy block is registered when it was not.
	 *
	 * @ticket 66601
	 *
	 * @covers ::register_legacy_post_comments_block
	 */
	public function test_register_legacy_post_comments_block_when_it_is_not_registered() {
		$registry = WP_Block_Type_Registry::get_instance();

		unregister_block_type( 'core/post-comments' );

		$this->assertFalse( $registry->is_registered( 'core/post-comments' ) );

		register_legacy_post_comments_block();

		$this->assertTrue( $registry->is_registered( 'core/post-comments' ) );
	}

	/**
	 * Tests the filter of the metadata of the legacy block.
	 *
	 * @ticket 66601
	 *
	 * @covers ::register_legacy_post_comments_block
	 */
	public function test_register_legacy_post_comments_block_filters_the_metadata() {
		add_filter(
			'block_type_metadata',
			static function ( $metadata ) {
				if ( 'core/post-comments' === $metadata['name'] ) {
					$metadata['category'] = 'widgets';
				}

				return $metadata;
			}
		);

		register_legacy_post_comments_block();

		$this->assertSame( 'widgets', WP_Block_Type_Registry::get_instance()->get_registered( 'core/post-comments' )->category );
	}
}
