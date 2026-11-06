<?php
/**
 * Tests for the server-side functions of the Tabs block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 */
class Tests_Blocks_RenderTabs extends WP_UnitTestCase {

	/**
	 * Original Interactivity API instance.
	 *
	 * @var WP_Interactivity_API|null
	 */
	private $original_interactivity;

	public function set_up() {
		global $wp_interactivity;

		parent::set_up();

		$this->original_interactivity = $wp_interactivity;
		$wp_interactivity             = new WP_Interactivity_API();
	}

	public function tear_down() {
		global $wp_interactivity;

		$wp_interactivity = $this->original_interactivity;

		parent::tear_down();
	}

	/**
	 * Creates a parsed block.
	 *
	 * @param string $name         Name of the block.
	 * @param array  $attrs        Attributes of the block.
	 * @param array  $inner_blocks Parsed inner blocks.
	 * @return array The parsed block.
	 */
	private function parsed_block( $name, $attrs = array(), $inner_blocks = array() ) {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Creates the inner blocks of a Tabs block, with a Tab Panels block that has the panels that are passed.
	 *
	 * @param array[] $panel_attrs Attributes of each Tab Panel block.
	 * @return array[] The parsed inner blocks.
	 */
	private function tab_panels( $panel_attrs ) {
		$panels = array();

		foreach ( $panel_attrs as $attrs ) {
			$panels[] = $this->parsed_block( 'core/tab-panel', $attrs );
		}

		return array(
			$this->parsed_block( 'core/tab-list' ),
			$this->parsed_block( 'core/tab-panels', array(), $panels ),
		);
	}

	/**
	 * Tests the IDs of the tabs.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_generate_tabs_list
	 */
	public function test_should_generate_the_ids_of_the_tabs_from_their_position() {
		$inner_blocks = $this->tab_panels( array( array(), array(), array() ) );

		$this->assertSame( array( 'tab-1', 'tab-2', 'tab-3' ), block_core_tabs_generate_tabs_list( $inner_blocks ), 'The IDs should have no prefix without an ID of the tabs.' );
		$this->assertSame( array( 'tabs_7-tab-1', 'tabs_7-tab-2', 'tabs_7-tab-3' ), block_core_tabs_generate_tabs_list( $inner_blocks, 'tabs_7' ), 'The IDs should use the ID of the tabs.' );
	}

	/**
	 * Tests that the anchor of a tab is used as its ID.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_generate_tabs_list
	 */
	public function test_should_use_the_anchor_of_a_tab_as_its_id() {
		$inner_blocks = $this->tab_panels(
			array(
				array( 'anchor' => 'first' ),
				array(),
				array( 'anchor' => '' ),
			)
		);

		$this->assertSame( array( 'first', 'tabs_1-tab-2', 'tabs_1-tab-3' ), block_core_tabs_generate_tabs_list( $inner_blocks, 'tabs_1' ) );
	}

	/**
	 * Tests that the IDs of the tabs are escaped.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_generate_tabs_list
	 */
	public function test_should_escape_the_ids_of_the_tabs() {
		$inner_blocks = $this->tab_panels( array( array( 'anchor' => '"><script>' ) ) );

		$this->assertSame( array( '&quot;&gt;&lt;script&gt;' ), block_core_tabs_generate_tabs_list( $inner_blocks, 'tabs_1' ) );
	}

	/**
	 * Tests the list of the tabs for the inner blocks that are not the ones of the tabs.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_generate_tabs_list
	 *
	 * @dataProvider data_inner_blocks_without_tabs
	 *
	 * @param array $inner_blocks Parsed inner blocks.
	 */
	public function test_should_return_an_empty_list_without_tabs( $inner_blocks ) {
		$this->assertSame( array(), block_core_tabs_generate_tabs_list( $inner_blocks, 'tabs_1' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inner_blocks_without_tabs() {
		return array(
			'no inner blocks'                         => array( array() ),
			'no tab panels block'                     => array(
				array(
					array(
						'blockName'   => 'core/tab-list',
						'innerBlocks' => array(),
					),
				),
			),
			'a tab panels block without tabs'         => array(
				array(
					array(
						'blockName'   => 'core/tab-panels',
						'innerBlocks' => array(),
					),
				),
			),
			'a tab panels block without inner blocks' => array( array( array( 'blockName' => 'core/tab-panels' ) ) ),
			'blocks without a name'                   => array( array( array( 'innerBlocks' => array() ) ) ),
			'a panel outside the panels'              => array( array( array( 'blockName' => 'core/tab-panel' ) ) ),
		);
	}

	/**
	 * Tests that only the Tab Panel blocks are counted, and only the first Tab Panels block is used.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_generate_tabs_list
	 */
	public function test_should_only_use_the_tab_panel_blocks_of_the_first_tab_panels_block() {
		$inner_blocks = array(
			$this->parsed_block(
				'core/tab-panels',
				array(),
				array(
					$this->parsed_block( 'core/paragraph' ),
					$this->parsed_block( 'core/tab-panel' ),
					array( 'attrs' => array() ),
					$this->parsed_block( 'core/tab-panel' ),
				)
			),
			$this->parsed_block( 'core/tab-panels', array(), array( $this->parsed_block( 'core/tab-panel', array( 'anchor' => 'other' ) ) ) ),
		);

		$this->assertSame( array( 'tabs_1-tab-1', 'tabs_1-tab-2' ), block_core_tabs_generate_tabs_list( $inner_blocks, 'tabs_1' ) );
	}

	/**
	 * Tests that the context is not changed for the other blocks.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_not_change_the_context_of_the_other_blocks() {
		$context = array( 'postId' => 5 );

		$this->assertSame( $context, block_core_tabs_provide_context( $context, $this->parsed_block( 'core/group' ) ) );
	}

	/**
	 * Tests that the context of a Tabs block has the ID and the list of the tabs.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_provide_the_id_and_the_list_of_the_tabs() {
		$block   = $this->parsed_block( 'core/tabs', array(), $this->tab_panels( array( array(), array() ) ) );
		$context = block_core_tabs_provide_context( array( 'postId' => 5 ), $block );

		$this->assertSame( 5, $context['postId'], 'The other values should be kept.' );
		$this->assertMatchesRegularExpression( '/^tabs_\d+$/', $context['core/tabs-id'], 'The ID should be generated.' );
		$this->assertSame(
			array( $context['core/tabs-id'] . '-tab-1', $context['core/tabs-id'] . '-tab-2' ),
			$context['core/tabs-list'],
			'The list should use the ID.'
		);
	}

	/**
	 * Tests that the anchor of the Tabs block is used as its ID.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_use_the_anchor_of_the_tabs_block_as_its_id() {
		$block   = $this->parsed_block( 'core/tabs', array( 'anchor' => 'my-tabs' ), $this->tab_panels( array( array(), array( 'anchor' => 'second' ) ) ) );
		$context = block_core_tabs_provide_context( array(), $block );

		$this->assertSame( 'my-tabs', $context['core/tabs-id'] );
		$this->assertSame( array( 'my-tabs-tab-1', 'second' ), $context['core/tabs-list'] );
	}

	/**
	 * Tests that each Tabs block has an ID that is different.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_generate_a_different_id_for_each_tabs_block() {
		$block = $this->parsed_block( 'core/tabs', array(), $this->tab_panels( array( array() ) ) );

		$first  = block_core_tabs_provide_context( array(), $block );
		$second = block_core_tabs_provide_context( array(), $block );

		$this->assertNotSame( $first['core/tabs-id'], $second['core/tabs-id'] );
	}

	/**
	 * Tests the context of a Tabs block without inner blocks.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_provide_an_empty_list_for_a_tabs_block_without_inner_blocks() {
		$context = block_core_tabs_provide_context( array(), array( 'blockName' => 'core/tabs' ) );

		$this->assertSame( array(), $context['core/tabs-list'] );
		$this->assertMatchesRegularExpression( '/^tabs_\d+$/', $context['core/tabs-id'] );
	}

	/**
	 * Tests that the context is provided when a block is rendered.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_provide_context
	 */
	public function test_should_be_hooked_to_the_context_of_the_blocks() {
		$this->assertSame( 10, has_filter( 'render_block_context', 'block_core_tabs_provide_context' ) );
	}

	/**
	 * Renders the Tabs block with a context.
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Context of the block.
	 * @param string $content    Content of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $context, $content = '<div class="wp-block-tabs"><p>Inner</p></div>' ) {
		$block          = new WP_Block( $this->parsed_block( 'core/tabs', $attributes ) );
		$block->context = $context;

		return block_core_tabs_render_block_callback( $attributes, $content, $block );
	}

	/**
	 * Tests that nothing is rendered without an ID of the tabs.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 *
	 * @dataProvider data_contexts_without_an_id
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_render_nothing_without_an_id_of_the_tabs( $context ) {
		$this->assertSame( '', $this->render( array(), $context ) );
		$this->assertSame( array(), wp_interactivity_state( 'core/tabs' ), 'No state should be added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contexts_without_an_id() {
		return array(
			'an empty context' => array( array() ),
			'a null ID'        => array( array( 'core/tabs-id' => null ) ),
			'an empty ID'      => array( array( 'core/tabs-id' => '' ) ),
			'only a list'      => array( array( 'core/tabs-list' => array( 'tab-1' ) ) ),
		);
	}

	/**
	 * Tests the directives that are added to the block.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_add_the_directives_to_the_block() {
		$output = $this->render(
			array( 'activeTabIndex' => 2 ),
			array(
				'core/tabs-id'   => 'tabs_9',
				'core/tabs-list' => array( 'tabs_9-tab-1' ),
			)
		);

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'wp-block-tabs' ) ), 'The block should be rendered.' );

		$this->assertSame( 'core/tabs', $processor->get_attribute( 'data-wp-interactive' ) );
		$this->assertSame( '{"tabsId":"tabs_9","activeTabIndex":2}', $processor->get_attribute( 'data-wp-context' ) );
		$this->assertSame( 'callbacks.activateTabByHash', $processor->get_attribute( 'data-wp-init' ) );
		$this->assertSame( 'callbacks.activateTabByHash', $processor->get_attribute( 'data-wp-on-window--hashchange' ) );
		$this->assertSame( 'actions.handleTabKeyDown', $processor->get_attribute( 'data-wp-on--keydown' ) );
		$this->assertStringContainsString( '<p>Inner</p></div>', $output, 'The content should be kept.' );
	}

	/**
	 * Tests that the first tab is active by default.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_activate_the_first_tab_by_default() {
		$output = $this->render( array(), array( 'core/tabs-id' => 'tabs_9' ) );

		$this->assertStringContainsString( '{&quot;tabsId&quot;:&quot;tabs_9&quot;,&quot;activeTabIndex&quot;:0}', $output );
	}

	/**
	 * Tests that the state has the list of the tabs.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_add_the_list_of_the_tabs_to_the_state() {
		$this->render(
			array(),
			array(
				'core/tabs-id'   => 'tabs_9',
				'core/tabs-list' => array( 'tabs_9-tab-1', 'tabs_9-tab-2' ),
			)
		);
		$this->render(
			array(),
			array(
				'core/tabs-id'   => 'tabs_10',
				'core/tabs-list' => array( 'tabs_10-tab-1' ),
			)
		);

		$this->assertSame(
			array(
				'tabs_9'  => array( 'tabs_9-tab-1', 'tabs_9-tab-2' ),
				'tabs_10' => array( 'tabs_10-tab-1' ),
			),
			wp_interactivity_state( 'core/tabs' ),
			'Each Tabs block should have its own state.'
		);
	}

	/**
	 * Tests that the state has an empty list without a list in the context.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_add_an_empty_list_to_the_state_without_a_list() {
		$this->render( array(), array( 'core/tabs-id' => 'tabs_9' ) );

		$this->assertSame( array( 'tabs_9' => array() ), wp_interactivity_state( 'core/tabs' ) );
	}

	/**
	 * Tests that the content without the element of the block is not changed.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_not_change_the_content_without_the_element_of_the_block() {
		$content = '<div class="other"><p>Inner</p></div>';

		$this->assertSame( $content, $this->render( array(), array( 'core/tabs-id' => 'tabs_9' ), $content ) );
	}

	/**
	 * Tests that the directives are only added to the first element of the block.
	 *
	 * @ticket 66526
	 *
	 * @covers ::block_core_tabs_render_block_callback
	 */
	public function test_should_only_add_the_directives_to_the_first_element_of_the_block() {
		$output = $this->render( array(), array( 'core/tabs-id' => 'tabs_9' ), '<div class="wp-block-tabs"></div><div class="wp-block-tabs"></div>' );

		$this->assertSame( 1, substr_count( $output, 'data-wp-interactive' ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66526
	 *
	 * @covers ::register_block_core_tabs
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/tabs' );

		if ( $was_registered ) {
			unregister_block_type( 'core/tabs' );
		}

		register_block_core_tabs();
		$block_type = $registry->get_registered( 'core/tabs' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/tabs' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'block_core_tabs_render_block_callback', $block_type->render_callback );
	}
}
