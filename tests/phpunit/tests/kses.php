<?php
/**
 * Some simple test cases for KSES post content filtering
 *
 * @group formatting
 * @group kses
 */
class Tests_Kses extends WP_UnitTestCase {

	/**
	 * @dataProvider data_wp_filter_post_kses_address
	 * @ticket 20210
	 *
	 * @param string $content  Test string for kses.
	 * @param string $expected Expected result after passing through kses.
	 */
	public function test_wp_filter_post_kses_address( $content, $expected ) {
		global $allowedposttags;

		$this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * Data provider for test_wp_filter_post_kses_address.
	 *
	 * @return array[] Arguments {
	 *     @type string $content  Test string for kses.
	 *     @type string $expected Expected result after passing through kses.
	 * }
	 */
	public function data_wp_filter_post_kses_address() {
		$attributes = array(
			'class' => 'classname',
			'id'    => 'id',
			'style' => array(
				'color: red;',
				'color: red',
				'color: red; text-align:center',
				'color: red; text-align:center;',
			),
			'title' => 'title',
		);

		$data = array();

		foreach ( $attributes as $name => $values ) {
			foreach ( (array) $values as $value ) {
				$content  = "<address $name='$value'>1 WordPress Avenue, The Internet.</address>";
				$expected = "<address $name='" . str_replace( '; ', ';', trim( $value, ';' ) ) . "'>1 WordPress Avenue, The Internet.</address>";

				$data[] = array( $content, $expected );
			}
		}

		return $data;
	}

	/**
	 * @dataProvider data_wp_filter_post_kses_a
	 * @ticket 20210
	 *
	 * @param string $content  Test string for kses.
	 * @param string $expected Expected result after passing through kses.
	 */
	public function test_wp_filter_post_kses_a( $content, $expected ) {
		global $allowedposttags;

		$this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * Data provider for test_wp_filter_post_kses_a.
	 *
	 * @return array[] Arguments {
	 *     @type string $content  Test string for kses.
	 *     @type string $expected Expected result after passing through kses.
	 * }
	 */
	public function data_wp_filter_post_kses_a() {
		$attributes = array(
			'class'    => 'classname',
			'id'       => 'id',
			'style'    => 'color: red;',
			'title'    => 'title',
			'href'     => 'http://example.com',
			'rel'      => 'related',
			'rev'      => 'revision',
			'name'     => 'name',
			'target'   => '_blank',
			'download' => '',
		);

		$data = array();

		foreach ( $attributes as $name => $value ) {
			if ( $value ) {
				$attr          = "$name='$value'";
				$expected_attr = "$name='" . trim( $value, ';' ) . "'";
			} else {
				$attr          = $name;
				$expected_attr = $name;
			}
			$content  = "<a $attr>I link this</a>";
			$expected = "<a $expected_attr>I link this</a>";
			$data[]   = array( $content, $expected );
		}

		return $data;
	}

	/**
	 * Test video tag.
	 *
	 * @ticket 50167
	 * @ticket 29826
	 * @dataProvider data_wp_kses_video
	 *
	 * @param string $source   Source HTML.
	 * @param string $context  Context to use for parsing source.
	 * @param string $expected Expected output following KSES parsing.
	 */
	public function test_wp_kses_video( $source, $context, $expected ) {
		$this->assertEqualHTML( $expected, wp_kses( $source, $context ) );
	}

	/**
	 * Data provider for test_wp_kses_video
	 *
	 * @return array[] Array containing test data {
	 *     @type string $source   Source HTML.
	 *     @type string $context  Context to use for parsing source.
	 *     @type string $expected Expected output following KSES parsing.
	 * }
	 */
	public function data_wp_kses_video() {
		return array(
			// Set 0: Valid post object params in post context.
			array(
				'<video src="movie.mov" autoplay controls height=9 loop muted poster="still.gif" playsinline preload width=16 />',
				'post',
				'<video src="movie.mov" autoplay controls height="9" loop muted poster="still.gif" playsinline preload width="16" />',
			),
			// Set 1: Valid post object params in data context.
			array(
				'<video src="movie.mov" autoplay controls height=9 loop muted poster="still.gif" playsinline preload width=16 />',
				'data',
				'',
			),
			// Set 2: Disallowed urls in post context.
			array(
				'<video src="bad://w.org/movie.mov" poster="bad://w.org/movie.jpg" />',
				'post',
				'<video src="//w.org/movie.mov" poster="//w.org/movie.jpg" />',
			),
			// Set 3: Disallowed attributes in post context.
			array(
				'<video onload="alert(1);" src="https://videos.files.wordpress.com/DZEMDKxc/video-0f9c363010.mp4" />',
				'post',
				'<video src="https://videos.files.wordpress.com/DZEMDKxc/video-0f9c363010.mp4" />',
			),
		);
	}

	/**
	 * @dataProvider data_wp_filter_post_kses_abbr
	 * @ticket 20210
	 *
	 * @param string $content  Test string for kses.
	 * @param string $expected Expected result after passing through kses.
	 */
	public function test_wp_filter_post_kses_abbr( $content, $expected ) {
		global $allowedposttags;

		$this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * Data provider for data_wp_filter_post_kses_abbr.
	 *
	 * @return array[] Arguments {
	 *     @type string $content  Test string for kses.
	 *     @type string $expected Expected result after passing through kses.
	 * }
	 */
	public function data_wp_filter_post_kses_abbr() {
		$attributes = array(
			'class' => 'classname',
			'id'    => 'id',
			'style' => 'color: red;',
			'title' => 'title',
		);

		$data = array();

		foreach ( $attributes as $name => $value ) {
			$content  = "<abbr $name='$value'>WP</abbr>";
			$expected = "<abbr $name='" . trim( $value, ';' ) . "'>WP</abbr>";
			$data[]   = array( $content, $expected );
		}

		return $data;
	}

	public function test_feed_links() {
		global $allowedposttags;

		$content = <<<EOF
<a href="feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:javascript:feed:alert(1)">CLICK ME</a>
<a href="feed:feed:javascript:alert(1)">CLICK ME</a>
<a href="javascript:feed:alert(1)">CLICK ME</a>
<a href="javascript:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:feed:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:feed:feed:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:feed:feed:feed:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:javascript:feed:javascript:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:javascript:feed:javascript:feed:javascript:feed:javascript:feed:javascript:alert(1)">CLICK ME</a>
<a href="feed:feed:feed:http:alert(1)">CLICK ME</a>
EOF;

		$expected = <<<EOF
<a href="feed:alert(1)">CLICK ME</a>
<a href="feed:feed:alert(1)">CLICK ME</a>
<a href="feed:feed:alert(1)">CLICK ME</a>
<a href="feed:alert(1)">CLICK ME</a>
<a href="feed:alert(1)">CLICK ME</a>
<a href="">CLICK ME</a>
<a href="">CLICK ME</a>
<a href="">CLICK ME</a>
<a href="">CLICK ME</a>
<a href="">CLICK ME</a>
<a href="">CLICK ME</a>
EOF;

		$this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	public function test_wp_kses_bad_protocol() {
		$bad = array(
			'dummy:alert(1)',
			'javascript:alert(1)',
			'JaVaScRiPt:alert(1)',
			'javascript:alert(1);',
			'javascript&#58;alert(1);',
			'javascript&#0058;alert(1);',
			'javascript&#0000058alert(1);',
			'javascript&#x3A;alert(1);',
			'javascript&#X3A;alert(1);',
			'javascript&#X3a;alert(1);',
			'javascript&#x3a;alert(1);',
			'javascript&#x003a;alert(1);',
			'&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29',
			'jav	ascript:alert(1);',
			'jav&#x09;ascript:alert(1);',
			'jav&#x0A;ascript:alert(1);',
			'jav&#x0D;ascript:alert(1);',
			' &#14;  javascript:alert(1);',
			'javascript:javascript:alert(1);',
			'javascript&#58;javascript:alert(1);',
			'javascript&#0000058javascript:alert(1);',
			'javascript:javascript&#58;alert(1);',
			'javascript:javascript&#0000058alert(1);',
			'javascript&#0000058alert(1)//?:',
			'feed:javascript:alert(1)',
			'feed:javascript:feed:javascript:feed:javascript:alert(1)',
			'javascript&#58alert(1)',
			'javascript&#x3ax=1;alert(1)',
		);
		foreach ( $bad as $k => $x ) {
			$result = wp_kses_bad_protocol( wp_kses_normalize_entities( $x ), wp_allowed_protocols() );
			if ( ! empty( $result ) && 'alert(1);' !== $result && 'alert(1)' !== $result ) {
				switch ( $k ) {
					case 6:
						$this->assertSame( 'javascript&amp;#0000058alert(1);', $result );
						break;
					case 12:
						$this->assertSame( str_replace( '&', '&amp;', $x ), $result );
						break;
					case 22:
						$this->assertSame( 'javascript&amp;#0000058alert(1);', $result );
						break;
					case 23:
						$this->assertSame( 'javascript&amp;#0000058alert(1)//?:', $result );
						break;
					case 24:
						$this->assertSame( 'feed:alert(1)', $result );
						break;
					case 26:
						$this->assertSame( 'javascript&amp;#58alert(1)', $result );
						break;
					case 27:
						$this->assertSame( 'javascript&amp;#x3ax=1;alert(1)', $result );
						break;
					default:
						$this->fail( "wp_kses_bad_protocol failed on $k, $x. Result: $result" );
				}
			}
		}

		$bad_not_normalized = array(
			'dummy&colon;alert(1)',
			'javascript&colon;alert(1)',
			'javascript&CoLon;alert(1)',
			'javascript&COLON;alert(1);',
			'javascript&#58;alert(1);',
			'javascript&#0058;alert(1);',
			'javascript&#0000058alert(1);',
			'jav	ascript&COLON;alert(1);',
			'javascript&#58;javascript&colon;alert(1);',
			'javascript&#58;javascript&colon;alert(1);',
			'javascript&#0000058javascript&colon;alert(1);',
			'javascript&#58;javascript&#0000058alert(1);',
			'javascript&#58alert(1)',
		);
		foreach ( $bad_not_normalized as $k => $x ) {
			$result = wp_kses_bad_protocol( $x, wp_allowed_protocols() );
			if ( ! empty( $result ) && 'alert(1);' !== $result && 'alert(1)' !== $result ) {
				$this->fail( "wp_kses_bad_protocol failed on $k, $x. Result: $result" );
			}
		}

		$safe = array(
			'dummy:alert(1)',
			'HTTP://example.org/',
			'http://example.org/',
			'http&#58;//example.org/',
			'http&#x3A;//example.org/',
			'https://example.org',
			'http://example.org/wp-admin/post.php?post=2&amp;action=edit',
			'http://example.org/index.php?test=&#039;blah&#039;',
		);
		foreach ( $safe as $x ) {
			$result = wp_kses_bad_protocol( wp_kses_normalize_entities( $x ), array( 'http', 'https', 'dummy' ) );
			if ( $result !== $x && 'http://example.org/' !== $result ) {
				$this->fail( "wp_kses_bad_protocol incorrectly blocked $x" );
			}
		}
	}

	public function test_hackers_attacks() {
		$xss = simplexml_load_file( DIR_TESTDATA . '/formatting/xssAttacks.xml' );
		foreach ( $xss->attack as $attack ) {
			if ( in_array( (string) $attack->name, array( 'IMG Embedded commands 2', 'US-ASCII encoding', 'OBJECT w/Flash 2', 'Character Encoding Example' ), true ) ) {
				continue;
			}

			$code = (string) $attack->code;

			if ( 'See Below' === $code ) {
				continue;
			}

			if ( substr( $code, 0, 4 ) === 'perl' ) {
				$pos  = strpos( $code, '"' ) + 1;
				$code = substr( $code, $pos, strrpos( $code, '"' ) - $pos );
				$code = str_replace( '\0', "\0", $code );
			}

			$result = trim( wp_kses_data( $code ) );

			if ( in_array( $result, array( '', 'XSS', 'alert("XSS");', "alert('XSS');" ), true ) ) {
				continue;
			}

			switch ( $attack->name ) {
				case 'XSS Locator':
					$this->assertSame( '\';alert(String.fromCharCode(88,83,83))//\\\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//\\";alert(String.fromCharCode(88,83,83))//--&gt;"&gt;\'&gt;alert(String.fromCharCode(88,83,83))=&amp;{}', $result );
					break;
				case 'XSS Quick Test':
					$this->assertSame( '\'\';!--"=&amp;{()}', $result );
					break;
				case 'SCRIPT w/Alert()':
					$this->assertSame( "alert('XSS')", $result );
					break;
				case 'SCRIPT w/Char Code':
					$this->assertSame( 'alert(String.fromCharCode(88,83,83))', $result );
					break;
				case 'IMG STYLE w/expression':
					$this->assertSame( 'exp/*', $result );
					break;
				case 'List-style-image':
					$this->assertSame( 'li {list-style-image: url("javascript:alert(\'XSS\')");}XSS', $result );
					break;
				case 'STYLE':
					$this->assertSame( "alert('XSS');", $result );
					break;
				case 'STYLE w/background-image':
					$this->assertSame( '.XSS{background-image:url("javascript:alert(\'XSS\')");}<A></A>', $result );
					break;
				case 'STYLE w/background':
					$this->assertSame( 'BODY{background:url("javascript:alert(\'XSS\')")}', $result );
					break;
				case 'Remote Stylesheet 2':
					$this->assertSame( "@import'http://ha.ckers.org/xss.css';", $result );
					break;
				case 'Remote Stylesheet 3':
					$this->assertSame( '&lt;META HTTP-EQUIV=&quot;Link&quot; Content=&quot;; REL=stylesheet"&gt;', $result );
					break;
				case 'Remote Stylesheet 4':
					$this->assertSame( 'BODY{-moz-binding:url("http://ha.ckers.org/xssmoz.xml#xss")}', $result );
					break;
				case 'XML data island w/CDATA':
					$this->assertSame( '&lt;![CDATA[]]&gt;', $result );
					break;
				case 'XML data island w/comment':
					$this->assertSame( "<I><B>&lt;IMG SRC=&quot;javas<!-- -->cript:alert('XSS')\"&gt;</B></I>", $result );
					break;
				case 'XML HTML+TIME':
					$this->assertSame( '&lt;t:set attributeName=&quot;innerHTML&quot; to=&quot;XSSalert(\'XSS\')"&gt;', $result );
					break;
				case 'Commented-out Block':
					$this->assertSame( "<!--[if gte IE 4]&gt;-->\nalert('XSS');", $result );
					break;
				case 'Cookie Manipulation':
					$this->assertSame( '&lt;META HTTP-EQUIV=&quot;Set-Cookie&quot; Content=&quot;USERID=alert(\'XSS\')"&gt;', $result );
					break;
				case 'SSI':
					$this->assertSame( '&lt;!--#exec cmd=&quot;/bin/echo &#039;<!--#exec cmd="/bin/echo \'=http://ha.ckers.org/xss.js&gt;\'"-->', $result );
					break;
				case 'PHP':
					$this->assertSame( '&lt;? echo(&#039;alert("XSS")\'); ?&gt;', $result );
					break;
				case 'UTF-7 Encoding':
					$this->assertSame( '+ADw-SCRIPT+AD4-alert(\'XSS\');+ADw-/SCRIPT+AD4-', $result );
					break;
				case 'Escaping JavaScript escapes':
					$this->assertSame( '\";alert(\'XSS\');//', $result );
					break;
				case 'STYLE w/broken up JavaScript':
					$this->assertSame( '@im\port\'\ja\vasc\ript:alert("XSS")\';', $result );
					break;
				case 'Null Chars 2':
					$this->assertSame( '&amp;alert("XSS")', $result );
					break;
				case 'No Closing Script Tag':
					$this->assertSame( '&lt;SCRIPT SRC=http://ha.ckers.org/xss.js', $result );
					break;
				case 'Half-Open HTML/JavaScript':
					$this->assertSame( '&lt;IMG SRC=&quot;javascript:alert(&#039;XSS&#039;)&quot;', $result );
					break;
				case 'Double open angle brackets':
					$this->assertSame( '&lt;IFRAME SRC=http://ha.ckers.org/scriptlet.html &lt;', $result );
					break;
				case 'Extraneous Open Brackets':
					$this->assertSame( '&lt;alert("XSS");//&lt;', $result );
					break;
				case 'Malformed IMG Tags':
					$this->assertSame( 'alert("XSS")"&gt;', $result );
					break;
				case 'No Quotes/Semicolons':
					$this->assertSame( "a=/XSS/\nalert(a.source)", $result );
					break;
				case 'Evade Regex Filter 1':
					$this->assertSame( '" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Evade Regex Filter 4':
					$this->assertSame( '\'" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Evade Regex Filter 5':
					$this->assertSame( '` SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Filter Evasion 1':
					$this->assertSame( 'document.write("&lt;SCRI&quot;);PT SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Filter Evasion 2':
					$this->assertSame( '\'&gt;" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				default:
					$this->fail( 'KSES failed on ' . $attack->name . ': ' . $result );
			}
		}
	}

	public function wp_kses_allowed_html_filter( $html, $context ) {
		if ( 'post' === $context ) {
			return array( 'a' => array( 'href' => true ) );
		} else {
			return array( 'a' => array( 'href' => false ) );
		}
	}

	/**
	 * @ticket 20210
	 */
	public function test_wp_kses_allowed_html() {
		global $allowedposttags, $allowedtags, $allowedentitynames;

		$this->assertSame( $allowedposttags, wp_kses_allowed_html( 'post' ) );

		$tags = wp_kses_allowed_html( 'post' );

		$this->assertNotEmpty( $tags );

		foreach ( $tags as $tag ) {
			$this->assertTrue( $tag['class'] );
			$this->assertTrue( $tag['dir'] );
			$this->assertTrue( $tag['id'] );
			$this->assertTrue( $tag['lang'] );
			$this->assertTrue( $tag['style'] );
			$this->assertTrue( $tag['tabindex'] );
			$this->assertTrue( $tag['title'] );
			$this->assertTrue( $tag['xml:lang'] );
		}

		$this->assertSame( $allowedtags, wp_kses_allowed_html( 'data' ) );
		$this->assertSame( $allowedtags, wp_kses_allowed_html( '' ) );
		$this->assertSame( $allowedtags, wp_kses_allowed_html() );

		$tags = wp_kses_allowed_html( 'user_description' );
		$this->assertTrue( $tags['a']['rel'] );

		$tags = wp_kses_allowed_html();
		$this->assertArrayNotHasKey( 'rel', $tags['a'] );

		$this->assertSame( array(), wp_kses_allowed_html( 'strip' ) );

		$custom_tags = array(
			'a' => array(
				'href'   => true,
				'rel'    => true,
				'rev'    => true,
				'name'   => true,
				'target' => true,
			),
		);

		$this->assertSame( $custom_tags, wp_kses_allowed_html( $custom_tags ) );

		add_filter( 'wp_kses_allowed_html', array( $this, 'wp_kses_allowed_html_filter' ), 10, 2 );

		$this->assertSame( array( 'a' => array( 'href' => true ) ), wp_kses_allowed_html( 'post' ) );
		$this->assertSame( array( 'a' => array( 'href' => false ) ), wp_kses_allowed_html( 'data' ) );

		remove_filter( 'wp_kses_allowed_html', array( $this, 'wp_kses_allowed_html_filter' ) );
		$this->assertSame( $allowedposttags, wp_kses_allowed_html( 'post' ) );
		$this->assertSame( $allowedtags, wp_kses_allowed_html( 'data' ) );
	}

	/**
	 * Tests that the comment content context allows only the mention span beyond the defaults.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_allow_note_mention_span
	 */
	public function test_wp_kses_allowed_html_pre_comment_content_allows_only_the_mention_span() {
		global $allowedtags;

		$allowed = wp_kses_allowed_html( 'pre_comment_content' );

		$this->assertSame(
			array( 'class' => true ),
			$allowed['span'],
			'The mention span should be allowed in comment content.'
		);

		unset( $allowed['span'] );
		$this->assertSame(
			$allowedtags,
			$allowed,
			'Nothing beyond the mention span should be allowed on top of the default comment tags.'
		);
	}

	/**
	 * Tests that a note mention survives content sanitization of a `note` comment.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_allow_note_mention_span
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_markup_survives_note_content_sanitization() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );

		$content  = 'Hello <span class="wp-note-mention user-2">@admin</span>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'note', $content ) ) );

		remove_filter( 'pre_comment_content', 'wp_filter_kses' );

		$this->assertSame( $content, wp_unslash( $filtered['comment_content'] ) );
	}

	/**
	 * Tests that the mention markup also survives in regular comment content.
	 *
	 * The allowance is always on rather than scoped per comment type: the
	 * mention markup is inert, so uniform sanitization avoids stateful
	 * arming and disarming of kses filters around each note write.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_allow_note_mention_span
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_markup_survives_regular_comment_content_sanitization() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );
		$content  = 'Hello <span class="wp-note-mention user-2">@admin</span>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'comment', $content ) ) );

		$this->assertSame( $content, wp_unslash( $filtered['comment_content'] ) );
	}

	/**
	 * Tests that span classes are reduced to the two mention tokens.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_span_classes_are_reduced_to_the_mention_tokens() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );
		$content  = 'Hello <span class="wp-note-mention user-2 is-destructive components-button">@admin</span>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'note', $content ) ) );

		$this->assertSame(
			'Hello <span class="wp-note-mention user-2">@admin</span>!',
			wp_unslash( $filtered['comment_content'] ),
			'Class tokens beyond `wp-note-mention` and `user-N` should be stripped from spans.'
		);
	}

	/**
	 * Tests that class tokens are reduced on spans regardless of tag-name casing.
	 *
	 * kses preserves tag-name casing, so the class reduction must match `SPAN`
	 * case-insensitively rather than bail on a `<span` substring check.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_class_tokens_are_reduced_on_uppercase_span_tags() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );
		$content  = 'Hello <SPAN class="wp-note-mention user-2 is-destructive">@admin</SPAN>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'note', $content ) ) );

		$this->assertEqualHTML(
			'Hello <span class="wp-note-mention user-2">@admin</span>!',
			wp_unslash( $filtered['comment_content'] ),
			'<body>',
			'Class tokens should be reduced on spans regardless of tag-name casing.'
		);
	}

	/**
	 * Tests that the class attribute is removed when no mention tokens remain.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_class_attribute_removed_when_no_tokens_remain() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );
		$content  = 'Hello <span class="is-destructive user-0 user-x wp-note-mention-foo">there</span>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'comment', $content ) ) );

		// Markup-equivalence assertion: the HTML API's whitespace handling
		// when removing the final attribute is not part of its contract.
		$this->assertEqualHTML(
			'Hello <span>there</span>!',
			wp_unslash( $filtered['comment_content'] ),
			'<body>',
			'A span with no valid mention tokens should lose its class attribute entirely.'
		);
	}

	/**
	 * Tests that only the `class` attribute is allowed on mention spans.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_allow_note_mention_span
	 */
	public function test_note_mention_allows_only_class_on_mention_spans() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );
		$content  = 'Hello <span class="wp-note-mention user-2" data-user-id="2" onclick="alert(1)" style="color:red" id="mention">@admin</span>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'note', $content ) ) );

		$this->assertSame(
			'Hello <span class="wp-note-mention user-2">@admin</span>!',
			wp_unslash( $filtered['comment_content'] ),
			'Attributes beyond `class` should be stripped from spans.'
		);
	}

	/**
	 * Tests that `class` is still stripped from links in comment content.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_allow_note_mention_span
	 */
	public function test_class_is_still_stripped_from_links_in_comment_content() {
		add_filter( 'pre_comment_content', 'wp_filter_kses' );

		/*
		 * The href is external to the test site so that wp_rel_ugc() - which
		 * applies to notes like any other comment - deterministically appends
		 * `rel="nofollow ugc"`.
		 */
		$content  = 'Hello <a class="wp-note-mention user-2" href="https://example.com/author/admin/">@admin</a>!';
		$filtered = wp_filter_comment( wp_slash( $this->get_mention_commentdata( 'note', $content ) ) );

		$this->assertSame(
			'Hello <a href="https://example.com/author/admin/" rel="nofollow ugc">@admin</a>!',
			wp_unslash( $filtered['comment_content'] ),
			'The class allowance is scoped to spans; links keep the default sanitization.'
		);
	}

	/**
	 * Tests that the class reduction is skipped while the restrictive comment kses is inactive.
	 *
	 * Users with `unfiltered_html` are filtered through `wp_filter_post_kses`
	 * (or not at all), where arbitrary classes are permitted; the mention
	 * class reduction must not narrow what they can post.
	 *
	 * @ticket 65622
	 *
	 * @covers ::_wp_kses_sanitize_note_mention_classes
	 */
	public function test_note_mention_class_reduction_skipped_when_restrictive_kses_is_inactive() {
		// kses_init() hooks wp_filter_kses by default in the test
		// environment, so detach it to simulate the unfiltered_html setup.
		// The test framework restores filters after each test.
		remove_filter( 'pre_comment_content', 'wp_filter_kses' );

		$content = 'Hello <span class="components-button is-destructive">there</span>!';

		$this->assertSame(
			wp_slash( $content ),
			_wp_kses_sanitize_note_mention_classes( wp_slash( $content ) ),
			'Span classes should be left untouched when wp_filter_kses is not active.'
		);
	}

	/**
	 * Builds a complete commentdata array for wp_filter_comment().
	 *
	 * @param 'note'|'comment' $comment_type The comment type.
	 * @param string           $content      The comment content.
	 * @return array{
	 *     comment_content: string,
	 *     ...
	 * }
	 */
	private function get_mention_commentdata( string $comment_type, string $content ): array {
		return array(
			'comment_content'      => $content,
			'comment_type'         => $comment_type,
			'comment_author'       => 'admin',
			'comment_author_IP'    => '127.0.0.1',
			'comment_author_url'   => 'http://example.org',
			'comment_author_email' => 'admin@example.org',
			'comment_agent'        => '',
		);
	}

	public function test_hyphenated_tag() {
		$content     = '<hyphenated-tag attribute="value" otherattribute="value2">Alot of hyphens.</hyphenated-tag>';
		$custom_tags = array(
			'hyphenated-tag' => array(
				'attribute' => true,
			),
		);

		$expect_stripped_content = 'Alot of hyphens.';
		$expect_valid_content    = '<hyphenated-tag attribute="value">Alot of hyphens.</hyphenated-tag>';

		$this->assertEqualHTML( $expect_stripped_content, wp_kses_post( $content ) );
		$this->assertEqualHTML( $expect_valid_content, wp_kses( $content, $custom_tags ) );
	}

	/**
	 * Data provider.
	 */
	public static function data_normalize_entities(): array {
		return array(
			/**
			 * These examples are from the wp_kses_normalize_entities function description.
			 */
			'AT&T'                               => array( 'AT&T', 'AT&amp;T' ),
			'&#00058;'                           => array( '&#00058;', '&#058;' ),
			'&#XYZZY;'                           => array( '&#XYZZY;', '&amp;#XYZZY;' ),

			'Named ref &amp;'                    => array( '&spades;', '&spades;' ),
			'Named ref &AMP;'                    => array( '&spades;', '&spades;' ),
			'Named ref &spades;'                 => array( '&spades;', '&spades;' ),
			'Named ref &sup1;'                   => array( '&sup1;', '&sup1;' ),
			'Named ref &sup2;'                   => array( '&sup2;', '&sup2;' ),
			'Named ref &sup3;'                   => array( '&sup3;', '&sup3;' ),
			'Named ref &frac14;'                 => array( '&frac14;', '&frac14;' ),
			'Named ref &frac12;'                 => array( '&frac12;', '&frac12;' ),
			'Named ref &frac34;'                 => array( '&frac34;', '&frac34;' ),
			'Named ref &there4;'                 => array( '&there4;', '&there4;' ),

			'Decimal ref &#9; ( )'               => array( '&#9;', '&#009;' ),
			'Decimal ref &#34; (")'              => array( '&#34;', '&#034;' ),
			'Decimal ref &#0034; (")'            => array( '&#0034;', '&#034;' ),
			'Decimal ref &#38; (&)'              => array( '&#38;', '&#038;' ),
			"Decimal ref &#39; (')"              => array( '&#39;', '&#039;' ),
			'Decimal ref &#128525; (😍)'          => array( '&#128525;', '&#128525;' ),
			'Decimal ref &#00128525; (😍)'        => array( '&#00128525;', '&#128525;' ),

			'Hex ref &#x9; ( )'                  => array( '&#x9;', '&#x9;' ),
			'Hex ref &#x22; (")'                 => array( '&#x22;', '&#x22;' ),
			'Hex ref &#x0022; (")'               => array( '&#x0022;', '&#x22;' ),
			'Hex ref &#x26; (&)'                 => array( '&#x26;', '&#x26;' ),
			"Hex ref &#x27; (')"                 => array( '&#x27;', '&#x27;' ),
			'Hex ref &#x1f60d; (😍)'              => array( '&#x1f60d;', '&#x1f60d;' ),
			'Hex ref &#x001f60d; (😍)'            => array( '&#x001f60d;', '&#x1f60d;' ),

			'HEX REF &#X22; (")'                 => array( '&#X22;', '&#x22;' ),
			'HEX REF &#X26; (&)'                 => array( '&#X26;', '&#x26;' ),
			"HEX REF &#X27; (')"                 => array( '&#X27;', '&#x27;' ),
			'HEX REF &#X1F60D; (😍)'              => array( '&#X1F60D;', '&#x1F60D;' ),

			'Encoded named ref &amp;amp;'        => array( '&amp;amp;', '&amp;amp;' ),
			'Encoded named ref &#38;amp;'        => array( '&#38;amp;', '&#038;amp;' ),
			'Encoded named ref &#x26;amp;'       => array( '&#x26;amp;', '&#x26;amp;' ),
			'Encoded numeric ref &amp;#39;'      => array( '&amp;#39;', '&amp;#39;' ),
			'Encoded numeric ref &#38;#39;'      => array( '&#38;#39;', '&#038;#39;' ),
			'Encoded numeric ref &#x26;#39;'     => array( '&#x26;#39;', '&#x26;#39;' ),
			'Encoded hex ref &amp;#x27;'         => array( '&amp;#x27;', '&amp;#x27;' ),
			'Encoded hex ref &#38;#x27;'         => array( '&#38;#x27;', '&#038;#x27;' ),
			'Encoded hex ref &#x26;#x27;'        => array( '&#x26;#x27;', '&#x26;#x27;' ),

			/*
			 * The codepoint value here is outside of the valid unicode range whose
			 * maximum is 0x10FFFF or 1114111.
			 */
			'Invalid decimal unicode &#1114112;' => array( '&#1114112;', '&amp;#1114112;' ),
			'Invalid hex unicode &#x110000;'     => array( '&#x110000;', '&amp;#x110000;' ),
		);
	}

	/**
	 * @ticket 26290
	 * @ticket 63630
	 *
	 * @dataProvider data_normalize_entities
	 */
	public function test_wp_kses_normalize_entities( string $input, string $expected ) {
		$this->assertEqualHTML( $expected, wp_kses_normalize_entities( $input ) );
	}

	/**
	 * Test removal of invalid binary data for HTML.
	 *
	 * @ticket 28506
	 * @dataProvider data_ctrl_removal
	 */
	public function test_ctrl_removal( $content, $expected ) {
		global $allowedposttags;

		return $this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	public function data_ctrl_removal() {
		return array(
			array(
				"\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\X1C\x1D\x1E\x1F",
				'',
			),
			array(
				"\x00h\x01e\x02l\x03l\x04o\x05 \x06w\x07o\x08r\x0Bl\x0Cd\x0E.\x0F \x10W\x11O\x12R\x13D\x14P\x15R\x16E\x17S\x18S\x19 \x1AK\x1BS\X1CE\x1DS\x1E.\x1F/",
				'hello world. WORDPRESS KSES./',
			),
			array(
				"\x1F\x1E\x1D\x1C\x1B\x1A\x19\x18\x17\x16\x15\x14\x13\x12\x11\x10\x0F\x0E\x0C\x0B\x08\x07\x06\x05\x04\X03\x02\x01\x00",
				'',
			),
			array(
				"\x1Fh\x1Ee\x1Dl\x1Cl\x1Bo\x1A \x19w\x18o\x17r\x16l\x15d\x14.\x13 \x12W\x11O\x10R\x0FD\x0EP\x0CR\x0BE\x08S\x07S\x06 \x05K\x04S\X03E\x02S\x01.\x00/",
				'hello world. WORDPRESS KSES./',
			),
			array(
				"\t\r\n word \n\r\t",
				"\t\r\n word \n\r\t",
			),
		);
	}

	/**
	 * Test removal of '\0' strings.
	 *
	 * @ticket 28699
	 * @dataProvider data_slash_zero_removal
	 */
	public function test_slash_zero_removal( $content, $expected ) {
		global $allowedposttags;

		return $this->assertEqualHTML( $expected, wp_kses( $content, $allowedposttags ) );
	}

	public function data_slash_zero_removal() {
		return array(
			array(
				'This \\0 should be no big deal.',
				'This \\0 should be no big deal.',
			),
			array(
				'<div>This \\0 should be no big deal.</div>',
				'<div>This \\0 should be no big deal.</div>',
			),
			array(
				'<div align="\\0left">This should be no big deal.</div>',
				'<div align="\\0left">This should be no big deal.</div>',
			),
			array(
				'This <div style="float:\\0left"> is more of a concern.',
				'This <div style="float:left"> is more of a concern.',
			),
			array(
				'This <div style="float:\\0\\0left"> is more of a concern.',
				'This <div style="float:left"> is more of a concern.',
			),
			array(
				'This <div style="float:\\\\00left"> is more of a concern.',
				'This <div style="float:left"> is more of a concern.',
			),
			array(
				'This <div style="float:\\\\\\\\0000left"> is more of a concern.',
				'This <div style="float:left"> is more of a concern.',
			),
			array(
				'This <div style="float:\\0000left"> is more of a concern.',
				'This <div style="float:left"> is more of a concern.',
			),
			array(
				'<style type="text/css">div {background-image:\\0}</style>',
				'div {background-image:\\0}',
			),
		);
	}

	/**
	 * Test new function wp_kses_hair_parse().
	 *
	 * @dataProvider data_hair_parse
	 */
	public function test_hair_parse( $input, $output ) {
		return $this->assertSame( $output, wp_kses_hair_parse( $input ) );
	}

	public function data_hair_parse() {
		return array(
			array(
				'title="hello" href="#" id="my_id" ',
				array( 'title="hello" ', 'href="#" ', 'id="my_id" ' ),
			),
			array(
				'[shortcode attr="value"] href="http://www.google.com/"title="moo"disabled',
				array( '[shortcode attr="value"] ', 'href="http://www.google.com/"', 'title="moo"', 'disabled' ),
			),
			array(
				'',
				array(),
			),
			array(
				'a',
				array( 'a' ),
			),
			array(
				'title="hello"disabled href=# id=\'my_id\'',
				array( 'title="hello"', 'disabled ', 'href=# ', "id='my_id'" ),
			),
			array(
				'     ', // Calling function is expected to strip leading whitespace.
				false,
			),
			array(
				'abcd=abcd"abcd"',
				false,
			),
			array(
				"array[1]='z'z'z'z",
				false,
			),
			// Using a digit in attribute name should work.
			array(
				'href="https://example.com/[shortcode attr=\'value\']" data-op3-timer-seconds="0"',
				array( 'href="https://example.com/[shortcode attr=\'value\']" ', 'data-op3-timer-seconds="0"' ),
			),
			// Using an underscore in attribute name should work.
			array(
				'href="https://example.com/[shortcode attr=\'value\']" data-op_timer-seconds="0"',
				array( 'href="https://example.com/[shortcode attr=\'value\']" ', 'data-op_timer-seconds="0"' ),
			),
			// Using a period in attribute name should work.
			array(
				'href="https://example.com/[shortcode attr=\'value\']" data-op.timer-seconds="0"',
				array( 'href="https://example.com/[shortcode attr=\'value\']" ', 'data-op.timer-seconds="0"' ),
			),
			// Using a digit at the beginning of attribute name should return false.
			array(
				'href="https://example.com/[shortcode attr=\'value\']" 3data-op-timer-seconds="0"',
				false,
			),
		);
	}

	/**
	 * Test new function wp_kses_attr_parse().
	 *
	 * @dataProvider data_attr_parse
	 */
	public function test_attr_parse( $input, $output ) {
		return $this->assertSame( $output, wp_kses_attr_parse( $input ) );
	}

	public function data_attr_parse() {
		return array(
			array(
				'<a title="hello" href="#" id="my_id" >',
				array( '<a ', 'title="hello" ', 'href="#" ', 'id="my_id" ', '>' ),
			),
			array(
				'<a [shortcode attr="value"] href="http://www.google.com/"title="moo"disabled>',
				array( '<a ', '[shortcode attr="value"] ', 'href="http://www.google.com/"', 'title="moo"', 'disabled', '>' ),
			),
			array(
				'',
				false,
			),
			array(
				'a',
				false,
			),
			array(
				'<a>',
				array( '<a', '>' ),
			),
			array(
				'<a%%&&**>',
				false,
			),
			array(
				'<a title="hello"disabled href=# id=\'my_id\'>',
				array( '<a ', 'title="hello"', 'disabled ', 'href=# ', "id='my_id'", '>' ),
			),
			array(
				'<a     >',
				array( '<a     ', '>' ),
			),
			array(
				'<a abcd=abcd"abcd">',
				false,
			),
			array(
				"<a array[1]='z'z'z'z>",
				false,
			),
			array(
				'<img title="hello" src="#" id="my_id" />',
				array( '<img ', 'title="hello" ', 'src="#" ', 'id="my_id"', ' />' ),
			),
		);
	}

	/**
	 * Test new function wp_kses_one_attr().
	 *
	 * @dataProvider data_one_attr
	 */
	public function test_one_attr( $element, $input, $output ) {
		return $this->assertSame( $output, wp_kses_one_attr( $input, $element ) );
	}

	public function data_one_attr() {
		return array(
			array(
				'a',
				' title="hello" ',
				' title="hello" ',
			),
			array(
				'a',
				'title  =  "hello"',
				'title="hello"',
			),
			array(
				'a',
				"title='hello'",
				"title='hello'",
			),
			array(
				'a',
				'title=hello',
				'title="hello"',
			),
			array(
				'a',
				'href="javascript:alert(1)"',
				'href="alert(1)"',
			),
			array(
				'a',
				'style ="style "',
				'style="style"',
			),
			array(
				'a',
				'style="style "',
				'style="style"',
			),
			array(
				'a',
				'style ="style ="',
				'',
			),
			array(
				'img',
				'src="mypic.jpg"',
				'src="mypic.jpg"',
			),
			array(
				'img',
				'loading="lazy"',
				'loading="lazy"',
			),
			array(
				'img',
				'onerror=alert(1)',
				'',
			),
			array(
				'img',
				'title=>',
				'title="&gt;"',
			),
			array(
				'img',
				'title="&garbage";"',
				'title="&amp;garbage&quot;;"',
			),
		);
	}

	/**
	 * @ticket 34063
	 */
	public function test_bdo_tag_allowed() {
		global $allowedposttags;

		$content = '<p>This is <bdo dir="rtl">a BDO tag</bdo>. Weird, <bdo dir="ltr">right?</bdo></p>';

		$this->assertEqualHTML( $content, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * @ticket 54698
	 */
	public function test_ruby_tag_allowed() {
		global $allowedposttags;

		$content = '<ruby>✶<rp>: </rp><rt>Star</rt><rp>, </rp><rt lang="fr">Étoile</rt><rp>.</rp></ruby>';

		$this->assertEqualHTML( $content, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * @ticket 35079
	 */
	public function test_ol_reversed_attribute_allowed() {
		global $allowedposttags;

		$content = '<ol reversed="reversed"><li>Item 1</li><li>Item 2</li><li>Item 3</li></ol>';

		$this->assertEqualHTML( $content, wp_kses( $content, $allowedposttags ) );
	}

	/**
	 * @ticket 40680
	 */
	public function test_wp_kses_attr_no_attributes_allowed_with_empty_array() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEqualHTML( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => array() ), array() ) );
	}

	/**
	 * @ticket 40680
	 */
	public function test_wp_kses_attr_no_attributes_allowed_with_true() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEqualHTML( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => true ), array() ) );
	}

	/**
	 * @ticket 40680
	 */
	public function test_wp_kses_attr_single_attribute_is_allowed() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEqualHTML( "<{$element} title=\"foo\">", wp_kses_attr( $element, $attribute, array( 'foo' => array( 'title' => true ) ), array() ) );
	}

	/**
	 * @ticket 43312
	 */
	public function test_wp_kses_attr_no_attributes_allowed_with_false() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEqualHTML( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => false ), array() ) );
	}

	/**
	 * Testing the safecss_filter_attr() function.
	 *
	 * @ticket 37248
	 * @ticket 42729
	 * @ticket 48376
	 * @ticket 55966
	 * @ticket 56122
	 * @ticket 58551
	 * @ticket 60132
	 * @ticket 64414
	 * @ticket 65457
	 * @ticket 64974
	 * @ticket 65832
	 *
	 * @dataProvider data_safecss_filter_attr
	 *
	 * @param string $css      A string of CSS rules.
	 * @param string $expected Expected string of CSS rules.
	 */
	public function test_safecss_filter_attr( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * Data provider for test_safecss_filter_attr().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $css      A string of CSS rules.
	 *         @type string $expected Expected string of CSS rules.
	 *     }
	 * }
	 */
	public function data_safecss_filter_attr() {
		return array(
			// Empty input, empty output.
			array(
				'css'      => '',
				'expected' => '',
			),
			// An arbitrary attribute name isn't allowed.
			array(
				'css'      => 'foo:bar',
				'expected' => '',
			),
			// A single attribute name, with a single value.
			array(
				'css'      => 'margin-top: 2px',
				'expected' => 'margin-top: 2px',
			),
			// Backslash \ isn't supported.
			array(
				'css'      => 'margin-top: \2px',
				'expected' => '',
			),
			// Curly bracket } isn't supported.
			array(
				'css'      => 'margin-bottom: 2px}',
				'expected' => '',
			),
			// A single attribute name, with a single text value.
			array(
				'css'      => 'text-transform: uppercase',
				'expected' => 'text-transform: uppercase',
			),
			// Only lowercase attribute names are supported.
			array(
				'css'      => 'Text-transform: capitalize',
				'expected' => '',
			),
			// Uppercase attribute values goes through.
			array(
				'css'      => 'text-transform: None',
				'expected' => 'text-transform: None',
			),
			// A single attribute, with multiple values.
			array(
				'css'      => 'font: bold 15px arial, sans-serif',
				'expected' => 'font: bold 15px arial, sans-serif',
			),
			// Multiple attributes, with single values.
			array(
				'css'      => 'font-weight: bold;font-size: 15px',
				'expected' => 'font-weight: bold;font-size: 15px',
			),
			// Multiple attributes, separated by a space.
			array(
				'css'      => 'font-weight: bold; font-size: 15px',
				'expected' => 'font-weight: bold;font-size: 15px',
			),
			// Multiple attributes, with multiple values.
			array(
				'css'      => 'margin: 10px 20px;padding: 5px 10px',
				'expected' => 'margin: 10px 20px;padding: 5px 10px',
			),
			// Parenthesis ( is supported for some attributes.
			array(
				'css'      => 'background: green url("foo.jpg") no-repeat fixed center',
				'expected' => 'background: green url("foo.jpg") no-repeat fixed center',
			),
			// Additional background attributes introduced in 5.3.
			array(
				'css'      => 'background-size: cover;background-size: 200px 100px;background-attachment: local, scroll;background-blend-mode: hard-light',
				'expected' => 'background-size: cover;background-size: 200px 100px;background-attachment: local, scroll;background-blend-mode: hard-light',
			),
			// `border-radius` attribute introduced in 5.3.
			array(
				'css'      => 'border-radius: 10% 30% 50% 70%;border-radius: 30px',
				'expected' => 'border-radius: 10% 30% 50% 70%;border-radius: 30px',
			),
			// `flex` and related attributes introduced in 5.3.
			array(
				'css'      => 'flex: 0 1 auto;flex-basis: 75%;flex-direction: row-reverse;flex-flow: row-reverse nowrap;flex-grow: 2;flex-shrink: 1;flex-wrap: nowrap',
				'expected' => 'flex: 0 1 auto;flex-basis: 75%;flex-direction: row-reverse;flex-flow: row-reverse nowrap;flex-grow: 2;flex-shrink: 1;flex-wrap: nowrap',
			),
			// `grid` and related attributes introduced in 5.3.
			array(
				'css'      => 'grid-template-columns: 1fr 60px;grid-auto-columns: min-content;grid-column-start: span 2;grid-column-end: -1;grid-column-gap: 10%;grid-gap: 10px 20px',
				'expected' => 'grid-template-columns: 1fr 60px;grid-auto-columns: min-content;grid-column-start: span 2;grid-column-end: -1;grid-column-gap: 10%;grid-gap: 10px 20px',
			),
			array(
				'css'      => 'grid-template-rows: 40px 4em 40px;grid-auto-rows: min-content;grid-row-start: -1;grid-row-end: 3;grid-row-gap: 1em',
				'expected' => 'grid-template-rows: 40px 4em 40px;grid-auto-rows: min-content;grid-row-start: -1;grid-row-end: 3;grid-row-gap: 1em',
			),
			// `grid` does not yet support `\`.
			array(
				'css'      => 'grid-template: 1em / 20% 20px 1fr',
				'expected' => '',
			),
			// `flex` and `grid` alignments introduced in 5.3.
			array(
				'css'      => 'align-content: space-between;align-items: start;align-self: center;justify-items: center;justify-content: space-between;justify-self: end',
				'expected' => 'align-content: space-between;align-items: start;align-self: center;justify-items: center;justify-content: space-between;justify-self: end',
			),
			// `columns` and related attributes introduced in 5.3.
			array(
				'css'      => 'columns: 6rem auto;column-count: 4;column-fill: balance;column-gap: 9px;column-rule: thick inset blue;column-span: none;column-width: 120px',
				'expected' => 'columns: 6rem auto;column-count: 4;column-fill: balance;column-gap: 9px;column-rule: thick inset blue;column-span: none;column-width: 120px',
			),
			// Gradients introduced in 5.3.
			array(
				'css'      => 'background: linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
				'expected' => 'background: linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
			),
			array(
				'css'      => 'background: linear-gradient(135deg,rgba(6,147,227,1) ) (0%,rgb(155,81,224) 100%)',
				'expected' => '',
			),
			array(
				'css'      => 'background-image: linear-gradient(red,yellow);',
				'expected' => 'background-image: linear-gradient(red,yellow)',
			),
			array(
				'css'      => 'color: linear-gradient(red,yellow);',
				'expected' => '',
			),
			array(
				'css'      => 'background-image: linear-gradient(red,yellow); background: prop( red,yellow); width: 100px;',
				'expected' => 'background-image: linear-gradient(red,yellow);width: 100px',
			),
			array(
				'css'      => 'background: unknown-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
				'expected' => '',
			),
			array(
				'css'      => 'background: repeating-linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
				'expected' => 'background: repeating-linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
			),
			array(
				'css'      => 'width: 100px; height: 100px; background: linear-gradient(135deg,rgba(0,208,132,1) 0%,rgba(6,147,227,1) 100%);',
				'expected' => 'width: 100px;height: 100px;background: linear-gradient(135deg,rgba(0,208,132,1) 0%,rgba(6,147,227,1) 100%)',
			),
			array(
				'css'      => 'background: radial-gradient(#ff0, red, yellow, green, rgba(6,147,227,1), rgb(155,81,224) 90%);',
				'expected' => 'background: radial-gradient(#ff0, red, yellow, green, rgba(6,147,227,1), rgb(155,81,224) 90%)',
			),
			array(
				'css'      => 'background: radial-gradient(#ff0, red, yellow, green, rgba(6,147,227,1), rgb(155,81,224) 90%);',
				'expected' => 'background: radial-gradient(#ff0, red, yellow, green, rgba(6,147,227,1), rgb(155,81,224) 90%)',
			),
			array(
				'css'      => 'background: conic-gradient(at 0% 30%, red 10%, yellow 30%, #1e90ff 50%)',
				'expected' => 'background: conic-gradient(at 0% 30%, red 10%, yellow 30%, #1e90ff 50%)',
			),
			/*
			 * Background gradient support, introduced in 7.1 (ticket 64974).
			 * A gradient combined with a url() image is allowed, in either order.
			 */
			array(
				'css'      => "background-image: linear-gradient(135deg, rgb(255,0,0) 0%, rgb(0,0,255) 100%), url('https://example.com/image.jpg')",
				'expected' => "background-image: linear-gradient(135deg, rgb(255,0,0) 0%, rgb(0,0,255) 100%), url('https://example.com/image.jpg')",
			),
			array(
				'css'      => "background-image: url('https://example.com/image.jpg'), linear-gradient(135deg, rgb(255,0,0) 0%, rgb(0,0,255) 100%)",
				'expected' => "background-image: url('https://example.com/image.jpg'), linear-gradient(135deg, rgb(255,0,0) 0%, rgb(0,0,255) 100%)",
			),
			// A gradient using modern color functions is allowed.
			array(
				'css'      => 'background-image: linear-gradient(135deg, hsl(0,100%,50%) 0%, hsl(240,100%,50%) 100%)',
				'expected' => 'background-image: linear-gradient(135deg, hsl(0,100%,50%) 0%, hsl(240,100%,50%) 100%)',
			),
			// Nesting beyond one level inside a gradient is not supported (unchanged from before).
			array(
				'css'      => 'background-image: linear-gradient(red 0%, blue calc(50% + var(--x)))',
				'expected' => '',
			),
			/*
			 * As of 7.1 (ticket 64974) any single-level nested function is permitted inside a
			 * gradient, widening the previous rgb()/rgba()-only allowance. This includes the
			 * legacy expression() form, which is inert in all supported browsers and remains
			 * escaped when output as an attribute value.
			 */
			array(
				'css'      => 'background-image: linear-gradient(red, expression(alert))',
				'expected' => 'background-image: linear-gradient(red, expression(alert))',
			),
			// `object-position` introduced in 5.7.1.
			array(
				'css'      => 'object-position: right top',
				'expected' => 'object-position: right top',
			),
			// `object-fit` introduced in 6.1.
			array(
				'css'      => 'object-fit: cover',
				'expected' => 'object-fit: cover',
			),
			// `white-space` introduced in 6.9.0.
			array(
				'css'      => 'white-space: nowrap',
				'expected' => 'white-space: nowrap',
			),
			array(
				'css'      => 'white-space: pre',
				'expected' => 'white-space: pre',
			),
			array(
				'css'      => 'white-space: pre-wrap',
				'expected' => 'white-space: pre-wrap',
			),
			array(
				'css'      => 'white-space: pre-line',
				'expected' => 'white-space: pre-line',
			),
			// Expressions are not allowed.
			array(
				'css'      => 'height: expression( body.scrollTop + 50 + "px" )',
				'expected' => '',
			),
			// RGB color values are not allowed.
			array(
				'css'      => 'color: rgb( 100, 100, 100 )',
				'expected' => '',
			),
			// RGBA color values are not allowed.
			array(
				'css'      => 'color: rgb( 100, 100, 100, .4 )',
				'expected' => '',
			),
			// Allow min().
			array(
				'css'      => 'width: min(50%, 400px)',
				'expected' => 'width: min(50%, 400px)',
			),
			// Allow max().
			array(
				'css'      => 'width: max(50%, 40rem)',
				'expected' => 'width: max(50%, 40rem)',
			),
			// Allow minmax().
			array(
				'css'      => 'width: minmax(100px, 50%)',
				'expected' => 'width: minmax(100px, 50%)',
			),
			// Allow clamp().
			array(
				'css'      => 'width: clamp(100px, 50%, 100vw)',
				'expected' => 'width: clamp(100px, 50%, 100vw)',
			),
			// Allow two functions in the same CSS.
			array(
				'css'      => 'width: clamp(min(100px, 350px), 50%, 500px), 600px)',
				'expected' => 'width: clamp(min(100px, 350px), 50%, 500px), 600px)',
			),
			// Allow gradient() function.
			array(
				'css'      => 'background: linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(9,9,121,1) 35%, rgba(0,212,255,1) 100%)',
				'expected' => 'background: linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(9,9,121,1) 35%, rgba(0,212,255,1) 100%)',
			),
			// Combined CSS function names.
			array(
				'css'      => 'width: calcmax(100px + 50%)',
				'expected' => '',
			),
			// Allow calc().
			array(
				'css'      => 'width: calc(2em + 3px)',
				'expected' => 'width: calc(2em + 3px)',
			),
			// Allow calc() with nested brackets.
			array(
				'css'      => 'width: calc(3em + (10px * 2))',
				'expected' => 'width: calc(3em + (10px * 2))',
			),
			// Allow var().
			array(
				'css'      => 'padding: var(--wp-var1) var(--wp-var2)',
				'expected' => 'padding: var(--wp-var1) var(--wp-var2)',
			),
			// Allow var() with fallback (commas).
			array(
				'css'      => 'padding: var(--wp-var1, 10px)',
				'expected' => 'padding: var(--wp-var1, 10px)',
			),
			// Allow var() with fallback (percentage).
			array(
				'css'      => 'padding: var(--wp-var1, 50%)',
				'expected' => 'padding: var(--wp-var1, 50%)',
			),
			// Allow var() with fallback var().
			array(
				'css'      => 'background-color: var(--wp-var, var(--wp-var-fallback, pink))',
				'expected' => 'background-color: var(--wp-var, var(--wp-var-fallback, pink))',
			),
			// Allow var() with square brackets.
			array(
				'css'      => 'background-color: var(--wp-var, [pink])',
				'expected' => 'background-color: var(--wp-var, [pink])',
			),
			// Allow calc() with var().
			array(
				'css'      => 'margin-top: calc(var(--wp-var1) * 3 + 2em)',
				'expected' => 'margin-top: calc(var(--wp-var1) * 3 + 2em)',
			),
			// Malformed min, no closing `)`.
			array(
				'css'      => 'width: min(3em + 10px',
				'expected' => '',
			),
			// Malformed max, no closing `)`.
			array(
				'css'      => 'width: max(3em + 10px',
				'expected' => '',
			),
			// Malformed minmax, no closing `)`.
			array(
				'css'      => 'width: minmax(3em + 10px',
				'expected' => '',
			),
			// Malformed calc, no closing `)`.
			array(
				'css'      => 'width: calc(3em + 10px',
				'expected' => '',
			),
			// Malformed var, no closing `)`.
			array(
				'css'      => 'width: var(--wp-var1',
				'expected' => '',
			),
			// Malformed calc, mismatching brackets.
			array(
				'css'      => 'width: calc(3em + (10px * 2)',
				'expected' => '',
			),
			// Malformed var, mismatching brackets.
			array(
				'css'      => 'background-color: var(--wp-var, var(--wp-var-fallback, pink)',
				'expected' => '',
			),
			// Don't allow expressions outside of a calc().
			array(
				'css'      => 'width: (3em + (10px * 2))',
				'expected' => '',
			),
			// Gap introduced in 6.1.
			array(
				'css'      => 'gap: 10px;column-gap: 5px;row-gap: 20px',
				'expected' => 'gap: 10px;column-gap: 5px;row-gap: 20px',
			),
			// Margin and padding logical properties introduced in 6.1.
			array(
				'css'      => 'margin-block-start: 1px;margin-block-end: 2px;margin-inline-start: 3px;margin-inline-end: 4px;',
				'expected' => 'margin-block-start: 1px;margin-block-end: 2px;margin-inline-start: 3px;margin-inline-end: 4px',
			),
			array(
				'css'      => 'padding-block-start: 1px;padding-block-end: 2px;padding-inline-start: 3px;padding-inline-end: 4px;',
				'expected' => 'padding-block-start: 1px;padding-block-end: 2px;padding-inline-start: 3px;padding-inline-end: 4px',
			),
			// Assigning values to CSS variables introduced in 6.1.
			array(
				'css'      => '--wp--medium-width: 100px; --var_with_underscores: #cccccc;',
				'expected' => '--wp--medium-width: 100px;--var_with_underscores: #cccccc',
			),
			array(
				'css'      => '--miXeD-CAse: red; --with-numbers-3_56: red; --with-url-value: url("foo.jpg");',
				'expected' => '--miXeD-CAse: red;--with-numbers-3_56: red;--with-url-value: url("foo.jpg")',
			),
			array(
				'css'      => '--with-gradient: repeating-linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%);',
				'expected' => '--with-gradient: repeating-linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)',
			),
			array(
				'css'      => '--?><.%-not-allowed: red;',
				'expected' => '',
			),
			// Position properties introduced in 6.2.
			array(
				'css'      => 'position: sticky;top: 0;left: 0;right: 0;bottom: 0;z-index: 10;',
				'expected' => 'position: sticky;top: 0;left: 0;right: 0;bottom: 0;z-index: 10',
			),
			// `aspect-ratio` introduced in 6.2.
			array(
				'css'      => 'aspect-ratio: auto;',
				'expected' => 'aspect-ratio: auto',
			),
			array(
				'css'      => 'aspect-ratio: 0.5;',
				'expected' => 'aspect-ratio: 0.5',
			),
			array(
				'css'      => 'aspect-ratio: 1;',
				'expected' => 'aspect-ratio: 1',
			),
			array(
				'css'      => 'aspect-ratio: 16 / 9;',
				'expected' => 'aspect-ratio: 16 / 9',
			),
			array(
				'css'      => 'aspect-ratio: expression( 16 / 9 );',
				'expected' => '',
			),
			array(
				'css'      => 'aspect-ratio: calc( 16 / 9;',
				'expected' => '',
			),
			array(
				'css'      => 'aspect-ratio: calc( 16 / 9 );',
				'expected' => 'aspect-ratio: calc( 16 / 9 )',
			),
			array(
				'css'      => 'aspect-ratio: url( https://wordpress.org/wp-content/uploads/aspect-ratio.jpg );',
				'expected' => '',
			),
			// URL support for `filter` introduced in 6.3.
			array(
				'css'      => 'filter: url( my-file.svg#svg-blur );',
				'expected' => 'filter: url( my-file.svg#svg-blur )',
			),
			// Support for `repeat` function.
			array(
				'css'      => 'grid-template-columns: repeat(4, minmax(0, 1fr))',
				'expected' => 'grid-template-columns: repeat(4, minmax(0, 1fr))',
			),
			array(
				'css'      => 'grid-template-columns: repeat(auto-fill, minmax(min(12rem, 100%), 1fr))',
				'expected' => 'grid-template-columns: repeat(auto-fill, minmax(min(12rem, 100%), 1fr))',
			),
			// Malformed repeat, no closing `)`.
			array(
				'css'      => 'grid-template-columns: repeat(4, minmax(0, 1fr)',
				'expected' => '',
			),
			// Malformed repeat, contains unsupported function.
			array(
				'css'      => 'grid-template-columns: repeat(4, unsupported(0, 1fr)',
				'expected' => '',
			),
			// `writing-mode` introduced in 6.4.
			array(
				'css'      => 'writing-mode: vertical-rl',
				'expected' => 'writing-mode: vertical-rl',
			),
			// `background-repeat` introduced in 6.5.
			array(
				'css'      => 'background-repeat: no-repeat',
				'expected' => 'background-repeat: no-repeat',
			),
			// `opacity` introduced in 6.7.
			array(
				'css'      => 'opacity: 10',
				'expected' => 'opacity: 10',
			),
			// `display` introduced in 7.0.0.
			array(
				'css'      => 'display: none',
				'expected' => 'display: none',
			),
			array(
				'css'      => 'display: block',
				'expected' => 'display: block',
			),
			array(
				'css'      => 'display: inline',
				'expected' => 'display: inline',
			),
			array(
				'css'      => 'display: inline-block',
				'expected' => 'display: inline-block',
			),
			array(
				'css'      => 'display: inline-flex',
				'expected' => 'display: inline-flex',
			),
			array(
				'css'      => 'display: inline-grid',
				'expected' => 'display: inline-grid',
			),
			array(
				'css'      => 'display: table',
				'expected' => 'display: table',
			),
			array(
				'css'      => 'display: flex',
				'expected' => 'display: flex',
			),
			array(
				'css'      => 'display: grid',
				'expected' => 'display: grid',
			),
			// SVG presentation attributes introduced in 7.1.0.
			array(
				'css'      => 'fill: none',
				'expected' => 'fill: none',
			),
			array(
				'css'      => 'fill-rule: evenodd',
				'expected' => 'fill-rule: evenodd',
			),
			array(
				'css'      => 'stroke: red',
				'expected' => 'stroke: red',
			),
			array(
				'css'      => 'stroke-width: 2',
				'expected' => 'stroke-width: 2',
			),
			array(
				'css'      => 'stroke-linecap: round',
				'expected' => 'stroke-linecap: round',
			),
			array(
				'css'      => 'paint-order: stroke',
				'expected' => 'paint-order: stroke',
			),
			array(
				'css'      => 'vector-effect: non-scaling-stroke',
				'expected' => 'vector-effect: non-scaling-stroke',
			),
			array(
				'css'      => 'clip-rule: evenodd',
				'expected' => 'clip-rule: evenodd',
			),
			array(
				'css'      => 'text-anchor: middle',
				'expected' => 'text-anchor: middle',
			),
			// SVG transform functions (ticket #65832).
			array(
				'css'      => 'transform: rotate(45deg)',
				'expected' => 'transform: rotate(45deg)',
			),
			array(
				'css'      => 'transform: translate(10px, 20px)',
				'expected' => 'transform: translate(10px, 20px)',
			),
			array(
				'css'      => 'transform: scale(1.5)',
				'expected' => 'transform: scale(1.5)',
			),
			array(
				'css'      => 'transform: matrix(1, 0, 0, 1, 10, 20)',
				'expected' => 'transform: matrix(1, 0, 0, 1, 10, 20)',
			),
			array(
				'css'      => 'transform: skewX(30deg)',
				'expected' => 'transform: skewX(30deg)',
			),
			array(
				'css'      => 'transform: skewY(30deg)',
				'expected' => 'transform: skewY(30deg)',
			),
			// Multiple transform functions chained.
			array(
				'css'      => 'transform: rotate(45deg) scale(1.5)',
				'expected' => 'transform: rotate(45deg) scale(1.5)',
			),
			// transform: none is unchanged (regression control).
			array(
				'css'      => 'transform: none',
				'expected' => 'transform: none',
			),
			// SVG clip-path shape functions (ticket #65832).
			array(
				'css'      => 'clip-path: inset(10px)',
				'expected' => 'clip-path: inset(10px)',
			),
			array(
				'css'      => 'clip-path: circle(50%)',
				'expected' => 'clip-path: circle(50%)',
			),
			array(
				'css'      => 'clip-path: ellipse(25% 40% at 50% 50%)',
				'expected' => 'clip-path: ellipse(25% 40% at 50% 50%)',
			),
			array(
				'css'      => 'clip-path: polygon(50% 0%, 100% 100%, 0% 100%)',
				'expected' => 'clip-path: polygon(50% 0%, 100% 100%, 0% 100%)',
			),
			array(
				'css'      => "clip-path: path('M 0 0 L 100 0 L 50 100 Z')",
				'expected' => "clip-path: path('M 0 0 L 100 0 L 50 100 Z')",
			),
			array(
				'css'      => 'clip-path: rect(0 100% 100% 0)',
				'expected' => 'clip-path: rect(0 100% 100% 0)',
			),
			array(
				'css'      => 'clip-path: xywh(0 0 100% 100% round 10px)',
				'expected' => 'clip-path: xywh(0 0 100% 100% round 10px)',
			),
			array(
				'css'      => 'clip-path: shape(from 0 0, line to 100% 0, line to 50% 100%, close)',
				'expected' => 'clip-path: shape(from 0 0, line to 100% 0, line to 50% 100%, close)',
			),
			// Nested functions within a basic shape are allowed.
			array(
				'css'      => 'clip-path: inset(calc(10px + 1em) round var(--radius))',
				'expected' => 'clip-path: inset(calc(10px + 1em) round var(--radius))',
			),
			// SVG url() references for allowlisted properties (ticket #65832).
			array(
				'css'      => 'clip-path: url(#myClipper)',
				'expected' => 'clip-path: url(#myClipper)',
			),
			array(
				'css'      => 'fill: url(#gradient1)',
				'expected' => 'fill: url(#gradient1)',
			),
			array(
				'css'      => 'mask: url(#myMask)',
				'expected' => 'mask: url(#myMask)',
			),
			array(
				'css'      => 'marker-start: url(#arrowStart)',
				'expected' => 'marker-start: url(#arrowStart)',
			),
			array(
				'css'      => 'marker-end: url(#arrowEnd)',
				'expected' => 'marker-end: url(#arrowEnd)',
			),
			array(
				'css'      => 'marker-mid: url(#arrowMid)',
				'expected' => 'marker-mid: url(#arrowMid)',
			),
			array(
				'css'      => 'marker: url(#marker1)',
				'expected' => 'marker: url(#marker1)',
			),
			array(
				'css'      => 'stroke: url(#strokeGradient)',
				'expected' => 'stroke: url(#strokeGradient)',
			),
			// Disallow javascript: URLs in SVG url() references (security regression).
			array(
				'css'      => 'fill: url(javascript:alert(1))',
				'expected' => '',
			),
			array(
				'css'      => 'clip-path: url(javascript:alert(1))',
				'expected' => '',
			),
		);
	}

	/**
	 * Data attributes are globally accepted.
	 *
	 * @ticket 33121
	 */
	public function test_wp_kses_attr_data_attribute_is_allowed() {
		$test     = '<div data-foo="foo" data-bar="bar" datainvalid="gone" data-two-hyphens="remains">Pens and pencils</div>';
		$expected = '<div data-foo="foo" data-bar="bar" data-two-hyphens="remains">Pens and pencils</div>';

		$this->assertEqualHTML( $expected, wp_kses_post( $test ) );
	}

	/**
	 * Data attributes with leading, trailing, and double "-" are globally accepted.
	 *
	 * @ticket 61052
	 */
	public function test_wp_kses_attr_data_attribute_hypens_allowed() {
		$test     = '<div data--leading="remains" data-trailing-="remains" data-middle--double="remains">Pens and pencils</div>';
		$expected = '<div data--leading="remains" data-trailing-="remains" data-middle--double="remains">Pens and pencils</div>';

		$this->assertEqualHTML( $expected, wp_kses_post( $test ) );
	}

	/**
	 * Ensure wildcard attributes block unprefixed wildcard uses.
	 *
	 * @ticket 33121
	 */
	public function test_wildcard_requires_hyphen_after_prefix() {
		$allowed_html = array(
			'div' => array(
				'data-*' => true,
				'on-*'   => true,
			),
		);

		$content  = '<div datamelformed-prefix="gone" data="gone" data-="gone" onclick="alert(1)">Malformed attributes</div>';
		$expected = '<div>Malformed attributes</div>';

		$actual = wp_kses( $content, $allowed_html );

		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * Ensure wildcard allows two hyphen.
	 *
	 * @ticket 33121
	 */
	public function test_wildcard_allows_two_hyphens() {
		$allowed_html = array(
			'div' => array(
				'data-*' => true,
			),
		);

		$content  = '<div data-wp-id="pens-and-pencils">Well formed attribute</div>';
		$expected = '<div data-wp-id="pens-and-pencils">Well formed attribute</div>';

		$actual = wp_kses( $content, $allowed_html );

		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * Ensure wildcard attributes only support valid prefixes.
	 *
	 * @dataProvider data_wildcard_attribute_prefixes
	 *
	 * @ticket 33121
	 */
	public function test_wildcard_attribute_prefixes( $wildcard_attribute, $expected ) {
		$allowed_html = array(
			'div' => array(
				$wildcard_attribute => true,
			),
		);

		$name  = str_replace( '*', strtolower( __FUNCTION__ ), $wildcard_attribute );
		$value = __FUNCTION__;
		$whole = "{$name}=\"{$value}\"";

		$actual = wp_kses_attr_check( $name, $value, $whole, 'n', 'div', $allowed_html );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array Array of arguments for wildcard testing
	 *               [0] The prefix being tested.
	 *               [1] The outcome of `wp_kses_attr_check` for the prefix.
	 */
	public function data_wildcard_attribute_prefixes() {
		return array(
			// Ends correctly.
			array( 'data-*', true ),

			// Does not end with trialing `-`.
			array( 'data*', false ),

			// Multiple wildcards.
			array( 'd*ta-*', false ),
			array( 'data**', false ),
		);
	}

	/**
	 * Tests that style attribute values are decoded before CSS filtering.
	 *
	 * @ticket 65270
	 *
	 * @dataProvider data_wp_kses_style_attr_decodes_entities_before_css_filtering
	 *
	 * @param string $content  A string of HTML to test.
	 * @param string $expected Expected result after passing through KSES.
	 */
	public function test_wp_kses_style_attr_decodes_entities_before_css_filtering( $content, $expected ) {
		$allowed_html = array(
			'div' => array(
				'style' => true,
			),
		);

		$this->assertEqualHTML( $expected, wp_kses( $content, $allowed_html ) );
	}

	/**
	 * Data provider for test_wp_kses_style_attr_decodes_entities_before_css_filtering().
	 *
	 * @return array[]
	 */
	public function data_wp_kses_style_attr_decodes_entities_before_css_filtering() {
		return array(
			'background image URL with single quotes' => array(
				'<div style="background-image: url(\'https://localhost/image.jpg\');"></div>',
				'<div style="background-image: url(&#039;https://localhost/image.jpg&#039;)"></div>',
			),
			'background image URL with entity-encoded double quotes' => array(
				'<div style="background-image: url(&quot;https://localhost/image.jpg&quot;);"></div>',
				'<div style="background-image: url(&quot;https://localhost/image.jpg&quot;)"></div>',
			),
			'background image URL with query string ampersand' => array(
				'<div style="background-image: url(https://localhost/image.jpg?a=1&b=2);"></div>',
				'<div style="background-image: url(https://localhost/image.jpg?a=1&amp;b=2)"></div>',
			),
			'background image URL followed by another declaration' => array(
				'<div style="background-image:url(\'https://localhost/image.jpg\');background-size:cover;"></div>',
				'<div style="background-image:url(&#039;https://localhost/image.jpg&#039;);background-size:cover"></div>',
			),
		);
	}

	/**
	 * Test URL sanitization in the style tag.
	 *
	 * @dataProvider data_kses_style_attr_with_url
	 *
	 * @ticket 45067
	 * @ticket 46197
	 * @ticket 46498
	 *
	 * @param $input string The style attribute saved in the editor.
	 * @param $expected string The sanitized style attribute.
	 */
	public function test_kses_style_attr_with_url( $input, $expected ) {
		$actual = safecss_filter_attr( $input );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider testing style attribute sanitization.
	 *
	 * @return array Nested array of input, expected pairs.
	 */
	public function data_kses_style_attr_with_url() {
		return array(
			/*
			 * Valid use cases.
			 */

			// Double quotes.
			array(
				'background-image: url( "http://example.com/valid.gif" );',
				'background-image: url( "http://example.com/valid.gif" )',
			),

			// Single quotes.
			array(
				"background-image: url( 'http://example.com/valid.gif' );",
				"background-image: url( 'http://example.com/valid.gif' )",
			),

			// No quotes.
			array(
				'background-image: url( http://example.com/valid.gif );',
				'background-image: url( http://example.com/valid.gif )',
			),

			// Single quotes, extra spaces.
			array(
				"background-image: url( '  http://example.com/valid.gif ' );",
				"background-image: url( '  http://example.com/valid.gif ' )",
			),

			// Line breaks, single quotes.
			array(
				"background-image: url(\n'http://example.com/valid.gif' );",
				"background-image: url('http://example.com/valid.gif' )",
			),

			// Tabs not spaces, single quotes.
			array(
				"background-image: url(\t'http://example.com/valid.gif'\t\t);",
				"background-image: url('http://example.com/valid.gif')",
			),

			// Single quotes, absolute path.
			array(
				"background: url('/valid.gif');",
				"background: url('/valid.gif')",
			),

			// Single quotes, relative path.
			array(
				"background: url('../wp-content/uploads/2018/10/valid.gif');",
				"background: url('../wp-content/uploads/2018/10/valid.gif')",
			),

			// Error check: valid property not containing a URL.
			array(
				'background: red',
				'background: red',
			),

			/*
			 * Invalid use cases.
			 */

			// Attribute doesn't support URL properties.
			array(
				'color: url( "http://example.com/invalid.gif" );',
				'',
			),

			// Mismatched quotes.
			array(
				'background-image: url( "http://example.com/valid.gif\' );',
				'',
			),

			// Bad protocol, double quotes.
			array(
				'background-image: url( "bad://example.com/invalid.gif" );',
				'',
			),

			// Bad protocol, single quotes.
			array(
				"background-image: url( 'bad://example.com/invalid.gif' );",
				'',
			),

			// Bad protocol, single quotes.
			array(
				"background-image: url( 'bad://example.com/invalid.gif' );",
				'',
			),

			// Bad protocol, single quotes, strange spacing.
			array(
				"background-image: url( '  \tbad://example.com/invalid.gif ' );",
				'',
			),

			// Bad protocol, no quotes.
			array(
				'background-image: url( bad://example.com/invalid.gif );',
				'',
			),

			// No URL inside url().
			array(
				'background-image: url();',
				'',
			),

			// Malformed, no closing `)`.
			array(
				'background-image: url( "http://example.com" ;',
				'',
			),

			// Malformed, no closing `"`.
			array(
				'background-image: url( "http://example.com );',
				'',
			),
		);
	}

	/**
	 * Testing the safecss_filter_attr() function with the safecss_filter_attr_allow_css filter.
	 *
	 * @ticket 37134
	 *
	 * @dataProvider data_safecss_filter_attr_filtered
	 *
	 * @param string $css      A string of CSS rules.
	 * @param string $expected Expected string of CSS rules.
	 */
	public function test_safecss_filter_attr_filtered( $css, $expected ) {
		add_filter( 'safecss_filter_attr_allow_css', '__return_true' );
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
		remove_filter( 'safecss_filter_attr_allow_css', '__return_true' );
	}

	/**
	 * Data provider for test_safecss_filter_attr_filtered().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $css      A string of CSS rules.
	 *         @type string $expected Expected string of CSS rules.
	 *     }
	 * }
	 */
	public function data_safecss_filter_attr_filtered() {
		return array(

			// A single attribute name, with a single value.
			array(
				'css'      => 'margin-top: 2px',
				'expected' => 'margin-top: 2px',
			),
			// Backslash \ can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'margin-top: \2px',
				'expected' => 'margin-top: \2px',
			),
			// Curly bracket } can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'margin-bottom: 2px}',
				'expected' => 'margin-bottom: 2px}',
			),
			// Parenthesis ) can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'margin-bottom: 2px)',
				'expected' => 'margin-bottom: 2px)',
			),
			// Ampersand & can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'margin-bottom: 2px&',
				'expected' => 'margin-bottom: 2px&',
			),
			// Expressions can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'height: expression( body.scrollTop + 50 + "px" )',
				'expected' => 'height: expression( body.scrollTop + 50 + "px" )',
			),
			// RGB color values can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'color: rgb( 100, 100, 100 )',
				'expected' => 'color: rgb( 100, 100, 100 )',
			),
			// RGBA color values can be allowed with the 'safecss_filter_attr_allow_css' filter.
			array(
				'css'      => 'color: rgb( 100, 100, 100, .4 )',
				'expected' => 'color: rgb( 100, 100, 100, .4 )',
			),
		);
	}

	/**
	 * Test filtering a standard img tag.
	 *
	 * @ticket 50731
	 */
	public function test_wp_kses_img_tag_standard_attributes() {
		$html = array(
			'<img',
			'loading="lazy"',
			'src="https://example.com/img.jpg"',
			'width="1000"',
			'height="1000"',
			'alt=""',
			'class="wp-image-1000"',
			'/>',
		);

		$html = implode( ' ', $html );

		$this->assertEqualHTML( $html, wp_kses_post( $html ) );
	}

	/**
	 * Test filtering a standard main tag.
	 *
	 * @ticket 53156
	 */
	public function test_wp_kses_main_tag_standard_attributes() {
		$test = array(
			'<main',
			'class="wp-group-block"',
			'style="padding:10px"',
			'/>',
		);

		$html = implode( ' ', $test );

		$this->assertEqualHTML( $html, wp_kses_post( $html ) );
	}

	/**
	 * Tests that the autofocus attribute is allowed on dialog elements and removed from other focusable elements.
	 *
	 * @ticket 65491
	 */
	public function test_wp_kses_dialog_autofocus_attribute() {
		$html     = '<dialog open autofocus>Content</dialog><button type="button" autofocus>Button</button><textarea autofocus>Some content</textarea><div tabindex="0" autofocus>Some content</div>';
		$expected = '<dialog open autofocus>Content</dialog><button type="button">Button</button><textarea>Some content</textarea><div tabindex="0">Some content</div>';

		$this->assertEqualHTML( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Test that Invoker Commands API attributes are preserved on buttons in post content.
	 *
	 * @ticket 64576
	 */
	public function test_wp_kses_button_invoker_command_attributes() {
		$html = '<button type="button" commandfor="my-popover" command="toggle-popover">Toggle</button><div id="my-popover" popover>Content</div>';

		$this->assertEqualHTML( $html, wp_kses_post( $html ) );
	}

	/**
	 * Test that object tags are allowed under limited circumstances.
	 *
	 * @ticket 54261
	 *
	 * @dataProvider data_wp_kses_object_tag_allowed
	 *
	 * @param string $html     A string of HTML to test.
	 * @param string $expected The expected result from KSES.
	 */
	public function test_wp_kses_object_tag_allowed( $html, $expected ) {
		$this->assertEqualHTML( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Data provider for test_wp_kses_object_tag_allowed().
	 */
	public function data_wp_kses_object_tag_allowed() {
		return array(
			'valid value for type'                    => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'invalid value for type'                  => array(
				'<object type="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.exe" />',
				'',
			),
			'multiple type attributes, last invalid'  => array(
				'<object type="application/pdf" type="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'multiple type attributes, first uppercase, last invalid' => array(
				'<object TYPE="application/pdf" type="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object TYPE="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'multiple type attributes, last upper case and invalid' => array(
				'<object type="application/pdf" TYPE="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'multiple type attributes, first invalid' => array(
				'<object type="application/exe" type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'multiple type attributes, first upper case and invalid' => array(
				'<object TYPE="application/exe" type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'multiple type attributes, first invalid, last uppercase' => array(
				'<object type="application/exe" TYPE="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'multiple object tags, last invalid'      => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" /><object type="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.exe" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'multiple object tags, first invalid'     => array(
				'<object type="application/exe" data="https://' . WP_TESTS_DOMAIN . '/foo.exe" /><object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'type attribute with partially incorrect value' => array(
				'<object type="application/pdfa" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'type attribute with empty value'         => array(
				'<object type="" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'type attribute with no value'            => array(
				'<object type data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'no type attribute'                       => array(
				'<object data="https://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'different protocol in url'               => array(
				'<object type="application/pdf" data="http://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'<object type="application/pdf" data="http://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
			),
			'query string on url'                     => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf?lol=.pdf" />',
				'',
			),
			'fragment on url'                         => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.pdf#lol.pdf" />',
				'',
			),
			'wrong extension'                         => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/foo.php" />',
				'',
			),
			'protocol-relative url'                   => array(
				'<object type="application/pdf" data="//' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'unsupported protocol'                    => array(
				'<object type="application/pdf" data="ftp://' . WP_TESTS_DOMAIN . '/foo.pdf" />',
				'',
			),
			'relative url'                            => array(
				'<object type="application/pdf" data="/cat/foo.pdf" />',
				'',
			),
			'url with port number-like path'          => array(
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/cat:8888/foo.pdf" />',
				'<object type="application/pdf" data="https://' . WP_TESTS_DOMAIN . '/cat:8888/foo.pdf" />',
			),
		);
	}

	/**
	 * Test that object tags are allowed when there is a port number in the URL.
	 *
	 * @ticket 54261
	 *
	 * @dataProvider data_wp_kses_object_data_url_with_port_number_allowed
	 *
	 * @param string $html     A string of HTML to test.
	 * @param string $expected The expected result from KSES.
	 */
	public function test_wp_kses_object_data_url_with_port_number_allowed( $html, $expected ) {
		add_filter( 'upload_dir', array( $this, 'wp_kses_upload_dir_filter' ), 10, 2 );
		$this->assertEqualHTML( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Data provider for test_wp_kses_object_data_url_with_port_number_allowed().
	 */
	public function data_wp_kses_object_data_url_with_port_number_allowed() {
		return array(
			'url with port number'                   => array(
				'<object type="application/pdf" data="https://example.org:8888/cat/foo.pdf" />',
				'<object type="application/pdf" data="https://example.org:8888/cat/foo.pdf" />',
			),
			'url with port number and http protocol' => array(
				'<object type="application/pdf" data="http://example.org:8888/cat/foo.pdf" />',
				'<object type="application/pdf" data="http://example.org:8888/cat/foo.pdf" />',
			),
			'url with wrong port number'             => array(
				'<object type="application/pdf" data="http://example.org:3333/cat/foo.pdf" />',
				'',
			),
			'url without port number'                => array(
				'<object type="application/pdf" data="http://example.org/cat/foo.pdf" />',
				'',
			),
		);
	}

	/**
	 * Filter upload directory for tests using port number.
	 *
	 * @param  array $param See wp_upload_dir()
	 * @return array        $param with a modified `url`.
	 */
	public function wp_kses_upload_dir_filter( $param ) {
		// Take care to replace the entire domain, including cases where it already has a port number.
		$parsed         = parse_url( $param['url'] );
		$replace_domain = $parsed['host'];
		if ( isset( $parsed['port'] ) ) {
			$replace_domain .= ':' . $parsed['port'];
		}

		$url_with_port_number = is_string( $param['url'] ) ? str_replace( $replace_domain, 'example.org:8888', $param['url'] ) : $param['url'];
		$param['url']         = $url_with_port_number;
		return $param;
	}

	/**
	 * Test that object tags will continue to function if they've been added using the
	 * 'wp_kses_allowed_html' filter.
	 *
	 * @ticket 54261
	 */
	public function test_wp_kses_object_added_in_html_filter() {
		$html = <<<HTML
<object type="application/pdf" data="https://wordpress.org/foo.pdf" />
<object type="application/x-shockwave-flash" data="https://wordpress.org/foo.swf">
	<param name="foo" value="bar" />
</object>
HTML;

		add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_object_added_in_html_filter' ), 10, 2 );

		$filtered_html = wp_kses_post( $html );

		remove_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_object_added_in_html_filter' ) );

		$this->assertEqualHTML( $html, $filtered_html );
	}

	public function filter_wp_kses_object_added_in_html_filter( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['object'] = array(
				'type' => true,
				'data' => true,
			);

			$tags['param'] = array(
				'name'  => true,
				'value' => true,
			);
		}

		return $tags;
	}

	/**
	 * Ensures that `wp_kses()` preserves various kinds of HTML comments, both valid and invalid.
	 *
	 * @ticket 61009
	 *
	 * @dataProvider data_html_containing_various_kinds_of_html_comments
	 *
	 * @param string $html_comment    HTML containing a comment; must not be a valid comment
	 *                                but must be syntax which a browser interprets as a comment.
	 * @param string $expected_output How `wp_kses()` ought to transform the comment.
	 */
	public function test_wp_kses_preserves_html_comments( $html_comment, $expected_output ) {
		$this->assertEqualHTML(
			$expected_output,
			wp_kses( $html_comment, array() ),
			'<body>',
			'Failed to properly preserve HTML comment.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_containing_various_kinds_of_html_comments() {
		return array(
			'Normative HTML comment'            => array( 'before<!-- this is a comment -->after', 'before<!-- this is a comment -->after' ),
			'Closing tag with invalid tag name' => array( 'before<//not a tag>after', 'before<//not a tag>after' ),
			'Incorrectly opened comment (Markup declaration)' => array( 'before<!also not a tag>after', 'before<!also not a tag>after' ),
		);
	}

	/**
	 * Test that attributes with a list of allowed values are filtered correctly.
	 *
	 * @ticket 54261
	 *
	 * @dataProvider data_wp_kses_allowed_values_list
	 *
	 * @param string $content      A string of HTML to test.
	 * @param string $expected     The expected result from KSES.
	 * @param array  $allowed_html The allowed HTML to pass to KSES.
	 */
	public function test_wp_kses_allowed_values_list( $content, $expected, $allowed_html ) {
		$this->assertEqualHTML( $expected, wp_kses( $content, $allowed_html ) );
	}

	/**
	 * Data provider for test_wp_kses_allowed_values_list().
	 */
	public function data_wp_kses_allowed_values_list() {
		$data = array(
			'valid dir attribute value'             => array(
				'<p dir="ltr">foo</p>',
				'<p dir="ltr">foo</p>',
			),
			'valid dir attribute value, upper case' => array(
				'<p DIR="RTL">foo</p>',
				'<p DIR="RTL">foo</p>',
			),
			'invalid dir attribute value'           => array(
				'<p dir="up">foo</p>',
				'<p>foo</p>',
			),
			'dir attribute with empty value'        => array(
				'<p dir="">foo</p>',
				'<p>foo</p>',
			),
			'dir attribute with no value'           => array(
				'<p dir>foo</p>',
				'<p>foo</p>',
			),
		);

		return array_map(
			static function ( $datum ) {
				$datum[] = array(
					'p' => array(
						'dir' => array(
							'values' => array( 'ltr', 'rtl' ),
						),
					),
				);

				return $datum;
			},
			$data
		);
	}

	/**
	 * Test that attributes with the required flag are handled correctly.
	 *
	 * @ticket 54261
	 *
	 * @dataProvider data_wp_kses_required_attribute
	 *
	 * @param string $content      A string of HTML to test.
	 * @param string $expected     The expected result from KSES.
	 * @param array  $allowed_html The allowed HTML to pass to KSES.
	 */
	public function test_wp_kses_required_attribute( $content, $expected, $allowed_html ) {
		$this->assertEqualHTML( $expected, wp_kses( $content, $allowed_html ) );
	}

	/**
	 * Data provider for test_wp_kses_required_attribute().
	 */
	public function data_wp_kses_required_attribute() {
		$data = array(
			'valid dir attribute value'             => array(
				'<p dir="ltr">foo</p>', // Test HTML.
				'<p dir="ltr">foo</p>', // Expected result when dir is not required.
				'<p dir="ltr">foo</p>', // Expected result when dir is required.
				'<p dir="ltr">foo</p>', // Expected result when dir is required, but has no value filter.
			),
			'valid dir attribute value, upper case' => array(
				'<p DIR="RTL">foo</p>',
				'<p DIR="RTL">foo</p>',
				'<p DIR="RTL">foo</p>',
				'<p DIR="RTL">foo</p>',
			),
			'invalid dir attribute value'           => array(
				'<p dir="up">foo</p>',
				'<p>foo</p>',
				'<p>foo</p>',
				'<p dir="up">foo</p>',
			),
			'dir attribute with empty value'        => array(
				'<p dir="">foo</p>',
				'<p>foo</p>',
				'<p>foo</p>',
				'<p dir="">foo</p>',
			),
			'dir attribute with no value'           => array(
				'<p dir>foo</p>',
				'<p>foo</p>',
				'<p>foo</p>',
				'<p dir>foo</p>',
			),
			'dir attribute not set'                 => array(
				'<p>foo</p>',
				'<p>foo</p>',
				'<p>foo</p>',
				'<p>foo</p>',
			),
		);

		$return_data = array();

		foreach ( $data as $description => $datum ) {
			// Test that the required flag defaults to false.
			$return_data[ "$description - required flag not set" ] = array(
				$datum[0],
				$datum[1],
				array(
					'p' => array(
						'dir' => array(
							'values' => array( 'ltr', 'rtl' ),
						),
					),
				),
			);

			// Test when the attribute is not required, but has allowed values.
			$return_data[ "$description - required flag set to false" ] = array(
				$datum[0],
				$datum[1],
				array(
					'p' => array(
						'dir' => array(
							'required' => false,
							'values'   => array( 'ltr', 'rtl' ),
						),
					),
				),
			);

			// Test when the attribute is required, but has allowed values.
			$return_data[ "$description - required flag set to true" ] = array(
				$datum[0],
				$datum[2],
				array(
					'p' => array(
						'dir' => array(
							'required' => true,
							'values'   => array( 'ltr', 'rtl' ),
						),
					),
				),
			);

			// Test when the attribute is required, but has no allowed values.
			$return_data[ "$description - required flag set to true, no allowed values specified" ] = array(
				$datum[0],
				$datum[3],
				array(
					'p' => array(
						'dir' => array(
							'required' => true,
						),
					),
				),
			);
		}

		return $return_data;
	}

	/**
	 * Test that XML named entities are encoded correctly.
	 *
	 * @dataProvider data_wp_kses_xml_named_entities
	 *
	 * @ticket 54060
	 * @covers ::wp_kses_xml_named_entities
	 *
	 * @param array  $input    The input to wp_kses_xml_named_entities().
	 * @param string $expected The expected output.
	 */
	public function test_wp_kses_xml_named_entities( $input, $expected ) {
		$this->assertSame( $expected, wp_kses_xml_named_entities( $input ) );
	}

	/**
	 * Data provider for test_wp_kses_xml_named_entities().
	 *
	 * @return array Nested array of input, expected pairs.
	 */
	public function data_wp_kses_xml_named_entities() {
		return array(
			// Empty string value testing.
			'empty string'       => array(
				'input'    => '',
				'expected' => '',
			),

			// Empty string array value testing.
			'empty string array' => array(
				'input'    => array( '', '' ),
				'expected' => '',
			),

			// $allowedxmlentitynames values testing.
			'amp'                => array(
				'input'    => array( '', 'amp' ),
				'expected' => '&amp;',
			),
			'lt'                 => array(
				'input'    => array( '', 'lt' ),
				'expected' => '&lt;',
			),
			'gt'                 => array(
				'input'    => array( '', 'gt' ),
				'expected' => '&gt;',
			),

			// $allowedentitynames values testing.
			'nbsp'               => array(
				'input'    => array( '', 'nbsp' ),
				'expected' => "\u{00A0}",
			),
			'iexcl'              => array(
				'input'    => array( '', 'iexcl' ),
				'expected' => '¡',
			),
			'cent'               => array(
				'input'    => array( '', 'cent' ),
				'expected' => '¢',
			),

			// Some other value testing.
			'test'               => array(
				'input'    => array( '', 'test' ),
				'expected' => '&amp;test;',
			),

		);
	}

	/**
	 * Test that KSES globals are defined.
	 *
	 * @dataProvider data_kses_globals_are_defined
	 *
	 * @ticket 54060
	 *
	 * @param string $global_name The name of the global variable.
	 */
	public function test_kses_globals_are_defined( $global_name ) {
		$this->assertArrayHasKey( $global_name, $GLOBALS );
	}

	/**
	 * Data provider for test_kses_globals_are_defined().
	 *
	 * @return array
	 */
	public function data_kses_globals_are_defined() {
		$required_kses_globals = array(
			'allowedposttags',
			'allowedtags',
			'allowedentitynames',
			'allowedxmlentitynames',
		);

		return $this->text_array_to_dataprovider( $required_kses_globals );
	}

	/**
	 * Tests that the target attribute is preserved in various contexts.
	 *
	 * @dataProvider data_target_attribute_preserved_in_descriptions
	 *
	 * @ticket 12056
	 *
	 * @param string $context  The context to test ('user_description' or 'pre_term_description').
	 * @param string $input    The input HTML string.
	 * @param string $expected The expected output HTML string.
	 */
	public function test_target_attribute_preserved_in_context( $context, $input, $expected ) {
		$allowed = wp_kses_allowed_html( $context );
		$this->assertTrue( isset( $allowed['a']['target'] ), "Target attribute not allowed in {$context}" );
		$this->assertEqualHTML( $expected, wp_kses( $input, $context ) );
	}

	/**
	 * Data provider for test_target_attribute_preserved_in_context.
	 *
	 * @return array
	 */
	public function data_target_attribute_preserved_in_descriptions() {
		return array(
			array(
				'user_description',
				'<a href="https://example.com" target="_blank">Example</a>',
				'<a href="https://example.com" target="_blank">Example</a>',
			),
			array(
				'pre_term_description',
				'<a href="https://example.com" target="_blank">Example</a>',
				'<a href="https://example.com" target="_blank">Example</a>',
			),
		);
	}

	/**
	 * Tests that specific attributes are preserved in various contexts.
	 *
	 * @dataProvider data_allowed_attributes_in_descriptions
	 *
	 * @ticket 12056
	 *
	 * @param string $context    The context to test ('user_description' or 'pre_term_description').
	 * @param array  $attributes List of attributes to check for.
	 */
	public function test_specific_attributes_preserved_in_context( $context, $attributes ) {
		$allowed = wp_kses_allowed_html( $context );
		foreach ( $attributes as $attribute ) {
			$this->assertTrue( isset( $allowed['a'][ $attribute ] ), "{$attribute} attribute not allowed in {$context}" );
		}
	}

	/**
	 * Data provider for test_specific_attributes_preserved_in_context.
	 *
	 * @return array
	 */
	public function data_allowed_attributes_in_descriptions() {
		return array(
			array(
				'user_description',
				array( 'target', 'href', 'rel' ),
			),
			array(
				'pre_term_description',
				array( 'target', 'href', 'rel' ),
			),
		);
	}

	/**
	 * Test that wp_filter_post_kses() filters img tags correctly and allows the srcset element.
	 *
	 * @ticket 29807
	 */
	public function test_wp_filter_post_kses_img() {
		global $allowedposttags;

		$attributes = array(
			'class'    => 'classname',
			'id'       => 'idattr',
			'style'    => 'color: red;',
			'alt'      => 'alt',
			'src'      => '/test.png',
			'srcset'   => '/test.png 1x, /test-2x.png 2x, /test-3x.png',
			'width'    => '100',
			'height'   => '100',
			'usemap'   => '#hash',
			'vspace'   => '20',
			'hspace'   => '20',
			'longdesc' => 'this is the longdesc',
			'align'    => 'middle',
			'border'   => '5',
			'sizes'    => '(max-width: 600px) 100vw, 50vw',
		);

		foreach ( $attributes as $name => $value ) {
			$string        = "<img $name='$value' />";
			$expect_string = '<img ' . $name . '="' . trim( $value, ';' ) . '" />';

			$this->assertSame( $expect_string, wp_kses( $string, $allowedposttags ) );
		}
	}

	/**
	 * @ticket 29807
	 *
	 * @param string $unfiltered Unfiltered srcset value before wp_kses.
	 * @param string $expected   Expected srcset value after wp_kses.
	 *
	 * @dataProvider data_wp_kses_srcset
	 */
	public function test_wp_kses_srcset( $unfiltered, $expected ) {
		$unfiltered = "<img src='test.png' srcset='{$unfiltered}' />";
		$expected   = '<img src="test.png" srcset="' . $expected . '" />';
		$this->assertSame( $expected, wp_kses_post( $unfiltered ) );
	}

	public function data_wp_kses_srcset() {
		return array(
			array(
				'/test.png 1x, /test-2x.png 2x',
				'/test.png 1x, /test-2x.png 2x',
			),
			array(
				'bad://localhost/test.png 1x, http://localhost/test-2x.png 2x',
				'//localhost/test.png 1x, http://localhost/test-2x.png 2x',
			),
			array(
				'http://localhost/test.png 1x, bad://localhost/test-2x.png 2x',
				'http://localhost/test.png 1x, //localhost/test-2x.png 2x',
			),
			array(
				'http://localhost/test.png,big 1x, bad://localhost/test.png,medium 2x',
				'http://localhost/test.png,big 1x, //localhost/test.png,medium 2x',
			),
			array(
				'path/to/test.png 1x, path/to/test-2x.png 2x',
				'path/to/test.png 1x, path/to/test-2x.png 2x',
			),
		);
	}

	/**
	 * @ticket 29807
	 */
	public function test_wp_filter_post_kses_picture() {
		global $allowedposttags;

		$html = '<picture><source srcset="pear-mobile.jpeg" media="(max-width: 720px)" type="image/png"><source srcset="pear-tablet.jpeg" media="(max-width: 1280px)" type="image/png"><img src="pear-desktop.jpeg" alt="The pear is juicy."></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		$html = '<picture><source srcset="https://wordpress.org/pear-mobile.jpeg" media="(max-width: 720px)" type="image/png"><source srcset="https://wordpress.org/pear-tablet.jpeg 500w, https://wordpress.org/pear-tablet.jpeg" media="(max-width: 1280px)" type="image/png"><img src="pear-desktop.jpeg" alt="The pear is juicy."></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Test bad protocol in srcset.
		$original = '<picture><source srcset="bad://pear-mobile.jpeg" media="(max-width: 720px)" type="image/png"><source srcset="pear-tablet.jpeg" media="(max-width: 1280px)" type="image/png"><img src="pear-desktop.jpeg" alt="The pear is juicy."></picture>';
		$expected = '<picture><source srcset="//pear-mobile.jpeg" media="(max-width: 720px)" type="image/png"><source srcset="pear-tablet.jpeg" media="(max-width: 1280px)" type="image/png"><img src="pear-desktop.jpeg" alt="The pear is juicy."></picture>';
		$this->assertSame( $expected, wp_kses( $original, $allowedposttags ) );
	}

	/**
	 * Test wp_kses_sanitize_uris function directly.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_sanitize_uris
	 */
	public function test_wp_kses_sanitize_uris( $attrname, $attrvalue, $expected, $multi_uri = array( 'srcset' ) ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( $attrname, $attrvalue, $allowed_protocols, $multi_uri );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_sanitize_uris() {
		return array(
			// Test non-URI attribute.
			array( 'alt', 'description', 'description' ),

			// Test single URI attribute.
			array( 'src', 'http://example.com/image.jpg', 'http://example.com/image.jpg' ),

			// Test single URI with bad protocol.
			array( 'src', 'javascript:alert(1)', 'alert(1)' ),

			// Test srcset with multiple URIs.
			array( 'srcset', 'image1.jpg 1x, image2.jpg 2x', 'image1.jpg 1x, image2.jpg 2x' ),

			// Test srcset with bad protocol.
			array( 'srcset', 'javascript:alert(1) 1x, http://example.com/image.jpg 2x', 'alert(1) 1x, http://example.com/image.jpg 2x' ),

			// A custom $multi_uri_attrs entry is sanitized per URL even when the attribute
			// is not registered as a URI attribute (fail-safe: one registration suffices).
			array( 'custom', 'javascript:alert(1), url2.jpg', 'alert(1), url2.jpg', array( 'custom' ) ),

			// Uppercase attribute name on a single-URI attribute is normalised.
			array( 'SRC', 'javascript:alert(1)', 'alert(1)' ),

			// Mixed-case attribute name on a multi-URI attribute splits correctly.
			array( 'SrcSet', 'javascript:alert(1) 1x, http://example.com/image.jpg 2x', 'alert(1) 1x, http://example.com/image.jpg 2x' ),

			// Empty $multi_uri falls through to single-URI handling for a URI attribute.
			// The whole value is treated as one URL, so wp_kses_bad_protocol() strips more than per-entry parsing would.
			array( 'srcset', 'javascript:alert(1) 1x, http://example.com/image.jpg 2x', '//example.com/image.jpg 2x', array() ),
		);
	}

	/**
	 * Test that a custom attribute can opt in to multi-URI sanitization.
	 *
	 * Passing the attribute in $multi_uri_attrs is sufficient for per-URL
	 * sanitization to apply; it does not additionally need to be registered
	 * via the `wp_kses_uri_attributes` filter.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_sanitize_uris
	 */
	public function test_wp_kses_sanitize_uris_custom_multi_uri_attribute() {
		$result = wp_kses_sanitize_uris(
			'data-srcset',
			'javascript:alert(1) 1x, https://example.com/img.jpg 2x',
			wp_allowed_protocols(),
			array( 'data-srcset' )
		);

		$this->assertSame( 'alert(1) 1x, https://example.com/img.jpg 2x', $result );
	}

	/**
	 * Test edge cases for srcset sanitization.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_edge_cases
	 */
	public function test_wp_kses_srcset_edge_cases( $srcset_value, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $srcset_value, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_edge_cases() {
		return array(
			// Test an empty srcset.
			array( '', '' ),

			// Srcset with extra whitespace is preserved byte for byte.
			array( '  image1.jpg 1x  ,   image2.jpg 2x  ', '  image1.jpg 1x  ,   image2.jpg 2x  ' ),

			// Newlines are srcset whitespace and are preserved.
			array( "image1.jpg 1x,\nimage2.jpg 2x", "image1.jpg 1x,\nimage2.jpg 2x" ),

			// Srcset with single URL and no descriptor.
			array( 'image.jpg', 'image.jpg' ),

			// Srcset with complex descriptors.
			array( 'small.jpg 480w, medium.jpg 800w, large.jpg 1200w', 'small.jpg 480w, medium.jpg 800w, large.jpg 1200w' ),
		);
	}

	/**
	 * Test malicious input sanitization in srcset.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_malicious_input() {
		global $allowedposttags;

		// JavaScript in srcset - the entire img tag gets escaped when it contains dangerous content.
		$original = '<img srcset="javascript:alert(1) 1x, data:text/html,<script>alert(1)</script> 2x" />';
		$result   = wp_kses( $original, $allowedposttags );
		// The whole img tag should be escaped when it contains script content.
		$this->assertStringStartsWith( '&lt;', $result );

		// Script tag in picture element (should be stripped).
		$original = '<picture><script>alert(1)</script><source srcset="image.jpg"><img src="fallback.jpg"></picture>';
		$result   = wp_kses( $original, $allowedposttags );
		// Script content should be converted to text, not completely removed.
		$this->assertStringContainsString( 'alert(1)', $result );
		$this->assertStringNotContainsString( '<script>', $result );

		// Onclick in source element (should be stripped).
		$original = '<picture><source srcset="image.jpg" onclick="alert(1)"><img src="fallback.jpg"></picture>';
		$expected = '<picture><source srcset="image.jpg"><img src="fallback.jpg"></picture>';
		$this->assertSame( $expected, wp_kses( $original, $allowedposttags ) );
	}

	/**
	 * Test sizes attribute handling.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_sizes_attribute() {
		global $allowedposttags;

		// Valid sizes attribute.
		$html = '<img src="image.jpg" sizes="(max-width: 600px) 100vw, 50vw" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Complex sizes with multiple conditions.
		$html = '<img src="image.jpg" sizes="(max-width: 320px) 280px, (max-width: 640px) 580px, 800px" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Sizes in source element.
		$html = '<picture><source srcset="mobile.jpg" sizes="100vw" media="(max-width: 600px)"><img src="desktop.jpg"></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );
	}

	/**
	 * Test comprehensive responsive image scenarios.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_comprehensive_responsive_images() {
		global $allowedposttags;

		// Test complex srcset with width descriptors.
		$html = '<img src="default.jpg" srcset="small.jpg 480w, medium.jpg 768w, large.jpg 1024w, xlarge.jpg 1440w" sizes="(max-width: 480px) 100vw, (max-width: 768px) 75vw, 50vw" alt="Responsive image" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Test picture with multiple sources and mixed protocols.
		$original = '<picture><source srcset="javascript:void(0) 480w, https://example.com/mobile.webp 480w" type="image/webp" media="(max-width: 600px)"><source srcset="bad://example.com/tablet.jpg 768w, https://example.com/tablet.jpg 768w" type="image/jpeg" media="(max-width: 1200px)"><img src="https://example.com/desktop.jpg" alt="Picture element test" /></picture>';
		$result   = wp_kses( $original, $allowedposttags );

		// Should remove bad protocols but keep valid ones.
		$this->assertStringContainsString( 'https://example.com/mobile.webp', $result );
		$this->assertStringContainsString( 'https://example.com/tablet.jpg', $result );
		$this->assertStringNotContainsString( 'javascript:', $result );
		$this->assertStringNotContainsString( 'bad://', $result );

		// Test nested picture scenario.
		$original = '<picture><picture><source srcset="inner.jpg"></picture><source srcset="outer.jpg"><img src="fallback.jpg"></picture>';
		$result   = wp_kses( $original, $allowedposttags );
		// KSES allows the nesting but should preserve the structure.
		$this->assertStringContainsString( '<picture>', $result );
		$this->assertStringContainsString( '<source', $result );
	}

	/**
	 * Test that srcset URLs containing commas in the URL path are not broken.
	 *
	 * CDN image resizers (e.g. Cloudflare) use commas in URL paths like:
	 * cdn-cgi/image/format=auto,quality=80,width=412/https://bucket.example/img.jpg
	 *
	 * The srcset splitting logic must distinguish between commas that separate
	 * srcset entries (followed by a URL) and commas within a single URL.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_with_commas_in_urls
	 */
	public function test_wp_kses_srcset_with_commas_in_urls( $input, $expected ) {
		$unfiltered = "<img src='test.png' srcset='{$input}' />";
		$expected   = '<img src="test.png" srcset="' . $expected . '" />';
		$this->assertSame( $expected, wp_kses_post( $unfiltered ) );
	}

	public function data_wp_kses_srcset_with_commas_in_urls() {
		return array(
			'CDN resizer URL with commas in path, multiple srcset entries' => array(
				'https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=412,height=275,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/wp-content/uploads/2025/08/photo.jpg 412w, https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=824,height=550,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/wp-content/uploads/2025/08/photo.jpg 824w',
				'https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=412,height=275,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/wp-content/uploads/2025/08/photo.jpg 412w, https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=824,height=550,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/wp-content/uploads/2025/08/photo.jpg 824w',
			),
			'single CDN resizer URL with commas, no srcset separator' => array(
				'https://resizer.example/cdn-cgi/image/format=auto,quality=80/https://bucket.example/img.jpg',
				'https://resizer.example/cdn-cgi/image/format=auto,quality=80/https://bucket.example/img.jpg',
			),
			'CDN resizer URL with commas, pixel density descriptor'   => array(
				'https://resizer.example/cdn-cgi/image/format=auto,quality=80,width=200/https://bucket.example/img.jpg 1x, https://resizer.example/cdn-cgi/image/format=auto,quality=80,width=400/https://bucket.example/img.jpg 2x',
				'https://resizer.example/cdn-cgi/image/format=auto,quality=80,width=200/https://bucket.example/img.jpg 1x, https://resizer.example/cdn-cgi/image/format=auto,quality=80,width=400/https://bucket.example/img.jpg 2x',
			),
		);
	}

	/**
	 * Test that srcset values preserve their original spacing.
	 *
	 * wp_kses_sanitize_uris() should not add or remove spaces around commas.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_preserves_spacing
	 */
	public function test_wp_kses_srcset_preserves_spacing( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_preserves_spacing() {
		return array(
			'no space after comma'        => array(
				'image1.jpg 1x,image2.jpg 2x',
				'image1.jpg 1x,image2.jpg 2x',
			),
			'single space after comma'    => array(
				'image1.jpg 1x, image2.jpg 2x',
				'image1.jpg 1x, image2.jpg 2x',
			),
			'multiple spaces after comma' => array(
				'image1.jpg 1x,  image2.jpg 2x',
				'image1.jpg 1x,  image2.jpg 2x',
			),
		);
	}

	/**
	 * Test that decoding and fetchpriority attributes are allowed on img tags.
	 *
	 * These attributes are commonly added by WordPress core for performance
	 * optimization and should not be stripped by KSES.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_img_decoding_and_fetchpriority() {
		global $allowedposttags;

		// Test decoding attribute.
		$html = '<img src="test.jpg" decoding="async" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Test fetchpriority attribute.
		$html = '<img src="test.jpg" fetchpriority="high" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Test full real-world img tag with all responsive attributes.
		$html = '<img src="test.jpg" decoding="async" fetchpriority="high" srcset="small.jpg 1x, large.jpg 2x" sizes="100vw" loading="lazy" />';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );
	}

	/**
	 * Test that wp_kses_uri_attributes() includes srcset.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_uri_attributes
	 */
	public function test_wp_kses_uri_attributes_includes_srcset() {
		$uri_attrs = wp_kses_uri_attributes();

		$this->assertContains( 'srcset', $uri_attrs, 'srcset should be a URI attribute.' );
		$this->assertContains( 'src', $uri_attrs, 'src should be a URI attribute.' );
		$this->assertContains( 'href', $uri_attrs, 'href should be a URI attribute.' );
		$this->assertContains( 'action', $uri_attrs, 'action should be a URI attribute.' );
	}

	/**
	 * Test wp_kses_one_attr() with srcset attribute.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_one_attr
	 */
	public function test_wp_kses_one_attr_srcset() {
		// Valid multi-URI srcset passes through.
		$result = wp_kses_one_attr( ' srcset="image1.jpg 1x, image2.jpg 2x"', 'img' );
		$this->assertSame( ' srcset="image1.jpg 1x, image2.jpg 2x"', $result );

		// Bad protocol in srcset is stripped.
		$result = wp_kses_one_attr( ' srcset="javascript:alert(1) 1x, https://example.com/img.jpg 2x"', 'img' );
		$this->assertStringNotContainsString( 'javascript:', $result );
		$this->assertStringContainsString( 'https://example.com/img.jpg', $result );
	}

	/**
	 * Test source element attribute handling.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_source_element_attributes() {
		global $allowedposttags;

		// All four allowed attributes together.
		$html = '<source srcset="img.jpg" type="image/webp" media="(min-width: 800px)" sizes="100vw">';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Disallowed attribute (src) is stripped from source.
		$original = '<source srcset="img.jpg" src="fallback.jpg">';
		$expected = '<source srcset="img.jpg">';
		$this->assertSame( $expected, wp_kses( $original, $allowedposttags ) );

		// Event handler is stripped.
		$original = '<source srcset="img.jpg" onerror="alert(1)">';
		$expected = '<source srcset="img.jpg">';
		$this->assertSame( $expected, wp_kses( $original, $allowedposttags ) );
	}

	/**
	 * Test picture element edge cases.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_picture_element_edge_cases() {
		global $allowedposttags;

		// Empty picture element passes through.
		$html = '<picture></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Picture without fallback img.
		$html = '<picture><source srcset="img.webp" type="image/webp"></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );

		// Picture with only img, no source elements.
		$html = '<picture><img src="only-img.jpg" /></picture>';
		$this->assertSame( $html, wp_kses( $html, $allowedposttags ) );
	}

	/**
	 * Test that disabling srcset sanitization requires removal from both lists.
	 *
	 * Multi-URI attributes are sanitized in their own right, so removing srcset
	 * from `wp_kses_uri_attributes` alone leaves per-URL sanitization in place
	 * (fail-safe). Only removing it from `wp_kses_multi_uri_attributes` as well
	 * disables sanitization.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_uri_attributes_filter() {
		$allowed_protocols = wp_allowed_protocols();

		$remove_srcset = static function ( $attrs ) {
			return array_diff( $attrs, array( 'srcset' ) );
		};

		// Removing srcset from the URI attributes list alone does not disable sanitization.
		add_filter( 'wp_kses_uri_attributes', $remove_srcset );
		$result = wp_kses_sanitize_uris( 'srcset', 'javascript:alert(1) 1x', $allowed_protocols );
		$this->assertSame( 'alert(1) 1x', $result, 'srcset should remain sanitized while still a multi-URI attribute' );

		// Removing it from the multi-URI attributes list as well disables sanitization.
		add_filter( 'wp_kses_multi_uri_attributes', $remove_srcset );
		$result = wp_kses_sanitize_uris( 'srcset', 'javascript:alert(1) 1x', $allowed_protocols );
		$this->assertSame( 'javascript:alert(1) 1x', $result, 'srcset should not be sanitized once removed from both lists' );

		remove_filter( 'wp_kses_uri_attributes', $remove_srcset );
		remove_filter( 'wp_kses_multi_uri_attributes', $remove_srcset );

		// With the filters removed, bad protocols are stripped again.
		$result = wp_kses_sanitize_uris( 'srcset', 'javascript:alert(1) 1x', $allowed_protocols );
		$this->assertStringNotContainsString( 'javascript:', $result );
	}

	/**
	 * Test srcset with no descriptor on the final entry.
	 *
	 * The last srcset candidate often omits the width/pixel descriptor.
	 * URLs with commas in the final descriptor-less entry must be preserved.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_no_final_descriptor
	 */
	public function test_wp_kses_srcset_no_final_descriptor( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_no_final_descriptor() {
		return array(
			'CDN URL with commas, no descriptor on last entry' => array(
				'https://cdn.example/image/format=auto,quality=80/img.jpg 400w, https://cdn.example/image/format=auto,quality=80/img-large.jpg',
				'https://cdn.example/image/format=auto,quality=80/img.jpg 400w, https://cdn.example/image/format=auto,quality=80/img-large.jpg',
			),
			'simple URLs, no descriptor on last entry' => array(
				'small.jpg 300w, medium.jpg 600w, large.jpg',
				'small.jpg 300w, medium.jpg 600w, large.jpg',
			),
			'single URL without any descriptor'        => array(
				'only-image.jpg',
				'only-image.jpg',
			),
		);
	}

	/**
	 * Test srcset with decimal pixel density descriptors.
	 *
	 * Descriptors like 1.5x and 2.5x are valid per the HTML spec.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_decimal_descriptors
	 */
	public function test_wp_kses_srcset_decimal_descriptors( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_decimal_descriptors() {
		return array(
			'decimal pixel density descriptors'     => array(
				'image-1x.jpg 1x, image-1.5x.jpg 1.5x, image-2x.jpg 2x',
				'image-1x.jpg 1x, image-1.5x.jpg 1.5x, image-2x.jpg 2x',
			),
			'mixed integer and decimal descriptors' => array(
				'small.jpg 1x, medium.jpg 2x, large.jpg 3x',
				'small.jpg 1x, medium.jpg 2x, large.jpg 3x',
			),
			'high density descriptor'               => array(
				'low.jpg 1x, high.jpg 4x',
				'low.jpg 1x, high.jpg 4x',
			),
		);
	}

	/**
	 * Test picture element with WebP/AVIF type-based fallback pattern.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_picture_type_fallback() {
		$html     = '<picture>'
			. '<source srcset="photo.avif" type="image/avif">'
			. '<source srcset="photo.webp" type="image/webp">'
			. '<img src="photo.jpg" alt="Photo" width="800" height="600" />'
			. '</picture>';
		$expected = $html;
		$this->assertSame( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Test picture element with art direction using media queries and srcset descriptors.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_picture_art_direction() {
		$html     = '<picture>'
			. '<source media="(min-width: 1200px)" srcset="hero-wide-1x.jpg 1x, hero-wide-2x.jpg 2x">'
			. '<source media="(min-width: 600px)" srcset="hero-medium-400.jpg 400w, hero-medium-800.jpg 800w" sizes="100vw">'
			. '<img src="hero-small.jpg" srcset="hero-small-300.jpg 300w, hero-small-600.jpg 600w" sizes="100vw" alt="Hero" />'
			. '</picture>';
		$expected = $html;
		$this->assertSame( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Test srcset with protocol-relative URLs.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_srcset_protocol_relative_urls() {
		$allowed_protocols = wp_allowed_protocols();
		$input             = '//cdn.example.com/img-300.jpg 300w, //cdn.example.com/img-600.jpg 600w';
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $input, $result );
	}

	/**
	 * Test that source element strips disallowed src attribute.
	 *
	 * The source element allows srcset but not src.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_source_strips_src_attribute() {
		$html     = '<source src="not-allowed.jpg" srcset="allowed.jpg" type="image/jpeg">';
		$expected = '<source srcset="allowed.jpg" type="image/jpeg">';
		$this->assertSame( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Test CDN URLs with gravity parameter containing x notation in srcset.
	 *
	 * Ensures that CDN-style gravity=0.5x0.5 is not mistaken for a pixel density descriptor.
	 *
	 * @ticket 29807
	 */
	public function test_wp_kses_srcset_cdn_gravity_parameter() {
		$html     = '<img src="https://resizer.example/cdn-cgi/image/format=auto,quality=80/https://bucket.example/img.jpg" '
			. 'srcset="https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=412,height=275,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/img.jpg 412w, '
			. 'https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=824,height=550,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/img.jpg 824w" '
			. 'decoding="async" fetchpriority="high" width="824" height="550" alt="Test" />';
		$expected = $html;
		$this->assertSame( $expected, wp_kses_post( $html ) );
	}

	/**
	 * Test srcset with bad protocols on entries separated by decimal descriptors.
	 *
	 * Ensures the split pattern recognises descriptors like "1.5x" as entry
	 * boundaries so per-entry protocol sanitization still applies.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_decimal_descriptor_bad_protocol
	 */
	public function test_wp_kses_srcset_decimal_descriptor_bad_protocol( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_decimal_descriptor_bad_protocol() {
		return array(
			'bad protocol after decimal descriptor'  => array(
				'good.jpg 1.5x, javascript:alert(1) 2x',
				'good.jpg 1.5x, alert(1) 2x',
			),
			'bad protocol before decimal descriptor' => array(
				'javascript:alert(1) 1.5x, good.jpg 2x',
				'alert(1) 1.5x, good.jpg 2x',
			),
			'three entries with middle decimal descriptor and bad middle URL' => array(
				'image-1x.jpg 1x, javascript:alert(1) 1.5x, image-2x.jpg 2x',
				'image-1x.jpg 1x, alert(1) 1.5x, image-2x.jpg 2x',
			),
		);
	}

	/**
	 * Test malformed srcset values (leading/trailing/consecutive commas).
	 *
	 * wp_kses_sanitize_uris() should not throw and should still strip bad protocols.
	 *
	 * @ticket 29807
	 * @dataProvider data_wp_kses_srcset_malformed
	 */
	public function test_wp_kses_srcset_malformed( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_malformed() {
		return array(
			'trailing comma after descriptor' => array(
				'image.jpg 1x,',
				'image.jpg 1x,',
			),
			'leading comma'                   => array(
				', image.jpg 1x',
				', image.jpg 1x',
			),
			'whitespace only'                 => array(
				'   ',
				'   ',
			),
		);
	}

	/**
	 * Test wp_kses_one_attr() with the sizes attribute.
	 *
	 * sizes is not a URI attribute, so it must pass through unchanged (apart from
	 * the standard attribute reformatting done by wp_kses_one_attr()).
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_one_attr
	 */
	public function test_wp_kses_one_attr_sizes() {
		$result = wp_kses_one_attr( ' sizes="(max-width: 600px) 100vw, 50vw"', 'img' );
		$this->assertSame( ' sizes="(max-width: 600px) 100vw, 50vw"', $result );
	}

	/**
	 * Test srcset entries without descriptors are still sanitized individually.
	 *
	 * Per the HTML spec, the descriptor is optional, so "safe.jpg, evil.jpg" contains
	 * two candidates. URLs in srcset must encode whitespace as %20, so a comma
	 * adjacent to whitespace always separates two entries and each entry must be
	 * protocol-checked on its own.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_sanitize_uris
	 * @dataProvider data_wp_kses_srcset_entries_without_descriptors
	 */
	public function test_wp_kses_srcset_entries_without_descriptors( $input, $expected ) {
		$allowed_protocols = wp_allowed_protocols();
		$result            = wp_kses_sanitize_uris( 'srcset', $input, $allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	public function data_wp_kses_srcset_entries_without_descriptors() {
		return array(
			'bad protocol in second descriptor-less entry' => array(
				'safe.jpg, javascript:alert(1)',
				'safe.jpg, alert(1)',
			),
			'bad protocol in first descriptor-less entry'  => array(
				'javascript:alert(1), safe.jpg',
				'alert(1), safe.jpg',
			),
			'comma before whitespace separates entries'    => array(
				'a.jpg ,javascript:alert(1) 2x',
				'a.jpg ,alert(1) 2x',
			),
			'descriptor-less entries with valid URLs pass through' => array(
				'small.jpg, large.jpg',
				'small.jpg, large.jpg',
			),
			'comma without adjacent whitespace stays part of the URL' => array(
				'cdn.example/format=auto,quality=80/img.jpg',
				'cdn.example/format=auto,quality=80/img.jpg',
			),
		);
	}

	/**
	 * Test that wp_kses_multi_uri_attributes() includes srcset by default.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_multi_uri_attributes
	 */
	public function test_wp_kses_multi_uri_attributes_includes_srcset() {
		$this->assertContains( 'srcset', wp_kses_multi_uri_attributes(), 'srcset should be a multi-URI attribute by default.' );
	}

	/**
	 * Test that the wp_kses_multi_uri_attributes filter feeds the default
	 * $multi_uri_attrs list of wp_kses_sanitize_uris().
	 *
	 * An attribute added to the `wp_kses_multi_uri_attributes` filter receives
	 * per-URL sanitization without callers having to pass an explicit
	 * $multi_uri_attrs argument, and without also being registered via the
	 * `wp_kses_uri_attributes` filter (fail-safe: one registration suffices).
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_multi_uri_attributes
	 * @covers ::wp_kses_sanitize_uris
	 */
	public function test_wp_kses_multi_uri_attributes_filter_applies_by_default() {
		$add_custom = static function ( $attrs ) {
			$attrs[] = 'data-srcset';
			return $attrs;
		};
		add_filter( 'wp_kses_multi_uri_attributes', $add_custom );

		$result = wp_kses_sanitize_uris(
			'data-srcset',
			'javascript:alert(1) 1x, https://example.com/img.jpg 2x',
			wp_allowed_protocols()
		);

		remove_filter( 'wp_kses_multi_uri_attributes', $add_custom );

		$this->assertSame( 'alert(1) 1x, https://example.com/img.jpg 2x', $result );
	}

	/**
	 * Test that candidates after invalid descriptors are still protocol-checked.
	 *
	 * Per the HTML specification's srcset parsing algorithm, a comma inside a
	 * descriptor terminates the candidate, even when the descriptor itself is
	 * invalid (like "2q"). So in "a.jpg 2q,javascript:alert(1)" a browser reads
	 * "javascript:alert(1)" as a candidate URL of its own, and KSES must
	 * protocol-check it individually.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_sanitize_uris
	 * @dataProvider data_wp_kses_srcset_invalid_descriptor_comma
	 */
	public function test_wp_kses_srcset_invalid_descriptor_comma( $input, $expected ) {
		$this->assertSame( $expected, wp_kses_sanitize_uris( 'srcset', $input, wp_allowed_protocols() ) );
	}

	public function data_wp_kses_srcset_invalid_descriptor_comma() {
		return array(
			'comma in invalid descriptor starts a new candidate' => array(
				'a.jpg 2q,javascript:alert(1)',
				'a.jpg 2q,alert(1)',
			),
			'comma in invalid descriptor after a path query starts a new candidate' => array(
				'a.jpg/?v=1 2q,javascript:alert(1)',
				'a.jpg/?v=1 2q,alert(1)',
			),
		);
	}

	/**
	 * Test that URLs without a browser-parseable scheme are left intact.
	 *
	 * Per the HTML specification, a comma not terminating a run of
	 * non-whitespace characters belongs to the URL, so `a.jpg,https://…` is a
	 * single relative URL. Its prefix before the colon (`a.jpg,https`) is not a
	 * valid URL scheme, so no browser parses a protocol out of it; protocol
	 * sanitization must leave it alone rather than rewrite the same-origin
	 * relative URL into a cross-origin one.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_sanitize_uris
	 * @dataProvider data_wp_kses_srcset_schemeless_urls_with_colons
	 */
	public function test_wp_kses_srcset_schemeless_urls_with_colons( $input, $expected ) {
		$this->assertSame( $expected, wp_kses_sanitize_uris( 'srcset', $input, wp_allowed_protocols() ) );
	}

	public function data_wp_kses_srcset_schemeless_urls_with_colons() {
		return array(
			'tight comma with a colon later stays one relative URL' => array(
				'a.jpg,https://example.com/b.jpg',
				'a.jpg,https://example.com/b.jpg',
			),
			'relative CDN proxy URL wrapping an absolute URL'       => array(
				'cdn-cgi/image/format=auto,quality=80/https://bucket.example/img.jpg 1x, b.jpg 2x',
				'cdn-cgi/image/format=auto,quality=80/https://bucket.example/img.jpg 1x, b.jpg 2x',
			),
			'a scheme-shaped prefix is still protocol-checked'      => array(
				'javascript:alert(1),safe.jpg',
				'alert(1),safe.jpg',
			),
			'an entity-encoded colon is still protocol-checked'     => array(
				'javascript&#58;alert(1) 1x, safe.jpg 2x',
				'alert(1) 1x, safe.jpg 2x',
			),
			'an entity-encoded comma cannot fake a scheme prefix'   => array(
				'a.jpg&#44;https://example.com/b.jpg',
				'a.jpg&#44;https://example.com/b.jpg',
			),
		);
	}

	/**
	 * Test that data: URIs in srcset follow the allowed-protocols policy.
	 *
	 * `data` is not in wp_allowed_protocols(), so data: URIs are stripped from
	 * srcset candidates just as they are from src and href. Sites that need
	 * data: image placeholders can allow them via the `kses_allowed_protocols`
	 * filter, which extends to each srcset candidate.
	 *
	 * @ticket 29807
	 * @covers ::wp_kses_sanitize_uris
	 */
	public function test_wp_kses_srcset_data_uris_follow_allowed_protocols() {
		$srcset = 'data:image/gif;base64,R0lGODlhAQABAAAAACw= 1x, real.jpg 2x';

		$this->assertSame(
			'image/gif;base64,R0lGODlhAQABAAAAACw= 1x, real.jpg 2x',
			wp_kses_sanitize_uris( 'srcset', $srcset, wp_allowed_protocols() ),
			'data: URIs should be stripped from srcset candidates by default, consistent with src'
		);

		$this->assertSame(
			$srcset,
			wp_kses_sanitize_uris( 'srcset', $srcset, array_merge( wp_allowed_protocols(), array( 'data' ) ) ),
			'An extended protocol allowlist should apply to each srcset candidate'
		);
	}
}
