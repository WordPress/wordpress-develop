<?php

/**
 * @group rewrite
 *
 * @covers ::add_feed
 */
class Tests_Rewrite_AddFeed extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		global $wp_rewrite;
		$wp_rewrite->init();
		parent::tear_down();
	}

	/**
	 * Tests that a feed name is properly escaped in the generated rewrite rules regex pattern.
	 *
	 * @ticket 43571
	 *
	 * @dataProvider data_add_feed_is_escaped_in_rules
	 *
	 * @param string $feed_name        The feed name to add.
	 * @param string $expected_pattern The expected escaped pattern.
	 */
	public function test_add_feed_is_escaped_in_rules( $feed_name, $expected_pattern ) {
		global $wp_rewrite;

		add_feed( $feed_name, '__return_false' );
		flush_rewrite_rules();

		$rules = $wp_rewrite->rewrite_rules();

		$found = false;
		foreach ( array_keys( $rules ) as $pattern ) {
			if ( str_contains( $pattern, $expected_pattern ) ) {
				$found = true;

				// Confirm the full rewrite rule pattern is a syntactically valid regex.
				$this->assertNotFalse(
					preg_match( '#' . $pattern . '#', '' ),
					sprintf( 'The rewrite rule pattern "%s" is not a valid regular expression.', $pattern )
				);

				break;
			}
		}

		$this->assertTrue( $found, 'Expected to find a rewrite rule with the feed name properly escaped.' );
	}

	public function data_add_feed_is_escaped_in_rules() {
		return array(
			'plain name'          => array( 'json', 'json' ),
			'character class'     => array( 'test[json]', 'test\[json\]' ),
			'quantifier chars'    => array( 'test???', 'test\?\?\?' ),
		);
	}
}
