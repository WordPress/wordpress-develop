<?php
/**
 * Tests for the server-side rendering of the Post Template block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderPostTemplate extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * IDs of the posts, oldest first.
	 *
	 * @var int[]
	 */
	private static $post_ids = array();

	/**
	 * Whether the Post Template block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	/**
	 * Original value of the page of the request.
	 *
	 * @var array
	 */
	private $original_get;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		foreach ( array( 'First', 'Second', 'Third' ) as $index => $title ) {
			self::$post_ids[] = $factory->post->create(
				array(
					'post_title' => $title,
					'post_date'  => '2023-01-0' . ( $index + 1 ) . ' 10:00:00',
				)
			);
		}
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		$this->original_get                 = $_GET;
		WP_Block_Supports::$block_to_render = null;

		$this->was_registered = WP_Block_Type_Registry::get_instance()->is_registered( 'core/post-template' );

		if ( ! $this->was_registered ) {
			register_block_core_post_template();
		}

		register_block_type(
			'tests/post-info',
			array(
				'uses_context'    => array( 'postId', 'postType' ),
				'render_callback' => static function ( $attributes, $content, $block ) {
					return sprintf( '[%d|%s|%s]', $block->context['postId'], $block->context['postType'], get_the_title( $block->context['postId'] ) );
				},
			)
		);
	}

	public function tear_down() {
		$_GET = $this->original_get;

		unregister_block_type( 'tests/post-info' );

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/post-template' );
		}

		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

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
			'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
		);
	}

	/**
	 * Gets the query of the block.
	 *
	 * @param array $query Values to use instead of the defaults.
	 * @return array The query.
	 */
	private function get_query( $query = array() ) {
		return array_merge(
			array(
				'perPage'  => 10,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => 'post',
				'order'    => 'asc',
				'orderBy'  => 'date',
				'inherit'  => false,
			),
			$query
		);
	}

	/**
	 * Renders the block with a block that prints the post inside.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Values to add to the context.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$block = new WP_Block(
			$this->parsed_block( 'core/post-template', $attributes, array( $this->parsed_block( 'tests/post-info' ) ) ),
			array_merge( array( 'query' => $this->get_query() ), $context )
		);

		$block->context = array_merge( array( 'query' => $this->get_query() ), $context );

		return render_block_core_post_template( $attributes, '', $block );
	}

	/**
	 * Gets the list of the titles that are printed by the inner blocks.
	 *
	 * @param string $html Rendered block.
	 * @return string[] The titles.
	 */
	private function get_titles( $html ) {
		preg_match_all( '/\[\d+\|[a-z_]+\|([^\]]*)\]/', $html, $matches );

		return $matches[1];
	}

	/**
	 * Tests that a block is found in the inner blocks.
	 *
	 * @ticket 66544
	 *
	 * @covers ::block_core_post_template_uses_featured_image
	 *
	 * @dataProvider data_inner_blocks
	 *
	 * @param array $inner_blocks Parsed inner blocks.
	 * @param bool  $expected     Whether the featured image is used.
	 */
	public function test_should_find_the_blocks_that_use_the_featured_image( $inner_blocks, $expected ) {
		$block = new WP_Block( $this->parsed_block( 'core/post-template', array(), $inner_blocks ) );

		$this->assertSame( $expected, block_core_post_template_uses_featured_image( $block->inner_blocks ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inner_blocks() {
		$block = static function ( $name, $attrs = array(), $inner = array() ) {
			return array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => $inner,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $inner ), null ),
			);
		};

		return array(
			'no blocks'                       => array( array(), false ),
			'other blocks'                    => array( array( $block( 'core/post-title' ), $block( 'core/post-excerpt' ) ), false ),
			'a featured image'                => array( array( $block( 'core/post-title' ), $block( 'core/post-featured-image' ) ), true ),
			'a cover'                         => array( array( $block( 'core/cover' ) ), false ),
			'a cover without the image'       => array( array( $block( 'core/cover', array( 'useFeaturedImage' => false ) ) ), false ),
			'a cover with the image'          => array( array( $block( 'core/cover', array( 'useFeaturedImage' => true ) ) ), true ),
			'a featured image that is inside' => array( array( $block( 'core/group', array(), array( $block( 'core/columns', array(), array( $block( 'core/post-featured-image' ) ) ) ) ) ), true ),
			'a cover that is inside'          => array( array( $block( 'core/group', array(), array( $block( 'core/cover', array( 'useFeaturedImage' => true ) ) ) ) ), true ),
			'another block with the setting'  => array( array( $block( 'core/group', array( 'useFeaturedImage' => true ) ) ), false ),
			'blocks that are inside'          => array( array( $block( 'core/group', array(), array( $block( 'core/paragraph' ) ) ) ), false ),
		);
	}

	/**
	 * Tests that nothing is rendered without posts.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_render_nothing_without_posts() {
		$this->assertSame( '', $this->render( array(), array( 'query' => $this->get_query( array( 'postType' => 'page' ) ) ) ) );
	}

	/**
	 * Tests the list of the posts.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_render_a_list_item_for_each_post_with_the_inner_blocks() {
		$output = $this->render();

		$this->assertStringStartsWith( '<ul >', $output );
		$this->assertStringEndsWith( '</ul>', $output );
		$this->assertSame( array( 'First', 'Second', 'Third' ), $this->get_titles( $output ) );
		$this->assertSame( 3, substr_count( $output, '<li class="wp-block-post ' ), 'There should be a list item for each post.' );
		$this->assertStringContainsString( '[' . self::$post_ids[0] . '|post|First]', $output, 'The inner block should get the post.' );
	}

	/**
	 * Tests the classes of each post.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_add_the_classes_of_the_post_to_each_item() {
		$output = $this->render();

		$this->assertMatchesRegularExpression( '/<li class="wp-block-post post-' . self::$post_ids[0] . ' post type-post status-publish [^"]*">\[/', $output );
	}

	/**
	 * Tests the number and the order of the posts.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 *
	 * @dataProvider data_queries
	 *
	 * @param array    $query    Values of the query.
	 * @param string[] $expected Expected titles.
	 */
	public function test_should_use_the_query_of_the_block( $query, $expected ) {
		$this->assertSame( $expected, $this->get_titles( $this->render( array(), array( 'query' => $this->get_query( $query ) ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_queries() {
		return array(
			'oldest first' => array( array(), array( 'First', 'Second', 'Third' ) ),
			'newest first' => array( array( 'order' => 'desc' ), array( 'Third', 'Second', 'First' ) ),
			'two posts'    => array( array( 'perPage' => 2 ), array( 'First', 'Second' ) ),
			'an offset'    => array(
				array(
					'perPage' => 2,
					'offset'  => 1,
				),
				array( 'Second', 'Third' ),
			),
		);
	}

	/**
	 * Tests the page of the query.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 *
	 * @dataProvider data_page_keys
	 *
	 * @param array  $context Context of the block.
	 * @param string $key     Key of the page in the request.
	 */
	public function test_should_use_the_page_of_the_request( $context, $key ) {
		$_GET[ $key ] = '2';

		$titles = $this->get_titles( $this->render( array(), array_merge( array( 'query' => $this->get_query( array( 'perPage' => 2 ) ) ), $context ) ) );

		$this->assertSame( array( 'Third' ), $titles );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_page_keys() {
		return array(
			'a query without an ID' => array( array(), 'query-page' ),
			'a query with an ID'    => array( array( 'queryId' => 7 ), 'query-7-page' ),
		);
	}

	/**
	 * Tests that the page of another query is ignored.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_ignore_the_page_of_another_query() {
		$_GET['query-8-page'] = '2';

		$titles = $this->get_titles(
			$this->render(
				array(),
				array(
					'queryId' => 7,
					'query'   => $this->get_query( array( 'perPage' => 2 ) ),
				)
			)
		);

		$this->assertSame( array( 'First', 'Second' ), $titles );
	}

	/**
	 * Tests the key of the items for the enhanced pagination.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_add_the_key_of_the_items_for_the_enhanced_pagination() {
		$without = $this->render();
		$with    = $this->render( array(), array( 'enhancedPagination' => true ) );

		$this->assertStringNotContainsString( 'data-wp-key', $without );
		$this->assertStringContainsString( '<li data-wp-key="post-template-item-' . self::$post_ids[0] . '" class="wp-block-post ', $with );
	}

	/**
	 * Tests the classes of the list.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 *
	 * @dataProvider data_list_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param array  $context    Values to add to the context.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_list( $attributes, $context, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes, $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_list_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                     => array( array(), array(), '<ul >' ),
			'a flex layout'                  => array(
				array(),
				array(
					'displayLayout' => array(
						'type'    => 'flex',
						'columns' => 3,
					),
				),
				'<ul class="is-flex-container columns-3">',
			),
			'a list layout'                  => array( array(), array( 'displayLayout' => array( 'type' => 'list' ) ), '<ul >' ),
			'a link color'                   => array( array( 'style' => $link_color ), array(), '<ul class="has-link-color">' ),
			'a grid'                         => array( array( 'layout' => array( 'type' => 'grid' ) ), array(), '<ul >' ),
			'a grid with columns'            => array(
				array(
					'layout' => array(
						'type'        => 'grid',
						'columnCount' => 3,
					),
				),
				array(),
				'<ul class="columns-3">',
			),
			'a responsive grid'              => array(
				array(
					'layout' => array(
						'type'               => 'grid',
						'columnCount'        => 2,
						'minimumColumnWidth' => '12rem',
					),
				),
				array(),
				'<ul class="columns-2 has-native-responsive-grid">',
			),
			'columns in another layout'      => array(
				array(
					'layout' => array(
						'type'        => 'flex',
						'columnCount' => 3,
					),
				),
				array(),
				'<ul >',
			),
			'a link color and a flex layout' => array(
				array( 'style' => $link_color ),
				array(
					'displayLayout' => array(
						'type'    => 'flex',
						'columns' => 2,
					),
				),
				'<ul class="is-flex-container columns-2 has-link-color">',
			),
		);
	}

	/**
	 * Tests that the main query is used when the block inherits it.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_use_the_main_query_when_it_inherits_it() {
		update_option( 'posts_per_page', 2 );
		$this->go_to( home_url( '/' ) );

		$titles = $this->get_titles( $this->render( array(), array( 'query' => $this->get_query( array( 'inherit' => true ) ) ) ) );

		$this->assertSame( array( 'Third', 'Second' ), $titles, 'The posts of the main query should be rendered, with its order and number.' );
	}

	/**
	 * Tests that the inner blocks are rendered with a block name that is not registered.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_render_the_items_with_a_block_name_that_is_not_registered() {
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

		$this->render( array(), array( 'query' => $this->get_query( array( 'perPage' => 1 ) ) ) );

		$this->assertSame( array( 'tests/post-info', 'core/null' ), $names );
	}

	/**
	 * Tests that the classes of the post are escaped.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_escape_the_classes_of_the_post() {
		add_filter(
			'post_class',
			static function ( $classes ) {
				$classes[] = '"><script>';

				return $classes;
			}
		);

		$output = $this->render( array(), array( 'query' => $this->get_query( array( 'perPage' => 1 ) ) ) );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&quot;&gt;&lt;script&gt;', $output );
	}

	/**
	 * Tests that the context filter is removed and the post data is restored.
	 *
	 * @ticket 66544
	 *
	 * @covers ::render_block_core_post_template
	 */
	public function test_should_remove_the_filter_and_restore_the_post_data() {
		global $wp_filter;

		$this->go_to( get_permalink( self::$post_ids[0] ) );
		$original_post = $GLOBALS['post']->ID;
		$before        = isset( $wp_filter['render_block_context'] ) ? count( $wp_filter['render_block_context']->callbacks ) : 0;

		$this->render();

		$after = isset( $wp_filter['render_block_context'] ) ? count( $wp_filter['render_block_context']->callbacks ) : 0;

		$this->assertSame( $before, $after, 'The filter of the context should be removed.' );
		$this->assertSame( $original_post, $GLOBALS['post']->ID, 'The global post should be restored.' );
	}

	/**
	 * Tests that the block is registered with the callbacks.
	 *
	 * @ticket 66544
	 *
	 * @covers ::register_block_core_post_template
	 */
	public function test_should_register_the_block_with_the_callbacks() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-template' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-template' );
		}

		register_block_core_post_template();
		$block_type = $registry->get_registered( 'core/post-template' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-template' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_template', $block_type->render_callback );
		$this->assertTrue( $block_type->skip_inner_blocks, 'The inner blocks should be skipped.' );
	}
}
