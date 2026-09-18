<?php

/**
 * Tests the _default_wp_die_handler() function.
 *
 * @group functions
 *
 * @covers ::_default_wp_die_handler
 */
class Tests_Functions_DefaultWpDieHandler extends WP_UnitTestCase {

	/**
	 * Tests the default HTML output of the handler.
	 *
	 * @ticket 65655
	 */
	public function test_default_wp_die_handler_output() {
		ob_start();
		try {
			_default_wp_die_handler( 'Error Message', 'Error Title', array( 'exit' => false ) );
		} catch ( WPDieException $e ) {
		}
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="wp-die-message">Error Message</div>', $output );
		$this->assertStringContainsString( '<title>Error Title</title>', $output );
	}

	/**
	 * Tests handling of additional errors.
	 *
	 * @ticket 65655
	 */
	public function test_default_wp_die_handler_additional_errors() {
		$args = array(
			'exit'              => false,
			'additional_errors' => array(
				array( 'message' => 'Extra Error 1' ),
				array( 'message' => 'Extra Error 2' ),
			),
		);

		ob_start();
		try {
			_default_wp_die_handler( 'Main Error', '', $args );
		} catch ( WPDieException $e ) {
		}
		$output = ob_get_clean();

		$this->assertStringContainsString( '<li>Main Error</li>', $output );
		$this->assertStringContainsString( '<li>Extra Error 1</li>', $output );
		$this->assertStringContainsString( '<li>Extra Error 2</li>', $output );
	}

	/**
	 * Tests handling of a link.
	 *
	 * @ticket 65655
	 */
	public function test_default_wp_die_handler_link() {
		$args = array(
			'exit'      => false,
			'link_url'  => 'https://example.com',
			'link_text' => 'Go to Example',
		);

		ob_start();
		try {
			_default_wp_die_handler( 'Error', '', $args );
		} catch ( WPDieException $e ) {
		}
		$output = ob_get_clean();

		// The default handler uses single quotes for attributes in some cases.
		$this->assertStringContainsString( "href='https://example.com'", $output );
		$this->assertStringContainsString( '>Go to Example</a>', $output );
	}
}
