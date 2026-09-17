<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `xmlrpc_getpostcategory()` function.
 *
 * @group functions
 * @group xmlrpc
 *
 * @ticket 53490
 *
 * @covers ::xmlrpc_getpostcategory
 */
class XmlrpcGetpostcategoryTest extends WP_UnitTestCase {

	private $test_content = '
			<title>title</title>
			<category>category,category1</category>
			<content>content</content>
		';

	/**
	 * Tests that xmlrpc_getpostcategory() returns post categories if found in the XML.
	 */
	public function test_xmlrpc_getpostcategory() {
		$this->assertSame( array( 'category', 'category1' ), xmlrpc_getpostcategory( $this->test_content ) );
	}

	/**
	 * Tests that xmlrpc_getpostcategory() defaults to the `$post_default_category` global.
	 */
	public function test_xmlrpc_getpostcategory_default() {
		global $post_default_category;

		$post_default_category = 'post_default_category';

		$this->assertSame( 'post_default_category', xmlrpc_getpostcategory( '' ) );
	}
}