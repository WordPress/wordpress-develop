<?php declare( strict_types = 1 );

/**
 * Tests for build_tree_representation().
 *
 * @package WordPress
 *
 * @group testsuite
 */
class Tests_Assert_Equal_Markup extends WP_UnitTestCase {
	public function data_build_tree_representation() {
		return array(
			'Block delimiter' => array(
				<<<END
				<!-- wp:separator {"className":"is-style-default has-custom-classname","style":{"spacing":{"margin":{"top":"50px","bottom":"50px"}}},"backgroundColor":"accent-1"} -->
				  <hr class="wp-block-separator is-style-default has-custom-classname" style="margin-top: 50px; margin-bottom: 50px" />
				<!-- /wp:separator -->
				END,
				<<<END
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

				END,
			),
		);
	}

	/**
	 * @dataProvider data_build_tree_representation
	 */
	public function test_build_tree_representation( $markup, $expected ) {
		$actual = build_tree_representation( $markup, '<body>' );
		$this->assertSame( $expected, $actual );
	}

	public function data_assert_equal_markup_passes_for_equivalent_html() {
		return array(
			'Different attribute order' => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png">',
			),
			'Different class name order' => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-separator">',
			),
			'Differences in style attribute whitespace and trailing semicolon' => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top:50px;margin-bottom: 50px">',
			),
			'Different block attribute order' => array(
				'<!-- wp:separator {"className":"is-style-default","backgroundColor":"accent-1"} -->',
				'<!-- wp:separator {"backgroundColor":"accent-1","className":"is-style-default"} -->',
			),
			'Different block class name order' => array(
				'<!-- wp:separator {"className":"is-style-default has-custom-classname"} -->',
				'<!-- wp:separator {"className":"has-custom-classname is-style-default"} -->',
			),
		);
	}

	/**
	 * @dataProvider data_assert_equal_markup_passes_for_equivalent_html
	 */
	public function test_assert_equal_markup_passes_for_equivalent_html( $expected, $actual ) {
		$this->assertEqualMarkup( $expected, $actual );
	}

	public function data_assert_equal_markup_fails_for_non_equivalent_html() {
		return array(
			'Different attributes' => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png" title="WordPress">',
			),
			'Different class names' => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-hairline">',
			),
			'Different styles' => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top: 50px; margin-bottom: 100px">',
			),
			'Different comments' => array(
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
	 * @dataProvider data_assert_equal_markup_fails_for_non_equivalent_html
	 */
	public function test_assert_equal_markup_fails_for_non_equivalent_html( $expected, $actual ) {
		$this->expectException( 'PHPUnit\Framework\ExpectationFailedException' );
		$this->assertEqualMarkup( $expected, $actual );
	}
}
