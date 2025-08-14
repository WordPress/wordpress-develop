<?php

/**
 * Unit tests covering WP_Block_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since {WP_VERSION}
 *
 * @group block-scanner
 *
 * @coversDefaultClass WP_Block_Processor
 */
class Tests_Blocks_BlockScanner_WP_Block_Processor extends WP_UnitTestCase {
	public function test_creates_class_instance() {
		$processor = WP_Block_Processor::create( '' );

		$this->assertInstanceOf( 'WP_Block_Processor', $processor );
	}

	public function test_get_breadcrumbs() {
		$processor = WP_Block_Processor::create( '<!-- wp:top --><!-- wp:inside /--><!-- /wp:top -->' );

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
			$html .= "<!-- wp:ladder {\"level\":{$i}} -->\n";
		}

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$html .= "<!-- /wp:ladder -->\n";
		}

		$processor = WP_Block_Processor::create( $html );
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

	public function test_builds_block() {
		$cover_block     = [ 'blockName' => 'core/cover', 'attrs' => [], 'innerHTML' => '<img>', 'innerContent' => [ '<img>' ] ];
		$heading_block   = [ 'blockName' => 'core/heading', 'attrs' => [ 'level' => 2 ], 'innerHTML' => '<h2>Testing works!</h2>', 'innerContent' => [ '<h2>Testing works!</h2>' ] ];
		$paragraph_block = [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Who knew?</p>', 'innerContent' => [ '<p>Who knew?</p>' ] ];
		$group_block     = [ 'blockName' => 'core/group', 'attrs' => [], 'innerHTML' => '', 'innerBlocks' => [ $heading_block, $paragraph_block ], 'innerContent' => [ null, null ] ];
		$blocks          = [ $cover_block, $group_block ];
		$html            = serialize_blocks( $blocks );

		$processor = WP_Block_Processor::create( $html );

		$extracted = array();
		while ( $processor->next_delimiter() ) {
			$extracted[] = $processor->extract_block();
		}

		$this->assertSame(
			$blocks,
			$extracted,
			'Should have extracted a block matching the input group block.'
		);
	}
}
