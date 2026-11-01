<?php

/**
 * @group comment
 *
 * @covers ::get_comment_excerpt
 */
class Tests_Comment_GetCommentExcerpt extends WP_UnitTestCase {
	protected static $bacon_comment = 'Bacon ipsum dolor amet porchetta capicola sirloin prosciutto brisket shankle jerky. Ham hock filet mignon boudin ground round, prosciutto alcatra spare ribs meatball turducken pork beef ribs ham beef. Bacon pastrami short loin, venison tri-tip ham short ribs doner swine. Tenderloin pig tongue pork jowl doner. Pork loin rump t-bone, beef strip steak flank drumstick tri-tip short loin capicola jowl. Cow filet mignon hamburger doner rump. Short loin jowl drumstick, tongue tail beef ribs pancetta flank brisket landjaeger chuck venison frankfurter turkey.

Brisket shank rump, tongue beef ribs swine fatback turducken capicola meatball picanha chicken cupim meatloaf turkey. Bacon biltong shoulder tail frankfurter boudin cupim turkey drumstick. Porchetta pig shoulder, jerky flank pork tail meatball hamburger. Doner ham hock ribeye tail jerky swine. Leberkas ribeye pancetta, tenderloin capicola doner turducken chicken venison ground round boudin pork chop. Tail pork loin pig spare ribs, biltong ribeye brisket pork chop cupim. Short loin leberkas spare ribs jowl landjaeger tongue kevin flank bacon prosciutto.

Shankle pork chop prosciutto ribeye ham hock pastrami. T-bone shank brisket bacon pork chop. Cupim hamburger pork loin short loin. Boudin ball tip cupim ground round ham shoulder. Sausage rump cow tongue bresaola pork pancetta biltong tail chicken turkey hamburger. Kevin flank pork loin salami biltong. Alcatra landjaeger pastrami andouille kielbasa ham tenderloin drumstick sausage turducken tongue corned beef.';

	public function test_get_comment_excerpt() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_content' => self::$bacon_comment,
			)
		);

		$excerpt = get_comment_excerpt( $comment_id );

		$this->assertCount( 20, explode( ' ', $excerpt ) );
	}

	public function test_get_comment_excerpt_filtered() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_content' => self::$bacon_comment,
			)
		);

		add_filter( 'comment_excerpt_length', array( $this, '_filter_comment_excerpt_length' ) );

		$excerpt = get_comment_excerpt( $comment_id );

		$this->assertCount( 10, explode( ' ', $excerpt ) );
	}

	public function _filter_comment_excerpt_length() {
		remove_filter( 'comment_excerpt_length', array( $this, __METHOD__ ) );

		return 10;
	}

	/**
	 * @ticket 43792
	 *
	 * @dataProvider data_get_comment_excerpt_is_trimmed
	 *
	 * @param string $content  Comment content.
	 * @param int    $length   Excerpt length in words.
	 * @param bool   $expected Expected value of the `$is_trimmed` filter argument.
	 */
	public function test_get_comment_excerpt_filter_receives_is_trimmed( $content, $length, $expected ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_content' => $content,
			)
		);

		$is_trimmed = null;
		add_filter(
			'get_comment_excerpt',
			static function ( $excerpt, $id, $comment, $trimmed ) use ( &$is_trimmed ) {
				$is_trimmed = $trimmed;
				return $excerpt;
			},
			10,
			4
		);
		add_filter(
			'comment_excerpt_length',
			static function () use ( $length ) {
				return $length;
			}
		);

		get_comment_excerpt( $comment_id );

		$this->assertSame( $expected, $is_trimmed );
	}

	public function data_get_comment_excerpt_is_trimmed() {
		return array(
			'long comment'                        => array( self::$bacon_comment, 20, true ),
			'short comment'                       => array( 'Short comment.', 20, false ),
			'word count equals the limit'         => array( 'one two three four five', 5, false ),
			'word count exceeds the limit'        => array( 'one two three four five six', 5, true ),
			'short comment ending in an ellipsis' => array( 'Wait for it&hellip;', 20, false ),
		);
	}
}
