<?php
/**
 * Tests for the server-side rendering of the Query Pagination Previous block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_pagination_previous
 */
class Tests_Blocks_RenderQueryPaginationPrevious extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * The original value of the request data.
	 *
	 * @var array
	 */
	private $original_get;

	/**
	 * Whether the block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render = WP_Block_Supports::$block_to_render;
		$this->original_get             = $_GET;
		$this->was_registered           = WP_Block_Type_Registry::get_instance()->is_registered( 'core/query-pagination-previous' );

		if ( ! $this->was_registered ) {
			register_block_core_query_pagination_previous();
		}

		// Five posts that a query finds, which are three pages of two.
		self::factory()->post->create_many( 5, array( 'post_title' => 'Pager post' ) );
		update_option( 'posts_per_page', 2 );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$_GET                               = $this->original_get;

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/query-pagination-previous' );
		}

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Optional. Context of the block, which has a query for the posts with the title. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$parsed_block = array(
			'blockName'    => 'core/query-pagination-previous',
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		if ( ! isset( $context['query'] ) ) {
			$context['query'] = array();
		}

		$context['query'] = array_merge(
			array(
				'perPage'  => 2,
				'postType' => 'post',
				'search'   => 'Pager post',
				'inherit'  => false,
			),
			$context['query']
		);

		$block = new WP_Block( $parsed_block, $context );

		WP_Block_Supports::$block_to_render = $parsed_block;

		return render_block_core_query_pagination_previous( $attributes, '', $block );
	}

	/**
	 * Returns the attributes of the first link of the output.
	 *
	 * @param string $output The output.
	 * @return array|null The attributes of the link, or null without a link.
	 */
	private function get_link( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( 'a' ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that there is no link on the first page.
	 *
	 * @ticket 66572
	 */
	public function test_should_not_link_on_the_first_page() {
		$this->go_to( '/' );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests the link to the previous page.
	 *
	 * @ticket 66572
	 */
	public function test_should_link_to_the_previous_page() {
		$this->go_to( '/' );

		$_GET['query-page'] = '3';

		$output = $this->render();
		$link   = $this->get_link( $output );

		$this->assertNotNull( $link, 'A link should be output.' );
		$this->assertStringEndsWith( 'query-page=2', $link['href'] );
		$this->assertSame( 'wp-block-query-pagination-previous', $link['class'] );
		$this->assertStringContainsString( '>Previous Page</a>', $output );
	}

	/**
	 * Tests the link of the second page, which goes back to the first.
	 *
	 * @ticket 66572
	 */
	public function test_should_link_to_the_first_page_from_the_second() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$this->assertStringEndsWith( 'query-page=1', $this->get_link( $this->render() )['href'] );
	}

	/**
	 * Tests that there is no link for a page that is past the last one.
	 *
	 * @ticket 66572
	 */
	public function test_should_not_link_past_the_last_page() {
		$this->go_to( '/' );

		$_GET['query-page'] = '4';

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66572
	 */
	public function test_should_escape_the_url_of_the_link() {
		$this->go_to( '/?extra=1' );

		$_GET['query-page'] = '2';

		$this->assertStringContainsString( 'extra=1&#038;query-page=', $this->render() );
	}

	/**
	 * Tests the page of the query that has an ID.
	 *
	 * @ticket 66572
	 */
	public function test_should_use_the_page_of_the_query_with_the_same_id() {
		$this->go_to( '/' );

		$_GET['query-4-page'] = '3';
		$_GET['query-page']   = '1';

		$this->assertStringEndsWith( 'query-4-page=2', $this->get_link( $this->render( array(), array( 'queryId' => 4 ) ) )['href'] );
	}

	/**
	 * Tests the number of pages of the query as the limit of the last page.
	 *
	 * @ticket 66572
	 *
	 * @dataProvider data_page_limits
	 *
	 * @param int  $page     The page of the request.
	 * @param int  $pages    The number of pages of the query.
	 * @param bool $expected Whether the link should be output.
	 */
	public function test_should_not_link_past_the_number_of_pages_of_the_query( $page, $pages, $expected ) {
		$this->go_to( '/' );

		$_GET['query-page'] = (string) $page;

		$output = $this->render( array(), array( 'query' => array( 'pages' => $pages ) ) );

		$this->assertSame( $expected, '' !== $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_page_limits() {
		return array(
			'at the limit'         => array( 2, 2, true ),
			'past the limit'       => array( 3, 2, false ),
			'a limit of zero'      => array( 3, 0, true ),
			'a limit past the end' => array( 3, 10, true ),
		);
	}

	/**
	 * Tests the label of the block.
	 *
	 * @ticket 66572
	 */
	public function test_should_use_the_label_of_the_attributes_escaped() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$output = $this->render( array( 'label' => 'Newer <b>posts</b>' ) );

		$this->assertStringContainsString( '>Newer &lt;b&gt;posts&lt;/b&gt;</a>', $output );
		$this->assertStringNotContainsString( 'Previous Page', $output );
	}

	/**
	 * Tests that the default label is used for an empty label.
	 *
	 * @ticket 66572
	 */
	public function test_should_use_the_default_label_for_an_empty_label() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$this->assertStringContainsString( '>Previous Page</a>', $this->render( array( 'label' => '' ) ) );
	}

	/**
	 * Tests that the label is hidden, and used as the label for screen readers.
	 *
	 * @ticket 66572
	 */
	public function test_should_hide_the_label_and_name_the_link_when_the_label_is_not_shown() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$output = $this->render( array( 'label' => 'Newer' ), array( 'showLabel' => false ) );
		$link   = $this->get_link( $output );

		$this->assertSame( 'Newer', $link['aria-label'] );
		$this->assertStringContainsString( '></a>', $output, 'The link should have no text.' );
	}

	/**
	 * Tests the arrow before the label.
	 *
	 * @ticket 66572
	 *
	 * @dataProvider data_arrows
	 *
	 * @param string $arrow    The type of the arrow.
	 * @param string $expected The expected markup before the label, or an empty string for none.
	 */
	public function test_should_add_the_arrow_before_the_label( $arrow, $expected ) {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$output = $this->render( array(), array( 'paginationArrow' => $arrow ) );

		$this->assertStringContainsString( '>' . $expected . 'Previous Page</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_arrows() {
		return array(
			'an arrow'                 => array( 'arrow', "<span class='wp-block-query-pagination-previous-arrow is-arrow-arrow' aria-hidden='true'>←</span>" ),
			'a chevron'                => array( 'chevron', "<span class='wp-block-query-pagination-previous-arrow is-arrow-chevron' aria-hidden='true'>«</span>" ),
			'none'                     => array( 'none', '' ),
			'a type that is not known' => array( 'unknown', '' ),
			'an empty type'            => array( '', '' ),
		);
	}

	/**
	 * Tests the attributes of the enhanced pagination.
	 *
	 * @ticket 66572
	 */
	public function test_should_add_the_attributes_of_the_enhanced_pagination() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$link = $this->get_link( $this->render( array(), array( 'enhancedPagination' => true ) ) );

		$this->assertSame( 'query-pagination-previous', $link['data-wp-key'] );
		$this->assertSame( 'core/query::actions.navigate', $link['data-wp-on--click'] );
		$this->assertSame( 'core/query::actions.prefetch', $link['data-wp-on--mouseenter'] );
		$this->assertSame( 'core/query::callbacks.prefetch', $link['data-wp-watch'] );
	}

	/**
	 * Tests that the attributes of the enhanced pagination are not added without it.
	 *
	 * @ticket 66572
	 */
	public function test_should_not_add_the_attributes_of_the_enhanced_pagination_without_it() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$this->assertArrayNotHasKey( 'data-wp-key', $this->get_link( $this->render() ) );
	}

	/**
	 * Tests the link of a query that inherits the query of the page.
	 *
	 * @ticket 66572
	 */
	public function test_should_use_the_query_of_the_page_when_the_block_inherits_it() {
		$this->go_to( '/?paged=3' );

		$output = $this->render( array(), array( 'query' => array( 'inherit' => true ) ) );
		$link   = $this->get_link( $output );

		$this->assertMatchesRegularExpression( '#paged=2|/page/2/#', $link['href'], 'The link should be to the second page.' );
		$this->assertSame( 'wp-block-query-pagination-previous', $link['class'], 'The attributes of the block should be on the link.' );
		$this->assertStringContainsString( '>Previous Page</a>', $output );
	}

	/**
	 * Tests that the first page of the query of the page has no link.
	 *
	 * @ticket 66572
	 */
	public function test_should_not_link_on_the_first_page_of_the_query_of_the_page() {
		$this->go_to( '/' );

		$this->assertEmpty( $this->render( array(), array( 'query' => array( 'inherit' => true ) ) ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66572
	 *
	 * @covers ::register_block_core_query_pagination_previous
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-pagination-previous' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-pagination-previous' );
		}

		try {
			register_block_core_query_pagination_previous();
			$block_type = $registry->get_registered( 'core/query-pagination-previous' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_pagination_previous', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-pagination-previous' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
