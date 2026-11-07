<?php

/**
 * Tests for the `_make_clickable_rel_attr()` function.
 *
 * @group formatting
 *
 * @covers ::_make_clickable_rel_attr
 */
class Tests_Formatting_MakeClickableRelAttr extends WP_UnitTestCase {

	/**
	 * Tests that external HTTP and HTTPS links receive rel="nofollow".
	 *
	 * @ticket 65819
	 */
	public function test_make_clickable_rel_attr_external_urls() {
		$this->assertSame( ' rel="nofollow"', _make_clickable_rel_attr( 'https://external.example.com/test' ) );
		$this->assertSame( ' rel="nofollow"', _make_clickable_rel_attr( 'http://external.example.com/test' ) );
	}

	/**
	 * Tests that internal links receive no rel attribute.
	 *
	 * @ticket 65819
	 */
	public function test_make_clickable_rel_attr_internal_urls() {
		$internal_url = home_url( '/some-page/' );

		$this->assertSame( '', _make_clickable_rel_attr( $internal_url ) );
	}

	/**
	 * Tests that non-http/https schemes receive no rel attribute.
	 *
	 * @ticket 65819
	 */
	public function test_make_clickable_rel_attr_non_http_schemes() {
		$this->assertSame( '', _make_clickable_rel_attr( 'mailto:user@example.com' ) );
		$this->assertSame( '', _make_clickable_rel_attr( 'tel:1234567890' ) );
		$this->assertSame( '', _make_clickable_rel_attr( 'ftp://ftp.example.com/file.zip' ) );
	}

	/**
	 * Tests that ugc is included when within the comment_text filter context.
	 *
	 * @ticket 65819
	 */
	public function test_make_clickable_rel_attr_in_comment_context() {
		$actual = '';

		add_filter(
			'comment_text',
			static function ( $text ) use ( &$actual ) {
				$actual = _make_clickable_rel_attr( 'https://external.example.com' );
				return $text;
			}
		);

		apply_filters( 'comment_text', 'Sample comment content' );

		$this->assertSame( ' rel="nofollow ugc"', $actual );
	}

	/**
	 * Tests that the make_clickable_rel filter can modify the rel value.
	 *
	 * @ticket 65819
	 */
	public function test_make_clickable_rel_attr_filter() {
		add_filter(
			'make_clickable_rel',
			static function ( $rel, $url ) {
				return $rel . ' sponsored';
			},
			10,
			2
		);

		$this->assertSame( ' rel="nofollow sponsored"', _make_clickable_rel_attr( 'https://external.example.com' ) );
	}
}
