<?php
/**
 * Tests for the server-side rendering of the Pattern block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_pattern
 */
class Tests_Blocks_RenderPattern extends WP_UnitTestCase {

	/**
	 * The slugs of the patterns that the tests registered.
	 *
	 * @var string[]
	 */
	private $registered = array();

	public function tear_down() {
		foreach ( $this->registered as $slug ) {
			unregister_block_pattern( $slug );
		}

		$this->registered = array();

		parent::tear_down();
	}

	/**
	 * Registers a pattern for the test.
	 *
	 * @param string $slug    The slug of the pattern.
	 * @param string $content The content of the pattern.
	 */
	private function register_pattern( $slug, $content ) {
		register_block_pattern(
			$slug,
			array(
				'title'   => 'Test pattern',
				'content' => $content,
			)
		);

		$this->registered[] = $slug;
	}

	/**
	 * Tests the content of a pattern.
	 *
	 * @ticket 66579
	 */
	public function test_should_render_the_blocks_of_the_pattern() {
		$this->register_pattern( 'test/render-pattern', '<!-- wp:html --><p>Pattern text</p><!-- /wp:html -->' );

		$this->assertSame( '<p>Pattern text</p>', render_block_core_pattern( array( 'slug' => 'test/render-pattern' ) ) );
	}

	/**
	 * Tests the pattern through the rendering of the block.
	 *
	 * @ticket 66579
	 */
	public function test_should_render_the_pattern_with_the_block() {
		$this->register_pattern( 'test/render-pattern', '<!-- wp:html --><p>Pattern text</p><!-- /wp:html -->' );

		$this->assertSame( '<p>Pattern text</p>', do_blocks( '<!-- wp:pattern {"slug":"test/render-pattern"} /-->' ) );
	}

	/**
	 * Tests a pattern inside a pattern.
	 *
	 * @ticket 66579
	 */
	public function test_should_render_a_pattern_inside_another_pattern() {
		$this->register_pattern( 'test/inner', '<!-- wp:html --><p>Inner</p><!-- /wp:html -->' );
		$this->register_pattern( 'test/outer', '<!-- wp:pattern {"slug":"test/inner"} /--><!-- wp:html --><p>Outer</p><!-- /wp:html -->' );

		$this->assertSame( '<p>Inner</p><p>Outer</p>', render_block_core_pattern( array( 'slug' => 'test/outer' ) ) );
	}

	/**
	 * Tests that a slug that is not valid gives an empty string.
	 *
	 * @ticket 66579
	 *
	 * @dataProvider data_slugs_that_are_not_valid
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_return_an_empty_string_for_a_slug_that_is_not_valid( $attributes ) {
		$this->register_pattern( '0', '<!-- wp:html --><p>Zero</p><!-- /wp:html -->' );

		$this->assertSame( '', render_block_core_pattern( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_slugs_that_are_not_valid() {
		return array(
			'no slug'     => array( array() ),
			'null'        => array( array( 'slug' => null ) ),
			'empty'       => array( array( 'slug' => '' ) ),
			'zero string' => array( array( 'slug' => '0' ) ),
			'an array'    => array( array( 'slug' => array( 'test/render-pattern' ) ) ),
			'a number'    => array( array( 'slug' => 5 ) ),
		);
	}

	/**
	 * Tests that a pattern that is not registered gives an empty string.
	 *
	 * @ticket 66579
	 */
	public function test_should_return_an_empty_string_for_a_pattern_that_is_not_registered() {
		$this->assertSame( '', render_block_core_pattern( array( 'slug' => 'test/not-registered' ) ) );
	}

	/**
	 * Tests that a pattern that includes itself is not rendered again.
	 *
	 * @ticket 66579
	 */
	public function test_should_stop_a_pattern_that_includes_itself() {
		$this->register_pattern( 'test/self', '<!-- wp:html --><p>Self</p><!-- /wp:html --><!-- wp:pattern {"slug":"test/self"} /-->' );

		$halted   = WP_DEBUG && WP_DEBUG_DISPLAY ? '[block rendering halted for pattern "test/self"]' : '';
		$expected = '<p>Self</p>' . $halted;

		$this->assertSame( $expected, render_block_core_pattern( array( 'slug' => 'test/self' ) ) );
	}

	/**
	 * Tests that a pattern can be rendered again after it was stopped.
	 *
	 * @ticket 66579
	 */
	public function test_should_render_a_pattern_again_after_it_was_stopped() {
		$this->register_pattern( 'test/self', '<!-- wp:html --><p>Self</p><!-- /wp:html --><!-- wp:pattern {"slug":"test/self"} /-->' );

		$first  = render_block_core_pattern( array( 'slug' => 'test/self' ) );
		$second = render_block_core_pattern( array( 'slug' => 'test/self' ) );

		$this->assertSame( $first, $second );
		$this->assertStringStartsWith( '<p>Self</p>', $second );
	}

	/**
	 * Tests that a pattern is rendered twice when it is repeated in a row and does not include itself.
	 *
	 * @ticket 66579
	 */
	public function test_should_render_the_same_pattern_twice_in_a_row() {
		$this->register_pattern( 'test/twice', '<!-- wp:html --><p>Twice</p><!-- /wp:html -->' );

		$this->assertSame(
			'<p>Twice</p><p>Twice</p>',
			do_blocks( '<!-- wp:pattern {"slug":"test/twice"} /--><!-- wp:pattern {"slug":"test/twice"} /-->' )
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66579
	 *
	 * @covers ::register_block_core_pattern
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/pattern' );

		if ( $original_block ) {
			unregister_block_type( 'core/pattern' );
		}

		try {
			register_block_core_pattern();
			$block_type = $registry->get_registered( 'core/pattern' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_pattern', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/pattern' ) ) {
				unregister_block_type( 'core/pattern' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
