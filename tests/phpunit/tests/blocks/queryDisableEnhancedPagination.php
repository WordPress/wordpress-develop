<?php
/**
 * Tests for the function that disables the enhanced pagination of the Query block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 *
 * @covers ::block_core_query_disable_enhanced_pagination
 */
class Tests_Blocks_QueryDisableEnhancedPagination extends WP_UnitTestCase {

	/**
	 * Original Interactivity API instance.
	 *
	 * @var WP_Interactivity_API|null
	 */
	private $original_interactivity;

	/**
	 * Whether the Query block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	public function set_up() {
		global $wp_interactivity;

		parent::set_up();

		$this->original_interactivity = $wp_interactivity;
		$wp_interactivity             = new WP_Interactivity_API();

		$this->was_registered = WP_Block_Type_Registry::get_instance()->is_registered( 'core/query' );

		if ( ! $this->was_registered ) {
			register_block_core_query();
		}

		register_block_type(
			'tests/plugin-block',
			array(
				'render_callback' => static function () {
					return '<p>Plugin</p>';
				},
			)
		);
		register_block_type(
			'tests/interactive-block',
			array(
				'supports'        => array( 'interactivity' => true ),
				'render_callback' => static function () {
					return '<p>Interactive</p>';
				},
			)
		);
		register_block_type(
			'tests/navigation-block',
			array(
				'supports'        => array( 'interactivity' => array( 'clientNavigation' => true ) ),
				'render_callback' => static function () {
					return '<p>Navigation</p>';
				},
			)
		);
		register_block_type(
			'tests/other-interactive-block',
			array(
				'supports'        => array( 'interactivity' => array( 'clientNavigation' => false ) ),
				'render_callback' => static function () {
					return '<p>Other</p>';
				},
			)
		);
	}

	public function tear_down() {
		global $wp_interactivity;

		foreach ( array( 'tests/plugin-block', 'tests/interactive-block', 'tests/navigation-block', 'tests/other-interactive-block' ) as $name ) {
			unregister_block_type( $name );
		}

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/query' );
		}

		$wp_interactivity = $this->original_interactivity;

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
	 * Renders a Query block with inner blocks.
	 *
	 * @param array    $attrs        Attributes of the Query block.
	 * @param string[] $inner_blocks Names of the inner blocks.
	 */
	private function render_query( $attrs, $inner_blocks ) {
		$inner = array();

		foreach ( $inner_blocks as $name ) {
			$inner[] = $this->parsed_block( $name );
		}

		render_block( $this->parsed_block( 'core/query', $attrs, $inner ) );
	}

	/**
	 * Gets whether the client navigation was disabled in the configuration of the router.
	 *
	 * @return bool Whether the client navigation is disabled.
	 */
	private function is_client_navigation_disabled() {
		$config = wp_interactivity_config( 'core/router' );

		return ! empty( $config['clientNavigationDisabled'] );
	}

	/**
	 * Tests that the client navigation is disabled when a block of a plugin is inside the Query block.
	 *
	 * @ticket 66530
	 *
	 * @dataProvider data_inner_blocks_that_disable_the_navigation
	 *
	 * @param string[] $inner_blocks Names of the inner blocks.
	 */
	public function test_should_disable_the_client_navigation_with_a_block_that_does_not_support_it( $inner_blocks ) {
		$this->render_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			$inner_blocks
		);

