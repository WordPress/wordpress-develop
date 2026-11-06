<?php
/**
 * Tests for the server-side rendering of the Query block, and for the functions that disable its enhanced pagination.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 */
class Tests_Blocks_RenderQuery extends WP_UnitTestCase {

	/**
	 * Original script modules.
	 *
	 * @var WP_Script_Modules|null
	 */
	private $original_script_modules;

	/**
	 * Original Interactivity API instance.
	 *
	 * @var WP_Interactivity_API|null
	 */
	private $original_interactivity;

	/**
	 * Original style handles of the block type, when it was registered.
	 *
	 * @var string[]
	 */
	private $original_style_handles = array();

	/**
	 * Whether the Query block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	public function set_up() {
		global $wp_script_modules, $wp_interactivity;

		parent::set_up();

		$this->original_script_modules = $wp_script_modules;
		$this->original_interactivity  = $wp_interactivity;
		$wp_script_modules             = null;
		$wp_interactivity              = new WP_Interactivity_API();

		$this->was_registered = WP_Block_Type_Registry::get_instance()->is_registered( 'core/query' );

		if ( $this->was_registered ) {
			$this->original_style_handles = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' )->style_handles;
		}

		if ( ! $this->was_registered ) {
			register_block_core_query();
		}

		// The block has its styles only when the assets are built, so the tests set them.
		WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' )->style_handles = array( 'wp-block-query' );
	}

	public function tear_down() {
		global $wp_script_modules, $wp_interactivity;

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/query' );
		} else {
			// The tests remove the styles from the block type that is registered.
			WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' )->style_handles = $this->original_style_handles;
		}

		wp_dequeue_style( 'wp-block-query' );

		$wp_script_modules = $this->original_script_modules;
		$wp_interactivity  = $this->original_interactivity;

		parent::tear_down();
	}

	/**
	 * Creates a parsed block.
	 *
	 * @param string|null $name         Name of the block.
	 * @param array       $attrs        Attributes of the block.
	 * @param array       $inner_blocks Parsed inner blocks.
	 * @return array The parsed block.
	 */
	private function parsed_block( $name, $attrs = array(), $inner_blocks = array() ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="wp-block-query">' . str_repeat( ' ', count( $inner_blocks ) ) . '</div>',
			'innerContent' => array_merge( array( '<div class="wp-block-query">' ), array_fill( 0, count( $inner_blocks ), null ), array( '</div>' ) ),
		);
	}

	/**
	 * Calls the render callback of the Query block.
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $content    Content of the block.
	 * @return string The rendered content.
	 */
	private function render_callback( $attributes, $content = '<div class="wp-block-query"><p>Posts</p></div>' ) {
		$block = new WP_Block( $this->parsed_block( 'core/query', $attributes ) );

		return render_block_core_query( $attributes, $content, $block );
	}

	/**
	 * Tests that the content is not changed when the block is not interactive.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 *
	 * @dataProvider data_attributes_that_are_not_interactive
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_not_change_the_content_when_the_block_is_not_interactive( $attributes ) {
		$content = '<div class="wp-block-query"><p>Posts</p></div>';

		$this->assertSame( $content, $this->render_callback( $attributes, $content ) );
		$this->assertNotContains( '@wordpress/block-library/query/view', wp_script_modules()->get_queue(), 'The view script module should not be enqueued.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_that_are_not_interactive() {
		return array(
			'no attributes'                 => array( array() ),
			'the pagination is disabled'    => array(
				array(
					'enhancedPagination' => false,
					'queryId'            => 1,
				),
			),
			'no query ID'                   => array( array( 'enhancedPagination' => true ) ),
			'a query ID that is not scalar' => array(
				array(
					'enhancedPagination' => true,
					'queryId'            => array( 1 ),
				),
			),
			'a null query ID'               => array(
				array(
					'enhancedPagination' => true,
					'queryId'            => null,
				),
			),
			'a string for the pagination'   => array(
				array(
					'enhancedPagination' => 'true',
					'queryId'            => 1,
				),
			),
			'an integer for the pagination' => array(
				array(
					'enhancedPagination' => 1,
					'queryId'            => 1,
				),
			),
		);
	}

	/**
	 * Tests the directives that are added to an interactive block.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 *
	 * @dataProvider data_query_ids
	 *
	 * @param int|string|float $query_id Query ID.
	 * @param string           $region   Expected router region.
	 */
	public function test_should_add_the_directives_when_the_block_is_interactive( $query_id, $region ) {
		$output = $this->render_callback(
			array(
				'enhancedPagination' => true,
				'queryId'            => $query_id,
			)
		);

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag(), 'The block should have an element.' );

		$this->assertSame( 'core/query', $processor->get_attribute( 'data-wp-interactive' ) );
		$this->assertSame( $region, $processor->get_attribute( 'data-wp-router-region' ) );
		$this->assertSame( '{}', $processor->get_attribute( 'data-wp-context' ) );
		$this->assertSame( (string) $query_id, $processor->get_attribute( 'data-wp-key' ) );
		$this->assertStringContainsString( '<p>Posts</p></div>', $output, 'The content should be kept.' );
		$this->assertContains( '@wordpress/block-library/query/view', wp_script_modules()->get_queue(), 'The view script module should be enqueued.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_query_ids() {
		return array(
			'an integer' => array( 3, 'query-3' ),
			'zero'       => array( 0, 'query-0' ),
			'a string'   => array( 'main', 'query-main' ),
		);
	}

	/**
	 * Tests that the directives are only added to the first element.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_only_add_the_directives_to_the_first_element() {
		$output = $this->render_callback(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			'<div class="first"><div class="second"></div></div>'
		);

		$this->assertSame( 1, substr_count( $output, 'data-wp-interactive' ) );

		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag();
		$this->assertSame( 'first', $processor->get_attribute( 'class' ), 'The first element should have the directives.' );
		$this->assertNotNull( $processor->get_attribute( 'data-wp-interactive' ) );
		$processor->next_tag();
		$this->assertNull( $processor->get_attribute( 'data-wp-interactive' ), 'The second element should not have the directives.' );
	}

	/**
	 * Tests that the script module is enqueued even when the content has no element.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_enqueue_the_script_module_for_content_without_an_element() {
		$output = $this->render_callback(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			'Text'
		);

		$this->assertSame( 'Text', $output );
		$this->assertContains( '@wordpress/block-library/query/view', wp_script_modules()->get_queue() );
	}

	/**
	 * Tests that the styles are removed from the block when it is not interactive.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_remove_the_styles_when_the_block_is_not_interactive() {
		$block = new WP_Block( $this->parsed_block( 'core/query' ) );

		$this->assertContains( 'wp-block-query', $block->block_type->style_handles, 'The styles should be in the block type at first.' );

		render_block_core_query( array(), '<div></div>', $block );

		$this->assertNotContains( 'wp-block-query', $block->block_type->style_handles );
	}

	/**
	 * Tests that the styles are added back to the block when it is interactive.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_add_the_styles_back_when_the_block_is_interactive() {
		$block = new WP_Block( $this->parsed_block( 'core/query' ) );

		render_block_core_query( array(), '<div></div>', $block );
		$this->assertNotContains( 'wp-block-query', $block->block_type->style_handles, 'The styles should be removed at first.' );

		render_block_core_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			'<div></div>',
			$block
		);

		$this->assertContains( 'wp-block-query', $block->block_type->style_handles );
	}

	/**
	 * Tests that the styles are kept when the block is interactive.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_keep_the_styles_when_the_block_is_interactive() {
		$block = new WP_Block( $this->parsed_block( 'core/query' ) );

		render_block_core_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			'<div></div>',
			$block
		);

		$this->assertSame( array( 'wp-block-query' ), array_values( $block->block_type->style_handles ) );
	}

	/**
	 * Tests that the other styles are kept when the styles are added back.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_keep_the_other_styles_when_the_styles_are_added_back() {
		$block                            = new WP_Block( $this->parsed_block( 'core/query' ) );
		$block->block_type->style_handles = array( 'other-style' );

		render_block_core_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			'<div></div>',
			$block
		);

		$this->assertSame( array( 'other-style', 'wp-block-query' ), array_values( $block->block_type->style_handles ) );
	}

	/**
	 * Tests that the styles are not changed when they are already enqueued.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_not_change_the_styles_when_they_are_enqueued() {
		wp_enqueue_style( 'wp-block-query' );

		$block                            = new WP_Block( $this->parsed_block( 'core/query' ) );
		$block->block_type->style_handles = array( 'wp-block-query' );

		render_block_core_query( array(), '<div></div>', $block );

		$this->assertContains( 'wp-block-query', $block->block_type->style_handles );
	}

	/**
	 * Tests that the other styles of the block are kept.
	 *
	 * @ticket 66530
	 *
	 * @covers ::render_block_core_query
	 */
	public function test_should_keep_the_other_styles_of_the_block() {
		$block                            = new WP_Block( $this->parsed_block( 'core/query' ) );
		$block->block_type->style_handles = array( 'wp-block-query', 'other-style' );

		render_block_core_query( array(), '<div></div>', $block );

		$this->assertSame( array( 'other-style' ), array_values( $block->block_type->style_handles ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66530
	 *
	 * @covers ::register_block_core_query
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' );

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_query', $block_type->render_callback );
	}
}
