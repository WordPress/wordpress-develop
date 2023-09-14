<?php
/**
 * Unit tests covering WP_HTML_Processor string building functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor_StringBuilder extends WP_UnitTestCase {
	/**
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_html_and_associated_text_content
	 *
	 * @param string $html         HTML containing text that should be extracted.
	 * @param string $text_content Plaintext content represented inside the given HTML.
	 */
	public function test_extracts_text_chunks_properly( $html, $text_content ) {
		$processor = new WP_HTML_Tag_Processor( $html );

		$extracted_text_content = '';
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$extracted_text_content .= $processor->get_previous_text_chunk();
		}
		$extracted_text_content .= $processor->get_previous_text_chunk();

		$this->assertEquals( $text_content, $extracted_text_content, 'Extracted unexpected text content.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html_and_associated_text_content() {
		return array(
			'Basic text without HTML.'               => array( 'This is plain text.', 'This is plain text.' ),
			'Basic text with a character reference.' => array( 'A &lt; B', 'A < B' ),
			'Text before tag.'                       => array( 'Before<img>', 'Before' ),
			'Text after tag.'                        => array( '<img>After', 'After' ),
			'Text inside tag.'                       => array( '<div>Inside</div>', 'Inside' ),
			'Text around tag.'                       => array( 'In <em>the</em> jungle.', 'In the jungle.' ),
			'Text interrupted by many tags.'         => array( 'A <em>wild <a><img><span>adventure</span></a> awaits.', 'A wild adventure awaits.' ),
			'Text with comment inside it.'           => array( 'Ignore <!-- everything inside this --> comment.', 'Ignore  comment.' ),
			'Text with empty comment inside it.'     => array( 'Ignore <!--> comment.', 'Ignore  comment.' ),
			'Text with invalid comment inside it.'   => array( 'Ignore </^$%> comment.', 'Ignore  comment.' ),
			'Skipping SCRIPT content.'               => array( '<div>This <script>does not exist</script> in the output.', 'This  in the output.' ),
		);
	}

	/**
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_html_and_associated_html_content
	 *
	 * @param string $html            HTML containing text that should be extracted.
	 * @param int    $max_code_points Stop iterating after this many code points have been extracted.
	 * @param string $html_content    Full HTML containing text of max code point length from input.
	 */
	public function test_extracts_html_chunks_properly( $html, $max_code_points, $html_content ) {
		$processor = new WP_HTML_Tag_Processor( $html );

		$code_points            = 0;
		$extracted_html_content = '';
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$text_chunk = $processor->get_previous_text_chunk();
			$chunk_cps           = mb_strlen( $text_chunk );
			list( $html, $text ) = $processor->get_previous_html_chunk();
			$extracted_html_content .= $html;
			if ( 0 === $max_code_points || $code_points + $chunk_cps <= $max_code_points ) {
				$extracted_html_content .= $text;
				$code_points            += $chunk_cps;
			} else {
				break;
			}
		}

		$text_chunk = $processor->get_previous_text_chunk();
		$chunk_cps  = mb_strlen( $text_chunk );
		list( $html, $text ) = $processor->get_previous_html_chunk();
		$extracted_html_content .= $html;
		if ( 0 === $max_code_points || $code_points + $chunk_cps <= $max_code_points ) {
			$extracted_html_content .= $text;
		}

		$this->assertEquals( $html_content, $extracted_html_content, 'Extracted unexpected HTML content.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html_and_associated_html_content() {
		return array(
			'Basic text without HTML.'               => array( 'This is plain text.', 0, 'This is plain text.' ),
			'Basic text without HTML (too long).'    => array( 'This is plain text.', 8, '' ),
			'Basic text with a character reference.' => array( 'A &lt; B', 0, 'A &lt; B' ),
			'Character reference wider than text'    => array( 'A &lt; B', 5, 'A &lt; B' ),
			'Text before tag.'                       => array( 'Before<img>', 0, 'Before<img>' ),
			'Text after tag.'                        => array( '<img>After', 0, '<img>After' ),
			'Text inside tag.'                       => array( '<div>Inside</div>', 0, '<div>Inside</div>' ),
			'Text around tag.'                       => array( 'In <em>the</em> jungle.', 0, 'In <em>the</em> jungle.' ),
			'Text interrupted by many tags.'         => array( 'A <em>wild <a><img><span>adventure</span></a> awaits.', 0, 'A <em>wild <a><img><span>adventure</span></a> awaits.' ),
			'Text interrupted by many tags (long).'  => array( 'A <em>wild <a><img><span>adventure</span></a> awaits.', 16, 'A <em>wild <a><img><span>adventure</span></a>' ),
			'Text with comment inside it.'           => array( 'Ignore <!-- everything inside this --> comment.', 0, 'Ignore <!-- everything inside this --> comment.' ),
		);
	}

	/**
	 * @dataProvider data_html_with_locale_and_excerpt
	 *
	 * @param $html
	 * @param $locale
	 * @param $word_count
	 * @return void
	 */
	public function test_excerpt_of_so_many_words( $html, $locale, $max_word_count, $html_excerpt ) {
		$processor = new WP_HTML_Tag_Processor( $html );

		$excerpt_text       = '';
		$excerpt            = '';
		$words              = IntlBreakIterator::createWordInstance( $locale );

		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$word_count    = 0;
			$excerpt_text .= $processor->get_previous_text_chunk();
			$words->setText( $excerpt_text );

			list( $html, $text ) = $processor->get_previous_html_chunk();
			$excerpt .= $html;
			foreach ( $words as $_ ) {
				if ( IntlRuleBasedBreakIterator::WORD_NONE !== $words->getRuleStatus() ) {
					$word_count++;
				}

				if ( $word_count > $max_word_count ) {
					break 2;
				}
			}
			$excerpt .= $text;
		}
		if ( $word_count <= $max_word_count ) {
			list( $html, $text ) = $processor->get_previous_html_chunk();
			$excerpt .= $html;
		}

		$this->assertEquals( $html_excerpt, $excerpt, 'Extracted wrong excerpt from document.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_html_with_locale_and_excerpt() {
		return array(
			array( '<div>This is a <img> with <em>great</em> ability to inspire.</div>', 'en_US', 3, '<div>This is a <img>' ),
			array( '<div>This is a <img> with <em>great</em> ability to inspire.</div>', 'en_US', 4, '<div>This is a <img> with <em>' ),
			array( '<em>W</em>hat a <i>T</i>hing', 'en_US', 2, '<em>W</em>hat a <i>' ),
			array( '<span>彼</span>は<em>アメリカ人</em>です。', 'jp_JP', 2, '<span>彼</span>は<em>' ),
			array( '<span>彼</span>は<em>アメリカ人</em>です。', 'jp_JP', 4, '<span>彼</span>は<em>アメリカ人</em>' ),
			array( '<div>שְׁמַע יִשְׂרָאֵל<br> יְהוָה אֱלֹהֵינוּ<br> יְהוָה אֶחָֽד</div>', 'he_IL', 2, '<div>שְׁמַע יִשְׂרָאֵל<br>' ),
		);
	}
}
