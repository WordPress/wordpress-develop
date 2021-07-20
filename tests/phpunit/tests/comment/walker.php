<?php

/**
 * @group comment
 */
class Tests_Comment_Walker extends WP_UnitTestCase {
	private $post_id;
	private $comment_id;

	public function setUp() {
		parent::setUp();

		$this->post_id    = self::factory()->post->create();
		$this->comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $this->post_id ) );
	}

	/**
	 * @ticket 14041
	 */
	public function test_has_children() {
		$comment_child  = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_parent'  => $this->comment_id,
			)
		);
		$comment_parent = get_comment( $this->comment_id );
		$comment_child  = get_comment( $comment_child );

		$comment_walker   = new Walker_Comment();
		$comment_callback = new Comment_Callback_Test( $this, $comment_walker );

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
	 * @dataProvider dataAvatarSize
	 *
	 * @param bool  $has_avatar Indicates if the output should contain an avatar.
	 * @param array $args       Formatting options.
	 */
	public function test_avatar_size( $has_avatar, $args ) {
		$comment = get_comment( $this->comment_id );
		$actual  = wp_list_comments( $args, array( $comment ) );

		if ( $has_avatar ) {
			$this->assertContains( 'gravatar.com/avatar', $actual );
		} else {
			$this->assertNotContains( 'gravatar.com/avatar', $actual );
		}
	}

	public function dataAvatarSize() {
		$walker = new Walker_Comment();

		return array(
			'should_not_contain_avatar_when_size_false'  => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => false,
					'format'      => 'html5',
				),
			),
			'should_not_contain_avatar_when_size_empty_string' => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => '',
					'format'      => 'html5',
				),
			),
			'should_not_contain_avatar_when_size_0'      => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => 0,
					'format'      => 'html5',
				),
			),
			'should_contain_avatar_when_no_size'         => array(
				'has_avatar' => true,
				'args'       => array(
					'walker' => $walker,
					'echo'   => false,
					'format' => 'html5',
				),
			),
			'should_contain_avatar_when_size_100'        => array(
				'has_avatar' => true,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => 100,
					'format'      => 'html5',
				),
			),
			'should_contain_avatar_when_size_string_200' => array(
				'has_avatar' => true,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => '200',
					'format'      => 'html5',
				),
			),
			'should_not_contain_avatar_when_xhtml_size_false' => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => false,
					'format'      => 'xhtml',
				),
			),
			'should_not_contain_avatar_when_xhtml_size_empty_string' => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => '',
					'format'      => 'xhtml',
				),
			),
			'should_not_contain_avatar_xhtml_when_size_int_0' => array(
				'has_avatar' => false,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => 0,
					'format'      => 'xhtml',
				),
			),
			'should_contain_avatar_when_xhtml_no_size'   => array(
				'has_avatar' => true,
				'args'       => array(
					'walker' => $walker,
					'echo'   => false,
					'format' => 'xhtml',
				),
			),
			'should_contain_avatar_when_xhtml_size_int_100' => array(
				'has_avatar' => true,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => 100,
					'format'      => 'xhtml',
				),
			),
			'should_contain_avatar_when_xhtml_size_string_200' => array(
				'has_avatar' => true,
				'args'       => array(
					'walker'      => $walker,
					'echo'        => false,
					'avatar_size' => '200',
					'format'      => 'xhtml',
				),
			),
		);
	}
}

class Comment_Callback_Test {
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
