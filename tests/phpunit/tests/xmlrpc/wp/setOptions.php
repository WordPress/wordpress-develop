<?php
/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_setOptions
 */
class Tests_XMLRPC_wp_setOptions extends WP_XMLRPC_UnitTestCase {

	/**
	 * @ticket 22936
	 */
	public function test_set_option_no_escape_strings() {
		$this->make_user_by_role( 'administrator' );
		$string_with_quote         = "Mary's Lamb Shop";
		$escaped_string_with_quote = esc_html( $string_with_quote ); // Title is passed through esc_html().

		update_option( 'default_comment_status', 'closed' );
		$this->assertSame( 'closed', get_option( 'default_comment_status' ) );
		$result = $this->myxmlrpcserver->wp_setOptions(
			array(
				1,
				'administrator',
				'administrator',
				array(
					'blog_title'             => $string_with_quote,
					'default_comment_status' => 'open',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $escaped_string_with_quote, $result['blog_title']['value'] );
		$this->assertSame( 'open', $result['default_comment_status']['value'] );
	}
}
