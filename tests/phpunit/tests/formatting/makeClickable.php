<?php

/**
 * @group formatting
 *
 * @covers ::make_clickable
 */
class Tests_Formatting_MakeClickable extends WP_UnitTestCase {
	public function test_mailto_xss() {
		$in = 'testzzz@"STYLE="behavior:url(\'#default#time2\')"onBegin="alert(\'refresh-XSS\')"';
		$this->assertSame( $in, make_clickable( $in ) );
	}

	/**
	 * @dataProvider data_valid_mailto
	 *
	 * @param string $email Email to test.
	 */
	public function test_valid_mailto( $email ) {
		$this->assertSame( '<a href="mailto:' . $email . '">' . $email . '</a>', make_clickable( $email ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_valid_mailto() {
		return array(
			array( 'foo@example.com' ),
			array( 'foo.bar@example.com' ),
			array( 'Foo.Bar@a.b.c.d.example.com' ),
			array( '0@example.com' ),
			array( 'foo@example-example.com' ),
		);
	}

	/**
	 * @dataProvider data_invalid_mailto
	 *
	 * @param string $email Email to test.
	 */
	public function test_invalid_mailto( $email ) {
		$this->assertSame( $email, make_clickable( $email ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_invalid_mailto() {
		return array(
			array( 'foo' ),
			array( 'foo@' ),
			array( 'foo@@example.com' ),
			array( '@example.com' ),
			array( 'foo @example.com' ),
			array( 'foo@example' ),
		);
	}

	/**
	 * Tests that make_clickable() will not link trailing periods, commas,
	 * and (semi-)colons in URLs with protocol (i.e. http://wordpress.org).
	 *
	 * @dataProvider data_strip_trailing_with_protocol
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_strip_trailing_with_protocol( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strip_trailing_with_protocol() {
		return array(
			'URL only'                => array(
				'text'     => 'http://wordpress.org/hello.html',
				'expected' => '<a href="http://wordpress.org/hello.html" rel="nofollow">http://wordpress.org/hello.html</a>',
			),
			'URL before a period'     => array(
				'text'     => 'There was a spoon named http://wordpress.org. Alice!',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>. Alice!',
			),
			'URL before a comma'      => array(
				'text'     => 'There was a spoon named http://wordpress.org, said Alice.',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>, said Alice.',
			),
			'URL before a semi-colon' => array(
				'text'     => 'There was a spoon named http://wordpress.org; said Alice.',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>; said Alice.',
			),
			'URL before a colon'      => array(
				'text'     => 'There was a spoon named http://wordpress.org: said Alice.',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>: said Alice.',
			),
			'URL within parentheses'  => array(
				'text'     => 'There was a spoon named (http://wordpress.org) said Alice.',
				'expected' => 'There was a spoon named (<a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>) said Alice.',
			),
		);
	}

	/**
	 * Tests that make_clickable() will not link trailing periods, commas,
	 * and (semi-)colons in URLs with protocol (i.e. http://wordpress.org).
	 *
	 * @dataProvider data_strip_trailing_with_protocol_nothing_afterwards
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_strip_trailing_with_protocol_nothing_afterwards( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strip_trailing_with_protocol_nothing_afterwards() {
		return array(
			'URL only'                                    => array(
				'text'     => 'http://wordpress.org/hello.html',
				'expected' => '<a href="http://wordpress.org/hello.html" rel="nofollow">http://wordpress.org/hello.html</a>',
			),
			'URL before a period'                         => array(
				'text'     => 'There was a spoon named http://wordpress.org.',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>.',
			),
			'URL before a comma'                          => array(
				'text'     => 'There was a spoon named http://wordpress.org,',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>,',
			),
			'URL before a semi-colon'                     => array(
				'text'     => 'There was a spoon named http://wordpress.org;',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>;',
			),
			'URL before a colon'                          => array(
				'text'     => 'There was a spoon named http://wordpress.org:',
				'expected' => 'There was a spoon named <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>:',
			),
			'URL within parentheses'                      => array(
				'text'     => 'There was a spoon named (http://wordpress.org)',
				'expected' => 'There was a spoon named (<a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>)',
			),
			'URL within parentheses with character after' => array(
				'text'     => 'There was a spoon named (http://wordpress.org)x',
				'expected' => 'There was a spoon named (<a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>)x',
			),
		);
	}

	/**
	 * Tests that make_clickable() will not link trailing periods, commas,
	 * and (semi-)colons in URLs without protocol (i.e. www.wordpress.org).
	 *
	 * @dataProvider data_strip_trailing_without_protocol
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_strip_trailing_without_protocol( $text, $expected ) {
			$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strip_trailing_without_protocol() {
		return array(
			'URL only'                => array(
				'text'     => 'www.wordpress.org/hello.html',
				'expected' => '<a href="http://www.wordpress.org/hello.html" rel="nofollow">http://www.wordpress.org/hello.html</a>',
			),
			'URL before a period'     => array(
				'text'     => 'There was a spoon named www.wordpress.org. Alice!',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>. Alice!',
			),
			'URL before a comma'      => array(
				'text'     => 'There was a spoon named www.wordpress.org, said Alice.',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>, said Alice.',
			),
			'URL before a semi-colon' => array(
				'text'     => 'There was a spoon named www.wordpress.org; said Alice.',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>; said Alice.',
			),
			'URL before a colon'      => array(
				'text'     => 'There was a spoon named www.wordpress.org: said Alice.',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>: said Alice.',
			),
			'URL within parentheses'  => array(
				'text'     => 'There was a spoon named www.wordpress.org) said Alice.',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>) said Alice.',
			),
		);
	}

	/**
	 * Tests that make_clickable() will not link trailing periods, commas,
	 * and (semi-)colons in URLs without protocol (i.e. www.wordpress.org).
	 *
	 * @dataProvider data_strip_trailing_without_protocol_nothing_afterwards
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_strip_trailing_without_protocol_nothing_afterwards( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strip_trailing_without_protocol_nothing_afterwards() {
		return array(
			'URL only'                => array(
				'text'     => 'www.wordpress.org',
				'expected' => '<a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>',
			),
			'URL before a period'     => array(
				'text'     => 'There was a spoon named www.wordpress.org.',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>.',
			),
			'URL before a comma'      => array(
				'text'     => 'There was a spoon named www.wordpress.org,',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>,',
			),
			'URL before a semi-colon' => array(
				'text'     => 'There was a spoon named www.wordpress.org;',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>;',
			),
			'URL before a colon'      => array(
				'text'     => 'There was a spoon named www.wordpress.org:',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>:',
			),
			'URL within parentheses'  => array(
				'text'     => 'There was a spoon named www.wordpress.org)',
				'expected' => 'There was a spoon named <a href="http://www.wordpress.org" rel="nofollow">http://www.wordpress.org</a>)',
			),
		);
	}

	/**
	 * @ticket 4570
	 *
	 * @dataProvider data_iri
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_iri( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_iri() {
		return array(
			'in domain'       => array(
				'text'     => 'http://www.詹姆斯.com/',
				'expected' => '<a href="http://www.詹姆斯.com/" rel="nofollow">http://www.詹姆斯.com/</a>',
			),
			'in path'         => array(
				'text'     => 'http://bg.wikipedia.org/Баба',
				'expected' => '<a href="http://bg.wikipedia.org/Баба" rel="nofollow">http://bg.wikipedia.org/Баба</a>',
			),
			'in query string' => array(
				'text'     => 'http://example.com/?a=баба&b=дядо',
				'expected' => '<a href="http://example.com/?a=баба&#038;b=дядо" rel="nofollow">http://example.com/?a=баба&#038;b=дядо</a>',
			),
		);
	}

	/**
	 * @ticket 10990
	 *
	 * @dataProvider data_brackets_in_urls
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_brackets_in_urls( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_brackets_in_urls() {
		return array(
			'URL with brackets'                          => array(
				'text'     => 'http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)',
				'expected' => '<a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>',
			),
			'() around URL with brankets'                => array(
				'text'     => '(http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software))',
				'expected' => '(<a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>)',
			),
			'a word before and after URL with brankets'  => array(
				'text'     => 'blah http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software) blah',
				'expected' => 'blah <a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a> blah',
			),
			'() around URL with brankets, with a word before and after' => array(
				'text'     => 'blah (http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)) blah',
				'expected' => 'blah (<a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>) blah',
			),
			'URL with brankets within content'           => array(
				'text'     => 'blah blah blah http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software) blah blah',
				'expected' => 'blah blah blah <a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a> blah blah',
			),
			') after URL with brankets within content'   => array(
				'text'     => 'blah blah blah http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)) blah blah',
				'expected' => 'blah blah blah <a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>) blah blah',
			),
			'() around URL with brankets within content' => array(
				'text'     => 'blah blah (http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)) blah blah',
				'expected' => 'blah blah (<a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>) blah blah',
			),
			'.) after URL with brankets within content'  => array(
				'text'     => 'blah blah http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software).) blah blah',
				'expected' => 'blah blah <a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>.) blah blah',
			),
			'.)moreurl after URL with brankets within content' => array(
				'text'     => 'blah blah http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software).)moreurl blah blah',
				'expected' => 'blah blah <a href="http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)" rel="nofollow">http://en.wikipedia.org/wiki/PC_Tools_(Central_Point_Software)</a>.)moreurl blah blah',
			),
			'multiline URL with brankets'                => array(
				'text'     => 'In his famous speech “You and Your research” (here:
							   http://www.cs.virginia.edu/~robins/YouAndYourResearch.html)
							   Richard Hamming wrote about people getting more done with their doors closed, but',
				'expected' => 'In his famous speech “You and Your research” (here:
							   <a href="http://www.cs.virginia.edu/~robins/YouAndYourResearch.html" rel="nofollow">http://www.cs.virginia.edu/~robins/YouAndYourResearch.html</a>)
							   Richard Hamming wrote about people getting more done with their doors closed, but',
			),
		);
	}

	/**
	 * Based on real comments which were incorrectly linked.
	 *
	 * @ticket 11211
	 *
	 * @dataProvider data_real_world_examples
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_real_world_examples( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_real_world_examples() {
		return array(
			'example.com text (.org URL)' => array(
				'text'     => 'Example: WordPress, test (some text), I love example.com (http://example.org), it is brilliant',
				'expected' => 'Example: WordPress, test (some text), I love example.com (<a href="http://example.org">http://example.org</a>), it is brilliant',
			),
			'example.com text (.com URL)' => array(
				'text'     => 'Example: WordPress, test (some text), I love example.com (http://example.com), it is brilliant',
				'expected' => 'Example: WordPress, test (some text), I love example.com (<a href="http://example.com" rel="nofollow">http://example.com</a>), it is brilliant',
			),
			'(URL)...'                    => array(
				'text'     => 'Some text followed by a bracketed link with a trailing elipsis (http://example.com)...',
				'expected' => 'Some text followed by a bracketed link with a trailing elipsis (<a href="http://example.com" rel="nofollow">http://example.com</a>)...',
			),
			'(here: URL)'                 => array(
				'text'     => 'In his famous speech “You and Your research” (here: http://www.cs.virginia.edu/~robins/YouAndYourResearch.html) Richard Hamming wrote about people getting more done with their doors closed...',
				'expected' => 'In his famous speech “You and Your research” (here: <a href="http://www.cs.virginia.edu/~robins/YouAndYourResearch.html" rel="nofollow">http://www.cs.virginia.edu/~robins/YouAndYourResearch.html</a>) Richard Hamming wrote about people getting more done with their doors closed...',
			),
		);
	}

	/**
	 * @ticket 14993
	 *
	 * @dataProvider data_twitter_hash_bang
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_twitter_hash_bang( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_twitter_hash_bang() {
		return array(
			'Twitter hash bang URL'             => array(
				'text'     => 'http://twitter.com/#!/wordpress/status/25907440233',
				'expected' => '<a href="http://twitter.com/#!/wordpress/status/25907440233" rel="nofollow">http://twitter.com/#!/wordpress/status/25907440233</a>',
			),
			'Twitter hash bang URL in sentence' => array(
				'text'     => 'This is a really good tweet http://twitter.com/#!/wordpress/status/25907440233 !',
				'expected' => 'This is a really good tweet <a href="http://twitter.com/#!/wordpress/status/25907440233" rel="nofollow">http://twitter.com/#!/wordpress/status/25907440233</a> !',
			),
			'Twitter hash bang in sentence with trailing !' => array(
				'text'     => 'This is a really good tweet http://twitter.com/#!/wordpress/status/25907440233!',
				'expected' => 'This is a really good tweet <a href="http://twitter.com/#!/wordpress/status/25907440233" rel="nofollow">http://twitter.com/#!/wordpress/status/25907440233</a>!',
			),
		);
	}

	/**
	 * @dataProvider data_wrapped_in_angles
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_wrapped_in_angles( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wrapped_in_angles() {
		return array(
			'<URL>'   => array(
				'text'     => 'URL wrapped in angle brackets <http://example.com/>',
				'expected' => 'URL wrapped in angle brackets <<a href="http://example.com/" rel="nofollow">http://example.com/</a>>',
			),
			'< URL >' => array(
				'text'     => 'URL wrapped in angle brackets with padding < http://example.com/ >',
				'expected' => 'URL wrapped in angle brackets with padding < <a href="http://example.com/" rel="nofollow">http://example.com/</a> >',
			),
			'<email>' => array(
				'text'     => 'mailto wrapped in angle brackets <foo@example.com>',
				'expected' => 'mailto wrapped in angle brackets <foo@example.com>',
			),
		);
	}

	/**
	 * @dataProvider data_preceded_by_punctuation
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_preceded_by_punctuation( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_preceded_by_punctuation() {
		return array(
			',URL' => array(
				'text'     => 'Comma then URL,http://example.com/',
				'expected' => 'Comma then URL,<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
			'.URL' => array(
				'text'     => 'Period then URL.http://example.com/',
				'expected' => 'Period then URL.<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
			';URL' => array(
				'text'     => 'Semi-colon then URL;http://example.com/',
				'expected' => 'Semi-colon then URL;<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
			':URL' => array(
				'text'     => 'Colon then URL:http://example.com/',
				'expected' => 'Colon then URL:<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
			'!URL' => array(
				'text'     => 'Exclamation mark then URL!http://example.com/',
				'expected' => 'Exclamation mark then URL!<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
			'?URL' => array(
				'text'     => 'Question mark then URL?http://example.com/',
				'expected' => 'Question mark then URL?<a href="http://example.com/" rel="nofollow">http://example.com/</a>',
			),
		);
	}

	/**
	 * @dataProvider data_dont_break_attributes
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_dont_break_attributes( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_dont_break_attributes() {
		return array(
			'<img src=URL with attributes>'       => array(
				'text'     => "<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>",
				'expected' => "<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>",
			),
			'(<img src=URL with attributes>)'     => array(
				'text'     => "(<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
				'expected' => "(<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
			),
			'URL (<img src=URL with attributes>)' => array(
				'text'     => "http://trunk.domain/testing#something (<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
				'expected' => "<a href=\"http://trunk.domain/testing#something\" rel=\"nofollow\">http://trunk.domain/testing#something</a> (<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
			),
			'multiline URL (<img src=URL with attributes>)' => array(
				'text'     => "http://trunk.domain/testing#something
						  (<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
				'expected' => "<a href=\"http://trunk.domain/testing#something\" rel=\"nofollow\">http://trunk.domain/testing#something</a>
						  (<img src='http://trunk.domain/wp-includes/images/smilies/icon_smile.gif' alt=':)' class='wp-smiley'>)",
			),
			'<param value=URL><embed src=URL>'    => array(
				'text'     => "<span style='text-align:center; display: block;'><object width='425' height='350'><param name='movie' value='https://www.youtube.com/watch?v=72xdCU__XCk&rel=1&fs=1&showsearch=0&showinfo=1&iv_load_policy=1' /> <param name='allowfullscreen' value='true' /> <param name='wmode' value='opaque' /> <embed src='https://www.youtube.com/watch?v=72xdCU__XCk&rel=1&fs=1&showsearch=0&showinfo=1&iv_load_policy=1' type='application/x-shockwave-flash' allowfullscreen='true' width='425' height='350' wmode='opaque'></embed> </object></span>",
				'expected' => "<span style='text-align:center; display: block;'><object width='425' height='350'><param name='movie' value='https://www.youtube.com/watch?v=72xdCU__XCk&rel=1&fs=1&showsearch=0&showinfo=1&iv_load_policy=1' /> <param name='allowfullscreen' value='true' /> <param name='wmode' value='opaque' /> <embed src='https://www.youtube.com/watch?v=72xdCU__XCk&rel=1&fs=1&showsearch=0&showinfo=1&iv_load_policy=1' type='application/x-shockwave-flash' allowfullscreen='true' width='425' height='350' wmode='opaque'></embed> </object></span>",
			),
			'<a src=URL title=URL></a>'           => array(
				'text'     => '<a href="http://example.com/example.gif" title="Image from http://example.com">Look at this image!</a>',
				'expected' => '<a href="http://example.com/example.gif" title="Image from http://example.com">Look at this image!</a>',
			),
		);
	}

	/**
	 * @ticket 23756
	 *
	 * @dataProvider data_no_links_inside_pre_or_code
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_no_links_inside_pre_or_code( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_no_links_inside_pre_or_code() {
		return array(
			'Does not add link within <pre>'   => array(
				'text'     => '<pre>http://wordpress.org</pre>',
				'expected' => '<pre>http://wordpress.org</pre>',
			),
			'Does not add link within <code>'  => array(
				'text'     => '<code>http://wordpress.org</code>',
				'expected' => '<code>http://wordpress.org</code>',
			),
			'Does not add link within <pre with attributes>' => array(
				'text'     => '<pre class="foobar" id="foo">http://wordpress.org</pre>',
				'expected' => '<pre class="foobar" id="foo">http://wordpress.org</pre>',
			),
			'Does not add link within <code with attributes>' => array(
				'text'     => '<code class="foobar" id="foo">http://wordpress.org</code>',
				'expected' => '<code class="foobar" id="foo">http://wordpress.org</code>',
			),
			'Adds link within <precustomtag>'  => array(
				'text'     => '<precustomtag>http://wordpress.org</precustomtag>',
				'expected' => '<precustomtag><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a></precustomtag>',
			),
			'Adds link within <codecustomtag>' => array(
				'text'     => '<codecustomtag>http://wordpress.org</codecustomtag>',
				'expected' => '<codecustomtag><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a></codecustomtag>',
			),
			'Adds link to URL before <pre>, but does not add link within <pre>' => array(
				'text'     => 'URL before pre http://wordpress.org<pre>http://wordpress.org</pre>',
				'expected' => 'URL before pre <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a><pre>http://wordpress.org</pre>',
			),
			'Adds link to URL before <code>, but does not add link within <code>' => array(
				'text'     => 'URL before code http://wordpress.org<code>http://wordpress.org</code>',
				'expected' => 'URL before code <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a><code>http://wordpress.org</code>',
			),
			'Does not add link to <PRE>, but does add link to URL after <PRE>' => array(
				'text'     => 'URL after pre <PRE>http://wordpress.org</PRE>http://wordpress.org',
				'expected' => 'URL after pre <PRE>http://wordpress.org</PRE><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>',
			),
			'Does not add link within <code>, but does add link to URL after <code>' => array(
				'text'     => 'URL after code <code>http://wordpress.org</code>http://wordpress.org',
				'expected' => 'URL after code <code>http://wordpress.org</code><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>',
			),
			'Adds link to before and after URLs, but does not add link within <pre>' => array(
				'text'     => 'URL before and after pre http://wordpress.org<pre>http://wordpress.org</pre>http://wordpress.org',
				'expected' => 'URL before and after pre <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a><pre>http://wordpress.org</pre><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>',
			),
			'Adds link to before and after URLs, but does not add link within <code>' => array(
				'text'     => 'URL before and after code http://wordpress.org<code>http://wordpress.org</code>http://wordpress.org',
				'expected' => 'URL before and after code <a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a><code>http://wordpress.org</code><a href="http://wordpress.org" rel="nofollow">http://wordpress.org</a>',
			),
			'Does not add links within nested <pre>URL <code>URL</code> </pre>' => array(
				'text'     => 'code inside pre <pre>http://wordpress.org <code>http://wordpress.org</code> http://wordpress.org</pre>',
				'expected' => 'code inside pre <pre>http://wordpress.org <code>http://wordpress.org</code> http://wordpress.org</pre>',
			),
		);
	}

	/**
	 * @ticket 16892
	 *
	 * @dataProvider data_click_inside_html
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_click_inside_html( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_click_inside_html() {
		return array(
			'<span>URL</span>' => array(
				'text'     => '<span>http://example.com</span>',
				'expected' => '<span><a href="http://example.com" rel="nofollow">http://example.com</a></span>',
			),
			'<p>URL</p>'       => array(
				'text'     => '<p>http://example.com/</p>',
				'expected' => '<p><a href="http://example.com/" rel="nofollow">http://example.com/</a></p>',
			),
		);
	}

	/**
	 *
	 * @dataProvider data_no_links_within_links
	 *
	 * @param string $text     Content to test.
	 * @param string $expected Expected results.
	 */
	public function test_no_links_within_links( $text, $expected ) {
		$this->assertSame( $expected, make_clickable( $text ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_no_links_within_links() {
		return array(
			'<a>URL</a>' => array(
				'text'     => 'Some text with a link <a href="http://example.com">http://example.com</a>',
				'expected' => 'Some text with a link <a href="http://example.com">http://example.com</a>',
			),
			/*
			Fails in 3.3.1 too.
			'<a>text www.URL</a>'   => array(
				'text'     => '<a href="http://wordpress.org">This is already a link www.wordpress.org</a>',
				'expected' => '<a href="http://wordpress.org">This is already a link www.wordpress.org</a>',
			),
			*/
		);
	}

	/**
	 * @ticket 16892
	 */
	public function test_no_segfault() {
		$in  = str_repeat( 'http://example.com/2011/03/18/post-title/', 256 );
		$out = make_clickable( $in );
		$this->assertSame( $in, $out );
	}

	/**
	 * @ticket 19028
	 */
	public function test_line_break_in_existing_clickable_link() {
		$html = "<a
				  href='mailto:someone@example.com'>someone@example.com</a>";
		$this->assertSame( $html, make_clickable( $html ) );
	}

	/**
	 * @ticket 30162
	 * @dataProvider data_script_and_style_tags
	 */
	public function test_dont_link_script_and_style_tags( $tag ) {
		$this->assertSame( $tag, make_clickable( $tag ) );
	}

	public function data_script_and_style_tags() {
		return array(
			array(
				'<script>http://wordpress.org</script>',
			),
			array(
				'<style>http://wordpress.org</style>',
			),
			array(
				'<script type="text/javascript">http://wordpress.org</script>',
			),
			array(
				'<style type="text/css">http://wordpress.org</style>',
			),
		);
	}

	/**
	 * @ticket 48022
	 * @ticket 56444
	 * @dataProvider data_add_rel_ugc_in_comments
	 */
	public function test_add_rel_ugc_in_comments( $content, $expected ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_content' => $content,
			)
		);

		ob_start();
		comment_text( $comment_id );
		$comment_text = ob_get_clean();

		$this->assertStringContainsString( $expected, make_clickable( $comment_text ) );
	}

	public function data_add_rel_ugc_in_comments() {
		$home_url_http  = set_url_scheme( home_url(), 'http' );
		$home_url_https = set_url_scheme( home_url(), 'https' );

		return array(
			// @ticket 48022
			array(
				'http://wordpress.org',
				'<a href="http://wordpress.org" rel="nofollow ugc">http://wordpress.org</a>',
			),
			array(
				'www.wordpress.org',
				'<p><a href="http://www.wordpress.org" rel="nofollow ugc">http://www.wordpress.org</a>',
			),
			// @ticket 56444
			array(
				'www.example.org',
				'<p><a href="http://www.example.org" rel="nofollow ugc">http://www.example.org</a>',
			),
			array(
				$home_url_http,
				'<a href="' . $home_url_http . '" rel="ugc">' . $home_url_http . '</a>',
			),
			array(
				$home_url_https,
				'<a href="' . $home_url_https . '" rel="ugc">' . $home_url_https . '</a>',
			),
		);
	}

}
