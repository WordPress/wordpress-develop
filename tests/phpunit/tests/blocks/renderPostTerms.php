<?php
/**
 * Tests for the server-side rendering of the Post Terms block, and its variations.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group taxonomy
 */
class Tests_Blocks_RenderPostTerms extends WP_UnitTestCase {

	/**
	 * ID of a post that has two categories.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post without categories.
	 *
	 * @var int
	 */
	private static $post_without_terms_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$news    = $factory->category->create( array( 'name' => 'News' ) );
		$updates = $factory->category->create( array( 'name' => 'Updates' ) );

		self::$post_id = $factory->post->create();
		wp_set_object_terms( self::$post_id, array( $news, $updates ), 'category' );
		wp_set_object_terms( self::$post_id, array( 'featured' ), 'post_tag' );

		self::$post_without_terms_id = $factory->post->create();
		wp_set_object_terms( self::$post_without_terms_id, array(), 'category' );
		wp_remove_object_terms( self::$post_without_terms_id, array( 1 ), 'category' );
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
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block, with the post by default.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$attributes = array_merge( array( 'term' => 'category' ), $attributes );

		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-terms',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_terms( $attributes, '', $block );
	}

	/**
	 * Tests the list of the terms.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_render_the_terms_of_the_post_with_links() {
		$output = $this->render();

		$this->assertStringStartsWith( '<div class="taxonomy-category">', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertStringContainsString( '>News</a><span class="wp-block-post-terms__separator"> </span><a ', $output, 'The terms should be separated by a space.' );
		$this->assertStringContainsString( '>Updates</a></div>', $output );
		$this->assertStringContainsString( ' rel="tag">News</a>', $output );
	}

	/**
	 * Tests that nothing is rendered without a post or a taxonomy.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_render_nothing_without_a_post_or_a_taxonomy() {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-terms',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = array( 'postId' => self::$post_id );
		$this->assertSame( '', render_block_core_post_terms( array(), '', $block ), 'A taxonomy is needed.' );

		$this->assertSame( '', $this->render( array(), array() ), 'A post is needed.' );
	}

	/**
	 * Tests that nothing is rendered for a taxonomy that is not viewable.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 *
	 * @dataProvider data_taxonomies_that_are_not_viewable
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function test_should_render_nothing_for_a_taxonomy_that_is_not_viewable( $taxonomy ) {
		$this->assertSame( '', $this->render( array( 'term' => $taxonomy ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_taxonomies_that_are_not_viewable() {
		return array(
			'a taxonomy that does not exist' => array( 'does_not_exist' ),
			'a taxonomy that is not public'  => array( 'nav_menu' ),
		);
	}

	/**
	 * Tests that nothing is rendered for a taxonomy that is not viewable, even if the post has terms of it.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_render_nothing_for_a_taxonomy_that_is_not_viewable_when_the_post_has_terms() {
		register_taxonomy( 'wp_test_private', 'post', array( 'public' => false ) );

		try {
			wp_set_object_terms( self::$post_id, array( 'secret' ), 'wp_test_private' );

			$this->assertSame( '', $this->render( array( 'term' => 'wp_test_private' ) ) );
		} finally {
			unregister_taxonomy( 'wp_test_private' );
		}
	}

	/**
	 * Tests that nothing is rendered for a post without terms.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_render_nothing_for_a_post_without_terms() {
		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$post_without_terms_id ) ) );
	}

	/**
	 * Tests the separator.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 *
	 * @dataProvider data_separators
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected separator.
	 */
	public function test_should_use_the_separator( $attributes, $expected ) {
		$this->assertStringContainsString( '<span class="wp-block-post-terms__separator">' . $expected . '</span>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_separators() {
		return array(
			'no separator'   => array( array(), ' ' ),
			'an empty one'   => array( array( 'separator' => '' ), ' ' ),
			'a separator'    => array( array( 'separator' => ', ' ), ', ' ),
			'an escaped one' => array( array( 'separator' => '<b>|</b>' ), '&lt;b&gt;|&lt;/b&gt;' ),
		);
	}

	/**
	 * Tests the prefix and the suffix.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_add_the_prefix_and_the_suffix() {
		$output = $this->render(
			array(
				'prefix' => 'Filed under: ',
				'suffix' => ' (end)',
			)
		);

		$this->assertStringStartsWith( '<div class="taxonomy-category"><span class="wp-block-post-terms__prefix">Filed under: </span><a ', $output );
		$this->assertStringEndsWith( '</a><span class="wp-block-post-terms__suffix"> (end)</span></div>', $output );
	}

	/**
	 * Tests that the prefix and the suffix are not added when they are empty.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 *
	 * @dataProvider data_empty_prefixes
	 *
	 * @param mixed $value Value of the prefix and of the suffix.
	 */
	public function test_should_not_add_an_empty_prefix_or_suffix( $value ) {
		$output = $this->render(
			array(
				'prefix' => $value,
				'suffix' => $value,
			)
		);

		$this->assertStringNotContainsString( '__prefix', $output );
		$this->assertStringNotContainsString( '__suffix', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_prefixes() {
		return array(
			'an empty string' => array( '' ),
			'null'            => array( null ),
			'zero'            => array( '0' ),
		);
	}

	/**
	 * Tests that the markup of the prefix and the suffix is cleaned.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 */
	public function test_should_clean_the_prefix_and_the_suffix() {
		$output = $this->render(
			array(
				'prefix' => 'By <strong>tag</strong><script>alert( 1 );</script>',
				'suffix' => '<em>end</em><script>alert( 2 );</script>',
			)
		);

		$this->assertStringContainsString( '<strong>tag</strong>', $output );
		$this->assertStringContainsString( '<em>end</em>', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_terms
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'the taxonomy'                => array( array(), '<div class="taxonomy-category">' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), '<div class="taxonomy-category has-text-align-center">' ),
			'a link color'                => array( array( 'style' => $link_color ), '<div class="taxonomy-category has-link-color">' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'<div class="taxonomy-category has-text-align-right has-link-color">',
			),
			'another taxonomy'            => array( array( 'term' => 'post_tag' ), '<div class="taxonomy-post_tag">' ),
		);
	}

	/**
	 * Tests the variations of the taxonomies.
	 *
	 * @ticket 66545
	 *
	 * @covers ::block_core_post_terms_build_variations
	 */
	public function test_should_build_a_variation_for_each_taxonomy_that_is_queryable_and_in_the_rest_api() {
		register_taxonomy(
			'wp_test_genre',
			'post',
			array(
				'label'              => 'Genres',
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);
		register_taxonomy(
			'wp_test_hidden',
			'post',
			array(
				'publicly_queryable' => true,
				'show_in_rest'       => false,
			)
		);
		register_taxonomy(
			'wp_test_private',
			'post',
			array(
				'publicly_queryable' => false,
				'show_in_rest'       => true,
			)
		);

		try {
			$variations = block_core_post_terms_build_variations();
			$names      = wp_list_pluck( $variations, 'name' );

			$this->assertContains( 'category', $names );
			$this->assertContains( 'post_tag', $names );
			$this->assertContains( 'wp_test_genre', $names );
			$this->assertNotContains( 'wp_test_hidden', $names, 'A taxonomy that is not in the REST API should be left out.' );
			$this->assertNotContains( 'wp_test_private', $names, 'A taxonomy that is not publicly queryable should be left out.' );

			$genre = $variations[ array_search( 'wp_test_genre', $names, true ) ];

			$this->assertSame(
				array(
					'name'        => 'wp_test_genre',
					'title'       => 'Genres',
					'description' => 'Display a list of assigned terms from the taxonomy: Genres',
					'attributes'  => array( 'term' => 'wp_test_genre' ),
					'isActive'    => array( 'term' ),
					'scope'       => array( 'inserter', 'transform' ),
				),
				$genre
			);
		} finally {
			unregister_taxonomy( 'wp_test_genre' );
			unregister_taxonomy( 'wp_test_hidden' );
			unregister_taxonomy( 'wp_test_private' );
		}
	}

	/**
	 * Tests the order and the default of the variations.
	 *
	 * @ticket 66545
	 *
	 * @covers ::block_core_post_terms_build_variations
	 */
	public function test_should_put_the_built_in_taxonomies_first_and_make_the_category_the_default() {
		global $wp_taxonomies;

		register_taxonomy(
			'wp_test_genre',
			'post',
			array(
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);

		$original_taxonomies = $wp_taxonomies;

		// Put the custom taxonomy first, so the order of the variations cannot come from the order of registration.
		$wp_taxonomies = array_merge(
			array( 'wp_test_genre' => $wp_taxonomies['wp_test_genre'] ),
			$wp_taxonomies
		);

		try {
			$variations = block_core_post_terms_build_variations();
			$names      = wp_list_pluck( $variations, 'name' );

			$this->assertSame( 'wp_test_genre', array_key_first( $wp_taxonomies ), 'The custom taxonomy should be registered first.' );
			$this->assertLessThan( array_search( 'wp_test_genre', $names, true ), array_search( 'category', $names, true ), 'A built-in taxonomy should come before a custom one.' );
			$this->assertLessThan( array_search( 'wp_test_genre', $names, true ), array_search( 'post_tag', $names, true ), 'A built-in taxonomy should come before a custom one.' );

			foreach ( $variations as $variation ) {
				if ( 'category' === $variation['name'] ) {
					$this->assertTrue( $variation['isDefault'], 'The category should be the default.' );
				} else {
					$this->assertArrayNotHasKey( 'isDefault', $variation, "The variation '{$variation['name']}' should not be the default." );
				}
			}
		} finally {
			$wp_taxonomies = $original_taxonomies;
			unregister_taxonomy( 'wp_test_genre' );
		}
	}

	/**
	 * Tests that the block is registered with the callbacks.
	 *
	 * @ticket 66545
	 *
	 * @covers ::register_block_core_post_terms
	 */
	public function test_should_register_the_block_with_the_callbacks() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/post-terms' );

		if ( $original_block ) {
			unregister_block_type( 'core/post-terms' );
		}

		try {
			register_block_core_post_terms();
			$block_type = $registry->get_registered( 'core/post-terms' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_post_terms', $block_type->render_callback );
			$this->assertSame( 'block_core_post_terms_build_variations', $block_type->variation_callback );
		} finally {
			unregister_block_type( 'core/post-terms' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
