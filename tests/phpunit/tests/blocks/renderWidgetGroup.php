<?php
/**
 * Tests for the server-side rendering of the Widget Group block, and the functions that note the sidebar being rendered.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group widgets
 */
class Tests_Blocks_RenderWidgetGroup extends WP_UnitTestCase {

	/**
	 * Original value of the registered sidebars.
	 *
	 * @var array
	 */
	private $original_sidebars;

	/**
	 * Original value of the sidebar being rendered.
	 *
	 * @var mixed
	 */
	private $original_sidebar_being_rendered;

	public function set_up() {
		parent::set_up();

		global $wp_registered_sidebars, $_sidebar_being_rendered;

		$this->original_sidebars               = $wp_registered_sidebars;
		$this->original_sidebar_being_rendered = isset( $_sidebar_being_rendered ) ? $_sidebar_being_rendered : null;

		$wp_registered_sidebars = array();
		unset( $GLOBALS['_sidebar_being_rendered'] );
	}

	public function tear_down() {
		global $wp_registered_sidebars;

		$wp_registered_sidebars = $this->original_sidebars;

		if ( null === $this->original_sidebar_being_rendered ) {
			unset( $GLOBALS['_sidebar_being_rendered'] );
		} else {
			$GLOBALS['_sidebar_being_rendered'] = $this->original_sidebar_being_rendered;
		}

		parent::tear_down();
	}

	/**
	 * Creates a Widget Group block with inner blocks that only contain HTML.
	 *
	 * @param array    $attributes Attributes of the block.
	 * @param string[] $inner_html HTML of each inner block.
	 * @return WP_Block The block.
	 */
	private function get_block( $attributes = array(), $inner_html = array() ) {
		$inner_blocks = array();

		foreach ( $inner_html as $html ) {
			$inner_blocks[] = array(
				'blockName'    => null,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			);
		}

		return new WP_Block(
			array(
				'blockName'    => 'core/widget-group',
				'attrs'        => $attributes,
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
			)
		);
	}

	/**
	 * Registers a sidebar.
	 *
	 * @param string $id Sidebar ID.
	 */
	private function register_test_sidebar( $id ) {
		register_sidebar(
			array(
				'id'           => $id,
				'name'         => 'Sidebar ' . $id,
				'before_title' => '<h3 class="custom-title">',
				'after_title'  => '</h3>',
			)
		);
	}

	/**
	 * Tests that a title uses the default heading when no sidebar is being rendered.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_use_the_default_heading_for_the_title_without_a_sidebar() {
		$block = $this->get_block( array( 'title' => 'My group' ) );

		$this->assertSame(
			'<h2 class="widget-title">My group</h2><div class="wp-widget-group__inner-blocks"></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that the title uses the markup of the sidebar being rendered.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_use_the_title_markup_of_the_sidebar_being_rendered() {
		$this->register_test_sidebar( 'group-sidebar' );
		$GLOBALS['_sidebar_being_rendered'] = 'group-sidebar';

		$block = $this->get_block( array( 'title' => 'My group' ) );

		$this->assertSame(
			'<h3 class="custom-title">My group</h3><div class="wp-widget-group__inner-blocks"></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that the default heading is used when the sidebar being rendered is not registered.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_use_the_default_heading_when_the_sidebar_being_rendered_is_not_registered() {
		$this->register_test_sidebar( 'group-sidebar' );
		$GLOBALS['_sidebar_being_rendered'] = 'unknown-sidebar';

		$block = $this->get_block( array( 'title' => 'My group' ) );

		$this->assertSame(
			'<h2 class="widget-title">My group</h2><div class="wp-widget-group__inner-blocks"></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that the title is escaped.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_escape_the_title() {
		$block = $this->get_block( array( 'title' => '<script>alert( "x" );</script> & more' ) );

		$this->assertSame(
			'<h2 class="widget-title">&lt;script&gt;alert( &quot;x&quot; );&lt;/script&gt; &amp; more</h2><div class="wp-widget-group__inner-blocks"></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that no heading is rendered without a title.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 *
	 * @dataProvider data_attributes_without_a_title
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_not_render_a_heading_without_a_title( $attributes ) {
		$block = $this->get_block( $attributes );

		// The attributes are passed as they are, because the block removes the ones that are null when it prepares them.
		$this->assertSame(
			'<div class="wp-widget-group__inner-blocks"></div>',
			render_block_core_widget_group( $attributes, '', $block )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_a_title() {
		return array(
			'no attributes' => array( array() ),
			'empty title'   => array( array( 'title' => '' ) ),
			'null title'    => array( array( 'title' => null ) ),
		);
	}

	/**
	 * Tests that the inner blocks are rendered in order inside the wrapper.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_render_the_inner_blocks_in_order_inside_the_wrapper() {
		$block = $this->get_block( array( 'title' => 'Group' ), array( '<p>One</p>', '<p>Two</p>', '<p>Three</p>' ) );

		$this->assertSame(
			'<h2 class="widget-title">Group</h2><div class="wp-widget-group__inner-blocks"><p>One</p><p>Two</p><p>Three</p></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that the content that is passed is not used.
	 *
	 * @ticket 66514
	 *
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_ignore_the_content_that_is_passed() {
		$block = $this->get_block( array(), array( '<p>Inner</p>' ) );

		$this->assertSame(
			'<div class="wp-widget-group__inner-blocks"><p>Inner</p></div>',
			render_block_core_widget_group( $block->attributes, '<p>Ignored</p>', $block )
		);
	}

	/**
	 * Tests that the block is rendered by the dynamic sidebar with the markup of that sidebar.
	 *
	 * @ticket 66514
	 *
	 * @covers ::note_sidebar_being_rendered
	 * @covers ::render_block_core_widget_group
	 */
	public function test_should_use_the_markup_of_the_sidebar_when_rendered_from_a_dynamic_sidebar() {
		$this->register_test_sidebar( 'group-sidebar' );

		$block = $this->get_block( array( 'title' => 'My group' ), array( '<p>Inner</p>' ) );

		do_action( 'dynamic_sidebar_before', 'group-sidebar', true );

		$this->assertSame(
			'<h3 class="custom-title">My group</h3><div class="wp-widget-group__inner-blocks"><p>Inner</p></div>',
			render_block_core_widget_group( $block->attributes, '', $block )
		);
	}

