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
		$n         = new NumberFormatter( 'en-US', NumberFormatter::ORDINAL );

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$this->assertTrue(
				$processor->next_delimiter(),
				"Should have found {$n->format( $i + 1 )} opening delimiter: check test setup."
			);

			$this->assertSame(
				$i + 1,
				$processor->get_depth(),
				"Should have identified the proper depth of the {$n->format( $i + 1 )} opening delimiter."
			);
		}

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$this->assertTrue(
				$processor->next_delimiter(),
				"Should have found {$n->format( $i + 1 )} closing delimiter: check test setup."
			);

			$this->assertSame(
				$max_depth - $i - 1,
				$processor->get_depth(),
				"Should have identified the proper depth of the {$n->format( $i + 1 )} closing delimiter."
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
			$extracted[] = $processor->extract_block();
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
