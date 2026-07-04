<?php
/**
 * Tests for the WP_Filesystem_SSH2::__construct() method.
 *
 * @package WordPress
 *
 * @group admin
 * @group filesystem
 * @group filesystem-ssh2
 *
 * @covers WP_Filesystem_SSH2::__construct
 */
class Tests_Filesystem_WpFilesystemSsh2_Construct extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-ssh2.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	public function set_up() {
		parent::set_up();

		if ( ! extension_loaded( 'ssh2' ) ) {
			$this->markTestSkipped( 'The ssh2 PHP extension is not available.' );
		}
	}

	/**
	 * @ticket 58541
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_resolve_credentials_from_constants_when_opt_is_empty() {
		define( 'FTP_HOST', '127.0.0.1:2222' );
		define( 'FTP_USER', 'testuser' );
		define( 'FTP_PASS', 'testpass' );

		$filesystem = new WP_Filesystem_SSH2( array() );

		$this->assertSame( '127.0.0.1', $filesystem->options['hostname'] );
		$this->assertSame( '2222', $filesystem->options['port'] );
		$this->assertSame( 'testuser', $filesystem->options['username'] );
		$this->assertSame( 'testpass', $filesystem->options['password'] );
		$this->assertFalse( $filesystem->errors->has_errors() );
	}

	/**
	 * @ticket 58541
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_resolve_key_credentials_from_constants() {
		define( 'FTP_HOST', '127.0.0.1' );
		define( 'FTP_USER', 'keyuser' );
		define( 'FTP_PASS', '' );
		define( 'FTP_PUBKEY', '/home/keyuser/.ssh/id_rsa.pub' );
		define( 'FTP_PRIKEY', '/home/keyuser/.ssh/id_rsa' );

		$filesystem = new WP_Filesystem_SSH2( array() );

		$this->assertTrue( $filesystem->keys );
		$this->assertSame( '/home/keyuser/.ssh/id_rsa.pub', $filesystem->options['public_key'] );
		$this->assertSame( '/home/keyuser/.ssh/id_rsa', $filesystem->options['private_key'] );
		$this->assertNull( $filesystem->options['password'] );
		$this->assertFalse( $filesystem->errors->has_errors() );
	}

	/**
	 * @ticket 58541
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_prefer_passed_opt_over_constants() {
		define( 'FTP_HOST', '10.0.0.1' );
		define( 'FTP_USER', 'constant_user' );
		define( 'FTP_PASS', 'constant_pass' );

		$filesystem = new WP_Filesystem_SSH2(
			array(
				'hostname' => '192.168.1.1',
				'username' => 'explicit_user',
				'password' => 'explicit_pass',
			)
		);

		$this->assertSame( '192.168.1.1', $filesystem->options['hostname'] );
		$this->assertSame( 'explicit_user', $filesystem->options['username'] );
		$this->assertSame( 'explicit_pass', $filesystem->options['password'] );
	}
}
