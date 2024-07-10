<?php
/**
 * Tests for WP_Block_Type.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlockType extends WP_UnitTestCase {

	/**
	 * Editor user ID.
	 *
	 * @since 5.0.0
	 * @var int
	 */
	protected static $editor_user_id;

	/**
	 * ID for a post containing blocks.
	 *
	 * @since 5.0.0
	 * @var int
	 */
	protected static $post_with_blocks;

	/**
	 * ID for a post without blocks.
	 *
	 * @since 5.0.0
	 * @var int
	 */
	protected static $post_without_blocks;

	/**
	 * Set up before class.
	 *
	 * @since 5.0.0
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_user_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$post_with_blocks = $factory->post->create(
			array(
				'post_title'   => 'Example',
				'post_content' => "<!-- wp:core/text {\"dropCap\":true} -->\n<p class=\"has-drop-cap\">Tester</p>\n<!-- /wp:core/text -->",
			)
		);

		self::$post_without_blocks = $factory->post->create(
			array(
				'post_title'   => 'Example',
				'post_content' => 'Tester',
			)
		);
	}

	/**
	 * @ticket 45097
	 */
	public function test_set_props() {
		$name = 'core/fake';
		$args = array(
			'render_callback' => array( $this, 'render_fake_block' ),
			'foo'             => 'bar',
		);

		$block_type = new WP_Block_Type( $name, $args );

		$this->assertSame( $name, $block_type->name );
		$this->assertSame( $args['render_callback'], $block_type->render_callback );
		$this->assertSame( $args['foo'], $block_type->foo );
	}

	/*
	 * @ticket 55567
	 * @ticket 59797
	 * @covers WP_Block_Type::set_props
	 */
	public function test_core_attributes() {
		$block_type = new WP_Block_Type( 'core/fake', array() );

		$this->assertSameSetsWithIndex(
			array(
				'lock'     => array( 'type' => 'object' ),
				'metadata' => array( 'type' => 'object' ),
			),
			$block_type->attributes
		);
	}

	/*
	 * @ticket 55567
	 * @ticket 59797
	 * @covers WP_Block_Type::set_props
	 */
	public function test_core_attributes_matches_custom() {
		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'attributes' => array(
					'lock'     => array(
						'type' => 'string',
					),
					'metadata' => array(
						'type' => 'number',
					),
				),
			)
		);

		// Backward compatibility: Don't override attributes with the same name.
		$this->assertSameSetsWithIndex(
			array(
				'lock'     => array( 'type' => 'string' ),
				'metadata' => array( 'type' => 'number' ),
			),
			$block_type->attributes
		);
	}

	/**
	 * @ticket 45097
	 */
	public function test_render() {
		$attributes = array(
			'foo' => 'bar',
			'bar' => 'foo',
		);

		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'render_callback' => array( $this, 'render_fake_block' ),
			)
		);
		$output     = $block_type->render( $attributes );
		$this->assertSame( $attributes, json_decode( $output, true ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_render_with_content() {
		$attributes = array(
			'foo' => 'bar',
			'bar' => 'foo',
		);

		$content = 'baz';

		$expected = array_merge( $attributes, array( '_content' => $content ) );

		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'render_callback' => array( $this, 'render_fake_block_with_content' ),
			)
		);
		$output     = $block_type->render( $attributes, $content );
		$this->assertSame( $expected, json_decode( $output, true ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_render_for_static_block() {
		$block_type = new WP_Block_Type( 'core/fake', array() );
		$output     = $block_type->render();

		$this->assertSame( '', $output );
	}

	/**
	 * @ticket 45097
	 */
	public function test_is_dynamic_for_static_block() {
		$block_type = new WP_Block_Type( 'core/fake', array() );

		$this->assertFalse( $block_type->is_dynamic() );
	}

	/**
	 * @ticket 45097
	 */
	public function test_is_dynamic_for_dynamic_block() {
		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'render_callback' => array( $this, 'render_fake_block' ),
			)
		);

		$this->assertTrue( $block_type->is_dynamic() );
	}

	/**
	 * @ticket 45097
	 */
	public function test_prepare_attributes() {
		$attributes = array(
			'correct'            => 'include',
			'wrongType'          => 5,
			'wrongTypeDefaulted' => 5,
			/* missingDefaulted */
			'undefined'          => 'include',
			'intendedNull'       => null,
		);

		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'attributes' => array(
					'correct'            => array(
						'type' => 'string',
					),
					'wrongType'          => array(
						'type' => 'string',
					),
					'wrongTypeDefaulted' => array(
						'type'    => 'string',
						'default' => 'defaulted',
					),
					'missingDefaulted'   => array(
						'type'    => 'string',
						'default' => 'define',
					),
					'intendedNull'       => array(
						'type'    => array( 'string', 'null' ),
						'default' => 'wrong',
					),
				),
			)
		);

		$prepared_attributes = $block_type->prepare_attributes_for_render( $attributes );

		$this->assertSameSetsWithIndex(
			array(
				'correct'            => 'include',
				/* wrongType */
				'wrongTypeDefaulted' => 'defaulted',
				'missingDefaulted'   => 'define',
				'undefined'          => 'include',
				'intendedNull'       => null,
			),
			$prepared_attributes
		);
	}

	/**
	 * @ticket 45145
	 */
	public function test_prepare_attributes_none_defined() {
		$attributes = array( 'exists' => 'keep' );

		$block_type = new WP_Block_Type( 'core/dummy', array() );

		$prepared_attributes = $block_type->prepare_attributes_for_render( $attributes );

		$this->assertSame( $attributes, $prepared_attributes );
	}

	/**
	 * @ticket 45097
	 */
	public function test_has_block_with_mixed_content() {
		$mixed_post_content = 'before' .
		'<!-- wp:core/fake --><!-- /wp:core/fake -->' .
		'<!-- wp:core/fake_atts {"value":"b1"} --><!-- /wp:core/fake_atts -->' .
		'<!-- wp:core/fake-child -->
		<p>testing the test</p>
		<!-- /wp:core/fake-child -->' .
		'between' .
		'<!-- wp:core/self-close-fake /-->' .
		'<!-- wp:custom/fake {"value":"b2"} /-->' .
		'after';

		$this->assertTrue( has_block( 'core/fake', $mixed_post_content ) );

		$this->assertTrue( has_block( 'core/fake_atts', $mixed_post_content ) );

		$this->assertTrue( has_block( 'core/fake-child', $mixed_post_content ) );

		$this->assertTrue( has_block( 'core/self-close-fake', $mixed_post_content ) );

		$this->assertTrue( has_block( 'custom/fake', $mixed_post_content ) );

		// checking for a partial block name should fail.
		$this->assertFalse( has_block( 'core/fak', $mixed_post_content ) );

		// checking for a wrong namespace should fail.
		$this->assertFalse( has_block( 'custom/fake_atts', $mixed_post_content ) );

		// checking for namespace only should not work. Or maybe ... ?
		$this->assertFalse( has_block( 'core', $mixed_post_content ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_has_block_with_invalid_content() {
		// some content with invalid HTML comments and a single valid block.
		$invalid_content = 'before' .
		'<!- - wp:core/weird-space --><!-- /wp:core/weird-space -->' .
		'<!--wp:core/untrimmed-left --><!-- /wp:core/untrimmed -->' .
		'<!-- wp:core/fake --><!-- /wp:core/fake -->' .
		'<!-- wp:core/untrimmed-right--><!-- /wp:core/untrimmed2 -->' .
		'after';

		$this->assertFalse( has_block( 'core/text', self::$post_without_blocks ) );

		$this->assertFalse( has_block( 'core/weird-space', $invalid_content ) );

		$this->assertFalse( has_block( 'core/untrimmed-left', $invalid_content ) );

		$this->assertFalse( has_block( 'core/untrimmed-right', $invalid_content ) );

		$this->assertTrue( has_block( 'core/fake', $invalid_content ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_post_has_block() {
		// should fail for a non-existent block `custom/fake`.
		$this->assertFalse( has_block( 'custom/fake', self::$post_with_blocks ) );

		// this functions should not work without the second param until the $post global is set.
		$this->assertFalse( has_block( 'core/text' ) );
		$this->assertFalse( has_block( 'core/fake' ) );

		global $post;
		$post = get_post( self::$post_with_blocks );

		// check if the function correctly detects content from the $post global.
		$this->assertTrue( has_block( 'core/text' ) );
		// even if it detects a proper $post global it should still be false for a missing block.
		$this->assertFalse( has_block( 'core/fake' ) );
	}

	public function test_post_has_block_serialized_name() {
		$content = '<!-- wp:serialized /--><!-- wp:core/normalized /--><!-- wp:plugin/third-party /-->';

		$this->assertTrue( has_block( 'core/serialized', $content ) );

		/*
		 * Technically, `has_block` should receive a "full" (normalized, parsed)
		 * block name. But this test conforms to expected pre-5.3.1 behavior.
		 */
		$this->assertTrue( has_block( 'serialized', $content ) );
		$this->assertTrue( has_block( 'core/normalized', $content ) );
		$this->assertTrue( has_block( 'normalized', $content ) );
		$this->assertFalse( has_block( 'plugin/normalized', $content ) );
		$this->assertFalse( has_block( 'plugin/serialized', $content ) );
		$this->assertFalse( has_block( 'third-party', $content ) );
		$this->assertFalse( has_block( 'core/third-party', $content ) );
	}

	/**
	 * Renders a test block without content.
	 *
	 * @since 5.0.0
	 *
	 * @param array $attributes Block attributes. Default empty array.
	 * @return string JSON encoded list of attributes.
	 */
	public function render_fake_block( $attributes ) {
		return json_encode( $attributes );
	}

	/**
	 * Renders a test block with content.
	 *
	 * @since 5.0.0
	 *
	 * @param array  $attributes Block attributes. Default empty array.
	 * @param string $content    Block content. Default empty string.
	 * @return string JSON encoded list of attributes.
	 */
	public function render_fake_block_with_content( $attributes, $content ) {
		$attributes['_content'] = $content;

		return json_encode( $attributes );
	}

	/**
	 * @ticket 48529
	 */
	public function test_register_block() {
		$block_type = new WP_Block_Type(
			'core/fake',
			array(
				'title'       => 'Test title',
				'category'    => 'Test category',
				'parent'      => array( 'core/third-party' ),
				'icon'        => 'icon.png',
				'description' => 'test description',
				'keywords'    => array( 'test keyword' ),
				'textdomain'  => 'test_domain',
				'supports'    => array( 'alignment' => true ),
			)
		);

		$this->assertSame( 'Test title', $block_type->title );
		$this->assertSame( 'Test category', $block_type->category );
		$this->assertSameSets( array( 'core/third-party' ), $block_type->parent );
		$this->assertSame( 'icon.png', $block_type->icon );
		$this->assertSame( 'test description', $block_type->description );
		$this->assertSameSets( array( 'test keyword' ), $block_type->keywords );
		$this->assertSame( 'test_domain', $block_type->textdomain );
		$this->assertSameSets( array( 'alignment' => true ), $block_type->supports );
	}

	/**
	 * Testing the block version.
	 *
	 * @ticket 43887
	 *
	 * @dataProvider data_block_version
	 *
	 * @param string|null $content  Content.
	 * @param int         $expected Expected block version.
	 */
	public function test_block_version( $content, $expected ) {
		$this->assertSame( $expected, block_version( $content ) );
	}

	/**
	 * Test cases for test_block_version().
	 *
	 * @since 5.0.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type string|null Content.
	 *         @type int         Expected block version.
	 *     }
	 * }
	 */
	public function data_block_version() {
		return array(
			// Null.
			array( null, 0 ),
			// Empty post content.
			array( '', 0 ),
			// Post content without blocks.
			array( '<hr class="wp-block-separator" />', 0 ),
			// Post content with a block.
			array( '<!-- wp:core/separator -->', 1 ),
			// Post content with a fake block.
			array( '<!-- wp:core/fake --><!-- /wp:core/fake -->', 1 ),
			// Post content with an invalid block.
			array( '<!- - wp:core/separator -->', 0 ),
		);
	}

	/**
	 * @ticket 59969
	 */
	public function test_variation_callback() {
		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => array( $this, 'mock_variation_callback' ),
			)
		);

		$this->assertSameSets( $this->mock_variation_callback(), $block_type->variations );
	}

	/**
	 * @ticket 59969
	 * @covers WP_Block_Type::get_variations
	 */
	public function test_get_variations() {
		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => array( $this, 'mock_variation_callback' ),
			)
		);

		$this->assertSameSets( $this->mock_variation_callback(), $block_type->get_variations() );
	}

	/**
	 * @ticket 59969
	 */
	public function test_variations_precedence_over_callback() {
		$test_variations = array( 'name' => 'test1' );

		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variations'         => $test_variations,
				'variation_callback' => array( $this, 'mock_variation_callback' ),
			)
		);

		// If the variations are defined, the callback should not be used.
		$this->assertSameSets( $test_variations, $block_type->variations );
	}

	/**
	 * @ticket 59969
	 */
	public function test_variations_callback_are_lazy_loaded() {
		$callback_called = false;

		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => function () use ( &$callback_called ) {
					$callback_called = true;
					return $this->mock_variation_callback();
				},
			)
		);

		$this->assertSame( false, $callback_called, 'The callback should not be called before the variations are accessed.' );
		$block_type->variations; // access the variations.
		$this->assertSame( true, $callback_called, 'The callback should be called when the variations are accessed.' );
	}

	/**
	 * @ticket 59969
	 * @covers WP_Block_Type::get_variations
	 */
	public function test_variations_precedence_over_callback_post_registration() {
		$test_variations = array( 'name' => 'test1' );
		$callback_called = false;

		$block_type             = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => function () use ( &$callback_called ) {
					$callback_called = true;
					return $this->mock_variation_callback();
				},
			)
		);
		$block_type->variations = $test_variations;

		// If the variations are defined after registration but before first access, the callback should not override it.
		$this->assertSameSets( $test_variations, $block_type->get_variations(), 'Variations are same as variations set' );
		$this->assertSame( false, $callback_called, 'The callback was never called.' );
	}

	/**
	 * @ticket 59969
	 * @covers WP_Block_Type::get_variations
	 */
	public function test_variations_callback_happens_only_once() {
		$callback_count = 0;

		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => function () use ( &$callback_count ) {
					$callback_count++;
					return $this->mock_variation_callback();
				},
			)
		);

		$this->assertSame( 0, $callback_count, 'The callback should not be called before the variations are accessed.' );
		$block_type->get_variations(); // access the variations.
		$this->assertSame( 1, $callback_count, 'The callback should be called when the variations are accessed.' );
		$block_type->get_variations(); // access the variations again.
		$this->assertSame( 1, $callback_count, 'The callback should not be called again.' );
	}

	/**
	 * Test filter function for get_block_type_variations filter.
	 *
	 * @param array $variations Block variations before filter.
	 * @param WP_Block_Type $block_type Block type.
	 *
	 * @return array Block variations after filter.
	 */
	public function filter_test_variations( $variations, $block_type ) {
		return array( array( 'name' => 'test1' ) );
	}

	/**
	 * @ticket 59969
	 */
	public function test_get_block_type_variations_filter_with_variation_callback() {
		// Filter will override the variations obtained from the callback.
		add_filter( 'get_block_type_variations', array( $this, 'filter_test_variations' ), 10, 2 );
		$expected_variations = array( array( 'name' => 'test1' ) );

		$callback_called = false;
		$block_type      = new WP_Block_Type(
			'test/block',
			array(
				'title'              => 'Test title',
				'variation_callback' => function () use ( &$callback_called ) {
					$callback_called = true;
					return $this->mock_variation_callback();
				},
			)
		);

		$obtained_variations = $block_type->variations; // access the variations.

		$this->assertSame( true, $callback_called, 'The callback should be called when the variations are accessed.' );
		$this->assertSameSets( $obtained_variations, $expected_variations, 'The variations obtained from the callback should be filtered.' );
	}

	/**
	 * @ticket 59969
	 */
	public function test_get_block_type_variations_filter_variations() {
		// Filter will override the variations set during registration.
		add_filter( 'get_block_type_variations', array( $this, 'filter_test_variations' ), 10, 2 );
		$expected_variations = array( array( 'name' => 'test1' ) );

		$block_type = new WP_Block_Type(
			'test/block',
			array(
				'title'      => 'Test title',
				'variations' => $this->mock_variation_callback(),
			)
		);

		$obtained_variations = $block_type->variations; // access the variations.
		$this->assertSameSets( $obtained_variations, $expected_variations, 'The variations that was initially set should be filtered.' );
	}

	/**
	 * Mock variation callback.
	 *
	 * @return array
	 */
	public function mock_variation_callback() {
		return array(
			array( 'name' => 'var1' ),
			array( 'name' => 'var2' ),
		);
	}
}
