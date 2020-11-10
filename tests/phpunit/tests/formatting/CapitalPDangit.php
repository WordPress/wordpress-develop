<?php
// phpcs:disable WordPress.WP.CapitalPDangit.Misspelled -- 🙃

/**
 * @group formatting
 */
class Tests_Formatting_CapitalPDangit extends WP_UnitTestCase {
	function test_esc_attr_quotes() {
		global $wp_current_filter;
		$this->assertSame( 'Something about WordPress', capital_P_dangit( 'Something about Wordpress' ) );
		$this->assertSame( 'Something about (WordPress', capital_P_dangit( 'Something about (Wordpress' ) );
		$this->assertSame( 'Something about &#8216;WordPress', capital_P_dangit( 'Something about &#8216;Wordpress' ) );
		$this->assertSame( 'Something about &#8220;WordPress', capital_P_dangit( 'Something about &#8220;Wordpress' ) );
		$this->assertSame( 'Something about >WordPress', capital_P_dangit( 'Something about >Wordpress' ) );
		$this->assertSame( 'Wordpress', capital_P_dangit( 'Wordpress' ) );

		$wp_current_filter = array( 'the_title' );
		$this->assertSame( 'WordPress', capital_P_dangit( 'Wordpress' ) );
	}
}
