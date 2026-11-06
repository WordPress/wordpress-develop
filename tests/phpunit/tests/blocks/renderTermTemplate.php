<?php
/**
 * Tests for the server-side rendering of the Term Template block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 *
 * @covers ::render_block_core_term_template
 */
class Tests_Blocks_RenderTermTemplate extends WP_UnitTestCase {

	/**
	 * Term IDs, keyed by a short name.
	 *
	 * @var int[]
	 */
	private static $terms = array();

	/**
	 * Whether the Term Template block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$names = array(
			'alpha'      => 'Alpha',
			'beta'       => 'Beta',
			'gamma'      => 'Gamma',
			'beta-child' => 'Beta Child',
		);

		foreach ( $names as $key => $name ) {
			self::$terms[ $key ] = $factory->category->create(
				array(
					'name'   => $name,
					'parent' => 'beta-child' === $key ? self::$terms['beta'] : 0,
				)
			);
		}

		self::$terms['beta-grandchild'] = $factory->category->create(
			array(
				'name'   => 'Beta Grandchild',
				'parent' => self::$terms['beta-child'],
			)
		);

		foreach ( array( 'Red', 'Green', 'Blue' ) as $name ) {
			self::$terms[ strtolower( $name ) ] = $factory->tag->create( array( 'name' => $name ) );
		}

		// Make one of the categories and one of the tags not empty.
		$post_id = $factory->post->create();
		wp_set_object_terms( $post_id, array( self::$terms['gamma'] ), 'category' );
		wp_set_object_terms( $post_id, array( self::$terms['green'] ), 'post_tag' );
	}

	public function set_up() {
		parent::set_up();

		$this->was_registered = WP_Block_Type_Registry::get_instance()->is_registered( 'core/term-template' );

		if ( ! $this->was_registered ) {
			register_block_core_term_template();
		}

		register_block_type(
			'tests/term-info',
			array(
				'uses_context'    => array( 'termId', 'taxonomy', 'termData' ),
				'render_callback' => static function ( $attributes, $content, $block ) {
					return sprintf(
						'[%d|%s|%s]',
						$block->context['termId'],
						$block->context['taxonomy'],
						$block->context['termData']->name
					);
				},
			)
		);
	}

	public function tear_down() {
		unregister_block_type( 'tests/term-info' );

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/term-template' );
		}

		parent::tear_down();
	}

	/**
	 * Creates a Term Template block with the query that is passed in its context.
	 *
	 * @param array $query      Value of the `termQuery` context.
	 * @param array $attributes Attributes of the block.
	 * @param bool  $with_info  Whether the block has an inner block that prints the term.
	 * @return WP_Block The block.
	 */
	private function get_block( $query, $attributes = array(), $with_info = true ) {
		$inner_blocks = array();

		if ( $with_info ) {
			$inner_blocks[] = array(
				'blockName'    => 'tests/term-info',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		return new WP_Block(
			array(
				'blockName'    => 'core/term-template',
				'attrs'        => $attributes,
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
			),
			array( 'termQuery' => $query )
		);
	}

	/**
	 * Gets the query of a block, with the values of the editor as defaults.
	 *
	 * @param array $query Values to use instead of the defaults.
	 * @return array The query.
	 */
	private function get_query( $query = array() ) {
		return array_merge(
			array(
				'perPage'    => 10,
				'order'      => 'asc',
				'orderBy'    => 'name',
				'hideEmpty'  => false,
				'taxonomy'   => 'category',
				'showNested' => false,
				'inherit'    => false,
			),
			$query
		);
	}

	/**
	 * Renders the block with the query that is passed.
	 *
	 * @param array $query      Query of the block.
	 * @param array $attributes Attributes of the block.
	 * @return string The rendered block.
	 */
	private function render( $query, $attributes = array() ) {
		$block = $this->get_block( $this->get_query( $query ), $attributes );

		return render_block_core_term_template( $block->attributes, '', $block );
	}

	/**
	 * Gets the names that are printed by the inner blocks of a rendered Term Template block.
	 *
	 * @param string $html Rendered block.
	 * @return string[] Names of the terms.
	 */
	private function get_names( $html ) {
		preg_match_all( '/\[\d+\|[a-z_]+\|([^\]]*)\]/', $html, $matches );

		return $matches[1];
	}

	/**
	 * Tests that nothing is rendered without a query.
	 *
	 * @ticket 66516
	 *
	 * @dataProvider data_contexts_without_a_query
	 *
	 * @param mixed $query Value of the `termQuery` context.
	 */
	public function test_should_render_nothing_without_a_query( $query ) {
		$block = $this->get_block( $query );

		$this->assertSame( '', render_block_core_term_template( $block->attributes, '', $block ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contexts_without_a_query() {
		return array(
			'an empty array' => array( array() ),
			'null'           => array( null ),
			'false'          => array( false ),
		);
	}

	/**
	 * Tests that a list item with the term classes is rendered for each term.
	 *
	 * @ticket 66516
	 */
	public function test_should_render_a_list_item_with_the_term_classes_for_each_term() {
		$output = $this->render( array( 'taxonomy' => 'post_tag' ) );

		$this->assertStringStartsWith( '<ul', $output );
		$this->assertStringEndsWith( '</ul>', $output );

		foreach ( array( 'blue', 'green', 'red' ) as $key ) {
			$id = self::$terms[ $key ];

			$this->assertStringContainsString( '<li class="wp-block-term term-' . $id . ' post_tag taxonomy-post_tag">', $output, "The item of the tag '$key' should have the term classes." );
		}

		$this->assertSame( 3, substr_count( $output, '<li ' ), 'There should be an item for each tag.' );
	}

	/**
	 * Tests that the inner blocks are rendered with the context of each term.
	 *
	 * @ticket 66516
	 */
	public function test_should_render_the_inner_blocks_with_the_context_of_each_term() {
		$output = $this->render( array( 'taxonomy' => 'post_tag' ) );

		$this->assertStringContainsString(
			sprintf( '<li class="wp-block-term term-%1$d post_tag taxonomy-post_tag">[%1$d|post_tag|Blue]</li>', self::$terms['blue'] ),
			$output
		);
		$this->assertSame( array( 'Blue', 'Green', 'Red' ), $this->get_names( $output ) );
	}

	/**
	 * Tests that each item is rendered with a block name that is not a registered block.
	 *
	 * @ticket 66516
	 */
	public function test_should_render_each_item_with_a_block_name_that_is_not_registered() {
		$names = array();

		add_filter(
			'render_block',
			static function ( $block_content, $parsed_block ) use ( &$names ) {
				$names[] = $parsed_block['blockName'];

				return $block_content;
			},
			10,
			2
		);

		$output = $this->render( array( 'taxonomy' => 'post_tag' ) );

		$this->assertSame( 1, substr_count( $output, '<ul' ), 'There should be a single list.' );
		$this->assertSame(
			array( 'tests/term-info', 'core/null', 'tests/term-info', 'core/null', 'tests/term-info', 'core/null' ),
			$names,
			'The block of each item should not use the name of the Term Template block.'
		);
	}

	/**
	 * Tests that the terms are limited, and ordered.
	 *
	 * @ticket 66516
	 *
	 * @dataProvider data_orders
	 *
	 * @param array    $query    Values of the query.
	 * @param string[] $expected Names of the terms that are expected.
	 */
	public function test_should_limit_and_order_the_terms( $query, $expected ) {
		$this->assertSame( $expected, $this->get_names( $this->render( array_merge( array( 'taxonomy' => 'post_tag' ), $query ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_orders() {
		return array(
			'name ascending'        => array(
				array(
					'orderBy' => 'name',
					'order'   => 'asc',
				),
				array( 'Blue', 'Green', 'Red' ),
			),
			'name descending'       => array(
				array(
					'orderBy' => 'name',
					'order'   => 'desc',
				),
				array( 'Red', 'Green', 'Blue' ),
			),
			'one term'              => array( array( 'perPage' => 1 ), array( 'Blue' ) ),
			'two terms, descending' => array(
				array(
					'perPage' => 2,
					'order'   => 'desc',
				),
				array( 'Red', 'Green' ),
			),
			'ordered by the count'  => array(
				array(
					'orderBy' => 'count',
					'order'   => 'desc',
					'perPage' => 1,
				),
				array( 'Green' ),
			),
		);
	}

	/**
	 * Tests that the empty terms are hidden when it is requested.
	 *
	 * @ticket 66516
	 */
	public function test_should_hide_the_empty_terms_when_it_is_requested() {
		$this->assertSame(
			array( 'Blue', 'Green', 'Red' ),
			$this->get_names(
				$this->render(
					array(
						'taxonomy'  => 'post_tag',
						'hideEmpty' => false,
					)
				)
			),
			'The empty terms should be shown.'
		);
		$this->assertSame(
			array( 'Green' ),
			$this->get_names(
				$this->render(
					array(
						'taxonomy'  => 'post_tag',
						'hideEmpty' => true,
					)
				)
			),
			'The empty terms should be hidden.'
		);
	}

	/**
	 * Tests that only the top level terms of a hierarchical taxonomy are shown without the nested terms.
	 *
	 * @ticket 66516
	 */
	public function test_should_only_show_the_top_level_terms_of_a_hierarchical_taxonomy_without_the_nested_terms() {
		$names = $this->get_names( $this->render( array( 'showNested' => false ) ) );

		$this->assertContains( 'Alpha', $names );
		$this->assertContains( 'Beta', $names );
		$this->assertNotContains( 'Beta Child', $names, 'A child should not be shown.' );
		$this->assertNotContains( 'Beta Grandchild', $names, 'A grandchild should not be shown.' );
	}

	/**
	 * Tests that the nested terms are shown when it is requested.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_the_nested_terms_when_it_is_requested() {
		$names = $this->get_names( $this->render( array( 'showNested' => true ) ) );

		foreach ( array( 'Alpha', 'Beta', 'Beta Child', 'Beta Grandchild', 'Gamma' ) as $name ) {
			$this->assertContains( $name, $names, "'$name' should be shown." );
		}
	}

	/**
	 * Tests that a non hierarchical taxonomy does not depend on the nested terms.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_all_the_terms_of_a_non_hierarchical_taxonomy_without_the_nested_terms() {
		$this->assertSame(
			array( 'Blue', 'Green', 'Red' ),
			$this->get_names(
				$this->render(
					array(
						'taxonomy'   => 'post_tag',
						'showNested' => false,
					)
				)
			)
		);
	}

	/**
	 * Tests that the terms that are included are shown in the order they are included, without duplicates.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_the_included_terms_in_the_order_they_are_included() {
		$names = $this->get_names(
			$this->render(
				array(
					'include'    => array( self::$terms['gamma'], (string) self::$terms['alpha'], self::$terms['gamma'] ),
					'orderBy'    => 'name',
					'order'      => 'desc',
					'showNested' => false,
				)
			)
		);

		$this->assertSame( array( 'Gamma', 'Alpha' ), $names );
	}

	/**
	 * Tests that the included terms ignore the nested terms setting.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_the_included_nested_terms_without_the_nested_terms_setting() {
		$names = $this->get_names(
			$this->render(
				array(
					'include'    => array( self::$terms['beta-child'] ),
					'showNested' => false,
				)
			)
		);

		$this->assertSame( array( 'Beta Child' ), $names );
	}

	/**
	 * Tests that nothing is rendered when there are no terms.
	 *
	 * @ticket 66516
	 */
	public function test_should_render_nothing_without_terms() {
		$this->assertSame(
			'',
			$this->render(
				array(
					'taxonomy' => 'category',
					'include'  => array( 999999 ),
				)
			)
		);
	}

	/**
	 * Tests that nothing is rendered when the taxonomy does not exist.
	 *
	 * @ticket 66516
	 */
	public function test_should_render_nothing_for_a_taxonomy_that_does_not_exist() {
		$this->assertSame( '', $this->render( array( 'taxonomy' => 'does_not_exist' ) ) );
	}

	/**
	 * Tests the link color class of the list.
	 *
	 * @ticket 66516
	 *
	 * @dataProvider data_link_colors
	 *
	 * @param array $attributes Attributes of the block.
	 * @param bool  $expected   Whether the class is expected.
	 */
	public function test_should_add_the_link_color_class_only_with_a_link_color( $attributes, $expected ) {
		$output = $this->render( array( 'taxonomy' => 'post_tag' ), $attributes );

		$list_tag = substr( $output, 0, strpos( $output, '>' ) + 1 );

		if ( $expected ) {
			$this->assertStringContainsString( 'has-link-color', $list_tag );
		} else {
			$this->assertStringNotContainsString( 'has-link-color', $list_tag );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_colors() {
		return array(
			'a link color'          => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), true ),
			'a link color variable' => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => 'var:preset|color|primary' ) ) ) ) ), true ),
			'no style'              => array( array(), false ),
			'another element'       => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), false ),
			'a link background'     => array( array( 'style' => array( 'elements' => array( 'link' => array( 'color' => array( 'background' => '#ff0000' ) ) ) ) ), false ),
		);
	}

	/**
	 * Tests that the context filter is only added while the inner blocks are rendered.
	 *
	 * @ticket 66516
	 */
	public function test_should_remove_the_context_filter_after_the_inner_blocks_are_rendered() {
		global $wp_filter;

		$before = isset( $wp_filter['render_block_context'] ) ? count( $wp_filter['render_block_context']->callbacks ) : 0;

		$this->render( array( 'taxonomy' => 'post_tag' ) );

		$after = isset( $wp_filter['render_block_context'] ) ? count( $wp_filter['render_block_context']->callbacks ) : 0;

		$this->assertSame( $before, $after );
	}

	/**
	 * Tests that the other context filters have access to the context of the term.
	 *
	 * @ticket 66516
	 */
	public function test_should_pass_the_context_of_the_term_to_the_other_context_filters() {
		$contexts = array();

		add_filter(
			'render_block_context',
			static function ( $context, $parsed_block ) use ( &$contexts ) {
				if ( 'tests/term-info' === $parsed_block['blockName'] ) {
					$contexts[] = $context;
				}

				return $context;
			},
			10,
			2
		);

		$this->render(
			array(
				'taxonomy' => 'post_tag',
				'perPage'  => 2,
			)
		);

		$this->assertCount( 2, $contexts );
		$this->assertSame( self::$terms['blue'], $contexts[0]['termId'] );
		$this->assertSame( 'post_tag', $contexts[0]['taxonomy'] );
		$this->assertInstanceOf( WP_Term::class, $contexts[0]['termData'] );
		$this->assertSame( self::$terms['green'], $contexts[1]['termId'] );
	}

	/**
	 * Tests that the terms of the archive are used when the query inherits it.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_the_children_of_the_current_term_when_it_inherits_a_hierarchical_archive() {
		$this->go_to( get_term_link( self::$terms['beta'], 'category' ) );

		$names = $this->get_names(
			$this->render(
				array(
					'inherit'    => true,
					'taxonomy'   => 'post_tag',
					'showNested' => false,
				)
			)
		);

		$this->assertSame( array( 'Beta Child' ), $names, 'Only the direct children of the current term should be shown.' );
	}

	/**
	 * Tests that the nested terms of the archive are used when it is requested.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_all_the_descendants_of_the_current_term_when_it_inherits_and_shows_the_nested_terms() {
		$this->go_to( get_term_link( self::$terms['beta'], 'category' ) );

		$names = $this->get_names(
			$this->render(
				array(
					'inherit'    => true,
					'taxonomy'   => 'post_tag',
					'showNested' => true,
				)
			)
		);

		$this->assertSame( array( 'Beta Child', 'Beta Grandchild' ), $names );
	}

	/**
	 * Tests that all the terms of a non hierarchical archive are shown when the query inherits it.
	 *
	 * @ticket 66516
	 */
	public function test_should_show_all_the_terms_when_it_inherits_a_non_hierarchical_archive() {
		$this->go_to( get_term_link( self::$terms['red'], 'post_tag' ) );

		$names = $this->get_names(
			$this->render(
				array(
					'inherit'    => true,
					'taxonomy'   => 'category',
					'showNested' => false,
				)
			)
		);

		$this->assertSame( array( 'Blue', 'Green', 'Red' ), $names );
	}

	/**
	 * Tests that the taxonomy of the block is used when the query inherits a page that is not an archive.
	 *
	 * @ticket 66516
	 */
	public function test_should_use_the_taxonomy_of_the_block_when_it_inherits_a_page_that_is_not_an_archive() {
		$this->go_to( home_url( '/' ) );

		$names = $this->get_names(
			$this->render(
				array(
					'inherit'  => true,
					'taxonomy' => 'post_tag',
				)
			)
		);

		$this->assertSame( array( 'Blue', 'Green', 'Red' ), $names );
	}

	/**
	 * Tests that the archive is not used when the query does not inherit it.
	 *
	 * @ticket 66516
	 */
	public function test_should_not_use_the_archive_when_it_does_not_inherit_it() {
		$this->go_to( get_term_link( self::$terms['beta'], 'category' ) );

		$names = $this->get_names(
			$this->render(
				array(
					'inherit'  => false,
					'taxonomy' => 'post_tag',
				)
			)
		);

		$this->assertSame( array( 'Blue', 'Green', 'Red' ), $names );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66516
	 *
	 * @covers ::register_block_core_term_template
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/term-template' );

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_term_template', $block_type->render_callback );
	}
}
