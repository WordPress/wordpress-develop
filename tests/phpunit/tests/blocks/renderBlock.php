<?php
/**
 * Tests for render block functions.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 *
 * @group blocks
 */
class Tests_Blocks_RenderBlock extends WP_UnitTestCase {

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		global $post;

		parent::set_up();

		$args = array(
			'post_content' => 'example',
			'post_excerpt' => '',
		);

		$post = self::factory()->post->create_and_get( $args );
		setup_postdata( $post );
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		// Removes test block types registered by test cases.
		$block_types = WP_Block_Type_Registry::get_instance()->get_all_registered();
		foreach ( $block_types as $block_type ) {
			$block_name = $block_type->name;
			if ( str_starts_with( $block_name, 'tests/' ) ) {
				unregister_block_type( $block_name );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that a block which provides context makes that context available to
	 * its inner blocks.
	 *
	 * @ticket 49927
	 *
	 * @covers ::register_block_type
	 * @covers ::render_block
	 */
	public function test_provides_block_context() {
		$provided_context = array();

		register_block_type(
			'tests/context-provider',
			array(
				'attributes'       => array(
					'contextWithAssigned'   => array(
						'type' => 'number',
					),
					'contextWithDefault'    => array(
						'type'    => 'number',
						'default' => 0,
					),
					'contextWithoutDefault' => array(
						'type' => 'number',
					),
					'contextNotRequested'   => array(
						'type' => 'number',
					),
				),
				'provides_context' => array(
					'tests/contextWithAssigned'   => 'contextWithAssigned',
					'tests/contextWithDefault'    => 'contextWithDefault',
					'tests/contextWithoutDefault' => 'contextWithoutDefault',
					'tests/contextNotRequested'   => 'contextNotRequested',
				),
			)
		);

		register_block_type(
			'tests/context-consumer',
			array(
				'uses_context'    => array(
					'tests/contextWithDefault',
					'tests/contextWithAssigned',
					'tests/contextWithoutDefault',
				),
				'render_callback' => static function ( $attributes, $content, $block ) use ( &$provided_context ) {
					$provided_context[] = $block->context;

					return '';
				},
			)
		);

		$parsed_blocks = parse_blocks(
			'<!-- wp:tests/context-provider {"contextWithAssigned":10} -->' .
			'<!-- wp:tests/context-consumer /-->' .
			'<!-- /wp:tests/context-provider -->'
		);

		render_block( $parsed_blocks[0] );

		$this->assertSame(
			array(
				'tests/contextWithDefault'  => 0,
				'tests/contextWithAssigned' => 10,
			),
			$provided_context[0]
		);
	}

	/**
	 * Tests that a block can receive default-provided context through
	 * render_block.
	 *
	 * @ticket 49927
	 *
	 * @covers ::register_block_type
	 * @covers ::render_block
	 */
	public function test_provides_default_context() {
		global $post;

		$provided_context = array();

		register_block_type(
			'tests/context-consumer',
			array(
				'uses_context'    => array( 'postId', 'postType' ),
				'render_callback' => static function ( $attributes, $content, $block ) use ( &$provided_context ) {
					$provided_context[] = $block->context;

					return '';
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:tests/context-consumer /-->' );

		render_block( $parsed_blocks[0] );

		$this->assertSame(
			array(
				'postId'   => $post->ID,
				'postType' => $post->post_type,
			),
			$provided_context[0]
		);
	}

	/**
	 * Tests that default block context can be filtered.
	 *
	 * @ticket 49927
	 *
	 * @covers ::register_block_type
	 * @covers ::render_block
	 */
	public function test_default_context_is_filterable() {
		$provided_context = array();

		register_block_type(
			'tests/context-consumer',
			array(
				'uses_context'    => array( 'example' ),
				'render_callback' => static function ( $attributes, $content, $block ) use ( &$provided_context ) {
					$provided_context[] = $block->context;

					return '';
				},
			)
		);

		$filter_block_context = static function ( $context ) {
			$context['example'] = 'ok';
			return $context;
		};

		$parsed_blocks = parse_blocks( '<!-- wp:tests/context-consumer /-->' );

		add_filter( 'render_block_context', $filter_block_context );

		render_block( $parsed_blocks[0] );

		remove_filter( 'render_block_context', $filter_block_context );

		$this->assertSame( array( 'example' => 'ok' ), $provided_context[0] );
	}

	/**
	 * Tests the behavior of the 'render_block_context' filter based on the location of the filtered block.
	 *
	 * @ticket 62046
	 */
	public function test_render_block_context_inner_blocks() {
		$provided_context = array();

		register_block_type(
			'tests/context-provider',
			array(
				'provides_context' => array( 'example' ),
			)
		);

		register_block_type(
			'tests/context-consumer',
			array(
				'uses_context'    => array( 'example' ),
				'render_callback' => static function ( $attributes, $content, $block ) use ( &$provided_context ) {
					$provided_context = $block->context;

					return '';
				},
			)
		);

		// Filter the context provided by the test block.
		add_filter(
			'render_block_context',
			function ( $context, $parsed_block ) {
				if ( isset( $parsed_block['blockName'] ) && 'tests/context-provider' === $parsed_block['blockName'] ) {
					$context['example'] = 'ok';
				}

				return $context;
			},
			10,
			2
		);

		// Test inner block context when the provider block is a top-level block.
		do_blocks(
			<<<HTML
<!-- wp:tests/context-provider -->
<!-- wp:tests/context-consumer /-->
<!-- /wp:tests/context-provider -->
HTML
		);
		$this->assertTrue( isset( $provided_context['example'] ), 'Test block is top-level block: Context should include "example"' );
		$this->assertSame( 'ok', $provided_context['example'], 'Test block is top-level block: "example" in context should be "ok"' );

		// Test inner block context when the provider block is an inner block.
		do_blocks(
			<<<HTML
<!-- wp:group {"layout":{"type":"constrained"}} -->
<!-- wp:tests/context-provider -->
<!-- wp:tests/context-consumer /-->
<!-- /wp:tests/context-provider -->
<!-- /wp:group -->
HTML
		);
		$this->assertTrue( isset( $provided_context['example'] ), 'Test block is inner block: Block context should include "example"' );
		$this->assertSame( 'ok', $provided_context['example'], 'Test block is inner block: "example" in context should be "ok"' );
	}
}
