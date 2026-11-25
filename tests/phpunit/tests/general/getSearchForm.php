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
	 * @ticket 66757
	 */
	public function test_get_search_form_returns_markup_when_echo_false() {
		$form = get_search_form( array( 'echo' => false ) );

		$this->assertIsString( $form );
		$this->assertStringContainsString( 'role="search"', $form );
		$this->assertStringContainsString( 'name="s"', $form );
		$this->assertStringContainsString( 'id="searchform"', $form );
	}

	/**
	 * Tests that aria_label is added to the default form markup.
	 *
	 * @ticket 66757
	 */
	public function test_get_search_form_includes_aria_label() {
		$form = get_search_form(
			array(
				'echo'       => false,
				'aria_label' => 'Site search',
			)
		);

		$this->assertStringContainsString( 'aria-label="Site search"', $form );
	}

	/**
	 * Tests the boolean back-compat argument still controls returning vs echoing.
	 *
	 * @ticket 66757
	 */
	public function test_get_search_form_boolean_echo_back_compat() {
		$form = get_search_form( false );

		$this->assertIsString( $form );
		$this->assertStringContainsString( 'name="s"', $form );

		$this->expectOutputRegex( '/name="s"/' );
		get_search_form( true );
	}
}
