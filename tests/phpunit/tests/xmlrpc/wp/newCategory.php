<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_newCategory
 */
class Tests_XMLRPC_wp_newCategory extends WP_XMLRPC_UnitTestCase {

	/**
	 * Tests that a category is created from a valid name.
	 *
	 * @ticket 66108
	 */
	public function test_new_category(): void {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newCategory( array( 1, 'editor', 'editor', array( 'name' => 'Foo' ) ) );

		$this->assertNotIXRError( $result );
		$this->assertIsInt( $result );
		$category = get_term( $result, 'category' );
		$this->assertInstanceOf( WP_Term::class, $category );
		$this->assertSame( 'Foo', $category->name );
	}

	/**
	 * Tests that a category name that is not a string or number returns an error instead of causing a fatal error.
	 *
	 * @ticket 66108
	 *
	 * @dataProvider data_invalid_category_name
	 *
	 * @param mixed $name The category name to send.
	 */
	public function test_invalid_category_name( $name ): void {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newCategory( array( 1, 'editor', 'editor', array( 'name' => $name ) ) );

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( 'Invalid term name.', $result->message );
	}

	/**
	 * Data provider for {@see self::test_invalid_category_name()}.
	 *
	 * @return array<truthy-string, array{ mixed }>
	 */
	public function data_invalid_category_name(): array {
		return array(
			'array'   => array( array( 'foo' ) ),
			'boolean' => array( true ),
		);
	}
}
