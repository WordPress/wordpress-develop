<?php

/**
 * Tests for `_default_wp_die_handler()`.
 *
 * @group functions
 *
 * @covers ::_default_wp_die_handler
 */
class Tests_Functions_DefaultWpDieHandler extends WP_UnitTestCase {

	/**
	 * Tests that the `heading` argument is printed as the page's main heading.
	 *
	 * @ticket 65797
	 */
	public function test_should_print_heading_before_message() {
		$actual = get_echo(
			'_default_wp_die_handler',
			array(
				'Something went wrong.',
				'',
				array(
					'heading' => 'WordPress &rsaquo; Error',
					'exit'    => false,
				),
			)
		);

		$this->assertStringContainsString(
			'<h1>WordPress &rsaquo; Error</h1><div class="wp-die-message">Something went wrong.</div>',
			$actual
		);
	}

	/**
	 * Tests that no heading is printed when the `heading` argument is not passed.
	 *
	 * @ticket 65797
	 */
	public function test_should_not_print_heading_by_default() {
		$actual = get_echo(
			'_default_wp_die_handler',
			array( 'Something went wrong.', '', array( 'exit' => false ) )
		);

		$this->assertStringNotContainsString( '<h1>', $actual );
	}

	/**
	 * Tests that the language attributes are used once general-template.php is loaded.
	 *
	 * @ticket 65797
	 */
	public function test_should_use_language_attributes_when_available() {
		add_filter(
			'language_attributes',
			static function () {
				return 'lang="de-DE"';
			}
		);

		$actual = get_echo(
			'_default_wp_die_handler',
			array( 'Something went wrong.', '', array( 'exit' => false ) )
		);

		$this->assertStringContainsString( '<html lang="de-DE">', $actual );
	}

	/**
	 * Tests that an explicit `text_direction` still takes priority over the
	 * language attributes.
	 *
	 * @ticket 65797
	 * @ticket 49060
	 */
	public function test_explicit_text_direction_should_take_priority() {
		$actual = get_echo(
			'_default_wp_die_handler',
			array(
				'Something went wrong.',
				'',
				array(
					'text_direction' => 'rtl',
					'exit'           => false,
				),
			)
		);

		$this->assertStringContainsString( "<html dir='rtl'>", $actual );
	}
}
