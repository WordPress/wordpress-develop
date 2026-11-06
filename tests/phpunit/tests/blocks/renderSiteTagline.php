<?php
/**
 * Tests for the server-side rendering of the Site Tagline block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_site_tagline
 */
class Tests_Blocks_RenderSiteTagline extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'blogdescription', 'Just another site' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Tests that nothing is rendered without a tagline.
	 *
	 * @ticket 66518
	 */
	public function test_should_render_nothing_without_a_tagline() {
		update_option( 'blogdescription', '' );

		$this->assertNull( render_block_core_site_tagline( array() ) );
	}

	/**
	 * Tests that the tagline is rendered in a paragraph by default.
	 *
	 * @ticket 66518
	 */
	public function test_should_render_the_tagline_in_a_paragraph_by_default() {
		$this->assertSame( '<p >Just another site</p>', render_block_core_site_tagline( array() ) );
	}

	/**
	 * Tests that the tag depends on the level.
	 *
	 * @ticket 66518
	 *
	 * @dataProvider data_levels
	 *
	 * @param mixed  $level    Level of the block.
	 * @param string $tag_name Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $level, $tag_name ) {
		$this->assertSame(
			"<{$tag_name} >Just another site</{$tag_name}>",
			render_block_core_site_tagline( array( 'level' => $level ) )
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
			'two'                 => array( 2, 'h2' ),
			'six'                 => array( 6, 'h6' ),
			'a numeric string'    => array( '3', 'h3' ),
		);
	}

	/**
	 * Tests the class of the text alignment.
	 *
	 * @ticket 66518
	 *
	 * @dataProvider data_text_alignments
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_class_of_the_text_alignment( $attributes, $expected ) {
		$this->assertSame( '<p ' . $expected . '>Just another site</p>', render_block_core_site_tagline( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_alignments() {
		return array(
			'no alignment'   => array( array(), '' ),
			'an empty value' => array( array( 'textAlign' => '' ), '' ),
			'a center value' => array( array( 'textAlign' => 'center' ), 'class="has-text-align-center"' ),
			'a right value'  => array( array( 'textAlign' => 'right' ), 'class="has-text-align-right"' ),
		);
	}

	/**
	 * Tests that the alignment and the level are used together.
	 *
	 * @ticket 66518
	 */
	public function test_should_use_the_level_and_the_text_alignment_together() {
		$this->assertSame(
			'<h2 class="has-text-align-left">Just another site</h2>',
			render_block_core_site_tagline(
				array(
					'level'     => 2,
					'textAlign' => 'left',
				)
			)
		);
	}

	/**
	 * Tests that the tagline is the one of the site that is rendered.
	 *
	 * @ticket 66518
	 */
	public function test_should_render_the_current_tagline() {
		update_option( 'blogdescription', 'A different tagline' );

		$this->assertSame( '<p >A different tagline</p>', render_block_core_site_tagline( array() ) );
	}
}
