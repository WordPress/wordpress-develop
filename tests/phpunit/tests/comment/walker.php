<?php

/**
 * @group comment
 *
 * @covers ::wp_list_comments
 */
class Tests_Comment_Walker extends WP_UnitTestCase {

	/**
	 * Comment post ID.
	 *
	 * @var int
	 */
	private $post_id;

	public function set_up() {
		parent::set_up();

		$this->post_id = self::factory()->post->create();
	}

	/**
	 * @ticket 14041
	 */
	public function test_has_children() {
		$comment_parent = self::factory()->comment->create( array( 'comment_post_ID' => $this->post_id ) );
		$comment_child  = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_parent'  => $comment_parent,
			)
		);
		$comment_parent = get_comment( $comment_parent );
		$comment_child  = get_comment( $comment_child );

		$comment_walker   = new Walker_Comment();
		$comment_callback = new Comment_Callback_Test_Helper( $this, $comment_walker );

		wp_list_comments(
			array(
				'callback' => array( $comment_callback, 'comment' ),
				'walker'   => $comment_walker,
				'echo'     => false,
			),
			array( $comment_parent, $comment_child )
		);
		wp_list_comments(
			array(
				'callback' => array( $comment_callback, 'comment' ),
				'walker'   => $comment_walker,
				'echo'     => false,
			),
			array( $comment_child, $comment_parent )
		);
	}

	/**
	 * @ticket 44923
	 *
	 * @dataProvider data_start_lvl_should_filter_nested_comment_list_classes
	 *
	 * @param array $test_case Test case arguments.
	 */
	public function test_start_lvl_should_filter_nested_comment_list_classes( $test_case ) {
		$output                = '';
		$args                  = array( 'style' => $test_case['style'] );
		$depth                 = 2;
		$filter                = null;
		$actual_filter_calls   = 0;
		$expected_filter_calls = $test_case['expected_filter_calls'] ?? ( array_key_exists( 'filter_value', $test_case ) ? 1 : 0 );

		if ( array_key_exists( 'filter_value', $test_case ) ) {
			$filter = function ( $classes, $filtered_args, $filtered_depth ) use ( $args, $depth, $test_case, &$actual_filter_calls ) {
				++$actual_filter_calls;

				$this->assertSame( array( 'children' ), $classes );
				$this->assertSame( $args, $filtered_args );
				$this->assertSame( $depth, $filtered_depth );

				return $test_case['filter_value'];
			};

			add_filter( 'comment_list_sublist_class', $filter, 10, 3 );
		}

		try {
			$comment_walker = new Walker_Comment();
			$comment_walker->start_lvl( $output, $depth, $args );
		} finally {
			if ( $filter ) {
				remove_filter( 'comment_list_sublist_class', $filter );
			}
		}

		$this->assertSame( $expected_filter_calls, $actual_filter_calls );
		$this->assertSame( $test_case['expected'], $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_start_lvl_should_filter_nested_comment_list_classes() {
		return array(
			'default ol list'  => array(
				array(
					'style'    => 'ol',
					'expected' => '<ol class="children">' . "\n",
				),
			),
			'default ul list'  => array(
				array(
					'style'    => 'ul',
					'expected' => '<ul class="children">' . "\n",
				),
			),
			'filtered ol list' => array(
				array(
					'style'        => 'ol',
					'filter_value' => array( 'children', 'custom-children', 'needs<escaping' ),
					'expected'     => '<ol class="children custom-children needs&lt;escaping">' . "\n",
				),
			),
			'filtered ul list' => array(
				array(
					'style'        => 'ul',
					'filter_value' => array( 'children', 'custom-children', 'needs<escaping' ),
					'expected'     => '<ul class="children custom-children needs&lt;escaping">' . "\n",
				),
			),
			'empty class list' => array(
				array(
					'style'        => 'ul',
					'filter_value' => array(),
					'expected'     => '<ul>' . "\n",
				),
			),
			'string classes'   => array(
				array(
					'style'        => 'ul',
					'filter_value' => 'children custom-children',
					'expected'     => '<ul class="children custom-children">' . "\n",
				),
			),
			'div style'        => array(
				array(
					'style'                 => 'div',
					'filter_value'          => array( 'should-not-appear' ),
					'expected'              => '',
					'expected_filter_calls' => 0,
				),
			),
		);
	}
}

class Comment_Callback_Test_Helper {
	private $test_walker;
	private $walker;

	public function __construct( Tests_Comment_Walker $test_walker, Walker_Comment $walker ) {
		$this->test_walker = $test_walker;
		$this->walker      = $walker;
	}

	public function comment( $comment, $args, $depth ) {
		if ( 1 === $depth ) {
			$this->test_walker->assertTrue( $this->walker->has_children );
			$this->test_walker->assertTrue( $args['has_children'] );  // Back compat.
		} elseif ( 2 === $depth ) {
			$this->test_walker->assertFalse( $this->walker->has_children );
			$this->test_walker->assertFalse( $args['has_children'] ); // Back compat.
		}
	}
}
