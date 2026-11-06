<?php
/**
 * Tests for the server-side rendering of the Post Time To Read block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderPostTimeToRead extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original filters of the type of the word count.
	 *
	 * @var bool
	 */
	private $filter_added = false;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Creates a post with a number of words, and goes to it.
	 *
	 * @param int $words Number of words of the content.
	 * @return int ID of the post.
	 */
	private function go_to_post_with_words( $words ) {
		$post_id = self::factory()->post->create( array( 'post_content' => implode( ' ', array_fill( 0, $words, 'word' ) ) ) );
		$this->go_to( get_permalink( $post_id ) );

		return $post_id;
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-time-to-read',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => get_the_ID() ) : $context;

		return render_block_core_post_time_to_read( $attributes, '', $block );
	}

	/**
	 * Tests the count of words and of characters.
	 *
	 * @ticket 66545
	 *
	 * @covers ::block_core_post_time_to_read_word_count
	 *
	 * @dataProvider data_counts
	 *
	 * @param string $text     Text to count.
	 * @param string $type     Type of the count.
	 * @param int    $expected Expected count.
	 */
	public function test_should_count_the_words_and_the_characters( $text, $type, $expected ) {
		$this->assertSame( $expected, block_core_post_time_to_read_word_count( $text, $type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_counts() {
		return array(
			'an empty text'                     => array( '', 'words', 0 ),
			'spaces'                            => array( " \n\t ", 'words', 0 ),
			'an empty text of characters'       => array( '', 'characters_including_spaces', 0 ),
			'a word'                            => array( 'Hello', 'words', 1 ),
			'some words'                        => array( 'One two three', 'words', 3 ),
			'extra spaces'                      => array( "  One   two\n\nthree  ", 'words', 3 ),
			'punctuation is removed'            => array( 'Hello, world! (and) [you].', 'words', 4 ),
			'only punctuation'                  => array( '!!! ... ???', 'words', 0 ),
			'html tags'                         => array( '<p>One <strong>two</strong></p><p>three</p>', 'words', 3 ),
			'tags that separate words'          => array( 'one<br>two', 'words', 2 ),
			'html comments'                     => array( 'One <!-- a comment with words --> two', 'words', 2 ),
			'a non-breaking space'              => array( 'One&nbsp;two&#160;three', 'words', 3 ),
			'html entities'                     => array( 'Fish &amp; chips &hellip; done', 'words', 3 ),
			'connectors'                        => array( "one\u{2014}two--three", 'words', 3 ),
			'letters with accents'              => array( "caf\u{E9} cr\u{E8}me br\u{FB}l\u{E9}e", 'words', 3 ),
			'a type that is not valid'          => array( 'One two three', 'something', 3 ),
			'characters without spaces'         => array( 'One two', 'characters_excluding_spaces', 6 ),
			'characters with spaces'            => array( 'One two', 'characters_including_spaces', 7 ),
			'characters of html'                => array( '<p>ab</p>', 'characters_excluding_spaces', 2 ),
			'entities counted as one character' => array( 'a&amp;b', 'characters_excluding_spaces', 3 ),
			'astral characters counted as one'  => array( "a\u{1F600}b", 'characters_excluding_spaces', 3 ),
			'punctuation counted as characters' => array( 'a, b.', 'characters_excluding_spaces', 4 ),
			'new lines not counted with spaces' => array( "a\nb", 'characters_including_spaces', 2 ),
		);
	}

	/**
	 * Tests that nothing is rendered without a post.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 */
	public function test_should_render_nothing_without_a_post() {
		$this->go_to_post_with_words( 10 );

		$this->assertSame( '', $this->render( array(), array() ) );
	}

	/**
	 * Tests the minutes to read.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 *
	 * @dataProvider data_minutes
	 *
	 * @param int    $words      Number of words of the post.
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected text.
	 */
	public function test_should_render_the_minutes_to_read( $words, $attributes, $expected ) {
		$this->go_to_post_with_words( $words );

		$this->assertSame( '<div >' . $expected . '</div>', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_minutes() {
		return array(
			'a short post'                  => array( 10, array(), '1 minute' ),
			'a post that needs a minute'    => array( 189, array(), '1 minute' ),
			'a post that needs two minutes' => array( 378, array(), '2 minutes' ),
			'the default reading speed'     => array( 945, array(), '5 minutes' ),
			'a post that needs ten minutes' => array( 1890, array(), '10 minutes' ),
			'a range that is rounded'       => array( 900, array( 'displayAsRange' => true ), '4–6 minutes' ),
			'a speed'                       => array( 600, array( 'averageReadingSpeed' => 100 ), '6 minutes' ),
			'a speed and a short post'      => array( 5, array( 'averageReadingSpeed' => 100 ), '1 minute' ),
			'the time as the display mode'  => array( 378, array( 'displayMode' => 'time' ), '2 minutes' ),
			'a range'                       => array( 945, array( 'displayAsRange' => true ), '4–6 minutes' ),
			'a range for a short post'      => array( 10, array( 'displayAsRange' => true ), '1–2 minutes' ),
			'a range with a speed'          => array(
				600,
				array(
					'displayAsRange'      => true,
					'averageReadingSpeed' => 100,
				),
				'5–7 minutes',
			),
		);
	}

	/**
	 * Tests the number of words.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 *
	 * @dataProvider data_word_counts
	 *
	 * @param int    $words    Number of words of the post.
	 * @param string $expected Expected text.
	 */
	public function test_should_render_the_number_of_words( $words, $expected ) {
		$this->go_to_post_with_words( $words );

		$this->assertSame( '<div >' . $expected . '</div>', $this->render( array( 'displayMode' => 'words' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_word_counts() {
		return array(
			'one word'   => array( 1, '1 word' ),
			'some words' => array( 12, '12 words' ),
			'many words' => array( 1200, '1,200 words' ),
		);
	}

	/**
	 * Tests the number of characters for the languages that count them.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 */
	public function test_should_render_the_number_of_characters_for_a_language_that_counts_them() {
		global $wp_locale;

		$original_type = $wp_locale->word_count_type;

		try {
			$wp_locale->word_count_type = 'characters_excluding_spaces';

			$post_id = self::factory()->post->create( array( 'post_content' => 'ab cd' ) );
			$this->go_to( get_permalink( $post_id ) );

			$this->assertSame( '<div >4 characters</div>', $this->render( array( 'displayMode' => 'words' ) ) );
		} finally {
			$wp_locale->word_count_type = $original_type;
		}
	}

	/**
	 * Tests that nothing is rendered for a display mode that is not known.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 */
	public function test_should_render_nothing_inside_for_a_display_mode_that_is_not_known() {
		$this->go_to_post_with_words( 100 );

		$this->assertSame( '<div ></div>', $this->render( array( 'displayMode' => 'other' ) ) );
	}

	/**
	 * Tests the class of the text alignment.
	 *
	 * @ticket 66545
	 *
	 * @covers ::render_block_core_post_time_to_read
	 *
	 * @dataProvider data_text_alignments
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_class_of_the_text_alignment( $attributes, $expected ) {
		$this->go_to_post_with_words( 10 );

		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_alignments() {
		return array(
			'no alignment'   => array( array(), '<div >' ),
			'an empty value' => array( array( 'textAlign' => '' ), '<div >' ),
			'center'         => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
		);
	}

	/**
	 * Tests that the block is registered with the callbacks.
	 *
	 * @ticket 66545
	 *
	 * @covers ::register_block_core_post_time_to_read
	 */
	public function test_should_register_the_block_with_the_callbacks() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-time-to-read' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-time-to-read' );
		}

		register_block_core_post_time_to_read();
		$block_type = $registry->get_registered( 'core/post-time-to-read' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-time-to-read' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_time_to_read', $block_type->render_callback );
	}
}
