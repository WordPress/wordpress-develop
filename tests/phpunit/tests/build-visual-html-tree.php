<?php

/**
 * Tests for build_visual_html_tree().
 *
 * @package WordPress
 *
 * @group testsuite
 */
class Tests_Build_Equivalent_HTML_Semantic_Tree extends WP_UnitTestCase {
	public function data_build_equivalent_html_semantic_tree() {
		$block_markup = <<<END
			<!-- wp:separator {"className":"is-style-default has-custom-classname","style":{"spacing":{"margin":{"top":"50px","bottom":"50px"}}},"backgroundColor":"accent-1"} -->
			  <hr class="wp-block-separator is-style-default has-custom-classname" style="margin-top: 50px; margin-bottom: 50px" />
			<!-- /wp:separator -->
END;

		$tree_structure = <<<END
BLOCK["core/separator"]
  {
    "backgroundColor": "accent-1",
    "className": "has-custom-classname is-style-default",
    "style": {
      "spacing": {
        "margin": {
          "top": "50px",
          "bottom": "50px"
        }
      }
    }
  }
  <hr>
    class="has-custom-classname is-style-default wp-block-separator"
    style="margin-top:50px;margin-bottom:50px;"

END;

		return array(
			'Block delimiter' => array( $block_markup, $tree_structure ),
		);
	}

	/**
	 * @ticket 63527
	 *
	 * @covers ::build_visual_html_tree
	 *
	 * @dataProvider data_build_equivalent_html_semantic_tree
	 */
	public function test_build_equivalent_html_semantic_tree( $markup, $expected ) {
		$actual = build_visual_html_tree( $markup, '<body>' );
		$this->assertSame( $expected, $actual );
	}

	public function data_build_equivalent_html_semantic_tree_with_equivalent_html() {
		return array(
			'Different attribute order'                => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png">',
			),
			'Different class name order'               => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-separator">',
			),
			'Differences in style attribute whitespace and trailing semicolon' => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top:50px;margin-bottom: 50px">',
			),
			'Different block attribute order'          => array(
				'<!-- wp:separator {"className":"is-style-default","backgroundColor":"accent-1"} -->',
				'<!-- wp:separator {"backgroundColor":"accent-1","className":"is-style-default"} -->',
			),
			'Different block class name order'         => array(
				'<!-- wp:separator {"className":"is-style-default has-custom-classname"} -->',
				'<!-- wp:separator {"className":"has-custom-classname is-style-default"} -->',
			),
			'Different whitespace in block class name' => array(
				'<!-- wp:separator {"className":"wp-block-separator is-style-default"} -->',
				'<!-- wp:separator {"className":"wp-block-separator   is-style-default "} -->',
			),
			'Duplicated block class names'             => array(
				'<!-- wp:separator {"className":"wp-block-separator is-style-default"} -->',
				'<!-- wp:separator {"className":"wp-block-separator is-style-default wp-block-separator"} -->',
			),
			'Different Capitalization of tag'          => array(
				'<IMG src="wp.png" alt="The WordPress logo">',
				'<img src="wp.png" alt="The WordPress logo">',
			),
		);
	}

	/**
	 * @ticket 63527
	 *
	 * @covers ::build_visual_html_tree
	 *
	 * @dataProvider data_build_equivalent_html_semantic_tree_with_equivalent_html
	 */
	public function test_build_equivalent_html_semantic_tree_with_equivalent_html( $expected, $actual ) {
		$tree_expected = build_visual_html_tree( $expected, '<body>' );
		$tree_actual   = build_visual_html_tree( $actual, '<body>' );

		$this->assertSame( $tree_expected, $tree_actual );
	}

	public function data_build_equivalent_html_semantic_tree_with_non_equivalent_html() {
		return array(
			'Different attributes'             => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png" title="WordPress">',
			),
			'Different class names'            => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-hairline">',
			),
			'Different styles'                 => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top: 50px; margin-bottom: 100px">',
			),
			'Different comments'               => array(
				'<!-- abc -->',
				'<!-- xyz -->',
			),
			'Semantically relevant whitespace' => array(
				'<div style="color: rgb(50 139 31)">Test</div>',
				'<div style="color:rgb(5013931)">Test</div>',
			),
		);
	}

	/**
	 * @ticket 63527
	 *
	 * @covers ::build_visual_html_tree
	 *
	 * @dataProvider data_build_equivalent_html_semantic_tree_with_non_equivalent_html
	 */
	public function test_build_equivalent_html_semantic_tree_with_non_equivalent_html( $expected, $actual ) {
		$tree_expected = build_visual_html_tree( $expected, '<body>' );
		$tree_actual   = build_visual_html_tree( $actual, '<body>' );

		$this->assertNotSame( $tree_expected, $tree_actual );
	}
}
