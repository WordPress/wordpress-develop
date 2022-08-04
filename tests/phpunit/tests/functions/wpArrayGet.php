<?php

/**
 * Tests for the _wp_array_get() function
 *
 * @since 5.6.0
 *
 * @group functions.php
 * @covers ::_wp_array_get
 */
class Tests_Functions_wpArrayGet extends WP_UnitTestCase {

	/**
	 * Tests _wp_array_get() with invalid parameters.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_invalid_parameters() {
		$this->assertSame(
			_wp_array_get(
				null,
				array( 'a' )
			),
			null
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				null
			),
			null
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				array()
			),
			null
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				array(),
				true
			),
			true
		);
	}

	/**
	 * Tests _wp_array_get() with non-subtree paths.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_simple_non_subtree() {
		// Simple non-subtree test.
		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				array( 'key' )
			),
			4
		);

		// Simple non-subtree not found.
		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				array( 'invalid' )
			),
			null
		);

		// Simple non-subtree not found with a default.
		$this->assertSame(
			_wp_array_get(
				array(
					'key' => 4,
				),
				array( 'invalid' ),
				1
			),
			1
		);

		// Simple non-subtree integer path.
		$this->assertSame(
			_wp_array_get(
				array(
					'a',
					'b',
					'c',
				),
				array( 1 )
			),
			'b'
		);
	}

	/**
	 * Tests _wp_array_get() with subtrees.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_subtree() {
		$this->assertSame(
			_wp_array_get(
				array(
					'a' => array(
						'b' => array(
							'c' => 1,
						),
					),
				),
				array( 'a', 'b' )
			),
			array( 'c' => 1 )
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'a' => array(
						'b' => array(
							'c' => 1,
						),
					),
				),
				array( 'a', 'b', 'c' )
			),
			1
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'a' => array(
						'b' => array(
							'c' => 1,
						),
					),
				),
				array( 'a', 'b', 'c', 'd' )
			),
			null
		);
	}

	/**
	 * Tests _wp_array_get() with zero strings.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_handle_zeros() {
		$this->assertSame(
			_wp_array_get(
				array(
					'-0' => 'a',
					'0'  => 'b',
				),
				array( 0 )
			),
			'b'
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'-0' => 'a',
					'0'  => 'b',
				),
				array( -0 )
			),
			'b'
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'-0' => 'a',
					'0'  => 'b',
				),
				array( '-0' )
			),
			'a'
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'-0' => 'a',
					'0'  => 'b',
				),
				array( '0' )
			),
			'b'
		);
	}

	/**
	 * Tests _wp_array_get() with null values.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_null() {
		$this->assertSame(
			_wp_array_get(
				array(
					'key' => null,
				),
				array( 'key' ),
				true
			),
			null
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'key' => null,
				),
				array( 'key', 'subkey' ),
				true
			),
			true
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'key' => array(
						null => 4,
					),
				),
				array( 'key', null ),
				true
			),
			4
		);
	}

	/**
	 * Tests _wp_array_get() with empty paths.
	 *
	 * @ticket 51720
	 */
	public function test_wp_array_get_empty_paths() {
		$this->assertSame(
			_wp_array_get(
				array(
					'a' => 4,
				),
				array()
			),
			null
		);

		$this->assertSame(
			_wp_array_get(
				array(
					'a' => array(
						'b' => array(
							'c' => 1,
						),
					),
				),
				array( 'a', 'b', array() )
			),
			null
		);
	}
}
