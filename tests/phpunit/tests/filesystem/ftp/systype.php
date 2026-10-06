<?php
/**
 * Tests for the ftp_base::systype() method.
 *
 * @package WordPress
 */

/**
 * @group filesystem
 * @group ftp
 *
 * @covers ftp_base::systype
 */
class Tests_Filesystem_Ftp_Systype extends WP_UnitTestCase {

	/**
	 * Loads the FTP client before running the tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-ftp.php';
	}

	/**
	 * Tests that the server's system type can be read with or without a byte size.
	 *
	 * @ticket 59917
	 *
	 * @dataProvider data_systype_responses
	 *
	 * @param string $response The server's response to the SYST command.
	 * @param array  $expected The expected system type and byte size.
	 */
	public function test_should_parse_system_type( $response, $expected ) {
		$ftp = $this->getMockBuilder( 'ftp' )
			->disableOriginalConstructor()
			// setMethods() also supports the older PHPUnit versions used by Core.
			->setMethods( array( '_exec' ) )
			->getMock();

		$ftp->_message = $response;
		$ftp->_code    = 215;
		$ftp->expects( $this->once() )
			->method( '_exec' )
			->with( 'SYST', 'systype' )
			->willReturn( true );

		$this->assertSame( $expected, $ftp->systype() );
	}

	/**
	 * Data provider for system type responses.
	 *
	 * @return array[]
	 */
	public function data_systype_responses() {
		return array(
			'Windows without a byte size' => array( '215 Windows_NT', array( 'Windows_NT', null ) ),
			'UNIX without a byte size'    => array( '215 UNIX', array( 'UNIX', null ) ),
			'Windows with a byte size'    => array( '215 Windows_NT Type: L8', array( 'Windows_NT', 'L8' ) ),
			'UNIX with a byte size'       => array( '215 UNIX Type: L8', array( 'UNIX', 'L8' ) ),
		);
	}

	/**
	 * Tests that an unsuccessful SYST command returns false.
	 *
	 * @ticket 59917
	 *
	 * @dataProvider data_unsuccessful_commands
	 *
	 * @param bool $executed Whether the command was executed.
	 * @param int  $code     The response code from the server.
	 */
	public function test_should_return_false_for_unsuccessful_command( $executed, $code ) {
		$ftp = $this->getMockBuilder( 'ftp' )
			->disableOriginalConstructor()
			// setMethods() also supports the older PHPUnit versions used by Core.
			->setMethods( array( '_exec' ) )
			->getMock();

		$ftp->_code = $code;
		$ftp->expects( $this->once() )
			->method( '_exec' )
			->with( 'SYST', 'systype' )
			->willReturn( $executed );

		$this->assertFalse( $ftp->systype() );
	}

	/**
	 * Data provider for unsuccessful SYST commands.
	 *
	 * @return array[]
	 */
	public function data_unsuccessful_commands() {
		return array(
			'command could not be sent' => array( false, 0 ),
			'command rejected'          => array( true, 500 ),
		);
	}
}
