<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `xmlrpc_removepostdata()` function.
 *
 * @group functions
 *
 * @ticket 53490
 *
 * @covers ::xmlrpc_removepostdata
 */
class XmlrpcRemovepostdataTest extends WP_UnitTestCase {

	private $test_content = '
			<title>title</title>
			<category>category,category1</category>
			<content>content</content>
		';

	/**
	 * Tests that xmlrpc_removepostdata() returns XML content without title and category elements.
	 */
	public function test_xmlrpc_removepostdata() {
		$this->assertSame( '<content>content</content>', xmlrpc_removepostdata( $this->test_content ) );
	}
}