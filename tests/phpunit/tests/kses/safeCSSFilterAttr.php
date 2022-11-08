<?php

/**
 * @group kses
 *
 * @covers ::safecss_filter_attr
 */
class Test_Kses_safeCSSFilterAttr extends WP_UnitTestCase {
	public function test_allowed_css() {
		$css      = 'margin-right: 20px;';
		$filtered = safecss_filter_attr( $css );

		$this->assertSame( $css, $filtered, 'Allowed CSS should not be filtered by safecss_filter_attr().' );
	}

	public function test_forbidden_css() {
		$allowed_css   = 'margin-right: 20px;';
		$forbidden_css = 'word-spacing: 30px;';
		$filtered      = safecss_filter_attr( $allowed_css . $forbidden_css );

		$this->assertSame( $allowed_css, $filtered, 'Forbidden CSS should be filtered by safecss_filter_attr().' );
	}
}
