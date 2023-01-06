<?php
/**
 * @group comment
 */
class Tests_Comment_Template extends WP_UnitTestCase {
	/**
	 * Shared post ID.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * Set up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = self::factory()->post->create();
	}

	/**
	 * @covers ::get_comments_number
	 */
	public function test_get_comments_number() {
		$post_id = self::$post_id;

		$this->assertSame( 0, get_comments_number( 0 ) );
		$this->assertSame( '0', get_comments_number( $post_id ) );
		$this->assertSame( '0', get_comments_number( get_post( $post_id ) ) );

		self::factory()->comment->create_post_comments( $post_id, 12 );

		$this->assertSame( '12', get_comments_number( $post_id ) );
		$this->assertSame( '12', get_comments_number( get_post( $post_id ) ) );
	}

	/**
	 * @covers ::get_comments_number
	 */
	public function test_get_comments_number_without_arg() {
		$post_id   = self::$post_id;
		$permalink = get_permalink( $post_id );
		$this->go_to( $permalink );

		$this->assertSame( '0', get_comments_number() );

		self::factory()->comment->create_post_comments( $post_id, 12 );
		$this->go_to( $permalink );

		$this->assertSame( '12', get_comments_number() );
	}

	/**
	 * @ticket 48772
	 *
	 * @covers ::get_comments_number_text
	 */
	public function test_get_comments_number_text_with_post_id() {
		$post_id = self::$post_id;
		self::factory()->comment->create_post_comments( $post_id, 6 );

		$comments_number_text = get_comments_number_text( false, false, false, $post_id );

		$this->assertSame( sprintf( _n( '%s Comment', '%s Comments', 6 ), '6' ), $comments_number_text );

		ob_start();
		comments_number( false, false, false, $post_id );
		$comments_number_text = ob_get_clean();

		$this->assertSame( sprintf( _n( '%s Comment', '%s Comments', 6 ), '6' ), $comments_number_text );

	}

	/**
	 * @ticket 13651
	 *
	 * @covers ::get_comments_number_text
	 */
	public function test_get_comments_number_text_declension_with_default_args() {
		$post_id   = self::$post_id;
		$permalink = get_permalink( $post_id );
		$this->go_to( $permalink );

		$this->assertSame( __( 'No Comments' ), get_comments_number_text() );

		self::factory()->comment->create_post_comments( $post_id, 1 );
		$this->go_to( $permalink );

		$this->assertSame( __( '1 Comment' ), get_comments_number_text() );

		self::factory()->comment->create_post_comments( $post_id, 1 );
		$this->go_to( $permalink );

		$this->assertSame( sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ), get_comments_number_text() );

	}

	/**
	 * @ticket 13651
	 * @dataProvider data_get_comments_number_text_declension
	 *
	 * @covers ::get_comments_number_text
	 */
	public function test_get_comments_number_text_declension_with_custom_args( $number, $input, $output ) {
		$post_id   = self::$post_id;
		$permalink = get_permalink( $post_id );

		self::factory()->comment->create_post_comments( $post_id, $number );
		$this->go_to( $permalink );

		add_filter( 'gettext_with_context', array( $this, 'enable_comment_number_declension' ), 10, 4 );

		$this->assertSame( $output, get_comments_number_text( false, false, $input ) );

		remove_filter( 'gettext_with_context', array( $this, 'enable_comment_number_declension' ), 10, 4 );
	}

	public function enable_comment_number_declension( $translation, $text, $context, $domain ) {
		if ( 'Comment number declension: on or off' === $context ) {
			$translation = 'on';
		}

		return $translation;
	}

	/**
	 * Data provider for test_get_comments_number_text_declension_with_custom_args().
	 *
	 * @return array {
	 *     @type array {
	 *         @type int    $comments_number The number of comments passed to get_comments_number_text().
	 *         @type string $input           Custom text for comments number, e.g. '%s Comments'.
	 *         @type string $output          The expected output with the correct plural form of '%s Comments'.
	 *     }
	 * }
	 */
	public function data_get_comments_number_text_declension() {
		return array(
			array(
				2,
				'Comments (%)',
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
			array(
				2,
				'2 Comments',
				'2 Comments',
			),
			array(
				2,
				'2 Comments<span class="screen-reader-text"> on Hello world!</span>',
				'2 Comments<span class="screen-reader-text"> on Hello world!</span>',
			),
			array(
				2,
				'2 Comments<span class="screen-reader-text"> on Hello % world!</span>',
				'2 Comments<span class="screen-reader-text"> on Hello 2 world!</span>', // See #WP37103.
			),
			array(
				2,
				__( '% Comments', 'twentyten' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
			array(
				2,
				_x( '%', 'comments number', 'twentyeleven' ),
				'2',
			),
			array(
				2,
				__( '<b>%</b> Replies', 'twentyeleven' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '<b>2</b>' ),
			),
			array(
				2,
				__( '% <span class="reply">comments &rarr;</span>', 'twentyeleven' ),
				sprintf( '2 <span class="reply">%s &rarr;</span>', trim( sprintf( _n( '%s Comment', '%s Comments', 2 ), '' ) ) ),
			),
			array(
				2,
				__( '% Replies', 'twentytwelve' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
			array(
				2,
				__( 'View all % comments', 'twentythirteen' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
			array(
				2,
				__( '% Comments', 'twentyfourteen' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
			array(
				2,
				__( '% Comments', 'twentyfifteen' ),
				sprintf( _n( '%s Comment', '%s Comments', 2 ), '2' ),
			),
		);
	}

}
