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
	 * @ticket 56539
	 */
	public function test_start_lvl_with_empty_args_should_not_produce_warnings() {
		$walker = new Walker_Comment();
		$output = '';

		$walker->start_lvl( $output, 0, array() );

		$this->assertStringContainsString( '<ul class="children">', $output );
	}

	/**
	 * @ticket 56539
	 */
	public function test_end_lvl_with_empty_args_should_not_produce_warnings() {
		$walker = new Walker_Comment();
		$output = '';

		$walker->end_lvl( $output, 0, array() );

		$this->assertStringContainsString( '</ul>', $output );
	}

	/**
	 * @ticket 56539
	 */
	public function test_end_el_with_empty_args_should_not_produce_warnings() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $this->post_id ) );
		$comment    = get_comment( $comment_id );
		$walker     = new Walker_Comment();
		$output     = '';

		$walker->end_el( $output, $comment, 0, array() );

		$this->assertStringContainsString( '</li>', $output );
	}

	/**
	 * @ticket 56539
	 */
	public function test_start_el_with_empty_args_should_not_produce_warnings() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_type'    => 'comment',
			)
		);
		$comment    = get_comment( $comment_id );
		$walker     = new Walker_Comment();
		$output     = '';

		$walker->start_el( $output, $comment, 0, array() );

		$this->assertNotEmpty( $output );
	}

	/**
	 * @ticket 56539
	 */
	public function test_walk_with_empty_args_should_not_produce_warnings() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_type'    => 'comment',
			)
		);
		$comments   = array( get_comment( $comment_id ) );
		$walker     = new Walker_Comment();

		$output = $walker->walk( $comments, -1, array() );

		$this->assertNotEmpty( $output );
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
