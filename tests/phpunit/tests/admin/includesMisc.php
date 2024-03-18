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
}
