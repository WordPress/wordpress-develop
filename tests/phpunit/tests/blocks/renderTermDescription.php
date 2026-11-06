<?php
/**
 * Tests for the server-side rendering of the Term Description block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 *
 * @covers ::render_block_core_term_description
 */
class Tests_Blocks_RenderTermDescription extends WP_UnitTestCase {

	/**
	 * ID of a category with a description.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * ID of a tag with a description.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * ID of a category without a description.
	 *
	 * @var int
	 */
	private static $empty_category_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id       = $factory->category->create( array( 'description' => 'Category description' ) );
		self::$tag_id            = $factory->tag->create( array( 'description' => 'Tag description' ) );
		self::$empty_category_id = $factory->category->create( array( 'description' => '' ) );
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
				'blockName'    => 'core/term-description',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = $context;

		return render_block_core_term_description( $attributes, '', $block );
	}

	/**
	 * Gets the context of a category.
	 *
	 * @param int $id ID of the category.
	 * @return array The context.
	 */
	private function get_category_context( $id ) {
		return array(
			'termId'   => $id,
			'taxonomy' => 'category',
		);
	}

	/**
	 * Tests that the description of the term of the context is rendered.
	 *
	 * @ticket 66521
	 */
	public function test_should_render_the_description_of_the_term_of_the_context() {
		$this->assertSame( "<div ><p>Category description</p>\n</div>", $this->render( array(), $this->get_category_context( self::$category_id ) ) );
	}

	/**
	 * Tests that the term of the context is used on the archive of another term.
	 *
	 * @ticket 66521
	 */
	public function test_should_use_the_term_of_the_context_instead_of_the_archive() {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( "<div ><p>Category description</p>\n</div>", $this->render( array(), $this->get_category_context( self::$category_id ) ) );
	}

	/**
	 * Tests that nothing is rendered when the term of the context has no description.
	 *
	 * @ticket 66521
	 */
	public function test_should_render_nothing_when_the_term_of_the_context_has_no_description() {
		$this->assertSame( '', $this->render( array(), $this->get_category_context( self::$empty_category_id ) ) );
	}

	/**
	 * Tests that nothing is rendered when the term of the context does not exist.
	 *
	 * @ticket 66521
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
	 * Tests that the description of the archive is used without a context.
	 *
	 * @ticket 66521
	 *
	 * @dataProvider data_archives
	 *
	 * @param string $taxonomy    Taxonomy of the term.
	 * @param string $id_property Name of the property that has the ID of the term.
	 * @param string $expected    Expected description.
	 */
	public function test_should_use_the_description_of_the_archive_without_a_context( $taxonomy, $id_property, $expected ) {
		$this->go_to( get_term_link( self::${$id_property}, $taxonomy ) );

		$this->assertSame( '<div ><p>' . $expected . "</p>\n</div>", $this->render() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_archives() {
		return array(
			'a category' => array( 'category', 'category_id', 'Category description' ),
			'a tag'      => array( 'post_tag', 'tag_id', 'Tag description' ),
		);
	}

	/**
	 * Tests that the description of a custom taxonomy archive is used.
	 *
	 * @ticket 66521
	 */
	public function test_should_use_the_description_of_a_custom_taxonomy_archive() {
		register_taxonomy( 'wp_test_genre', 'post', array( 'public' => true ) );
		flush_rewrite_rules();
		$term_id = self::factory()->term->create(
			array(
				'taxonomy'    => 'wp_test_genre',
				'description' => 'Genre description',
			)
		);

		try {
			$this->go_to( get_term_link( $term_id, 'wp_test_genre' ) );

			$this->assertTrue( is_tax(), 'The page should be a taxonomy archive.' );
			$this->assertSame( "<div ><p>Genre description</p>\n</div>", $this->render() );
		} finally {
			unregister_taxonomy( 'wp_test_genre' );
		}
	}

	/**
	 * Tests that nothing is rendered when the page is not an archive of a term.
	 *
	 * @ticket 66521
	 */
	public function test_should_render_nothing_when_the_page_is_not_an_archive_of_a_term() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that nothing is rendered on the archive of a term that has no description.
	 *
	 * @ticket 66521
	 */
	public function test_should_render_nothing_on_the_archive_of_a_term_without_a_description() {
		$this->go_to( get_term_link( self::$empty_category_id, 'category' ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that the archive is used when the context has only one of the values.
	 *
	 * @ticket 66521
	 *
	 * @dataProvider data_incomplete_contexts
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_use_the_archive_when_the_context_is_incomplete( $context ) {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( "<div ><p>Tag description</p>\n</div>", $this->render( array(), $context ) );
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
	 * Tests the classes of the block.
	 *
	 * @ticket 66521
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertSame(
			'<div ' . $expected . '><p>Category description</p>' . "\n</div>",
			$this->render( $attributes, $this->get_category_context( self::$category_id ) )
		);
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
	 * Tests that the description is the one that is filtered for the display.
	 *
	 * @ticket 66521
	 */
	public function test_should_use_the_description_of_the_term_for_the_display() {
		add_filter(
			'term_description',
			static function ( $description ) {
				return $description . ' (filtered)';
			}
		);

		$this->assertStringEndsWith(
			' (filtered)</div>',
			$this->render( array(), $this->get_category_context( self::$category_id ) )
		);
	}
}
