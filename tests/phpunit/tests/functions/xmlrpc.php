<?php

/**
 * @group xmlrpc
 * @ticket 53490
 */
class Tests_function_xmlrpc extends WP_UnitTestCase{

	private $test_content = '
	<title>title</title>
	<category>category,category1</category>
	<content>content</content>';

	/**
	 * tests that the title is returned
	 *
	 * @covers ::xmlrpc_getposttitle
	 */
	public function test_xmlrpc_getposttitle(){

		$this->assertSame( 'title', xmlrpc_getposttitle( $this->test_content ) );
	}

	/**
	 * tests that default title is return if no title in xml
	 *
	 * @covers ::xmlrpc_getposttitle
	 */
	public function test_xmlrpc_getposttitle_default(){
		global $post_default_title;
		$post_default_title = 'post_default_title';

		$this->assertSame( 'post_default_title', xmlrpc_getposttitle('' ) );
	}


	/**
	 * tests that the category(ies) are returned
	 *
	 * @covers ::xmlrpc_getpostcategory
	 */
	public function test_xmlrpc_getpostcategory(){

		$this->assertSame( array('category','category1'), xmlrpc_getpostcategory( $this->test_content ) );
	}

	/**
	 * tests that default category is returned in no category in xml
	 *
	 * @covers ::xmlrpc_getpostcategory
	 */
	public function test_xmlrpc_getpostcategory_default(){
		global $post_default_category;
		$post_default_category = 'post_default_category';

		$this->assertSame( 'post_default_category', xmlrpc_getpostcategory('' ) );
	}

	/**
	 * tests that just the content is returned
	 *
	 * @covers ::xmlrpc_removepostdata
	 */
	public function test_xmlrpc_removepostdata(){
		global $post_default_category;
		$post_default_category = 'post_default_category';

		$this->assertSame( '<content>content</content>', xmlrpc_removepostdata( $this->test_content ) );
	}
}
