<?php
/**
 * Some simple test cases for KSES post content filtering
 *
 * @group formatting
 * @group kses
 */
class Tests_Kses extends WP_UnitTestCase {

	/**
	 * @ticket 20210
	 */
	function test_wp_filter_post_kses_address() {
		global $allowedposttags;

		$attributes = array(
			'class' => 'classname',
			'id'    => 'id',
			'style' => 'color: red;',
			'style' => 'color: red',
			'style' => 'color: red; text-align:center',
			'style' => 'color: red; text-align:center;',
			'title' => 'title',
		);

		foreach ( $attributes as $name => $value ) {
			$string        = "<address $name='$value'>1 WordPress Avenue, The Internet.</address>";
			$expect_string = "<address $name='" . str_replace( '; ', ';', trim( $value, ';' ) ) . "'>1 WordPress Avenue, The Internet.</address>";
			$this->assertEquals( $expect_string, wp_kses( $string, $allowedposttags ) );
		}
	}

	/**
	 * @ticket 20210
	 */
	function test_wp_filter_post_kses_a() {
		global $allowedposttags;

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

		foreach ( $attributes as $name => $value ) {
			if ( $value ) {
				$attr          = "$name='$value'";
				$expected_attr = "$name='" . trim( $value, ';' ) . "'";
			} else {
				$attr          = $name;
				$expected_attr = $name;
			}
			$string        = "<a $attr>I link this</a>";
			$expect_string = "<a $expected_attr>I link this</a>";
			$this->assertEquals( $expect_string, wp_kses( $string, $allowedposttags ) );
		}
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
	 * @return void
	 */
	function test_wp_kses_video( $source, $context, $expected ) {
		$actual = wp_kses( $source, $context );
		$this->assertSame( $expected, $actual );
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
	function data_wp_kses_video() {
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
	 * @ticket 20210
	 */
	function test_wp_filter_post_kses_abbr() {
		global $allowedposttags;

		$attributes = array(
			'class' => 'classname',
			'id'    => 'id',
			'style' => 'color: red;',
			'title' => 'title',
		);

		foreach ( $attributes as $name => $value ) {
			$string        = "<abbr $name='$value'>WP</abbr>";
			$expect_string = "<abbr $name='" . trim( $value, ';' ) . "'>WP</abbr>";
			$this->assertEquals( $expect_string, wp_kses( $string, $allowedposttags ) );
		}
	}

	function test_feed_links() {
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

		$this->assertEquals( $expected, wp_kses( $content, $allowedposttags ) );
	}

	function test_wp_kses_bad_protocol() {
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
						$this->assertEquals( 'javascript&amp;#0000058alert(1);', $result );
						break;
					case 12:
						$this->assertEquals( str_replace( '&', '&amp;', $x ), $result );
						break;
					case 22:
						$this->assertEquals( 'javascript&amp;#0000058alert(1);', $result );
						break;
					case 23:
						$this->assertEquals( 'javascript&amp;#0000058alert(1)//?:', $result );
						break;
					case 24:
						$this->assertEquals( 'feed:alert(1)', $result );
						break;
					case 26:
						$this->assertEquals( 'javascript&amp;#58alert(1)', $result );
						break;
					case 27:
						$this->assertEquals( 'javascript&amp;#x3ax=1;alert(1)', $result );
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
					$this->assertEquals( '\';alert(String.fromCharCode(88,83,83))//\\\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//\\";alert(String.fromCharCode(88,83,83))//--&gt;"&gt;\'&gt;alert(String.fromCharCode(88,83,83))=&amp;{}', $result );
					break;
				case 'XSS Quick Test':
					$this->assertEquals( '\'\';!--"=&amp;{()}', $result );
					break;
				case 'SCRIPT w/Alert()':
					$this->assertEquals( "alert('XSS')", $result );
					break;
				case 'SCRIPT w/Char Code':
					$this->assertEquals( 'alert(String.fromCharCode(88,83,83))', $result );
					break;
				case 'IMG STYLE w/expression':
					$this->assertEquals( 'exp/*', $result );
					break;
				case 'List-style-image':
					$this->assertEquals( 'li {list-style-image: url("javascript:alert(\'XSS\')");}XSS', $result );
					break;
				case 'STYLE':
					$this->assertEquals( "alert('XSS');", $result );
					break;
				case 'STYLE w/background-image':
					$this->assertEquals( '.XSS{background-image:url("javascript:alert(\'XSS\')");}<A></A>', $result );
					break;
				case 'STYLE w/background':
					$this->assertEquals( 'BODY{background:url("javascript:alert(\'XSS\')")}', $result );
					break;
				case 'Remote Stylesheet 2':
					$this->assertEquals( "@import'http://ha.ckers.org/xss.css';", $result );
					break;
				case 'Remote Stylesheet 3':
					$this->assertEquals( '&lt;META HTTP-EQUIV=&quot;Link&quot; Content=&quot;; REL=stylesheet"&gt;', $result );
					break;
				case 'Remote Stylesheet 4':
					$this->assertEquals( 'BODY{-moz-binding:url("http://ha.ckers.org/xssmoz.xml#xss")}', $result );
					break;
				case 'XML data island w/CDATA':
					$this->assertEquals( '&lt;![CDATA[]]&gt;', $result );
					break;
				case 'XML data island w/comment':
					$this->assertEquals( "<I><B>&lt;IMG SRC=&quot;javas<!-- -->cript:alert('XSS')\"&gt;</B></I>", $result );
					break;
				case 'XML HTML+TIME':
					$this->assertEquals( '&lt;t:set attributeName=&quot;innerHTML&quot; to=&quot;XSSalert(\'XSS\')"&gt;', $result );
					break;
				case 'Commented-out Block':
					$this->assertEquals( "<!--[if gte IE 4]&gt;-->\nalert('XSS');", $result );
					break;
				case 'Cookie Manipulation':
					$this->assertEquals( '&lt;META HTTP-EQUIV=&quot;Set-Cookie&quot; Content=&quot;USERID=alert(\'XSS\')"&gt;', $result );
					break;
				case 'SSI':
					$this->assertEquals( '&lt;!--#exec cmd=&quot;/bin/echo &#039;<!--#exec cmd="/bin/echo \'=http://ha.ckers.org/xss.js&gt;\'"-->', $result );
					break;
				case 'PHP':
					$this->assertEquals( '&lt;? echo(&#039;alert("XSS")\'); ?&gt;', $result );
					break;
				case 'UTF-7 Encoding':
					$this->assertEquals( '+ADw-SCRIPT+AD4-alert(\'XSS\');+ADw-/SCRIPT+AD4-', $result );
					break;
				case 'Escaping JavaScript escapes':
					$this->assertEquals( '\";alert(\'XSS\');//', $result );
					break;
				case 'STYLE w/broken up JavaScript':
					$this->assertEquals( '@im\port\'\ja\vasc\ript:alert("XSS")\';', $result );
					break;
				case 'Null Chars 2':
					$this->assertEquals( '&amp;alert("XSS")', $result );
					break;
				case 'No Closing Script Tag':
					$this->assertEquals( '&lt;SCRIPT SRC=http://ha.ckers.org/xss.js', $result );
					break;
				case 'Half-Open HTML/JavaScript':
					$this->assertEquals( '&lt;IMG SRC=&quot;javascript:alert(&#039;XSS&#039;)&quot;', $result );
					break;
				case 'Double open angle brackets':
					$this->assertEquals( '&lt;IFRAME SRC=http://ha.ckers.org/scriptlet.html &lt;', $result );
					break;
				case 'Extraneous Open Brackets':
					$this->assertEquals( '&lt;alert("XSS");//&lt;', $result );
					break;
				case 'Malformed IMG Tags':
					$this->assertEquals( 'alert("XSS")"&gt;', $result );
					break;
				case 'No Quotes/Semicolons':
					$this->assertEquals( "a=/XSS/\nalert(a.source)", $result );
					break;
				case 'Evade Regex Filter 1':
					$this->assertEquals( '" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Evade Regex Filter 4':
					$this->assertEquals( '\'" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Evade Regex Filter 5':
					$this->assertEquals( '` SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Filter Evasion 1':
					$this->assertEquals( 'document.write("&lt;SCRI&quot;);PT SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				case 'Filter Evasion 2':
					$this->assertEquals( '\'&gt;" SRC="http://ha.ckers.org/xss.js"&gt;', $result );
					break;
				default:
					$this->fail( 'KSES failed on ' . $attack->name . ': ' . $result );
			}
		}
	}

	function _wp_kses_allowed_html_filter( $html, $context ) {
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

		$this->assertEquals( $allowedposttags, wp_kses_allowed_html( 'post' ) );

		$tags = wp_kses_allowed_html( 'post' );

		foreach ( $tags as $tag ) {
			$this->assertTrue( $tag['class'] );
			$this->assertTrue( $tag['id'] );
			$this->assertTrue( $tag['style'] );
			$this->assertTrue( $tag['title'] );
		}

		$this->assertEquals( $allowedtags, wp_kses_allowed_html( 'data' ) );
		$this->assertEquals( $allowedtags, wp_kses_allowed_html( '' ) );
		$this->assertEquals( $allowedtags, wp_kses_allowed_html() );

		$tags = wp_kses_allowed_html( 'user_description' );
		$this->assertTrue( $tags['a']['rel'] );

		$tags = wp_kses_allowed_html();
		$this->assertFalse( isset( $tags['a']['rel'] ) );

		$this->assertEquals( array(), wp_kses_allowed_html( 'strip' ) );

		$custom_tags = array(
			'a' => array(
				'href'   => true,
				'rel'    => true,
				'rev'    => true,
				'name'   => true,
				'target' => true,
			),
		);

		$this->assertEquals( $custom_tags, wp_kses_allowed_html( $custom_tags ) );

		add_filter( 'wp_kses_allowed_html', array( $this, '_wp_kses_allowed_html_filter' ), 10, 2 );

		$this->assertEquals( array( 'a' => array( 'href' => true ) ), wp_kses_allowed_html( 'post' ) );
		$this->assertEquals( array( 'a' => array( 'href' => false ) ), wp_kses_allowed_html( 'data' ) );

		remove_filter( 'wp_kses_allowed_html', array( $this, '_wp_kses_allowed_html_filter' ) );
		$this->assertEquals( $allowedposttags, wp_kses_allowed_html( 'post' ) );
		$this->assertEquals( $allowedtags, wp_kses_allowed_html( 'data' ) );
	}

	function test_hyphenated_tag() {
		$string                 = '<hyphenated-tag attribute="value" otherattribute="value2">Alot of hyphens.</hyphenated-tag>';
		$custom_tags            = array(
			'hyphenated-tag' => array(
				'attribute' => true,
			),
		);
		$expect_stripped_string = 'Alot of hyphens.';

		$expect_valid_string = '<hyphenated-tag attribute="value">Alot of hyphens.</hyphenated-tag>';
		$this->assertEquals( $expect_stripped_string, wp_kses_post( $string ) );
		$this->assertEquals( $expect_valid_string, wp_kses( $string, $custom_tags ) );
	}

	/**
	 * @ticket 26290
	 */
	public function test_wp_kses_normalize_entities() {
		$this->assertEquals( '&spades;', wp_kses_normalize_entities( '&spades;' ) );

		$this->assertEquals( '&sup1;', wp_kses_normalize_entities( '&sup1;' ) );
		$this->assertEquals( '&sup2;', wp_kses_normalize_entities( '&sup2;' ) );
		$this->assertEquals( '&sup3;', wp_kses_normalize_entities( '&sup3;' ) );
		$this->assertEquals( '&frac14;', wp_kses_normalize_entities( '&frac14;' ) );
		$this->assertEquals( '&frac12;', wp_kses_normalize_entities( '&frac12;' ) );
		$this->assertEquals( '&frac34;', wp_kses_normalize_entities( '&frac34;' ) );
		$this->assertEquals( '&there4;', wp_kses_normalize_entities( '&there4;' ) );
	}

	/**
	 * Test removal of invalid binary data for HTML.
	 *
	 * @ticket 28506
	 * @dataProvider data_ctrl_removal
	 */
	function test_ctrl_removal( $input, $output ) {
		global $allowedposttags;

		return $this->assertEquals( $output, wp_kses( $input, $allowedposttags ) );
	}

	function data_ctrl_removal() {
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
	function test_slash_zero_removal( $input, $output ) {
		global $allowedposttags;

		return $this->assertEquals( $output, wp_kses( $input, $allowedposttags ) );
	}

	function data_slash_zero_removal() {
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
	function test_hair_parse( $input, $output ) {
		return $this->assertEquals( $output, wp_kses_hair_parse( $input ) );
	}

	function data_hair_parse() {
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
		);
	}

	/**
	 * Test new function wp_kses_attr_parse().
	 *
	 * @dataProvider data_attr_parse
	 */
	function test_attr_parse( $input, $output ) {
		return $this->assertEquals( $output, wp_kses_attr_parse( $input ) );
	}

	function data_attr_parse() {
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
	function test_one_attr( $element, $input, $output ) {
		return $this->assertEquals( $output, wp_kses_one_attr( $input, $element ) );
	}

	function data_one_attr() {
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
	function test_bdo() {
		global $allowedposttags;

		$input = '<p>This is <bdo dir="rtl">a BDO tag</bdo>. Weird, <bdo dir="ltr">right?</bdo></p>';

		$this->assertEquals( $input, wp_kses( $input, $allowedposttags ) );
	}

	/**
	 * @ticket 35079
	 */
	function test_ol_reversed() {
		global $allowedposttags;

		$input = '<ol reversed="reversed"><li>Item 1</li><li>Item 2</li><li>Item 3</li></ol>';

		$this->assertEquals( $input, wp_kses( $input, $allowedposttags ) );
	}

	/**
	 * @ticket 40680
	 */
	function test_wp_kses_attr_no_attributes_allowed_with_empty_array() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEquals( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => array() ), array() ) );
	}

	/**
	 * @ticket 40680
	 */
	function test_wp_kses_attr_no_attributes_allowed_with_true() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEquals( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => true ), array() ) );
	}

	/**
	 * @ticket 40680
	 */
	function test_wp_kses_attr_single_attribute_is_allowed() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEquals( "<{$element} title=\"foo\">", wp_kses_attr( $element, $attribute, array( 'foo' => array( 'title' => true ) ), array() ) );
	}

	/**
	 * @ticket 43312
	 */
	function test_wp_kses_attr_no_attributes_allowed_with_false() {
		$element   = 'foo';
		$attribute = 'title="foo" class="bar"';

		$this->assertEquals( "<{$element}>", wp_kses_attr( $element, $attribute, array( 'foo' => false ), array() ) );
	}

	/**
	 * Testing the safecss_filter_attr() function.
	 *
	 * @ticket 37248
	 * @ticket 42729
	 * @ticket 48376
	 * @dataProvider data_test_safecss_filter_attr
	 *
	 * @param string $css      A string of CSS rules.
	 * @param string $expected Expected string of CSS rules.
	 */
	public function test_safecss_filter_attr( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * Data Provider for test_safecss_filter_attr().
	 *
	 * @return array {
	 *     @type array {
	 *         @string string $css      A string of CSS rules.
	 *         @string string $expected Expected string of CSS rules.
	 *     }
	 * }
	 */
	public function data_test_safecss_filter_attr() {
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
				'css'      => 'flex: 0 1 auto;flex-basis: 75%;flex-direction: row-reverse;flex-flow: row-reverse nowrap;flex-grow: 2;flex-shrink: 1',
				'expected' => 'flex: 0 1 auto;flex-basis: 75%;flex-direction: row-reverse;flex-flow: row-reverse nowrap;flex-grow: 2;flex-shrink: 1',
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
			// `grid` does not yet support functions or `\`.
			array(
				'css'      => 'grid-template-columns: repeat(2, 50px 1fr);grid-template: 1em / 20% 20px 1fr',
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

		);
	}

	/**
	 * Data attributes are globally accepted.
	 *
	 * @ticket 33121
	 */
	function test_wp_kses_attr_data_attribute_is_allowed() {
		$test     = '<div data-foo="foo" data-bar="bar" datainvalid="gone" data--invaild="gone"  data-also-invaild-="gone" data-two-hyphens="remains">Pens and pencils</div>';
		$expected = '<div data-foo="foo" data-bar="bar" data-two-hyphens="remains">Pens and pencils</div>';

		$this->assertEquals( $expected, wp_kses_post( $test ) );
	}

	/**
	 * Ensure wildcard attributes block unprefixed wildcard uses.
	 *
	 * @ticket 33121
	 */
	function test_wildcard_requires_hyphen_after_prefix() {
		$allowed_html = array(
			'div' => array(
				'data-*' => true,
				'on-*'   => true,
			),
		);

		$string   = '<div datamelformed-prefix="gone" data="gone" data-="gone" onclick="alert(1)">Malformed attributes</div>';
		$expected = '<div>Malformed attributes</div>';

		$actual = wp_kses( $string, $allowed_html );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Ensure wildcard allows two hyphen.
	 *
	 * @ticket 33121
	 */
	function test_wildcard_allows_two_hyphens() {
		$allowed_html = array(
			'div' => array(
				'data-*' => true,
			),
		);

		$string   = '<div data-wp-id="pens-and-pencils">Well formed attribute</div>';
		$expected = '<div data-wp-id="pens-and-pencils">Well formed attribute</div>';

		$actual = wp_kses( $string, $allowed_html );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Ensure wildcard attributes only support valid prefixes.
	 *
	 * @dataProvider data_wildcard_attribute_prefixes
	 *
	 * @ticket 33121
	 */
	function test_wildcard_attribute_prefixes( $wildcard_attribute, $expected ) {
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
	function data_wildcard_attribute_prefixes() {
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
	 * Test URL sanitization in the style tag.
	 *
	 * @dataProvider data_kses_style_attr_with_url
	 *
	 * @ticket 45067
	 *
	 * @param $input string The style attribute saved in the editor.
	 * @param $expected string The sanitized style attribute.
	 */
	function test_kses_style_attr_with_url( $input, $expected ) {
		$actual = safecss_filter_attr( $input );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider testing style attribute sanitization.
	 *
	 * @return array Nested array of input, expected pairs.
	 */
	function data_kses_style_attr_with_url() {
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
}
