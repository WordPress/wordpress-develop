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

	/**
	 * Test that the `IXR_Message::parse()` method correctly sets callback functions to handle certain parts of the XML.
	 *
	 * Safeguards handling of the PHP 8.4 deprecation of `xml_set_object()`.
	 *
	 * @covers IXR_Message::parse
	 */
	public function test_parse_sets_handlers() {
		$xml     = '<methodResponse><params><param><value>1</value></param></params></methodResponse>';
		$message = new class( $xml ) extends IXR_Message {
			public $tag_open_call_counter  = 0;
			public $tag_close_call_counter = 0;
			public $cdata_call_counter     = 0;

			public function tag_open( $parser, $tag, $attr ) {
				++$this->tag_open_call_counter;
			}
			public function cdata( $parser, $cdata ) {
				++$this->cdata_call_counter;
			}
			public function tag_close( $parser, $tag ) {
				++$this->tag_close_call_counter;
			}
		};

		$this->assertTrue( $message->parse(), 'XML parsing failed' );

		$msg = '%s() handler did not get called expected nr of times';
		$this->assertSame( 4, $message->tag_open_call_counter, sprintf( $msg, 'tag_open' ) );
		$this->assertSame( 4, $message->tag_close_call_counter, sprintf( $msg, 'tag_close' ) );
		$this->assertSame( 1, $message->cdata_call_counter, sprintf( $msg, 'cdata' ) );
	}
}
