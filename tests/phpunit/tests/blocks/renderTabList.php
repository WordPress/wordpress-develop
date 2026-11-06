<?php
/**
 * Tests for the server-side rendering of the Tab List block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderTabList extends WP_UnitTestCase {

	const CONTENT = '<div class="wp-block-tab-list"><button class="first">One</button><button class="second">Two</button><button class="third">Three</button></div>';

	/**
	 * Renders the block.
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Context of the block.
	 * @param string $content    Content of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array(), $content = self::CONTENT ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/tab-list',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = $context;

		return block_core_tab_list_render_callback( $attributes, $content, $block );
	}

	/**
	 * Gets the attributes of the elements of a tag of the output.
	 *
	 * @param string $output Rendered block.
	 * @param string $tag    Name of the tag.
	 * @return array[] The attributes of each element, keyed by name.
	 */
	private function get_attributes_of( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );
		$elements  = array();

		while ( $processor->next_tag( $tag ) ) {
			$attributes = array();
			foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = $processor->get_attribute( $name );
			}
			$elements[] = $attributes;
		}

		return $elements;
	}

	/**
	 * Tests the label of the list.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected label.
	 */
	public function test_should_add_the_label_of_the_list( $attributes, $expected ) {
		$lists = $this->get_attributes_of( $this->render( $attributes ), 'div' );

		$this->assertSame( $expected, $lists[0]['aria-label'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'the default label'     => array( array(), 'Tabbed content' ),
			'an empty label'        => array( array( 'ariaLabel' => '' ), 'Tabbed content' ),
			'a null label'          => array( array( 'ariaLabel' => null ), 'Tabbed content' ),
			'a custom label'        => array( array( 'ariaLabel' => 'Product details' ), 'Product details' ),
			'a label with markup'   => array( array( 'ariaLabel' => 'Product <b>details</b>' ), 'Product details' ),
			'a label with a script' => array( array( 'ariaLabel' => 'Details<script>alert( 1 );</script>' ), 'Details' ),
		);
	}

	/**
	 * Tests that the label is escaped in the markup.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_escape_the_label_in_the_markup() {
		$output = $this->render( array( 'ariaLabel' => 'Say "hi" & go' ) );

		$this->assertStringContainsString( 'aria-label="Say &quot;hi&quot; &amp; go"', $output );
	}

	/**
	 * Tests that the label is the only change without tabs.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_only_add_the_label_without_tabs() {
		$lists = $this->get_attributes_of( $this->render(), 'div' );

		$this->assertEqualsCanonicalizing(
			array(
				'class'      => 'wp-block-tab-list',
				'aria-label' => 'Tabbed content',
			),
			$lists[0]
		);
		$this->assertCount( 2, $lists[0], 'The list should only have a class and a label.' );
	}

	/**
	 * Tests that the content without the list is not given a label.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_not_add_a_label_without_the_element_of_the_list() {
		$content = '<div class="other"><button>One</button></div>';

		$this->assertSame( $content, $this->render( array(), array(), $content ) );
	}

	/**
	 * Tests the attributes that are added to the buttons.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_add_the_attributes_to_the_buttons() {
		$output  = $this->render( array(), array( 'core/tabs-list' => array( 'tabs_1-tab-1', 'tabs_1-tab-2', 'custom' ) ) );
		$buttons = $this->get_attributes_of( $output, 'button' );

		$this->assertCount( 3, $buttons );

		$ids = array( 'tabs_1-tab-1', 'tabs_1-tab-2', 'custom' );
		foreach ( $ids as $index => $tab_id ) {
			$this->assertSame( 'tab__' . $tab_id, $buttons[ $index ]['id'], "The button $index should have an ID." );
			$this->assertSame( $tab_id, $buttons[ $index ]['aria-controls'], "The button $index should control its panel." );
			$this->assertSame( 'actions.handleTabClick', $buttons[ $index ]['data-wp-on--click'] );
			$this->assertSame( 'actions.handleTabKeyDown', $buttons[ $index ]['data-wp-on--keydown'] );
			$this->assertSame( 'state.isActiveTab', $buttons[ $index ]['data-wp-bind--aria-selected'] );
			$this->assertSame( 'state.tabIndexAttribute', $buttons[ $index ]['data-wp-bind--tabindex'] );
			$this->assertSame( '{"tabIndex":' . $index . '}', $buttons[ $index ]['data-wp-context'], "The button $index should have its index in its context." );
		}
	}

	/**
	 * Tests that the other attributes of the buttons are kept.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_keep_the_attributes_and_the_text_of_the_buttons() {
		$output  = $this->render( array(), array( 'core/tabs-list' => array( 'a', 'b', 'c' ) ) );
		$buttons = $this->get_attributes_of( $output, 'button' );

		$this->assertSame( 'first', $buttons[0]['class'] );
		$this->assertSame( 'second', $buttons[1]['class'] );
		$this->assertSame( 'third', $buttons[2]['class'] );
		$this->assertStringContainsString( '>One</button>', $output );
		$this->assertStringContainsString( '>Three</button>', $output );
	}

	/**
	 * Tests that the buttons that have no tab are not changed.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_not_change_the_buttons_that_have_no_tab() {
		$output  = $this->render( array(), array( 'core/tabs-list' => array( 'tab-1', 'tab-2' ) ) );
		$buttons = $this->get_attributes_of( $output, 'button' );

		$this->assertArrayHasKey( 'id', $buttons[1] );
		$this->assertSame( array( 'class' => 'third' ), $buttons[2], 'The third button should not be changed.' );
	}

	/**
	 * Tests that a list that has more tabs than buttons is handled.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 */
	public function test_should_handle_more_tabs_than_buttons() {
		$output  = $this->render( array(), array( 'core/tabs-list' => array( 'a', 'b', 'c', 'd', 'e' ) ) );
		$buttons = $this->get_attributes_of( $output, 'button' );

		$this->assertCount( 3, $buttons );
		$this->assertSame( 'tab__c', $buttons[2]['id'] );
	}

	/**
	 * Tests that the buttons are not changed without a list of the tabs.
	 *
	 * @ticket 66528
	 *
	 * @covers ::block_core_tab_list_render_callback
	 *
	 * @dataProvider data_contexts_without_tabs
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_not_change_the_buttons_without_a_list_of_the_tabs( $context ) {
		$buttons = $this->get_attributes_of( $this->render( array(), $context ), 'button' );

		$this->assertSame(
			array(
				array( 'class' => 'first' ),
				array( 'class' => 'second' ),
				array( 'class' => 'third' ),
			),
			$buttons
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contexts_without_tabs() {
		return array(
			'an empty context' => array( array() ),
			'an empty list'    => array( array( 'core/tabs-list' => array() ) ),
			'a null list'      => array( array( 'core/tabs-list' => null ) ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66528
	 *
	 * @covers ::register_block_core_tab_list
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/tab-list' );

		if ( $was_registered ) {
			unregister_block_type( 'core/tab-list' );
		}

		register_block_core_tab_list();
		$block_type = $registry->get_registered( 'core/tab-list' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/tab-list' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'block_core_tab_list_render_callback', $block_type->render_callback );
	}
}
