<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `xmlrpc_getposttitle()` function.
 *
 * @group functions
 * @group xmlrpc
 *
 * @ticket 53490
 *
 * @covers ::xmlrpc_getposttitle
 */
class XmlrpcGetposttitleTest extends WP_UnitTestCase {

	private $test_content = '
			<title>title</title>
			<category>category,category1</category>
			<content>content</content>
		';

	/**
	 * Tests that xmlrpc_getposttitle() returns the post title if found in the XML.
	 */
	public function test_xmlrpc_getposttitle() {
		$this->assertSame( 'title', xmlrpc_getposttitle( $this->test_content ) );
	}

	/**
	 * Tests that xmlrpc_getposttitle() defaults to the `$post_default_title` global.
	 */
	public function test_xmlrpc_getposttitle_default() {
		global $post_default_title;

		$post_default_title = 'post_default_title';

		$this->assertSame( 'post_default_title', xmlrpc_getposttitle( '' ) );
	}
}