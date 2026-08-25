<?php

/**
 * @group admin
 *
 * @covers ::wp_doc_link_parse
 */
class Tests_Admin_Includes_Misc_WpDocLinkParse_Test extends WP_UnitTestCase {

	/**
	 * Tests wp_doc_link_parse() with various PHP content.
	 *
	 * @dataProvider data_wp_doc_link_parse
	 * @ticket 65182
	 *
	 * @param string $content  The PHP content to parse.
	 * @param array  $expected The expected array of function names.
	 */
	public function test_wp_doc_link_parse( $content, $expected ) {
		$this->assertSame( $expected, wp_doc_link_parse( $content ) );
	}

	/**
	 * Data provider for test_wp_doc_link_parse().
	 *
	 * @return array<string, array{
	 *     content:  string,
	 *     expected: string[],
	 * }>
	 */
	public function data_wp_doc_link_parse(): array {
		return array(
			'empty string'              => array(
				'content'  => '',
				'expected' => array(),
			),
			'null (invalid type)'       => array(
				'content'  => null,
				'expected' => array(),
			),
			'simple function call'      => array(
				'content'  => '<?php get_header(); ?>',
				'expected' => array( 'get_header' ),
			),
			'multiple unique functions' => array(
				'content'  => '<?php get_header(); wp_footer(); ?>',
				'expected' => array( 'get_header', 'wp_footer' ),
			),
			'duplicate functions'       => array(
				'content'  => '<?php _e( "test" ); _e( "again" ); ?>',
				'expected' => array( '_e' ),
			),
			'function call with space'  => array(
				'content'  => '<?php is_array ( $val ); ?>',
				'expected' => array( 'is_array' ),
			),
			'sorted output'             => array(
				'content'  => '<?php zeta(); alpha(); ?>',
				'expected' => array( 'alpha', 'zeta' ),
			),
			'local function definition' => array(
				'content'  => '<?php function my_local_func() {} my_local_func(); ?>',
				'expected' => array(),
			),
			'class method call'         => array(
				'content'  => '<?php $obj->my_method(); ?>',
				'expected' => array(),
			),
			'static class method call'  => array(
				'content'  => '<?php MyClass::my_static_method(); ?>',
				'expected' => array( 'my_static_method' ), // token_get_all() handles :: differently.
			),
			'mixed content'             => array(
				'content'  => '<?php
					function local_f() {}
					local_f();
					wp_remote_get();
					$o->method();
					esc_html( "test" );
				?>',
				'expected' => array( 'esc_html', 'wp_remote_get' ),
			),
		);
	}

	/**
	 * Tests the `documentation_ignore_functions` filter.
	 *
	 * @ticket 65182
	 */
	public function test_wp_doc_link_parse_filter() {
		$filter = function ( $ignore ) {
			$ignore[] = 'wp_remote_get';
			return $ignore;
		};

		add_filter( 'documentation_ignore_functions', $filter );
		$result = wp_doc_link_parse( '<?php wp_remote_get(); esc_html(); ?>' );
		remove_filter( 'documentation_ignore_functions', $filter );

		$this->assertSame( array( 'esc_html' ), $result );
	}
}
