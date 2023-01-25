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
 * @group xmlrpc
 */
class Tests_XMLRPC_Message extends WP_UnitTestCase {

	/**
	 * Tests that `IXR_Message::tag_open()` does not create a dynamic `currentTag` property,
	 * and uses the declared `_currentTag` property instead.
	 *
	 * The notice that we should not see:
	 * `Deprecated: Creation of dynamic property IXR_Message::$currentTag is deprecated`.
	 *
	 * @ticket 56033
	 *
	 * @covers IXR_Message::tag_open
	 */
	public function test_tag_open_does_not_create_dynamic_property() {
		$message = new IXR_Message( '<methodResponse><params><param><value>1</value></param></params></methodResponse>' );
		$this->assertTrue( $message->parse() );
		$this->assertSame( 'methodResponse', $message->messageType ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->assertSame( array( '1' ), $message->params );
	}

}
