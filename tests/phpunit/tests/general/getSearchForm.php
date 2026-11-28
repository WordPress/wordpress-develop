<?php

/**
 * Tests for the `get_search_form()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::get_search_form
 */
class Tests_General_GetSearchForm extends WP_UnitTestCase {

	/**
	 * Tests that echo => false returns the form markup.
	 *
	 * @ticket 66830
	 */
	public function test_get_search_form_returns_markup_when_echo_false() {
		$form = get_search_form( array( 'echo' => false ) );

		$this->assertIsString( $form );
		$this->assertStringContainsString( 'role="search"', $form );
		$this->assertStringContainsString( 'name="s"', $form );
		$this->assertStringContainsString( 'id="searchform"', $form );
	}

	/**
	 * Tests that aria_label is escaped and HTML5 markup is used when supported.
	 *
	 * @ticket 66830
	 */
	public function test_get_search_form_includes_aria_label() {
		add_theme_support( 'html5', array( 'search-form' ) );

		$form = get_search_form(
			array(
				'echo'       => false,
				'aria_label' => 'News & reviews',
			)
		);

		$this->assertStringContainsString( 'aria-label="News &amp; reviews"', $form, 'The aria-label attribute should be escaped.' );
		$this->assertStringContainsString( 'class="search-form"', $form, 'The HTML5 form class should be used.' );
		$this->assertStringContainsString( 'type="search"', $form, 'The HTML5 search input type should be used.' );
		$this->assertStringNotContainsString( 'id="searchform"', $form, 'The XHTML form ID should not be used.' );
	}

	/**
	 * Tests the boolean back-compat argument still controls returning vs echoing.
	 *
	 * @ticket 66830
	 */
	public function test_get_search_form_boolean_echo_back_compat() {
		$form = get_search_form( false );

		$this->assertIsString( $form );
		$this->assertStringContainsString( 'name="s"', $form );

		$this->expectOutputRegex( '/name="s"/' );
		get_search_form( true );
	}
}
