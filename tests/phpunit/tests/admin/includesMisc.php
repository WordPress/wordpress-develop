<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesMisc extends WP_UnitTestCase {

	/**
	 * @covers ::url_shorten
	 */
	public function test_shorten_url() {
		$tests = array(
			'wordpress\.org/about/philosophy'
				=> 'wordpress\.org/about/philosophy',     // No longer strips slashes.
			'wordpress.org/about/philosophy'
				=> 'wordpress.org/about/philosophy',
			'http://wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, trailing slash.
			'http://www.wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, www.
			'http://wordpress.org/about/philosophy/#box'
				=> 'wordpress.org/about/philosophy/#box',      // Don't shorten 35 characters.
			'http://wordpress.org/about/philosophy/#decisions'
				=> 'wordpress.org/about/philosophy/#&hellip;', // Shorten to 32 if > 35 after cleaning.
		);
		foreach ( $tests as $k => $v ) {
			$this->assertSame( $v, url_shorten( $k ) );
		}
	}

	/**
	 * @ticket 59520
	 */
	public function test_new_admin_email_subject_filter() {
		// Default value.
		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( '[Test Blog] New Admin Email Address', $mailer->get_sent()->subject );

		// Filtered value.
		add_filter(
			'new_admin_email_subject',
			function () {
				return 'Filtered Admin Email Address';
			},
			10,
			1
		);

		$mailer->mock_sent = array();

		$mailer = tests_retrieve_phpmailer_instance();
		update_option_new_admin_email( 'old@example.com', 'new@example.com' );
		$this->assertSame( 'Filtered Admin Email Address', $mailer->get_sent()->subject );
	}

	/**
	 * @ticket 27888
	 *
	 * @covers ::get_current_admin_page_url
	 */
	public function test_get_current_admin_page_url() {
		$this->assertFalse( get_current_admin_page_url() );

		set_current_screen( 'edit.php' );
		global $pagenow;
		$pagenow                 = 'edit.php';
		$_SERVER['QUERY_STRING'] = 'post_type=page&orderby=title';

		$this->assertSame( 'edit.php?post_type=page&orderby=title', get_current_admin_page_url() );

		$_SERVER['QUERY_STRING'] = '';
		$this->assertSame( 'edit.php', get_current_admin_page_url() );

		$_SERVER['QUERY_STRING'] = '';
		set_current_screen( 'front' );
	}

	/**
	 * Data provider for test_get_current_admin_hook
	 *
	 * @return array Test data.
	 */
	public static function current_filters() {
		return array(
			array( '', array( '' ) ),
			array( 'some_hook', array( 'some_hook' ) ),
			array( 'another_hook', array( 'some_hook', 'another_hook' ) ),
		);
	}

	/**
	 * @ticket 27888
	 *
	 * @dataProvider current_filters
	 * @covers ::get_current_admin_hook
	 *
	 * @param string $expected Expected value.
	 * @param string $mock_current_filter Mock value for $wp_current_filter.
	 */
	public function test_get_current_admin_hook( $expected, $mock_current_filter ) {
		set_current_screen( 'edit.php' );

		global $wp_current_filter;
		$wp_current_filter = $mock_current_filter;

		$this->assertSame( $expected, get_current_admin_hook() );
	}
}
