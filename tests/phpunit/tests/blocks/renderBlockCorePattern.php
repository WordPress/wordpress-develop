<?php
/**
 * Tests for the core/pattern block renderer.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_pattern
 */
class Tests_Blocks_RenderBlockCorePattern extends WP_UnitTestCase {
	/**
	 * Pattern used by the tests.
	 *
	 * @var string
	 */
	const PATTERN_NAME = 'tests/pattern-with-shortcode';

	/**
	 * Shortcode used by the tests.
	 *
	 * @var string
	 */
	const SHORTCODE_NAME = 'pattern_shortcode';

	/**
	 * Cleans up registered test data.
	 */
	public function tear_down() {
		if ( WP_Block_Patterns_Registry::get_instance()->is_registered( self::PATTERN_NAME ) ) {
			unregister_block_pattern( self::PATTERN_NAME );
		}
		remove_shortcode( self::SHORTCODE_NAME );

		parent::tear_down();
	}

	/**
	 * Tests that shortcodes in patterns are processed when rendered in templates.
	 *
	 * @ticket 58397
	 */
	public function test_renders_shortcode_in_pattern() {
		add_shortcode(
			self::SHORTCODE_NAME,
			static function () {
				return 'Shortcode output';
			}
		);

		register_block_pattern(
			self::PATTERN_NAME,
			array(
				'title'   => 'Pattern containing a shortcode',
				'content' => '<!-- wp:shortcode -->[pattern_shortcode]<!-- /wp:shortcode -->',
			)
		);

		$template = '<!-- wp:pattern {"slug":"tests/pattern-with-shortcode"} /-->';
		$content  = do_shortcode( $template );
		$content  = do_blocks( $content );

		$this->assertSame( "<p>Shortcode output</p>\n", $content );
	}
}
