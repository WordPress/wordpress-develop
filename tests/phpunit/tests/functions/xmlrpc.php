<?php

/**
 * @group xmlrpc
 * @ticket 53490
 */
class Tests_Functions_XMLRPC extends WP_UnitTestCase {

	private $test_content = '
			<title>title</title>
			<category>category,category1</category>
			<content>content</content>
		';

	/**
	 * Tests that xmlrpc_getposttitle() returns the post title if found in the XML.
	 *
	 * @covers ::xmlrpc_getposttitle
	 */
	public function test_xmlrpc_getposttitle() {
		$this->assertSame( 'title', xmlrpc_getposttitle( $this->test_content ) );
	}

	/**
	 * Tests that xmlrpc_getposttitle() defaults to the `$post_default_title` global.
	 *
	 * @covers ::xmlrpc_getposttitle
	 */
	public function test_xmlrpc_getposttitle_default() {
		global $post_default_title;

		$post_default_title = 'post_default_title';

		$this->assertSame( 'post_default_title', xmlrpc_getposttitle( '' ) );
	}


	/**
	 * Tests that xmlrpc_getpostcategory() returns post categories if found in the XML.
	 *
	 * @covers ::xmlrpc_getpostcategory
	 */
	public function test_xmlrpc_getpostcategory() {
		$this->assertSame( array( 'category', 'category1' ), xmlrpc_getpostcategory( $this->test_content ) );
	}

	/**
	 * Tests that xmlrpc_getpostcategory() defaults to the `$post_default_category` global.
	 *
	 * @covers ::xmlrpc_getpostcategory
	 */
	public function test_xmlrpc_getpostcategory_default() {
		global $post_default_category;

		$post_default_category = 'post_default_category';

		$this->assertSame( 'post_default_category', xmlrpc_getpostcategory( '' ) );
	}

	/**
	 * Tests that xmlrpc_removepostdata() returns XML content without title and category elements.
	 *
	 * @covers ::xmlrpc_removepostdata
	 */
	public function test_xmlrpc_removepostdata() {
		$this->assertSame( '<content>content</content>', xmlrpc_removepostdata( $this->test_content ) );
	}
}
