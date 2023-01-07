<?php
/**
 * @group formatting
 *
 * @covers ::links_add_target
 */
class Tests_Formatting_LinksAddTarget extends WP_UnitTestCase {
	/**
	 * Test Content DataProvider
	 *
	 * array ( input_txt, converted_output_txt)
	 */
	public function get_input_output() {
		return array(
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> END TEXT',
				null,
				null,
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC" target="_blank">LINK</a> HERE </div> END TEXT',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <A href="XYZ" src="ABC">LINK</A> HERE </div> END TEXT',
				null,
				null,
				'MY CONTENT <div> SOME ADDITIONAL TEXT <A href="XYZ" src="ABC" target="_blank">LINK</A> HERE </div> END TEXT',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <a href="XYZ"  >LINK</a>END TEXT',
				null,
				null,
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC" target="_blank">LINK</a> HERE </div> <a href="XYZ"   target="_blank">LINK</a>END TEXT',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span>END TEXT</span>',
				'_top',
				null,
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC" target="_top">LINK</a> HERE </div> <span>END TEXT</span>',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span>END TEXT</span>',
				'_top',
				array( 'span' ),
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="_top">END TEXT</span>',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span>END TEXT</span>',
				'_top',
				array( 'SPAN' ),
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="_top">END TEXT</span>',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="_top">END TEXT</span>',
				'_top',
				array( 'span', 'div' ),
				'MY CONTENT <div target="_top"> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="_top">END TEXT</span>',
			),
			array(
				'MY CONTENT <div target=\'ABC\'> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="xyz">END TEXT</span>',
				'_top',
				array( 'span', 'div' ),
				'MY CONTENT <div target="_top"> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="_top">END TEXT</span>',
			),
			array(
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span target="xyz" src="ABC">END TEXT</span>',
				'_top',
				array( 'span' ),
				'MY CONTENT <div> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </div> <span src="ABC" target="_top">END TEXT</span>',
			),
			array(
				'MY CONTENT <aside> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </aside> END TEXT',
				null,
				null,
				'MY CONTENT <aside> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC" target="_blank">LINK</a> HERE </aside> END TEXT',
			),
			array(
				'MY CONTENT <aside class="_blank"> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC">LINK</a> HERE </aside> END TEXT',
				null,
				null,
				'MY CONTENT <aside class="_blank"> SOME ADDITIONAL TEXT <a href="XYZ" src="ABC" target="_blank">LINK</a> HERE </aside> END TEXT',
			),
			array(
				'MY CONTENT <blockquote>SOME</blockquote> ADDITIONAL TEXT <b>LINK</b> HERE END TEXT',
				'_blank',
				array( 'b' ),
				'MY CONTENT <blockquote>SOME</blockquote> ADDITIONAL TEXT <b target="_blank">LINK</b> HERE END TEXT',
			),
			array(
				'MY CONTENT <blockquote target="_self">SOME</blockquote> ADDITIONAL TEXT <b>LINK</b> HERE END TEXT',
				'_blank',
				array( 'b' ),
				'MY CONTENT <blockquote target="_self">SOME</blockquote> ADDITIONAL TEXT <b target="_blank">LINK</b> HERE END TEXT',
			),
		);
	}

	/**
	 * Validate the normalize_whitespace function
	 *
	 * @dataProvider get_input_output
	 */
	public function test_normalize_whitespace( $content, $target, $tags, $exp_str ) {
		if ( true === is_null( $target ) ) {
			$this->assertSame( $exp_str, links_add_target( $content ) );
		} elseif ( true === is_null( $tags ) ) {
			$this->assertSame( $exp_str, links_add_target( $content, $target ) );
		} else {
			$this->assertSame( $exp_str, links_add_target( $content, $target, $tags ) );
		}
	}
}
