<?php

/**
 * @group formatting
 *
 * @covers ::is_email
 */
class Tests_Formatting_IsEmail extends WP_UnitTestCase {

	/**
	 * @dataProvider valid_email_provider
	 */
	public function test_returns_the_email_address_if_it_is_valid( $email ) {
		$this->assertSame( $email, is_email( $email ), "is_email() should return the email address for $email." );
	}

	/**
	 * Data provider for valid email addresses.
	 *
	 * @return array
	 */
	public static function valid_email_provider() {
		$valid_emails = array(
			'bob@example.com',
			'phil@example.info',
			'ace@204.32.222.14',
			'kevin@many.subdomains.make.a.happy.man.edu',
			'a@b.co',
			'bill+ted@example.com',
		);

		foreach ( $valid_emails as $email ) {
			yield $email => array( $email );
		}
	}

	/**
	 * @dataProvider invalid_email_provider
	 */
	public function test_returns_false_if_given_an_invalid_email_address( $email ) {
		$this->assertFalse( is_email( $email ), "is_email() should return false for $email." );
	}

	/**
	 * Data provider for invalid email addresses.
	 *
	 * @return array
	 */
	public static function invalid_email_provider() {
		$invalid_emails = array(
			'khaaaaaaaaaaaaaaan!',
			'http://bob.example.com/',
			"sif i'd give u it, spamer!1",
			'com.exampleNOSPAMbob',
			'bob@your mom',
			'a@b.c',
		);

		foreach ( $invalid_emails as $email ) {
			yield $email => array( $email );
		}
	}
}