	/**
	 * Tests that the sidebar index is noted.
	 *
	 * @ticket 66514
	 *
	 * @covers ::note_sidebar_being_rendered
	 *
	 * @dataProvider data_sidebar_indexes
	 *
	 * @param int|string $index Index, name or ID of the sidebar.
	 */
	public function test_should_note_the_sidebar_being_rendered( $index ) {
		note_sidebar_being_rendered( $index );

		$this->assertSame( $index, $GLOBALS['_sidebar_being_rendered'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_sidebar_indexes() {
		return array(
			'an ID'  => array( 'sidebar-1' ),
			'a name' => array( 'Main Sidebar' ),
			'an int' => array( 2 ),
		);
	}

	/**
	 * Tests that the latest sidebar being rendered is the one that is noted.
	 *
	 * @ticket 66514
	 *
	 * @covers ::note_sidebar_being_rendered
	 */
	public function test_should_replace_the_noted_sidebar() {
		note_sidebar_being_rendered( 'first' );
		note_sidebar_being_rendered( 'second' );

		$this->assertSame( 'second', $GLOBALS['_sidebar_being_rendered'] );
	}

	/**
	 * Tests that the functions are hooked to the start and the end of a dynamic sidebar.
	 *
	 * @ticket 66514
	 *
	 * @covers ::note_sidebar_being_rendered
	 * @covers ::discard_sidebar_being_rendered
	 */
	public function test_should_hook_the_functions_to_the_dynamic_sidebar_actions() {
		$this->assertSame( 10, has_action( 'dynamic_sidebar_before', 'note_sidebar_being_rendered' ), 'The sidebar should be noted before it is rendered.' );
		$this->assertSame( 10, has_action( 'dynamic_sidebar_after', 'discard_sidebar_being_rendered' ), 'The sidebar should be discarded after it was rendered.' );
	}

	/**
	 * Tests that the noted sidebar is set by the action before a dynamic sidebar.
	 *
	 * @ticket 66514
	 *
	 * @covers ::note_sidebar_being_rendered
	 */
	public function test_should_note_the_sidebar_on_the_dynamic_sidebar_before_action() {
		do_action( 'dynamic_sidebar_before', 'sidebar-9', true );

		$this->assertSame( 'sidebar-9', $GLOBALS['_sidebar_being_rendered'] );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66514
	 *
	 * @covers ::register_block_core_widget_group
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/widget-group' );

		if ( $was_registered ) {
			unregister_block_type( 'core/widget-group' );
		}

		register_block_core_widget_group();
		$block_type = $registry->get_registered( 'core/widget-group' );

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_widget_group', $block_type->render_callback );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/widget-group' );
		}
	}
}
