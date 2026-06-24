<?php

/**
 * @group functions
 * @group add_query_arg
 *
 * @covers ::add_query_arg
 */
class Tests_Functions_AddQueryArg extends WP_UnitTestCase {

	public function test_add_query_arg() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$urls = array(
			'/',
			'/2012/07/30/',
			'edit.php',
			admin_url( 'edit.php' ),
			admin_url( 'edit.php', 'https' ),
		);

		$frag_urls = array(
			'/#frag',
			'/2012/07/30/#frag',
			'edit.php#frag',
			admin_url( 'edit.php#frag' ),
			admin_url( 'edit.php#frag', 'https' ),
		);

		foreach ( $urls as $url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';

			$this->assertSame( "$url?foo=1", add_query_arg( 'foo', '1', $url ) );
			$this->assertSame( "$url?foo=1", add_query_arg( array( 'foo' => '1' ), $url ) );
			$this->assertSame(
				"$url?foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$url
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$url
				)
			);

			$_SERVER['REQUEST_URI'] = $url;

			$this->assertSame( "$url?foo=1", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url?foo=1", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url?foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		foreach ( $frag_urls as $frag_url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';
			$url                    = str_replace( '#frag', '', $frag_url );

			$this->assertSame( "$url?foo=1#frag", add_query_arg( 'foo', '1', $frag_url ) );
			$this->assertSame( "$url?foo=1#frag", add_query_arg( array( 'foo' => '1' ), $frag_url ) );
			$this->assertSame(
				"$url?foo=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$frag_url
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$frag_url
				)
			);

			$_SERVER['REQUEST_URI'] = $frag_url;

			$this->assertSame( "$url?foo=1#frag", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url?foo=1#frag", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url?foo=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url?foo=1&bar=2#frag",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		$qs_urls = array(
			'baz=1', // #WP4903
			'/?baz',
			'/2012/07/30/?baz',
			'edit.php?baz',
			admin_url( 'edit.php?baz' ),
			admin_url( 'edit.php?baz', 'https' ),
			admin_url( 'edit.php?baz&za=1' ),
			admin_url( 'edit.php?baz=1&za=1' ),
			admin_url( 'edit.php?baz=0&za=0' ),
		);

		foreach ( $qs_urls as $url ) {
			$_SERVER['REQUEST_URI'] = 'nothing';

			$this->assertSame( "$url&foo=1", add_query_arg( 'foo', '1', $url ) );
			$this->assertSame( "$url&foo=1", add_query_arg( array( 'foo' => '1' ), $url ) );
			$this->assertSame(
				"$url&foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					),
					$url
				)
			);
			$this->assertSame(
				"$url&foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					),
					$url
				)
			);

			$_SERVER['REQUEST_URI'] = $url;

			$this->assertSame( "$url&foo=1", add_query_arg( 'foo', '1' ) );
			$this->assertSame( "$url&foo=1", add_query_arg( array( 'foo' => '1' ) ) );
			$this->assertSame(
				"$url&foo=2",
				add_query_arg(
					array(
						'foo' => '1',
						'foo' => '2',
					)
				)
			);
			$this->assertSame(
				"$url&foo=1&bar=2",
				add_query_arg(
					array(
						'foo' => '1',
						'bar' => '2',
					)
				)
			);
		}

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	/**
	 * @ticket 31306
	 */
	public function test_add_query_arg_numeric_keys() {
		$url = add_query_arg( array( 'foo' => 'bar' ), '1=1' );
		$this->assertSame( '1=1&foo=bar', $url );

		$url = add_query_arg(
			array(
				'foo' => 'bar',
				'1'   => '2',
			),
			'1=1'
		);
		$this->assertSame( '1=2&foo=bar', $url );

		$url = add_query_arg( array( '1' => '2' ), 'foo=bar' );
		$this->assertSame( 'foo=bar&1=2', $url );
	}

	/**
	 * Tests that add_query_arg removes the question mark when
	 * a parameter is set to false.
	 *
	 * @dataProvider data_add_query_arg_removes_question_mark
	 *
	 * @ticket 44499
	 * @group  add_query_arg
	 *
	 * @covers ::add_query_arg
	 *
	 * @param string $url      Url to test.
	 * @param string $expected Expected URL.
	 */
	public function test_add_query_arg_removes_question_mark( $url, $expected, $key = 'param', $value = false ) {
		$this->assertSame( $expected, add_query_arg( $key, $value, $url ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_add_query_arg_removes_question_mark() {
		return array(
			'anchor'                                     => array(
				'url'      => 'http://example.org?#anchor',
				'expected' => 'http://example.org#anchor',
			),
			'/ then anchor'                              => array(
				'url'      => 'http://example.org/?#anchor',
				'expected' => 'http://example.org/#anchor',
			),
			'invalid query param and anchor'             => array(
				'url'      => 'http://example.org?param=value#anchor',
				'expected' => 'http://example.org#anchor',
			),
			'/ then invalid query param and anchor'      => array(
				'url'      => 'http://example.org/?param=value#anchor',
				'expected' => 'http://example.org/#anchor',
			),
			'?#anchor when adding valid key/value args'  => array(
				'url'      => 'http://example.org?#anchor',
				'expected' => 'http://example.org?foo=bar#anchor',
				'key'      => 'foo',
				'value'    => 'bar',
			),
			'/?#anchor when adding valid key/value args' => array(
				'url'      => 'http://example.org/?#anchor',
				'expected' => 'http://example.org/?foo=bar#anchor',
				'key'      => 'foo',
				'value'    => 'bar',
			),
		);
	}
}
