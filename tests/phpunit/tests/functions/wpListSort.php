<?php

/**
 * Test wp_list_sort().
 *
 * @group functions.php
 * @covers ::wp_list_sort
 */
class Tests_Functions_wpListSort extends WP_UnitTestCase {

	/**
	 * @dataProvider data_test_wp_list_sort
	 *
	 * @param string|array $orderby Either the field name to order by or an array
	 *                              of multiple orderby fields as $orderby => $order.
	 * @param string       $order   Either 'ASC' or 'DESC'.
	 */
	public function test_wp_list_sort( $list, $orderby, $order, $expected ) {
		$this->assertSame( $expected, wp_list_sort( $list, $orderby, $order ) );
	}

	public function data_test_wp_list_sort() {
		return array(
			'single orderby ascending'        => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'ASC',
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'single orderby descending'       => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'DESC',
				array(
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
				),
			),
			'single orderby array ascending'  => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				array( 'foo' => 'ASC' ),
				'IGNORED',
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'single orderby array descending' => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				array( 'foo' => 'DESC' ),
				'IGNORED',
				array(
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
				),
			),
			'multiple orderby ascending'      => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'ASC',
					'foo' => 'ASC',
				),
				'IGNORED',
				array(
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
			),
			'multiple orderby descending'     => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'DESC',
					'foo' => 'DESC',
				),
				'IGNORED',
				array(
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'multiple orderby mixed'          => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'DESC',
					'foo' => 'ASC',
				),
				'IGNORED',
				array(
					array(
						'foo' => 'bar',
						'key' => 'value',
					),
					array(
						'foo' => 'baz',
						'key' => 'key',
					),
					array(
						'foo' => 'foo',
						'key' => 'key',
					),
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider data_test_wp_list_sort_preserve_keys
	 *
	 * @param string|array $orderby Either the field name to order by or an array
	 *                              of multiple orderby fields as $orderby => $order.
	 * @param string       $order   Either 'ASC' or 'DESC'.
	 */
	public function test_wp_list_sort_preserve_keys( $list, $orderby, $order, $expected ) {
		$this->assertSame( $expected, wp_list_sort( $list, $orderby, $order, true ) );
	}

	public function data_test_wp_list_sort_preserve_keys() {
		return array(
			'single orderby ascending'        => array(
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'ASC',
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'single orderby descending'       => array(
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'DESC',
				array(
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
				),
			),
			'single orderby array ascending'  => array(
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				array( 'foo' => 'ASC' ),
				'IGNORED',
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'single orderby array descending' => array(
				array(
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				array( 'foo' => 'DESC' ),
				'IGNORED',
				array(
					'foofoo' => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobaz' => array(
						'foo' => 'baz',
						'key' => 'value',
					),
					'foobar' => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
				),
			),
			'multiple orderby ascending'      => array(
				array(
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'ASC',
					'foo' => 'ASC',
				),
				'IGNORED',
				array(
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
			),
			'multiple orderby descending'     => array(
				array(
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'DESC',
					'foo' => 'DESC',
				),
				'IGNORED',
				array(
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
			'multiple orderby mixed'          => array(
				array(
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
				),
				array(
					'key' => 'DESC',
					'foo' => 'ASC',
				),
				'IGNORED',
				array(
					'foobarvalue' => array(
						'foo' => 'bar',
						'key' => 'value',
					),
					'foobazkey'   => array(
						'foo' => 'baz',
						'key' => 'key',
					),
					'foofookey'   => array(
						'foo' => 'foo',
						'key' => 'key',
					),
					'foobarfoo'   => array(
						'foo' => 'bar',
						'bar' => 'baz',
						'key' => 'foo',
					),
					'foofoobar'   => array(
						'foo'   => 'foo',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
				),
			),
		);
	}
}
