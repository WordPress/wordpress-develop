<?php
/**
 * Unit tests covering IXR_Message functionality.
 *
 * @package    WordPress
 * @subpackage IXR
 */

/**
 * Test wp-includes/IXR/class-IXR-message.php
 *
 * @group IXR
 */
class Tests_IXR_IXR_Message extends WP_UnitTestCase {

	public function test_parse() {
		$message = new IXR_Message( '<methodResponse><params><param><value>1</value></param></params></methodResponse>' );
		$this->assertTrue( $message->parse() );
		$this->assertSame( 'methodResponse', $message->messageType ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->assertSame( array( '1' ), $message->params );
	}

}
