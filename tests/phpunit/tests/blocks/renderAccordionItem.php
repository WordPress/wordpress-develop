<?php
/**
 * Tests for the server-side rendering of the Accordion Item block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 *
 * @covers ::block_core_accordion_item_render
 */
class Tests_Blocks_RenderAccordionItem extends WP_UnitTestCase {

	/**
	 * Markup of an item with a heading and a panel.
	 *
	 * @var string
	 */
	const ITEM = '<div class="wp-block-accordion-item"><h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle">Title</button></h3><div class="wp-block-accordion-panel"><img src="a.jpg" alt=""><p>Text</p></div></div>';

	/**
	 * The original state of the interactivity API.
	 *
	 * @var array
	 */
	private $original_state;

	public function set_up() {
		parent::set_up();

		$property = new ReflectionProperty( WP_Interactivity_API::class, 'state_data' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$this->original_state = $property->getValue( wp_interactivity() );
	}

	public function tear_down() {
		$property = new ReflectionProperty( WP_Interactivity_API::class, 'state_data' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( wp_interactivity(), $this->original_state );

		parent::tear_down();
	}

	/**
	 * Returns the attributes of the first element with the class of the output.
	 *
	 * @param string $output     The output.
	 * @param string $class_name The class name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $class_name ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( array( 'class_name' => $class_name ) ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests the attributes of the item, the toggle and the panel.
	 *
	 * @ticket 66576
	 */
	public function test_should_add_the_attributes_of_the_interactivity() {
		$output = block_core_accordion_item_render( array( 'openByDefault' => false ), self::ITEM );

		$item   = $this->get_element( $output, 'wp-block-accordion-item' );
		$toggle = $this->get_element( $output, 'wp-block-accordion-heading__toggle' );
		$panel  = $this->get_element( $output, 'wp-block-accordion-panel' );
		$id     = $toggle['id'];

		$this->assertMatchesRegularExpression( '/^accordion-item-\d+$/', $id );
		$this->assertSame(
			array(
				'id'            => $id,
				'openByDefault' => false,
			),
			json_decode( $item['data-wp-context'], true )
		);
		$this->assertSame( 'state.isOpen', $item['data-wp-class--is-open'] );
		$this->assertSame( 'callbacks.initAccordionItems', $item['data-wp-init'] );
		$this->assertSame( 'callbacks.hashChange', $item['data-wp-on-window--hashchange'] );

		$this->assertSame( 'actions.toggle', $toggle['data-wp-on--click'] );
		$this->assertSame( $id . '-panel', $toggle['aria-controls'] );
		$this->assertSame( 'state.isOpen', $toggle['data-wp-bind--aria-expanded'] );

		$this->assertSame( $id . '-panel', $panel['id'] );
		$this->assertSame( $id, $panel['aria-labelledby'] );
		$this->assertSame( 'state.isHidden', $panel['data-wp-bind--hidden'] );
		$this->assertSame( 'actions.handleBeforeMatch', $panel['data-wp-on--beforematch'] );
	}

	/**
	 * Tests that the item that is open by default is in the context.
	 *
	 * @ticket 66576
	 */
	public function test_should_add_the_open_by_default_value_to_the_context() {
		$output = block_core_accordion_item_render( array( 'openByDefault' => true ), self::ITEM );
		$item   = $this->get_element( $output, 'wp-block-accordion-item' );

		$this->assertTrue( json_decode( $item['data-wp-context'], true )['openByDefault'] );
	}

	/**
	 * Tests that each item has an ID that is unique.
	 *
	 * @ticket 66576
	 */
	public function test_should_use_a_unique_id_for_each_item() {
		$first  = $this->get_element( block_core_accordion_item_render( array( 'openByDefault' => false ), self::ITEM ), 'wp-block-accordion-heading__toggle' );
		$second = $this->get_element( block_core_accordion_item_render( array( 'openByDefault' => false ), self::ITEM ), 'wp-block-accordion-heading__toggle' );

		$this->assertNotSame( $first['id'], $second['id'] );
	}

	/**
	 * Tests the state of the interactivity API for the open items.
	 *
	 * @ticket 66576
	 */
	public function test_should_add_the_state_for_the_open_items() {
		block_core_accordion_item_render( array( 'openByDefault' => false ), self::ITEM );

		$state = wp_interactivity_state( 'core/accordion' );

		$this->assertArrayHasKey( 'isOpen', $state );
		$this->assertInstanceOf( Closure::class, $state['isOpen'] );
	}

	/**
	 * Tests that the state of the item follows the value that is open by default when the directives are processed.
	 *
	 * @ticket 66576
	 *
	 * @dataProvider data_open_by_default
	 *
	 * @param bool   $open_by_default Whether the item is open by default.
	 * @param string $expected        The expected value of the aria-expanded attribute.
	 */
	public function test_should_set_the_state_from_the_open_by_default_value( $open_by_default, $expected ) {
		$output = block_core_accordion_item_render( array( 'openByDefault' => $open_by_default ), self::ITEM );
		$output = wp_interactivity()->process_directives( '<div data-wp-interactive="core/accordion">' . $output . '</div>' );

		$this->assertSame( $expected, $this->get_element( $output, 'wp-block-accordion-heading__toggle' )['aria-expanded'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_open_by_default() {
		return array(
			'open'   => array( true, 'true' ),
			'closed' => array( false, 'false' ),
		);
	}

	/**
	 * Tests that every image of a collapsed item gets a low fetch priority.
	 *
	 * @ticket 66576
	 */
	public function test_should_set_low_fetchpriority_on_every_image_of_a_collapsed_item() {
		$output = block_core_accordion_item_render( array( 'openByDefault' => false ), str_replace( '<p>Text</p>', '<img src="b.jpg" alt="">', self::ITEM ) );

		$this->assertSame( 2, substr_count( $output, 'fetchpriority="low"' ) );
	}

	/**
	 * Tests that a collapsed item gets a low fetch priority for the images.
	 *
	 * @ticket 66576
	 */
	public function test_should_set_low_fetchpriority_on_images_of_a_collapsed_item() {
		$output = block_core_accordion_item_render( array( 'openByDefault' => false ), str_replace( 'alt=""', 'alt="" fetchpriority="high"', self::ITEM ) );

		$this->assertStringContainsString( 'fetchpriority="low"', $output );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $output );
	}

	/**
	 * Tests that the images of an item that is open by default are not changed.
	 *
	 * @ticket 66576
	 */
	public function test_should_not_change_images_of_an_open_item() {
		$output = block_core_accordion_item_render( array( 'openByDefault' => true ), self::ITEM );

		$this->assertStringNotContainsString( 'fetchpriority', $output );
	}

	/**
	 * Tests that the content that is empty is returned.
	 *
	 * @ticket 66576
	 */
	public function test_should_return_empty_content() {
		$this->assertSame( '', block_core_accordion_item_render( array( 'openByDefault' => false ), '' ) );
	}

	/**
	 * Tests that the content is not changed when a part of the structure is missing.
	 *
	 * @ticket 66576
	 *
	 * @dataProvider data_incomplete_structures
	 *
	 * @param string $content The content.
	 */
	public function test_should_not_add_the_attributes_for_an_incomplete_structure( $content ) {
		$output = block_core_accordion_item_render( array( 'openByDefault' => true ), $content );

		$this->assertSame( $content, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_structures() {
		return array(
			'no item'   => array( '<div class="other"><button class="wp-block-accordion-heading__toggle"></button><div class="wp-block-accordion-panel"></div></div>' ),
			'no toggle' => array( '<div class="wp-block-accordion-item"><div class="wp-block-accordion-panel"></div></div>' ),
			'no panel'  => array( '<div class="wp-block-accordion-item"><button class="wp-block-accordion-heading__toggle"></button></div>' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66576
	 *
	 * @covers ::register_block_core_accordion_item
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/accordion-item' );

		if ( $original_block ) {
			unregister_block_type( 'core/accordion-item' );
		}

		try {
			register_block_core_accordion_item();
			$block_type = $registry->get_registered( 'core/accordion-item' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'block_core_accordion_item_render', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/accordion-item' ) ) {
				unregister_block_type( 'core/accordion-item' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
