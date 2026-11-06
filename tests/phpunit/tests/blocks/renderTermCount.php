<?php
/**
 * Tests for the server-side rendering of the Term Count block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 *
 * @covers ::render_block_core_term_count
 */
class Tests_Blocks_RenderTermCount extends WP_UnitTestCase {

	/**
	 * ID of a category that has two posts.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * ID of a tag that has one post.
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
		self::$category_id = $factory->category->create();
		self::$tag_id      = $factory->tag->create();

		$post_ids = $factory->post->create_many( 2 );

		foreach ( $post_ids as $post_id ) {
			wp_set_object_terms( $post_id, array( self::$category_id ), 'category' );
		}

		wp_set_object_terms( $post_ids[0], array( self::$tag_id ), 'post_tag' );
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
		$attributes = array_merge( array( 'bracketType' => 'none' ), $attributes );

		$block = new WP_Block(
			array(
				'blockName'    => 'core/term-count',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = $context;

		return render_block_core_term_count( $attributes, '', $block );
	}

	/**
	 * Gets the context of the category.
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
	 * Tests that the count of the term of the context is rendered.
	 *
	 * @ticket 66522
	 */
	public function test_should_render_the_count_of_the_term_of_the_context() {
		$this->assertSame( '<div >2</div>', $this->render( array(), $this->get_category_context() ) );
	}

	/**
	 * Tests that the term data of the context is used first.
	 *
	 * @ticket 66522
	 */
	public function test_should_use_the_term_data_of_the_context_first() {
		$term        = get_term( self::$tag_id, 'post_tag' );
		$term->count = 42;

		$context             = $this->get_category_context();
		$context['termData'] = $term;

		$this->assertSame( '<div >42</div>', $this->render( array(), $context ) );
	}

	/**
	 * Tests that the term data is used without an ID or a taxonomy.
	 *
	 * @ticket 66522
	 */
	public function test_should_use_the_term_data_without_an_id_or_a_taxonomy() {
		$term        = get_term( self::$tag_id, 'post_tag' );
		$term->count = 7;

		$this->assertSame( '<div >7</div>', $this->render( array(), array( 'termData' => $term ) ) );
	}

	/**
	 * Tests that the term data is ignored when it is not a term.
	 *
	 * @ticket 66522
	 *
	 * @dataProvider data_term_data_that_is_not_a_term
	 *
	 * @param mixed $term_data Value of the context.
	 */
	public function test_should_ignore_the_term_data_that_is_not_a_term( $term_data ) {
		$context             = $this->get_category_context();
		$context['termData'] = $term_data;

		$this->assertSame( '<div >2</div>', $this->render( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_term_data_that_is_not_a_term() {
		return array(
			'an object that is not a term' => array( (object) array( 'count' => 99 ) ),
			'an array'                     => array( array( 'count' => 99 ) ),
			'null'                         => array( null ),
		);
	}

	/**
	 * Tests that the queried term is used without a context.
	 *
	 * @ticket 66522
	 */
	public function test_should_use_the_queried_term_without_a_context() {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( '<div >1</div>', $this->render() );
	}

	/**
	 * Tests that the queried term is used when the context has only one of the values.
	 *
	 * @ticket 66522
	 *
	 * @dataProvider data_incomplete_contexts
	 *
	 * @param array $context Context of the block.
	 */
	public function test_should_use_the_queried_term_when_the_context_is_incomplete( $context ) {
		$this->go_to( get_term_link( self::$tag_id, 'post_tag' ) );

		$this->assertSame( '<div >1</div>', $this->render( array(), $context ) );
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
	 * Tests that nothing is rendered when the queried object is not a term.
	 *
	 * @ticket 66522
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
	 * @ticket 66522
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
	 * Tests the brackets around the count.
	 *
	 * @ticket 66522
	 *
	 * @dataProvider data_bracket_types
	 *
	 * @param string $bracket_type Type of the brackets.
	 * @param string $expected     Expected content of the block.
	 */
	public function test_should_wrap_the_count_in_the_brackets( $bracket_type, $expected ) {
		$this->assertSame(
			'<div >' . $expected . '</div>',
			$this->render( array( 'bracketType' => $bracket_type ), $this->get_category_context() )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_bracket_types() {
		return array(
			'none'                 => array( 'none', '2' ),
			'round'                => array( 'round', '(2)' ),
			'square'               => array( 'square', '[2]' ),
			'curly'                => array( 'curly', '{2}' ),
			'angle'                => array( 'angle', '<2>' ),
			'an unknown type'      => array( 'unknown', '2' ),
			'an empty type'        => array( '', '2' ),
			'a type with a letter' => array( 'ROUND', '2' ),
		);
	}

	/**
	 * Tests that a count of zero is rendered.
	 *
	 * @ticket 66522
	 */
	public function test_should_render_a_count_of_zero() {
		$term_id = self::factory()->category->create();

		$this->assertSame(
			'<div >(0)</div>',
			$this->render(
				array( 'bracketType' => 'round' ),
				array(
					'termId'   => $term_id,
					'taxonomy' => 'category',
				)
			)
		);
	}
}
