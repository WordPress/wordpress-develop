<?php

/**
 * Tests for the `_navigation_markup()` function.
 *
 * @group link
 * @group template
 *
 * @covers ::_navigation_markup
 */
class Tests_Link_NavigationMarkup extends WP_UnitTestCase {

	/**
	 * Tests default markup structure and attributes.
	 *
	 * @ticket 66612
	 */
	public function test_navigation_markup_default_structure() {
		$links  = '<span class="page-numbers current">1</span><a class="page-numbers" href="#2">2</a>';
		$actual = _navigation_markup( $links );

		$this->assertStringContainsString( 'class="navigation posts-navigation"', $actual );
		$this->assertStringContainsString( 'aria-label="Posts navigation"', $actual );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Posts navigation</h2>', $actual );
		$this->assertStringContainsString( '<div class="nav-links">' . $links . '</div>', $actual );
	}

	/**
	 * Tests navigation markup with custom class, screen reader text, and aria label.
	 *
	 * @ticket 66612
	 */
	public function test_navigation_markup_custom_arguments() {
		$links  = '<a href="#next">Next</a>';
		$actual = _navigation_markup( $links, 'custom-class', 'Custom Reader Text', 'Custom Aria Label' );

		$this->assertStringContainsString( 'class="navigation custom-class"', $actual );
		$this->assertStringContainsString( 'aria-label="Custom Aria Label"', $actual );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Custom Reader Text</h2>', $actual );
		$this->assertStringContainsString( '<div class="nav-links">' . $links . '</div>', $actual );
	}

	/**
	 * Tests that aria-label defaults to screen reader text when aria-label is empty.
	 *
	 * @ticket 66612
	 */
	public function test_navigation_markup_aria_label_falls_back_to_screen_reader_text() {
		$links  = '<a href="#prev">Previous</a>';
		$actual = _navigation_markup( $links, 'pagination', 'Pagination Heading', '' );

		$this->assertStringContainsString( 'aria-label="Pagination Heading"', $actual );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Pagination Heading</h2>', $actual );
	}

	/**
	 * Tests that navigation_markup_template filter modifies the markup and receives class parameter.
	 *
	 * @ticket 66612
	 */
	public function test_navigation_markup_template_filter() {
		$passed_template  = '';
		$passed_css_class = '';

		add_filter(
			'navigation_markup_template',
			static function ( $template, $css_class ) use ( &$passed_template, &$passed_css_class ) {
				$passed_template  = $template;
				$passed_css_class = $css_class;

				return '<nav role="navigation" class="%1$s"><p>%2$s</p><ul class="links">%3$s</ul></nav>';
			},
			10,
			2
		);

		$links  = '<li>1</li>';
		$actual = _navigation_markup( $links, 'my-nav', 'My Nav' );

		$this->assertStringContainsString( 'class="navigation %1$s"', $passed_template );
		$this->assertSame( 'my-nav', $passed_css_class );
		$this->assertSame( '<nav role="navigation" class="my-nav"><p>My Nav</p><ul class="links"><li>1</li></ul></nav>', $actual );
	}

	/**
	 * Tests that CSS class is sanitized with sanitize_html_class.
	 *
	 * @ticket 66612
	 */
	public function test_navigation_markup_sanitizes_html_class() {
		$links  = '<a href="#page">Page</a>';
		$actual = _navigation_markup( $links, 'invalid class with spaces & <chars>' );

		$this->assertStringContainsString( 'class="navigation invalidclasswithspaceschars"', $actual );
	}
}