		$this->assertTrue( $this->is_client_navigation_disabled() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inner_blocks_that_disable_the_navigation() {
		return array(
			'a block of a plugin'                         => array( array( 'tests/plugin-block' ) ),
			'a block that disables the client navigation' => array( array( 'tests/other-interactive-block' ) ),
			'a block of a plugin among others'            => array( array( 'tests/interactive-block', 'tests/plugin-block', 'tests/navigation-block' ) ),
		);
	}

	/**
	 * Tests that the client navigation is not disabled.
	 *
	 * @ticket 66530
	 *
	 * @dataProvider data_cases_that_keep_the_navigation
	 *
	 * @param array    $attrs        Attributes of the Query block.
	 * @param string[] $inner_blocks Names of the inner blocks.
	 */
	public function test_should_not_disable_the_client_navigation( $attrs, $inner_blocks ) {
		$this->render_query( $attrs, $inner_blocks );

		$this->assertFalse( $this->is_client_navigation_disabled() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cases_that_keep_the_navigation() {
		$enhanced = array(
			'enhancedPagination' => true,
			'queryId'            => 1,
		);

		return array(
			'a block without a name'               => array( $enhanced, array( null ) ),
			'no inner blocks'                      => array( $enhanced, array() ),
			'a block that supports interactivity'  => array( $enhanced, array( 'tests/interactive-block' ) ),
			'a block that supports the navigation' => array( $enhanced, array( 'tests/navigation-block' ) ),
			'the enhanced pagination is disabled'  => array(
				array(
					'enhancedPagination' => false,
					'queryId'            => 1,
				),
				array( 'tests/plugin-block' ),
			),
			'no query ID'                          => array( array( 'enhancedPagination' => true ), array( 'tests/plugin-block' ) ),
			'a query ID that is not scalar'        => array(
				array(
					'enhancedPagination' => true,
					'queryId'            => array( 1 ),
				),
				array( 'tests/plugin-block' ),
			),
			'no attributes'                        => array( array(), array( 'tests/plugin-block' ) ),
		);
	}

	/**
	 * Tests that a block of a plugin outside a Query block does not disable the client navigation.
	 *
	 * @ticket 66530
	 */
	public function test_should_not_disable_the_client_navigation_for_a_block_outside_a_query() {
		render_block( $this->parsed_block( 'tests/plugin-block' ) );

		$this->render_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			array()
		);

		$this->assertFalse( $this->is_client_navigation_disabled() );
	}

	/**
	 * Tests that a Query block does not change after another one that disabled the client navigation.
	 *
	 * @ticket 66530
	 */
	public function test_should_not_keep_the_state_of_a_query_block_that_was_rendered() {
		global $wp_interactivity;

		$attrs = array(
			'enhancedPagination' => true,
			'queryId'            => 1,
		);

		$this->render_query( $attrs, array( 'tests/plugin-block' ) );
		$this->assertTrue( $this->is_client_navigation_disabled(), 'The first block should disable the client navigation.' );

		$wp_interactivity = new WP_Interactivity_API();

		$this->render_query( $attrs, array() );
		$this->assertFalse( $this->is_client_navigation_disabled(), 'The second block should not disable the client navigation.' );
	}

	/**
	 * Tests that the client navigation is disabled for a Query block that has another one inside.
	 *
	 * @ticket 66530
	 */
	public function test_should_disable_the_client_navigation_for_nested_query_blocks() {
		$inner_query = $this->parsed_block(
			'core/query',
			array(
				'enhancedPagination' => true,
				'queryId'            => 2,
			),
			array( $this->parsed_block( 'tests/plugin-block' ) )
		);

		render_block(
			$this->parsed_block(
				'core/query',
				array(
					'enhancedPagination' => true,
					'queryId'            => 1,
				),
				array( $inner_query )
			)
		);

		$this->assertTrue( $this->is_client_navigation_disabled() );
	}

	/**
	 * Tests that the callback is removed after the Query block is rendered.
	 *
	 * @ticket 66530
	 */
	public function test_should_remove_the_filter_after_the_query_block_is_rendered() {
		$this->render_query(
			array(
				'enhancedPagination' => true,
				'queryId'            => 1,
			),
			array( 'tests/plugin-block' )
		);

		$this->assertFalse( has_filter( 'render_block_core/query' ) );
	}

	/**
	 * Tests that a block that is not a Query block does not add the callback.
	 *
	 * @ticket 66530
	 */
	public function test_should_not_add_the_callback_for_a_block_that_is_not_a_query_block() {
		block_core_query_disable_enhanced_pagination(
			$this->parsed_block(
				'tests/plugin-block',
				array(
					'enhancedPagination' => true,
					'queryId'            => 1,
				)
			)
		);

		$this->assertFalse( has_filter( 'render_block_core/query' ) );
	}

	/**
	 * Tests that the callback is removed after nested Query blocks are rendered.
	 *
	 * @ticket 66530
	 */
	public function test_should_remove_the_filter_after_nested_query_blocks_are_rendered() {
		$inner_query = $this->parsed_block(
			'core/query',
			array(
				'enhancedPagination' => true,
				'queryId'            => 2,
			)
		);

		render_block(
			$this->parsed_block(
				'core/query',
				array(
					'enhancedPagination' => true,
					'queryId'            => 1,
				),
				array( $inner_query )
			)
		);

		$this->assertFalse( has_filter( 'render_block_core/query' ) );
	}

	/**
	 * Tests that a Query block that is not enhanced does not affect the one that has it inside.
	 *
	 * @ticket 66530
	 */
	public function test_should_disable_the_client_navigation_with_a_query_block_that_is_not_enhanced_inside() {
		$inner_query = $this->parsed_block(
			'core/query',
			array(
				'enhancedPagination' => false,
				'queryId'            => 9,
			),
			array( $this->parsed_block( 'tests/plugin-block' ) )
		);

		render_block(
			$this->parsed_block(
				'core/query',
				array(
					'enhancedPagination' => true,
					'queryId'            => 1,
				),
				array( $inner_query )
			)
		);

		$this->assertTrue( $this->is_client_navigation_disabled() );
	}

	/**
	 * Tests that the parsed block is returned without changes, and that the function is hooked.
	 *
	 * @ticket 66530
	 */
	public function test_should_return_the_parsed_block_without_changes() {
		$parsed_block = $this->parsed_block( 'tests/plugin-block', array( 'a' => 'b' ) );

		$this->assertSame( $parsed_block, block_core_query_disable_enhanced_pagination( $parsed_block ) );
		$this->assertSame( 10, has_filter( 'render_block_data', 'block_core_query_disable_enhanced_pagination' ) );
	}
}
