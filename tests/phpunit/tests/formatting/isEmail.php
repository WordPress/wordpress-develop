<?php

/**
 * @group formatting
 *
 * @covers ::is_email
 */
class Tests_Formatting_IsEmail extends WP_UnitTestCase {

	/**
	 * Data provider for valid email addresses.
	 *
	 * @return array
	 */
	public function valid_email_provider() {
		return array(
			'valid email 1' => array( 'bob@example.com' ),
			'valid email 2' => array( 'phil@example.info' ),
			'valid email 3' => array( 'ace@204.32.222.14' ),
			'valid email 4' => array( 'kevin@many.subdomains.make.a.happy.man.edu' ),
			'valid email 5' => array( 'a@b.co' ),
			'valid email 6' => array( 'bill+ted@example.com' ),
		);
	}

	/**
	 * Data provider for invalid email addresses.
	 *
	 * @return array
	 */
	public function invalid_email_provider() {
		return array(
			'invalid email 1' => array( 'khaaaaaaaaaaaaaaan!' ),
			'invalid email 2' => array( 'http://bob.example.com/' ),
			'invalid email 3' => array( "sif i'd give u it, spamer!1" ),
			'invalid email 4' => array( 'com.exampleNOSPAMbob' ),
			'invalid email 5' => array( 'bob@your mom' ),
			'invalid email 6' => array( 'a@b.c' ),
		);
	}

	/**
	 * @dataProvider valid_email_provider
	 */
	public function test_returns_the_email_address_if_it_is_valid( $email ) {
		$this->assertSame( $email, is_email( $email ), $email );
	}

	/**
	 * @dataProvider invalid_email_provider
	 */
	public function test_returns_false_if_given_an_invalid_email_address( $email ) {
		$this->assertFalse( is_email( $email ), $email );
	}
}
