<?php
/**
 * Tests for the is_email() function.
 *
 * @group formatting
 *
 * @covers ::is_email
 */
class Tests_Formatting_IsEmail extends WP_UnitTestCase {
	/**
	 * Ensures that valid emails are returned unchanged.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_valid_email_provider
	 *
	 * @param string $email Valid email address.
	 */
	public function test_returns_the_email_address_if_it_is_valid( $email ) {
		$this->assertSame(
			$email,
			is_email( $email ),
			'Should return the given email address unchanged when valid.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return Generator
	 */
	public static function data_valid_email_provider() {
		$valid_emails = array(
			'bob@example.com',
			'phil@example.info',
			'phil@TLA.example',
			'ace@204.32.222.14',
			'kevin@many.subdomains.make.a.happy.man.edu',
			'a@b.co',
			'bill+ted@example.com',
			'..@example.com',
		);

		foreach ( $valid_emails as $email ) {
			yield $email => array( $email );
		}
	}

	/**
	 * Ensures that unrecognized email addresses are rejected.
	 *
	 * @ticket 31992
	 *
	 * @dataProvider data_invalid_email_provider
	 *
	 * @param string $email Invalid or unrecognized-to-WordPress email address.
	 */
	public function test_returns_false_if_given_an_invalid_email_address( $email ) {
		$this->assertFalse(
			is_email( $email ),
			'Should have rejected the email as invalid.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return Generator
	 */
	public static function data_invalid_email_provider() {
		$invalid_emails = array(
			'khaaaaaaaaaaaaaaan!',
			'http://bob.example.com/',
			"sif i'd give u it, spamer!1",
			'com.exampleNOSPAMbob',
			'bob@your mom',
			'a@b.c',
			'" "@b.c',
			'"@"@b.c',
			'a@route.org@b.c',
			'h(aj@couc.ou', // bad comment.
			'hi@',
			'hi@hi@couc.ou', // double @.

			/*
			 * The next address is not deliverable as described,
			 * SMTP servers should strip the (ab), so it is very
			 * likely a source of confusion or a typo.
			 * Best rejected.
			 */
			'(ab)cd@couc.ou',

			/*
			 * The next address is not globally deliverable,
			 * so it may work with PHPMailer and break with
			 * mail sending services. Best not allow users
			 * to paint themselves into that corner. This also
			 * avoids security problems like those that were
			 * used to probe the WordPress server's local
			 * network.
			*/
			'toto@to',

			/*
			 * Several addresses are best rejected because
			 * we don't want to allow sending to fe80::, 192.168
			 * and other special addresses; that too might
			 * be used to probe the WordPress server's local
			 * network.
			 */
			'to@[2001:db8::1]',
			'to@[IPv6:2001:db8::1]',
			'to@[192.168.1.1]',

			/*
			 * Ill-formed UTF-8 byte sequences must be rejected.
			 * A lone continuation byte (0x80) is not valid UTF-8
			 * whether it appears in the local part or the domain.
			 */
			"a\x80b@example.com",  // invalid UTF-8 in local part.
			"abc@\x80.org",        // invalid UTF-8 in domain subdomain.
		);

		foreach ( $invalid_emails as $email ) {
			yield $email => array( $email );
		}
	}
}
