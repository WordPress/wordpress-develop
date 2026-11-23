<?php

/**
 * @group admin
 * @group rewrite
 *
 * @covers ::split_by_markers
 */
class Tests_Admin_Includes_Misc_SplitByMarkers_Test extends WP_UnitTestCase {

	/**
	 * @ticket 26829
	 *
	 * @dataProvider data_split_by_markers
	 *
	 * @param string[] $lines    Lines to split.
	 * @param string   $marker   Marker to split by.
	 * @param array    $expected Expected preceding, existing and following lines.
	 */
	public function test_split_by_markers( $lines, $marker, $expected ) {
		$this->assertSame( $expected, split_by_markers( $lines, $marker ) );
	}

	/**
	 * Data provider for test_split_by_markers().
	 *
	 * @return array[]
	 */
	public static function data_split_by_markers() {
		$pre  = array( '# BEGIN FooPre', 'Line before', '# END FooPre' );
		$in   = array( '# BEGIN Foo', 'Line in', '# END Foo' );
		$post = array( '# BEGIN FooPost', 'Line after', '# END FooPost' );

		$lines = array_merge( $pre, $in, $post );

		return array(
			'marker that is a prefix of a preceding marker' => array(
				'lines'    => $lines,
				'marker'   => 'FooPre',
				'expected' => array( array(), array( 'Line before' ), array_merge( $in, $post ) ),
			),
			'marker that prefixes the surrounding markers' => array(
				'lines'    => $lines,
				'marker'   => 'Foo',
				'expected' => array( $pre, array( 'Line in' ), $post ),
			),
			'last marker in the file'                      => array(
				'lines'    => $lines,
				'marker'   => 'FooPost',
				'expected' => array( array_merge( $pre, $in ), array( 'Line after' ), array() ),
			),
			'marker containing a space'                    => array(
				'lines'    => array( '# BEGIN Foo Bar', 'Foo Bar line', '# END Foo Bar', '# BEGIN Foo', 'Foo line', '# END Foo' ),
				'marker'   => 'Foo',
				'expected' => array( array( '# BEGIN Foo Bar', 'Foo Bar line', '# END Foo Bar' ), array( 'Foo line' ), array() ),
			),
			'indented markers'                             => array(
				'lines'    => array( 'before', "\t# BEGIN Foo", '  Line in', '    # END Foo  ', 'after' ),
				'marker'   => 'Foo',
				'expected' => array( array( 'before' ), array( '  Line in' ), array( 'after' ) ),
			),
			'markers with trailing carriage returns'       => array(
				'lines'    => array( "before\r", "# BEGIN Foo\r", "Line in\r", "# END Foo\r", "after\r" ),
				'marker'   => 'Foo',
				'expected' => array( array( "before\r" ), array( "Line in\r" ), array( "after\r" ) ),
			),
			'end marker before begin marker is not a marker' => array(
				'lines'    => array( '# END Foo', '# BEGIN Foo', 'Line in', '# END Foo' ),
				'marker'   => 'Foo',
				'expected' => array( array( '# END Foo' ), array( 'Line in' ), array() ),
			),
			'missing end marker'                           => array(
				'lines'    => array( 'before', '# BEGIN Foo', 'Line in', 'more' ),
				'marker'   => 'Foo',
				'expected' => array( array( 'before' ), array( 'Line in', 'more' ), array() ),
			),
			'missing markers'                              => array(
				'lines'    => $lines,
				'marker'   => 'Bar',
				'expected' => array( $lines, array(), array() ),
			),
			'empty section'                                => array(
				'lines'    => array( '# BEGIN Foo', '# END Foo' ),
				'marker'   => 'Foo',
				'expected' => array( array(), array(), array() ),
			),
			'no lines'                                     => array(
				'lines'    => array(),
				'marker'   => 'Foo',
				'expected' => array( array(), array(), array() ),
			),
		);
	}
}
