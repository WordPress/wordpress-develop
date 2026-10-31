<?php

/**
 * Tests for get_comment_author_IP() and comment_author_IP().
 *
 * @group comment
 */
class Tests_Comment_GetCommentAuthorIP extends WP_UnitTestCase {

	/**
	 * Post fixture.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Comment fixture.
	 *
	 * @var WP_Comment
	 */
	private static $comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
		self::$comment = $factory->comment->create_and_get(
			array(
				'comment_post_ID'   => self::$post_id,
				'comment_author_IP' => '192.0.2.10',
			)
		);
	}

	public function tear_down() {
		unset( $GLOBALS['comment'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 *
	 * @dataProvider data_comment_author_ip_inputs
	 *
	 * @param string $input_type How to pass the comment to the helper.
	 */
	public function test_returns_comment_author_ip( $input_type ) {
		switch ( $input_type ) {
			case 'id':
				$this->assertSame( '192.0.2.10', get_comment_author_IP( self::$comment->comment_ID ) );
				break;
			case 'object':
				$this->assertSame( '192.0.2.10', get_comment_author_IP( self::$comment ) );
				break;
			case 'global':
				$GLOBALS['comment'] = self::$comment;
				$this->assertSame( '192.0.2.10', get_comment_author_IP() );
				break;
			case 'empty':
				$comment_id = self::factory()->comment->create(
					array(
						'comment_post_ID'   => self::$post_id,
						'comment_author_IP' => '',
					)
				);
				$this->assertSame( '', get_comment_author_IP( $comment_id ) );
				break;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comment_author_ip_inputs() {
		return array(
			'comment id'     => array( 'id' ),
			'comment object' => array( 'object' ),
			'global comment' => array( 'global' ),
			'empty ip'       => array( 'empty' ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 */
	public function test_filter_can_modify_ip() {
		add_filter(
			'get_comment_author_IP',
			static function () {
				return '203.0.113.5';
			}
		);

		$this->assertSame( '203.0.113.5', get_comment_author_IP( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_author_IP
	 * @covers ::get_comment_author_IP
	 *
	 * @dataProvider data_comment_author_ip_echo_inputs
	 *
	 * @param string $input_type How to pass the comment to the helper.
	 * @param string $expected   Expected echoed output.
	 */
	public function test_comment_author_ip_echoes_value( $input_type, $expected ) {
		switch ( $input_type ) {
			case 'escaped':
				add_filter(
					'get_comment_author_IP',
					static function () {
						return '<b>192.0.2.10</b>';
					}
				);
				$this->expectOutputString( $expected );
				comment_author_IP( self::$comment->comment_ID );
				break;
			case 'object':
				$this->expectOutputString( $expected );
				comment_author_IP( self::$comment );
				break;
			case 'global':
				$GLOBALS['comment'] = self::$comment;
				$this->expectOutputString( $expected );
				comment_author_IP();
				break;
			case 'empty':
				$comment_id = self::factory()->comment->create(
					array(
						'comment_post_ID'   => self::$post_id,
						'comment_author_IP' => '',
					)
				);
				$this->expectOutputString( $expected );
				comment_author_IP( $comment_id );
				break;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comment_author_ip_echo_inputs() {
		return array(
			'escapes html'   => array( 'escaped', '&lt;b&gt;192.0.2.10&lt;/b&gt;' ),
			'comment object' => array( 'object', '192.0.2.10' ),
			'global comment' => array( 'global', '192.0.2.10' ),
			'empty ip'       => array( 'empty', '' ),
		);
	}
}
