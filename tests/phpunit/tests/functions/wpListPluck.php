<?php

/**
 * Test wp_list_pluck().
 *
 * @group functions
 *
 * @covers ::wp_list_pluck
 */
class Tests_Functions_wpListPluck extends WP_UnitTestCase {
	public $object_list = array();
	public $array_list  = array();

	public function set_up() {
		/*
		 * This method deliberately does not call parent::set_up(). Why?
		 *
		 * The call stack for WP_UnitTestCase_Base::set_up() includes a call to
		 * WP_List_Util::pluck(), which creates an inaccurate coverage report
		 * for this method.
		 *
		 * To ensure that deprecation and incorrect usage notices continue to be
		 * detectable, this method uses WP_UnitTestCase_Base::expectDeprecated().
		 */
		$this->expectDeprecated();

		$this->array_list['foo'] = array(
			'name'   => 'foo',
			'id'     => 'f',
			'field1' => true,
			'field2' => true,
			'field3' => true,
			'field4' => array( 'red' ),
		);
		$this->array_list['bar'] = array(
			'name'   => 'bar',
			'id'     => 'b',
			'field1' => true,
			'field2' => true,
			'field3' => false,
			'field4' => array( 'green' ),
		);
		$this->array_list['baz'] = array(
			'name'   => 'baz',
			'id'     => 'z',
			'field1' => true,
			'field2' => false,
			'field3' => false,
			'field4' => array( 'blue' ),
		);
		foreach ( $this->array_list as $key => $value ) {
			$this->object_list[ $key ] = (object) $value;
		}
	}

