<?php
/**
 * Tests for the server-side rendering of the Term Name block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 *
 * @covers ::render_block_core_term_name
 */
class Tests_Blocks_RenderTermName extends WP_UnitTestCase {

	/**
	 * ID of a category.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * ID of a tag.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id = $factory->category->create( array( 'name' => 'News' ) );
		self::$tag_id      = $factory->tag->create( array( 'name' => 'Featured' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block with a context.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/term-name',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = $context;

		return render_block_core_term_name( $attributes, '', $block );
	}

	/**
	 * Gets the context of a category.
	 *
	 * @return array The context.
	 */
	private function get_category_context() {
		return array(
			'termId'   => self::$category_id,
			'taxonomy' => 'category',
		);
	}

	/**
	 * Tests that the name of the term of the context is rendered in a paragraph by default.
	 *
	 * @ticket 66520
	 */
	public function test_should_render_the_name_of_the_term_of_the_context_in_a_paragraph() {
		$this->assertSame( '<p >News</p>', $this->render( array(), $this->get_category_context() ) );
	}

	/**
	 * Tests that the term of the context is used instead of the one of the page.
	 *
	 * @ticket 66520
	 */
	public function test_should_use_the_term_of_the_context_instead_of_the_queried_term() {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( '<p >News</p>', $this->render( array(), $this->get_category_context() ) );
	}

	/**
	 * Tests that the queried term is used without a context.
	 *
	 * @ticket 66520
	 */
	public function test_should_use_the_queried_term_without_a_context() {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( '<p >Featured</p>', $this->render() );
	}

	/**
	 * Tests that the queried term is used when the context has only one of the values.
	 *
	 * @ticket 66520
	 *
	 * @dataProvider data_incomplete_contexts
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_use_the_queried_term_when_the_context_is_incomplete( $context ) {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( '<p >Featured</p>', $this->render( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_contexts() {
		return array(
			'only a taxonomy' => array( array( 'taxonomy' => 'category' ) ),
			'only an ID'      => array( array( 'termId' => 1 ) ),
		);
	}

	/**
	 * Tests that nothing is rendered when there is no term.
	 *
	 * @ticket 66520
	 */
	public function test_should_render_nothing_when_the_queried_object_is_not_a_term() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->render(), 'There should be no queried object on the home page.' );

		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$this->assertInstanceOf( WP_Post::class, get_queried_object(), 'The queried object should be a post.' );
		$this->assertSame( '', $this->render(), 'A post should not be used as a term.' );
	}

	/**
	 * Tests that nothing is rendered for a term that does not exist.
	 *
	 * @ticket 66520
	 *
	 * @dataProvider data_missing_terms
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_render_nothing_for_a_term_that_does_not_exist( $context ) {
		$this->assertSame( '', $this->render( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_missing_terms() {
		return array(
			'an ID that does not exist'      => array(
				array(
					'termId'   => 999999,
					'taxonomy' => 'category',
				),
			),
			'a taxonomy that does not exist' => array(
				array(
					'termId'   => 1,
					'taxonomy' => 'does_not_exist',
				),
			),
		);
	}

	/**
	 * Tests the tag of the level.
	 *
	 * @ticket 66520
	 *
	 * @dataProvider data_levels
	 *
	 * @param int    $level    Level of the block.
	 * @param string $tag_name Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $level, $tag_name ) {
		$this->assertSame(
			"<{$tag_name} >News</{$tag_name}>",
			$this->render( array( 'level' => $level ), $this->get_category_context() )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_levels() {
		return array(
			'zero is a paragraph' => array( 0, 'p' ),
			'one'                 => array( 1, 'h1' ),
			'three'               => array( 3, 'h3' ),
			'six'                 => array( 6, 'h6' ),
		);
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66520
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertSame( '<p ' . $expected . '>News</p>', $this->render( $attributes, $this->get_category_context() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                  => array( array(), '' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), 'class="has-text-align-center"' ),
			'a link color'                => array( array( 'style' => $link_color ), 'class="has-link-color"' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'class="has-text-align-right has-link-color"',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '' ),
		);
	}

	/**
	 * Tests that the name links to the archive of the term.
	 *
	 * @ticket 66520
	 */
	public function test_should_link_the_name_to_the_archive_of_the_term() {
		$this->assertSame(
			'<p ><a href="' . get_term_link( self::$category_id, 'category' ) . '">News</a></p>',
			$this->render( array( 'isLink' => true ), $this->get_category_context() )
		);
	}

	/**
	 * Tests that the name is not linked unless it is requested.
	 *
	 * @ticket 66520
	 *
	 * @dataProvider data_values_that_do_not_link
	 *
	 * @param mixed $is_link Value of the attribute.
	 */
	public function test_should_not_link_the_name_unless_it_is_requested( $is_link ) {
		$this->assertSame( '<p >News</p>', $this->render( array( 'isLink' => $is_link ), $this->get_category_context() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_do_not_link() {
		return array(
			'false' => array( false ),
			'null'  => array( null ),
			'zero'  => array( 0 ),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66520
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'term_link',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( '<a href="https://example.org/?a=1&#038;b=2">News</a>', $this->render( array( 'isLink' => true ), $this->get_category_context() ) );
	}

	/**
	 * Tests that the name is not linked when the link of the term is an error.
	 *
	 * @ticket 66520
	 */
	public function test_should_not_link_the_name_when_the_link_is_an_error() {
		add_filter(
			'term_link',
			static function () {
				return new WP_Error( 'no_link', 'No link.' );
			}
		);

		$this->assertSame( '<p >News</p>', $this->render( array( 'isLink' => true ), $this->get_category_context() ) );
	}

	/**
	 * Tests that the name is the one that is filtered for the display.
	 *
	 * @ticket 66520
	 */
	public function test_should_use_the_name_of_the_term_for_the_display() {
		add_filter(
			'term_name',
			static function ( $name ) {
				return $name . ' (filtered)';
			}
		);

		$this->assertSame( '<p >News (filtered)</p>', $this->render( array(), $this->get_category_context() ) );
	}
}
