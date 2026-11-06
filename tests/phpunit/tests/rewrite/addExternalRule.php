<?php

/**
 * Tests for WP_Rewrite::add_external_rule().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::add_external_rule
 */
class Tests_Rewrite_AddExternalRule extends WP_UnitTestCase {

	/**
	 * Tests that an external rule is added, with its query.
	 *
	 * @ticket 66496
	 */
	public function test_should_add_the_rule() {
		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_external_rule( 'external/(.+)$', 'external.php?name=$1' );

		$this->assertSame( array( 'external/(.+)$' => 'external.php?name=$1' ), $wp_rewrite->non_wp_rules );
	}

	/**
	 * Tests that rules are added in order, and that a rule with the same expression is replaced.
	 *
	 * @ticket 66496
	 */
	public function test_should_add_rules_in_order_and_replace_the_same_expression() {
		$wp_rewrite = new WP_Rewrite();

		$wp_rewrite->add_external_rule( 'first/(.+)$', 'first.php?a=$1' );
		$wp_rewrite->add_external_rule( 'second/(.+)$', 'second.php?b=$1' );
		$wp_rewrite->add_external_rule( 'first/(.+)$', 'other.php?a=$1' );

		$this->assertSame(
			array(
				'first/(.+)$'  => 'other.php?a=$1',
				'second/(.+)$' => 'second.php?b=$1',
			),
			$wp_rewrite->non_wp_rules
		);
	}

	/**
	 * Tests that the rules are removed when the object is initialized again.
	 *
	 * @ticket 66496
	 */
	public function test_should_be_removed_when_the_object_is_initialized_again() {
		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->add_external_rule( 'external/(.+)$', 'external.php?name=$1' );

		$wp_rewrite->init();

		$this->assertSame( array(), $wp_rewrite->non_wp_rules );
	}

	/**
	 * Tests that an external rule is not one of the rules of WordPress.
	 *
	 * @ticket 66496
	 */
	public function test_should_not_add_a_rule_to_the_rules_of_wordpress() {
		$this->set_permalink_structure( '/%postname%/' );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->init();
		$rules_before = $wp_rewrite->rewrite_rules();

		$wp_rewrite->add_external_rule( 'external/(.+)$', 'external.php?name=$1' );

		$this->assertSame( $rules_before, $wp_rewrite->rewrite_rules() );
	}
}