	public function test_wp_list_pluck_array_and_object() {
		$list = wp_list_pluck( $this->object_list, 'name' );
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
				'baz' => 'baz',
			),
			$list
		);

		$list = wp_list_pluck( $this->array_list, 'name' );
		$this->assertSame(
			array(
				'foo' => 'foo',
				'bar' => 'bar',
				'baz' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_index_key() {
		$list = wp_list_pluck( $this->array_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_object_index_key() {
		$list = wp_list_pluck( $this->object_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_missing_index_key() {
		$list = wp_list_pluck( $this->array_list, 'name', 'nonexistent' );
		$this->assertSame(
			array(
				0 => 'foo',
				1 => 'bar',
				2 => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_partial_missing_index_key() {
		$array_list = $this->array_list;
		unset( $array_list['bar']['id'] );
		$list = wp_list_pluck( $array_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				0   => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 28666
	 */
	public function test_wp_list_pluck_mixed_index_key() {
		$mixed_list        = $this->array_list;
		$mixed_list['bar'] = (object) $mixed_list['bar'];
		$list              = wp_list_pluck( $mixed_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
				'z' => 'baz',
			),
			$list
		);
	}

	/**
	 * @ticket 16895
	 */
	public function test_wp_list_pluck_containing_references() {
		$ref_list = array(
			& $this->object_list['foo'],
			& $this->object_list['bar'],
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );

		$list = wp_list_pluck( $ref_list, 'name' );
		$this->assertSame(
			array(
				'foo',
				'bar',
			),
			$list
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );
	}

	/**
	 * @ticket 16895
	 */
	public function test_wp_list_pluck_containing_references_keys() {
		$ref_list = array(
			& $this->object_list['foo'],
			& $this->object_list['bar'],
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );

		$list = wp_list_pluck( $ref_list, 'name', 'id' );
		$this->assertSame(
			array(
				'f' => 'foo',
				'b' => 'bar',
			),
			$list
		);

		$this->assertInstanceOf( 'stdClass', $ref_list[0] );
		$this->assertInstanceOf( 'stdClass', $ref_list[1] );
	}

	/**
	 * @dataProvider data_wp_list_pluck
	 *
	 * @param array      $input_list List of objects or arrays.
	 * @param int|string $field      Field from the object to place instead of the entire object
	 * @param int|string $index_key  Field from the object to use as keys for the new array.
	 * @param array      $expected   Expected result.
	 */
	public function test_wp_list_pluck( $input_list, $field, $index_key, $expected ) {
		$this->assertSameSetsWithIndex( $expected, wp_list_pluck( $input_list, $field, $index_key ) );
	}

	public function data_wp_list_pluck() {
		return array(
			'arrays'                         => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
					),
					array( 'foo' => 'baz' ),
				),
				'foo',
				null,
				array( 'bar', 'foo', 'baz' ),
			),
			'arrays with index key'          => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'foo'   => 'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
			'arrays with index key missing'  => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					array(
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'bar',
					'value' => 'baz',
				),
			),
			'arrays with key missing'        => array(
				array(
					array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'key',
				null,
				array(
					1 => 'bar',
					2 => 'value',
				),
			),
			'objects'                        => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
					),
					(object) array( 'foo' => 'baz' ),
				),
				'foo',
				null,
				array( 'bar', 'foo', 'baz' ),
			),
			'objects with index key'         => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
						'key' => 'foo',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					(object) array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'foo'   => 'bar',
					'bar'   => 'foo',
					'value' => 'baz',
				),
			),
			'objects with index key missing' => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					(object) array(
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					(object) array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'foo',
				'key',
				array(
					'bar',
					'value' => 'baz',
				),
			),
			'objects with field missing'     => array(
				array(
					(object) array(
						'foo' => 'bar',
						'bar' => 'baz',
						'abc' => 'xyz',
					),
					(object) array(
						'foo'   => 'foo',
						'123'   => '456',
						'lorem' => 'ipsum',
						'key'   => 'bar',
					),
					(object) array(
						'foo' => 'baz',
						'key' => 'value',
					),
				),
				'key',
				null,
				array(
					1 => 'bar',
					2 => 'value',
				),
			),
		);
	}

	/**
	 * @dataProvider data_class_with_get_and_isset_magic_methods
	 *
	 * @ticket 59774
	 */
	public function test_class_with_get_and_isset_magic_methods( $which_test, $before_r57698 ) {
		if ( $before_r57698 ) {
			$GLOBALS['before_r57698'] = true;
		}

		switch ( $which_test ) {
			case 'WP_User':
				$user_email = $before_r57698 ? 'before_r57698@test.com' : 'after_r57698@test.com';
				$user       = self::factory()->user->create_and_get(
					array(
						'user_email' => $user_email,
					)
				);
				$input_list = array( $user );
				$field      = 'user_email';
				$expected   = array( $user_email );
				$message    = 'Should pluck the user_email';
				break;

			case 'WP_Post':
			case 'WP_Comment':
				$post_title = sprintf( 'Test %s r57698', $before_r57698 ? 'before' : 'after' );
				$post_id    = self::factory()->post->create(
					array(
						'post_title' => $post_title,
					)
				);

				if ( 'WP_Post' === $which_test ) {
					$post       = get_post( $post_id );
					$input_list = array( $post );
					$field      = 'post_title';
					$message    = 'Should pluck the post_title';

				} else {
					// Test WP_Comment to access a field through the `post_fields` property and magic methods.
					$comment_id = self::factory()->comment->create(
						array(
							'comment_post_ID'  => $post_id,
							'comment_approved' => '1',
						)
					);
					$comment    = get_comment( $comment_id );
					$input_list = array( $comment );
					$field      = 'post_title';
					$message    = 'Should pluck the comment\'s post post_title';
				}

				$expected = array( $post_title );
				break;
		}

		$actual = wp_list_pluck( $input_list, $field );

		if ( $before_r57698 ) {
			unset( $GLOBALS['before_r57698'] );
		}

		$this->assertSameSetsWithIndex( $expected, $actual, $message );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_class_with_get_and_isset_magic_methods() {
		return array(
			'before r57698: WP_User non-declared "data" property' => array(
				'which_test'    => 'WP_User',
				'before_r57698' => true,
			),
			'after r57698: WP_User non-declared "data" property'  => array(
				'which_test'    => 'WP_User',
				'before_r57698' => false,
			),
			'before r57698: WP_Post'                              => array(
				'which_test'    => 'WP_Post',
				'before_r57698' => true,
			),
			'after r57698: WP_Post'                               => array(
				'which_test'    => 'WP_Post',
				'before_r57698' => false,
			),
			'before r57698: WP_Comment access via `post_fields`'  => array(
				'which_test'    => 'WP_Comment',
				'before_r57698' => true,
			),
			'after r57698: WP_Comment access via `post_fields`'   => array(
				'which_test'    => 'WP_Comment',
				'before_r57698' => false,
			),
		);
	}

	/**
	 * @dataProvider data_class_with_get_but_no_isset_magic_method
	 *
	 * @ticket 59774
	 */
	public function test_class_with_get_but_no_isset_magic_method( $which_test, $before_r57698 = false ) {
		if ( $before_r57698 ) {
			$GLOBALS['before_r57698'] = true;
		}

		switch ( $which_test ) {
			case 'WP_Term':
				// Pluck WP_Term::$data for the testcat term.
				$term_id = self::factory()->category->create(
					array(
						'slug'        => $before_r57698 ? 'before_r57698' : 'after_r57698',
						'name'        => $before_r57698 ? 'before_r57698' : 'after_r57698',
						'description' => sprintf( 'Description for %s r57698', $before_r57698 ? 'Before' : 'After' ),
					)
				);
				$term    = get_term( $term_id, 'category' );
				$actual  = wp_list_pluck( array( $term ), 'data' );

				if ( $before_r57698 ) {
					unset( $GLOBALS['before_r57698'] );
				}

				$this->assertIsArray( $actual, 'The plucked list should be an array' );
				$this->assertCount( 1, $actual, 'The plucked list should contain one element' );
				$this->assertSame( $term_id, $actual[0]->term_id, 'The plucked data should be for "testcat" with ID of ' . $term_id );
				break;

			case 'WP_Block':
				// WP_Block's dynamic property 'attributes'.
				$block      = new WP_Block(
					array(
						'blockName' => 'core/image',
						'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'var:preset|duotone|blue-orange' ) ) ),
					)
				);
				$actual     = wp_list_pluck( array( $block ), 'attributes' );
				$expected   = array(
					array(
						'style' => array(
							'color' => array(
								'duotone' => 'var:preset|duotone|blue-orange',
							),
						),
						'alt'   => '',
					),
				);

				if ( $before_r57698 ) {
					unset( $GLOBALS['before_r57698'] );
				}

				$this->assertSameSetsWithIndex( $expected, $actual, 'The plucked WP_Block::$attributes should match' );
				break;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_class_with_get_but_no_isset_magic_method() {
		return array(
			'before r57698: WP_Term via the `data` property'       => array(
				'which_test'    => 'WP_Term',
				'before_r57698' => true,
			),
			'after r57698: WP_Term via the `data` property'        => array(
				'which_test'    => 'WP_Term',
				'before_r57698' => false,
			),
			'before r57698: WP_Block `attribute` dynamic property' => array(
				'which_test'    => 'WP_Block',
				'before_r57698' => true,
			),
			'after r57698: WP_Block `attribute` dynamic property'  => array(
				'which_test'    => 'WP_Block',
				'before_r57698' => false,
			),
		);
	}

	/**
	 * @dataProvider data_dynamic_property_in_class_without_magic_methods
	 *
	 * @ticket 59774
	 */
	public function test_dynamic_property_in_class_without_magic_methods( $which_test, $before_r57698 = false ) {
		if ( $before_r57698 ) {
			$GLOBALS['before_r57698'] = true;
		}

		switch ( $which_test ) {
			case 'WP_Meta_Query':
				$meta                   = new WP_Meta_Query();
				$meta->dynamic_property = 'I am a dynamic property';

				$input_list = array( $meta );
				$field      = 'dynamic_property';
				$expected   = array( 'I am a dynamic property' );
				$message    = 'The plucked value should be for WP_Meta_Query::$dynamic_property';
				break;
		}


		$actual = wp_list_pluck( $input_list, $field );

		if ( $before_r57698 ) {
			unset( $GLOBALS['before_r57698'] );
		}

		$this->assertSameSetsWithIndex( $expected, $actual, $message );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_dynamic_property_in_class_without_magic_methods() {
		return array(
			'before r57698: WP_Meta_Query' => array(
				'which_test'    => 'WP_Meta_Query',
				'before_r57698' => true,
			),
			'after r57698: WP_Meta_Query'  => array(
				'which_test'    => 'WP_Meta_Query',
				'before_r57698' => false,
			),
		);
	}
}
