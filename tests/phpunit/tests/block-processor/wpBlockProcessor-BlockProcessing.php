<?php

/**
 * Unit tests covering WP_Block_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.9.0
 *
 * @group block-processor
 *
 * @coversDefaultClass WP_Block_Processor
 */
class Tests_Blocks_BlockProcessor_BlockProcessing extends WP_UnitTestCase {
	public function test_get_breadcrumbs() {
		$processor = new WP_Block_Processor( '<!-- wp:top --><!-- wp:inside /--><!-- /wp:top -->' );

		$this->assertTrue(
			$processor->next_delimiter(),
			'Should have found the opening "top" delimiter but found nothing.'
		);

		$this->assertSame(
			array( 'core/top' ),
			$processor->get_breadcrumbs(),
			'Should have found only the single opening delimiter.'
		);

		$processor->next_delimiter();
		$this->assertSame(
			array( 'core/top', 'core/inside' ),
			$processor->get_breadcrumbs(),
			'Should have detected the nesting structure of the blocks.'
		);
	}

	/**
	 * Verifies that void blocks and HTML spans appear in the breadcrumbs
	 * and depth only while the processor is paused on them.
	 *
	 * @ticket 66138
	 *
	 * @covers ::get_breadcrumbs
	 * @covers ::get_depth
	 */
	public function test_breadcrumbs_and_depth_for_void_blocks_and_html_spans(): void {
		$processor = new WP_Block_Processor( '<!-- wp:a -->x<!-- wp:b /--><!-- wp:c /-->y<!-- wp:d --><!-- /wp:d --><!-- /wp:a -->z<!-- wp:e /-->' );

		$expected = array(
			array( 'core/a' ),
			array( 'core/a', '#html' ),
			array( 'core/a', 'core/b' ),
			array( 'core/a', 'core/c' ),
			array( 'core/a', '#html' ),
			array( 'core/a', 'core/d' ),
			array( 'core/a' ),
			array(),
			array( '#html' ),
			array( 'core/e' ),
		);

		foreach ( $expected as $i => $breadcrumbs ) {
			$this->assertTrue(
				$processor->next_token(),
				"Should have found token #{$i}: check test setup."
			);

			$this->assertSame(
				$breadcrumbs,
				$processor->get_breadcrumbs(),
				"Should have reported the proper breadcrumbs for token #{$i}."
			);

			$this->assertSame(
				count( $breadcrumbs ),
				$processor->get_depth(),
				"Should have reported the proper depth for token #{$i}."
			);
		}

		$this->assertFalse(
			$processor->next_token(),
			'Should have found no more tokens: check test setup.'
		);

		$this->assertSame(
			array(),
			$processor->get_breadcrumbs(),
			'Should have reported no open blocks after the last void block.'
		);

		$this->assertSame(
			0,
			$processor->get_depth(),
			'Should have reported no depth after the last void block.'
		);
	}

	/**
	 * Verifies that blocks left open at the end of a document remain on the stack
	 * after the trailing HTML span is visited.
	 *
	 * @ticket 66138
	 *
	 * @covers ::get_breadcrumbs
	 * @covers ::get_depth
	 */
	public function test_breadcrumbs_and_depth_for_unclosed_blocks(): void {
		$processor = new WP_Block_Processor( '<!-- wp:a --><!-- wp:my/b -->inner' );

		$processor->next_token();
		$processor->next_token();
		$this->assertTrue(
			$processor->next_token(),
			'Should have found the trailing inner HTML: check test setup.'
		);

		$this->assertSame(
			array( 'core/a', 'my/b', '#html' ),
			$processor->get_breadcrumbs(),
			'Should have reported the inner HTML inside both open blocks.'
		);

		$this->assertFalse(
			$processor->next_token(),
			'Should have found no more tokens: check test setup.'
		);

		$this->assertSame(
			array( 'core/a', 'my/b' ),
			$processor->get_breadcrumbs(),
			'Should have left the unclosed blocks open at the end of the document.'
		);

		$this->assertSame(
			2,
			$processor->get_depth(),
			'Should have reported the depth of the unclosed blocks.'
		);
	}

	public function test_get_depth() {
		// Create a deeply-nested stack of blocks.
		$html      = '';
		$max_depth = 10;

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$html .= "<!-- wp:ladder {\"level\":{$i}} -->";
		}

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$html .= '<!-- /wp:ladder -->';
		}

		$processor = new WP_Block_Processor( $html );

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$nth = $i + 1;

			$this->assertTrue(
				$processor->next_delimiter(),
				"Should have found opening delimiter #{$nth}: check test setup."
			);

			$this->assertSame(
				$i + 1,
				$processor->get_depth(),
				"Should have identified the proper depth of opening delimiter #{$nth}."
			);
		}

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$nth = $i + 1;

			$this->assertTrue(
				$processor->next_delimiter(),
				"Should have found closing delimiter #{$nth}: check test setup."
			);

			$this->assertSame(
				$max_depth - $i - 1,
				$processor->get_depth(),
				"Should have identified the proper depth of closing delimiter #{$nth}."
			);
		}
	}

	/**
	 * @dataProvider data_block_content
	 */
	public function test_builds_block( $block_content ) {
		$processor = new WP_Block_Processor( $block_content );

		$extracted = array();
		while ( $processor->next_block( '*' ) ) {
			$extracted[] = $processor->extract_full_block_and_advance();
		}

		$this->assertSame(
			parse_blocks( $block_content ),
			$extracted,
			'Should have extracted a block matching the input group block.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_block_content() {
		$contents = array(
			'no blocks, just freeform HTML',
			'<!-- wp:void /-->',
			'<!-- wp:paragraph --><p>Inner HTML</p><!-- /wp:paragraph -->',
			<<<HTML
<!-- wp:cover -->
<img>
<!-- /wp:cover -->

<!-- wp:group -->
<!-- wp:heading {"level":2} -->
<h2>Testing works!</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Who knew?</p>
<!-- /wp:paragraph -->
<!-- /wp:group -->
HTML
			,
		);

		return array_map(
			function ( $content ) {
				return array( $content );
			},
			$contents
		);
	}
}
