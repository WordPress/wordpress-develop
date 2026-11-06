<?php
/**
 * Tests for the server-side rendering of the Tab Panel block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderTabPanel extends WP_UnitTestCase {

	/**
	 * Gets an ID of the tabs that is not used by another test, because the render callback counts the panels of each ID.
	 *
	 * @return string The ID.
	 */
	private function get_unique_tabs_id() {
		return 'tabs_test_' . wp_generate_uuid4();
	}

	/**
	 * Renders the block.
	 *
	 * @param string|null $tabs_id ID of the tabs of the context, or null for no ID.
	 * @param string      $content Content of the block.
	 * @return string The rendered block.
	 */
	private function render( $tabs_id, $content = '<div class="wp-block-tab-panel"><p>Panel</p></div>' ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/tab-panel',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $tabs_id ? array() : array( 'core/tabs-id' => $tabs_id );

		return block_core_tab_panel_render( array(), $content, $block );
	}

	/**
	 * Gets the attributes of the first panel of the output.
	 *
	 * @param string $output Rendered block.
	 * @return array|null The attributes, keyed by name, or null when there is no panel.
	 */
	private function get_panel_attributes( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-tab-panel' ) ) ) {
			return null;
		}

		$attributes = array();
		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that the panels get an ID from their position.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_add_an_id_from_the_position_of_the_panel() {
		$tabs_id = $this->get_unique_tabs_id();

		$first  = $this->get_panel_attributes( $this->render( $tabs_id ) );
		$second = $this->get_panel_attributes( $this->render( $tabs_id ) );
		$third  = $this->get_panel_attributes( $this->render( $tabs_id ) );

		$this->assertSame( $tabs_id . '-tab-1', $first['id'] );
		$this->assertSame( $tabs_id . '-tab-2', $second['id'] );
		$this->assertSame( $tabs_id . '-tab-3', $third['id'] );
	}

	/**
	 * Tests that each ID of the tabs has its own count.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_count_the_panels_of_each_tabs_block_separately() {
		$first_id  = $this->get_unique_tabs_id();
		$second_id = $this->get_unique_tabs_id();

		$this->render( $first_id );
		$this->render( $first_id );
		$panel = $this->get_panel_attributes( $this->render( $second_id ) );

		$this->assertSame( $second_id . '-tab-1', $panel['id'] );
	}

	/**
	 * Tests the ID of a panel without an ID of the tabs.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_add_an_id_without_the_id_of_the_tabs() {
		$first  = $this->get_panel_attributes( $this->render( null ) );
		$second = $this->get_panel_attributes( $this->render( null ) );

		$this->assertMatchesRegularExpression( '/^tab-(\d+)$/', $first['id'] );
		$this->assertMatchesRegularExpression( '/^tab-(\d+)$/', $second['id'] );

		preg_match( '/^tab-(\d+)$/', $first['id'], $first_number );
		preg_match( '/^tab-(\d+)$/', $second['id'], $second_number );

		$this->assertSame( (int) $first_number[1] + 1, (int) $second_number[1], 'The number should go up for each panel.' );
	}

	/**
	 * Tests that an empty ID of the tabs is handled as no ID.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_handle_an_empty_id_of_the_tabs_as_no_id() {
		$panel = $this->get_panel_attributes( $this->render( '' ) );

		$this->assertMatchesRegularExpression( '/^tab-\d+$/', $panel['id'] );
	}

	/**
	 * Tests that the ID of the panel is kept and is used by the label.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_keep_the_id_of_the_panel() {
		$panel = $this->get_panel_attributes( $this->render( $this->get_unique_tabs_id(), '<div class="wp-block-tab-panel" id="my-panel"><p>Panel</p></div>' ) );

		$this->assertSame( 'my-panel', $panel['id'] );
		$this->assertSame( 'tab__my-panel', $panel['aria-labelledby'] );
	}

	/**
	 * Tests that a panel with an ID is counted.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_count_a_panel_that_has_an_id() {
		$tabs_id = $this->get_unique_tabs_id();

		$this->render( $tabs_id, '<div class="wp-block-tab-panel" id="my-panel"></div>' );
		$panel = $this->get_panel_attributes( $this->render( $tabs_id ) );

		$this->assertSame( $tabs_id . '-tab-2', $panel['id'] );
	}

	/**
	 * Tests the label of the panel.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_label_the_panel_with_its_tab() {
		$tabs_id = $this->get_unique_tabs_id();
		$panel   = $this->get_panel_attributes( $this->render( $tabs_id ) );

		$this->assertSame( 'tab__' . $tabs_id . '-tab-1', $panel['aria-labelledby'] );
	}

	/**
	 * Tests the directives of the Interactivity API.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_add_the_directives_of_the_interactivity_api() {
		$panel = $this->get_panel_attributes( $this->render( $this->get_unique_tabs_id() ) );

		$this->assertSame( 'state.isHidden', $panel['data-wp-bind--hidden'] );
		$this->assertSame( 'state.tabIndexAttribute', $panel['data-wp-bind--tabindex'] );
		$this->assertSame( 'actions.handleBeforeMatch', $panel['data-wp-on--beforematch'] );
		$this->assertArrayNotHasKey( 'hidden', $panel, 'The panel should not be hidden by the server.' );
	}

	/**
	 * Tests that the content of the panel is kept.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_keep_the_content_of_the_panel() {
		$output = $this->render( $this->get_unique_tabs_id(), '<div class="wp-block-tab-panel other"><p>Panel</p><span>More</span></div>' );

		$panel = $this->get_panel_attributes( $output );

		$this->assertSame( 'wp-block-tab-panel other', $panel['class'], 'The classes should be kept.' );
		$this->assertStringEndsWith( '><p>Panel</p><span>More</span></div>', $output, 'The inner content should be kept.' );
	}

	/**
	 * Tests that the content without the element of the panel is not changed.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 *
	 * @dataProvider data_content_without_a_panel
	 *
	 * @param string $content Content of the block.
	 */
	public function test_should_not_change_the_content_without_the_element_of_the_panel( $content ) {
		$this->assertSame( $content, $this->render( $this->get_unique_tabs_id(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_without_a_panel() {
		return array(
			'an empty string' => array( '' ),
			'another element' => array( '<div class="other"><p>Panel</p></div>' ),
			'text'            => array( 'Text' ),
		);
	}

	/**
	 * Tests that only the first panel element is changed.
	 *
	 * @ticket 66527
	 *
	 * @covers ::block_core_tab_panel_render
	 */
	public function test_should_only_change_the_first_panel_element() {
		$output = $this->render( $this->get_unique_tabs_id(), '<div class="wp-block-tab-panel"></div><div class="wp-block-tab-panel"></div>' );

		$this->assertSame( 1, substr_count( $output, 'aria-labelledby' ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66527
	 *
	 * @covers ::register_block_core_tab_panel
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/tab-panel' );

		if ( $was_registered ) {
			unregister_block_type( 'core/tab-panel' );
		}

		register_block_core_tab_panel();
		$block_type = $registry->get_registered( 'core/tab-panel' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/tab-panel' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'block_core_tab_panel_render', $block_type->render_callback );
	}
}
