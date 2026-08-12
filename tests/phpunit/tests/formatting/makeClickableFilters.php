<?php

/**
 * @group formatting
 *
 * @covers ::make_clickable
 * @covers ::make_url_clickable
 * @covers ::make_ftp_clickable
 * @covers ::make_email_clickable
 */
class Tests_Formatting_MakeClickableFilters extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		remove_all_filters( 'make_clickable' );
		add_filter( 'make_clickable', 'make_url_clickable', 2 );
		add_filter( 'make_clickable', 'make_ftp_clickable', 4 );
		add_filter( 'make_clickable', 'make_email_clickable', 6 );
	}

	public function tear_down() {
		parent::tear_down();
		remove_all_filters( 'make_clickable' );
	}

	/**
	 * @ticket 32787
	 */
	public function test_remove_url_filter() {
		remove_filter( 'make_clickable', 'make_url_clickable', 2 );

		$text     = 'Visit http://wordpress.org and test@example.com';
		$expected = 'Visit http://wordpress.org and <a href="mailto:test@example.com">test@example.com</a>';

		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * @ticket 32787
	 */
	public function test_remove_email_filter() {
		remove_filter( 'make_clickable', 'make_email_clickable', 6 );

		$text     = 'Visit http://wordpress.org and test@example.com';
		$expected = 'Visit <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a> and test@example.com';

		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * @ticket 32787
	 */
	public function test_remove_ftp_filter() {
		remove_filter( 'make_clickable', 'make_ftp_clickable', 4 );

		$text     = 'Visit ftp.wordpress.org and http://wordpress.org';
		$expected = 'Visit ftp.wordpress.org and <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>';

		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * @ticket 32787
	 */
	public function test_remove_all_filters() {
		remove_all_filters( 'make_clickable' );

		$text     = 'Visit http://wordpress.org, ftp.wordpress.org and test@example.com';
		$expected = 'Visit http://wordpress.org, ftp.wordpress.org and test@example.com';

		$this->assertSame( $expected, make_clickable( $text ) );
	}
}
