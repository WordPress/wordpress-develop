<?php
/**
 * Tests for the WP_On_This_Day class.
 *
 * @group admin
 *
 * @coversDefaultClass WP_On_This_Day
 */
class Tests_Admin_wpOnThisDay extends WP_UnitTestCase {
	/**
	 * Reflection method for invoking WP_On_This_Day::extract_excerpt_text().
	 *
	 * @var ReflectionMethod
	 */
	private static $extract_excerpt_text;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-on-this-day.php';

		self::$extract_excerpt_text = new ReflectionMethod( 'WP_On_This_Day', 'extract_excerpt_text' );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$extract_excerpt_text->setAccessible( true );
		}
	}

	/**
	 * Invokes WP_On_This_Day::extract_excerpt_text().
	 *
	 * @param string $source    HTML source to extract text from.
	 * @param int    $max_chars Approximate character limit before truncation.
	 * @return string Plain-text excerpt.
	 */
	private static function extract_excerpt_text( $source, $max_chars = 160 ) {
		return self::$extract_excerpt_text->invoke( null, $source, $max_chars );
	}

	/**
	 * @dataProvider data_extract_excerpt_text_strips_html_formatting
	 *
	 * @covers ::extract_excerpt_text
	 *
	 * @param string $source   HTML source to extract text from.
	 * @param string $expected Expected plain-text excerpt.
	 */
	public function test_extract_excerpt_text_strips_html_formatting( $source, $expected ) {
		$this->assertSame( $expected, self::extract_excerpt_text( $source ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_extract_excerpt_text_strips_html_formatting() {
		return array(
			'plain text'                           => array(
				'Just words, no tags.',
				'Just words, no tags.',
			),
			'inline formatting'                    => array(
				'<strong>Hello</strong> <em>world</em>',
				'Hello world',
			),
			'adjacent inline tags and punctuation' => array(
				'<span>8</span>:<span>15</span><em>pm</em> <strong>now</strong>',
				'8:15pm now',
			),
			'nested inline formatting'             => array(
				'<p><strong><em>Deep</em></strong> <a href="#">link</a> and <code>code()</code></p>',
				'Deep link and code()',
			),
			'block boundaries'                     => array(
				'<p><strong>Hello</strong></p><p><span>world</span></p>',
				'Hello world',
			),
			'empty block elements'                 => array(
				'<p></p><p></p><p></p>',
				'',
			),
			'headings and blockquotes'             => array(
				'<h2>Memory</h2><blockquote><p>Quote <em>me</em></p></blockquote>',
				'Memory Quote me',
			),
			'lists'                                => array(
				'<ul><li>One</li><li><em>Two</em></li><li><strong>Three</strong></li></ul>',
				'One Two Three',
			),
			'table markup'                         => array(
				'<table><tr><th>Year</th><td><strong>2020</strong></td></tr></table>',
				'Year 2020',
			),
			'void tags'                            => array(
				'<p>Line<br>break<hr>Next<img src="x.jpg" alt="ignored">Done</p>',
				'Line break Next Done',
			),
			'comments'                             => array(
				'<p>Before <!-- hidden comment --> after</p>',
				'Before after',
			),
			'scripts and styles'                   => array(
				'<p>Visible</p><script>document.write("<p>hidden</p>");</script><style>.hidden{display:none}</style><p>Again</p>',
				'Visible Again',
			),
			'escaped markup remains text'          => array(
				'<p>Fish &amp; chips &lt;em&gt;not markup&lt;/em&gt;</p>',
				'Fish & chips <em>not markup</em>',
			),
			'non-breaking spaces collapse'         => array(
				'<p>One&nbsp;&nbsp;<strong>two</strong>&nbsp;three</p>',
				'One two three',
			),
			'tabs and newlines collapse'           => array(
				"<p>One\t<strong>two</strong>\nthree</p>",
				'One two three',
			),
			'malformed nested markup'              => array(
				'<p><strong>Broken <em>but fine</p><p>Next',
				'Broken but fine Next',
			),
		);
	}
}
