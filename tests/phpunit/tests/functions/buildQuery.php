<?php

/**
 * Tests for build_query().
 *
 * @group functions
 *
 * @covers ::build_query
 */
class Tests_Functions_BuildQuery extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_build_query
	 *
	 * @param array  $data     Query data.
	 * @param string $expected Expected query string.
	 */
	public function test_build_query( $data, $expected ) {
		$this->assertSame( $expected, build_query( $data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_build_query() {
		return array(
			'empty array'        => array( array(), '' ),
			'single pair'        => array( array( 'foo' => 'bar' ), 'foo=bar' ),
			'multiple pairs'     => array(
				array(
					'foo' => 'bar',
					'baz' => 'qux',
				),
				'foo=bar&baz=qux',
			),
			'does not urlencode' => array( array( 'a b' => 'c d' ), 'a b=c d' ),
			'nested array'       => array(
				array(
					'user' => array(
						'name' => 'jane',
					),
				),
				'user%5Bname%5D=jane',
			),
			'skips null values'  => array(
				array(
					'keep' => '1',
					'drop' => null,
				),
				'keep=1',
			),
			'false becomes zero' => array( array( 'flag' => false ), 'flag=0' ),
		);
	}
}
