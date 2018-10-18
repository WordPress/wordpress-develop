<?php
/**
 * WP_Block_Type Tests
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Tests for WP_Block_Type
 *
 * @since 5.0.0
 *
 * @group blocks
 */
class WP_Test_Block_Type extends WP_UnitTestCase {

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
	public static function wpSetUpBeforeClass() {
		self::$editor_user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$post_with_blocks = self::factory()->post->create(
			array(
				'post_title'   => 'Example',
				'post_content' => "<!-- wp:core/text {\"dropCap\":true} -->\n<p class=\"has-drop-cap\">Tester</p>\n<!-- /wp:core/text -->",
			)
		);

		self::$post_without_blocks = self::factory()->post->create(
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
		$this->assertEquals( $attributes, json_decode( $output, true ) );
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
		$this->assertEquals( $expected, json_decode( $output, true ) );
	}

	/**
	 * @ticket 45097
	 */
	public function test_render_for_static_block() {
		$block_type = new WP_Block_Type( 'core/fake', array() );
		$output     = $block_type->render();

		$this->assertEquals( '', $output );
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
			'undefined'          => 'omit',
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
				),
			)
		);

		$prepared_attributes = $block_type->prepare_attributes_for_render( $attributes );

		$this->assertEquals(
			array(
				'correct'            => 'include',
				'wrongType'          => null,
				'wrongTypeDefaulted' => 'defaulted',
				'missingDefaulted'   => 'define',
			),
			$prepared_attributes
		);
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
		// some content with invalid HMTL comments and a single valid block.
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
}
