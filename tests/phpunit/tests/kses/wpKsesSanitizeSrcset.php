<?php
/**
 * Test wp_kses_sanitize_srcset() function.
 *
 * @group kses
 *
 * @covers ::wp_kses_sanitize_srcset
 */
class Tests_Kses_WpKsesSanitizeSrcset extends WP_UnitTestCase {

	/**
	 * Allowed protocols used by every test.
	 *
	 * @var string[]
	 */
	protected array $allowed_protocols;

	public function set_up(): void {
		parent::set_up();
		$this->allowed_protocols = wp_allowed_protocols();
	}

	/**
	 * @ticket 29807
	 *
	 * @dataProvider data_allowed_values_are_returned_unchanged
	 *
	 * @param string $srcset Value whose candidates are all allowed.
	 */
	public function test_allowed_values_are_returned_unchanged( string $srcset ): void {
		$this->assertSame( $srcset, wp_kses_sanitize_srcset( $srcset, $this->allowed_protocols ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_allowed_values_are_returned_unchanged(): array {
		return array(
			'empty value'                           => array( '' ),
			'single URL, no descriptor'             => array( 'image.jpg' ),
			'relative URLs'                         => array( 'path/to/test.png 1x, path/to/test-2x.png 2x' ),
			'root-relative URLs'                    => array( '/test.png 1x, /test-2x.png 2x' ),
			'protocol-relative URLs'                => array( '//cdn.example.com/img-300.jpg 300w, //cdn.example.com/img-600.jpg 600w' ),
			'absolute URLs with width descriptors'  => array( 'https://example.com/small.jpg 480w, https://example.com/medium.jpg 800w, https://example.com/large.jpg 1200w' ),
			'uppercase scheme'                      => array( 'HTTPS://example.com/a.jpg 1x, Http://example.com/b.jpg 2x' ),
			'decimal density descriptors'           => array( 'image-1x.jpg 1x, image-1.5x.jpg 1.5x, image-2x.jpg 2x' ),
			'no descriptor on the last candidate'   => array( 'small.jpg 300w, medium.jpg 600w, large.jpg' ),
			'tab between URL and descriptor'        => array( "image1.jpg\t1x, image2.jpg\t2x" ),
			'URL syntax characters are kept as is'  => array( '/image-service?yolo=meme&size=large 2x, /image-service?size=small&q="80" 1x' ),
			'commas inside a URL belong to the URL' => array( 'http://localhost/test.png,big 1x, http://localhost/test.png,medium 2x' ),
			'CDN resizer URLs with commas'          => array( 'https://resizer.example/cdn-cgi/image/format=auto,onerror=redirect,quality=80,width=412,height=275,dpr=1,fit=crop,gravity=0.5x0.5/https://bucket.example/photo.jpg 412w, https://resizer.example/cdn-cgi/image/format=auto,quality=80,width=824,height=550/https://bucket.example/photo.jpg 824w' ),
			'CDN resizer URL without descriptor'    => array( 'https://cdn.example/image/format=auto,quality=80/img.jpg 400w, https://cdn.example/image/format=auto,quality=80/img-large.jpg' ),
			'root-relative URL containing a colon'  => array( '/images/a:b.jpg 1x' ),
			'comma inside descriptor parentheses'   => array( 'a.jpg 1x(,)z, b.jpg 2x' ),
			'unclosed descriptor parenthesis'       => array( 'a.jpg 1x(, javascript:alert(1) 2x' ),
			'descriptors are not sanitized as URLs' => array( 'a.jpg javascript:alert(1), b.jpg 2x' ),
			'whitespace ends the URL before a scheme forms' => array( "a.jpg 1x, java\tscript:alert(1) 2x" ),
		);
	}

	/**
	 * @ticket 29807
	 *
	 * @dataProvider data_separators_are_normalized
	 *
	 * @param string $srcset   Value with irregular whitespace or commas between candidates.
	 * @param string $expected Value with the candidates rejoined with `, `.
	 */
	public function test_separators_are_normalized( string $srcset, string $expected ): void {
		$this->assertSame( $expected, wp_kses_sanitize_srcset( $srcset, $this->allowed_protocols ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_separators_are_normalized(): array {
		return array(
			'whitespace only'                    => array( "  \t\n", '' ),
			'commas only'                        => array( ',,,,', '' ),
			'commas and whitespace only'         => array( ' , , ', '' ),
			'no whitespace after the comma'      => array( 'image1.jpg 1x,image2.jpg 2x', 'image1.jpg 1x, image2.jpg 2x' ),
			'surrounding whitespace'             => array( '  image1.jpg 1x  ,   image2.jpg 2x  ', 'image1.jpg 1x, image2.jpg 2x' ),
			'newline after the comma'            => array( "image1.jpg 1x,\nimage2.jpg 2x", 'image1.jpg 1x, image2.jpg 2x' ),
			'empty candidate between two commas' => array( 'image1.jpg 1x,, image2.jpg 2x', 'image1.jpg 1x, image2.jpg 2x' ),
			'empty candidate with whitespace'    => array( 'image1.jpg 1x, , image2.jpg 2x', 'image1.jpg 1x, image2.jpg 2x' ),
			'trailing comma'                     => array( 'image1.jpg 1x, image2.jpg 2x,', 'image1.jpg 1x, image2.jpg 2x' ),
			'leading comma'                      => array( ', image1.jpg 1x', 'image1.jpg 1x' ),
		);
	}

	/**
	 * @ticket 29807
	 *
	 * @dataProvider data_candidates_with_disallowed_protocols_are_removed
	 *
	 * @param string $srcset   Value containing at least one disallowed candidate.
	 * @param string $expected Value with those candidates removed.
	 */
	public function test_candidates_with_disallowed_protocols_are_removed( string $srcset, string $expected ): void {
		$this->assertSame( $expected, wp_kses_sanitize_srcset( $srcset, $this->allowed_protocols ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_candidates_with_disallowed_protocols_are_removed(): array {
		return array(
			'first candidate'                             => array(
				'bad://localhost/test.png 1x, http://localhost/test-2x.png 2x',
				'http://localhost/test-2x.png 2x',
			),
			'last candidate'                              => array(
				'http://localhost/test.png 1x, bad://localhost/test-2x.png 2x',
				'http://localhost/test.png 1x',
			),
			'middle candidate'                            => array(
				'image-1x.jpg 1x, javascript:alert(1) 1.5x, image-2x.jpg 2x',
				'image-1x.jpg 1x, image-2x.jpg 2x',
			),
			'only candidate'                              => array(
				'javascript:alert(1) 1x',
				'',
			),
			'every candidate'                             => array(
				'javascript:alert(1) 1x, data:text/html,<script>alert(1)</script> 2x',
				'',
			),
			'two in a row'                                => array(
				'a.jpg 1x, javascript:alert(1) 2x, vbscript:msgbox(1) 3x, d.jpg 4x',
				'a.jpg 1x, d.jpg 4x',
			),
			'data URL'                                    => array(
				'data:image/png;base64,iVBORw0KGgo= 1x, https://example.com/a.jpg 2x',
				'https://example.com/a.jpg 2x',
			),
			'URL with commas and a disallowed scheme'     => array(
				'http://localhost/test.png,big 1x, bad://localhost/test.png,medium 2x',
				'http://localhost/test.png,big 1x',
			),
			'descriptor-less candidate after a comma'     => array(
				'safe.jpg, javascript:alert(1)',
				'safe.jpg',
			),
			'comma-joined URLs form one candidate'        => array(
				'safe.jpg,javascript:alert(1) 2x',
				'',
			),
			'comma attached to an invalid descriptor'     => array(
				'a.jpg 2q,javascript:alert(1) 2x',
				'a.jpg 2q',
			),
			'scheme that empties under wp_kses_bad_protocol' => array(
				'a:b:c:d:e:f:g 100w, safe.jpg 200w',
				'safe.jpg 200w',
			),
			'emptied scheme without descriptor'           => array(
				'a:b:c:d:e:f:g, safe.jpg 200w',
				'safe.jpg 200w',
			),
			'irregular separators around a removed candidate' => array(
				"  a.jpg 1x  ,\n\tjavascript:alert(1) 2x ,b.jpg 3x  ",
				'a.jpg 1x, b.jpg 3x',
			),
			'allowed scheme is kept in its original case' => array(
				'HTTPS://example.com/a.jpg 1x, javascript:alert(1) 2x',
				'HTTPS://example.com/a.jpg 1x',
			),
			'comma inside descriptor parentheses'         => array(
				'a.jpg 1x(,)z,javascript:alert(1) 2x',
				'a.jpg 1x(,)z',
			),
			'URL after descriptor parentheses that look scheme-less' => array(
				'a.jpg 1x(,)/?,javascript:alert(1) 2x',
				'a.jpg 1x(,)/?',
			),
			'unclosed parenthesis runs to the end'        => array(
				'javascript:alert(1) 1x(, a.jpg 2x',
				'',
			),
		);
	}

	/**
	 * @ticket 29807
	 */
	public function test_respects_the_given_allowed_protocols(): void {
		$srcset = 'https://example.com/a.jpg 1x, ftp://example.com/b.jpg 2x';

		$this->assertSame( $srcset, wp_kses_sanitize_srcset( $srcset, array( 'https', 'ftp' ) ) );
		$this->assertSame( 'https://example.com/a.jpg 1x', wp_kses_sanitize_srcset( $srcset, array( 'https' ) ) );
		$this->assertSame( '', wp_kses_sanitize_srcset( $srcset, array() ) );
	}
}
