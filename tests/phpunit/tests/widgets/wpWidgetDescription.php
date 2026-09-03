<?php

/**
 * Unit tests for widget descriptions.
 *
 * @group widgets
 */
class Test_Widget_Descriptions extends WP_UnitTestCase {

	/**
	 * Tests that html_entity_decode handles null description correctly.
	 *
	 * @ticket 61179
	 */
	public function test_html_entity_decode_with_null_description() {
		$item = $this->create_mock_item( null );
		$desc = html_entity_decode( $item->get_description() ?? '', ENT_QUOTES, get_option( 'blog_charset' ) );
		$this->assertEquals( '', $desc );
	}

	/**
	 * Tests that html_entity_decode handles empty string description correctly.
	 *
	 * @ticket 61179
	 */
	public function test_html_entity_decode_with_empty_description() {
		$item = $this->create_mock_item( '' );
		$desc = html_entity_decode( $item->get_description() ?? '', ENT_QUOTES, get_option( 'blog_charset' ) );
		$this->assertEquals( '', $desc );
	}

	/**
	 * Tests that html_entity_decode handles valid string description correctly.
	 *
	 * @ticket 61179
	 */
	public function test_html_entity_decode_with_valid_description() {
		$item = $this->create_mock_item( 'Sample Description' );
		$desc = html_entity_decode( $item->get_description() ?? '', ENT_QUOTES, get_option( 'blog_charset' ) );
		$this->assertEquals( 'Sample Description', $desc );
	}

	/**
	 * Creates a mock item with a given description.
	 *
	 * @param string|null $description The description to return.
	 * @return object The mock item.
	 */
	private function create_mock_item( $description ) {
		$item = $this->getMockBuilder( 'Item' )
					->setMethods( array( 'get_description' ) )
					->getMock();
		$item->method( 'get_description' )->willReturn( $description );
		return $item;
	}
}
