<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_title
 */
class Tests_Formatting_SanitizeTitle extends WP_UnitTestCase {
	public function test_strips_html() {
		$input    = 'Captain <strong>Awesome</strong>';
		$expected = 'captain-awesome';
		$this->assertSame( $expected, sanitize_title( $input ) );
	}

	public function test_titles_sanitized_to_nothing_are_replaced_with_optional_fallback() {
		$input    = '<strong></strong>';
		$fallback = 'Captain Awesome';
		$this->assertSame( $fallback, sanitize_title( $input, $fallback ) );
	}

	/**
	 * @ticket 65431
	 */
	public function test_sanitize_title_removes_emoji() {
		$title    = '🚀 Rocket Launch 🎉';
		$expected = 'rocket-launch';

		$result = sanitize_title( $title );
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 65431
	 */
	public function test_sanitize_title_removes_multiple_emoji() {
		$title    = '✨ ✨ ✨ Stars ✨ ✨ ✨';
		$expected = 'stars';

		$result = sanitize_title( $title );
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 65431
	 */
	public function test_sanitize_title_removes_emoji_with_text() {
		$title  = 'Hello 🚀 World 🎉 Test ✨';
		$result = sanitize_title( $title );

		$this->assertStringContainsString( 'hello', $result );
		$this->assertStringContainsString( 'world', $result );
		$this->assertStringContainsString( 'test', $result );
		$this->assertStringNotContainsString( '%f0%9f%9a%80', $result );
		$this->assertStringNotContainsString( '%f0%9f%8e%89', $result );
	}

	/**
	 * @ticket 65431
	 */
	public function test_sanitize_title_preserves_non_emoji_unicode() {
		$title  = 'Café résumé ñandú';
		$result = sanitize_title( $title );

		$this->assertStringContainsString( 'cafe', $result );
		$this->assertStringContainsString( 'resume', $result );
		$this->assertStringContainsString( 'nandu', $result );
	}

	/**
	 * @ticket 65431
	 */
	public function test_sanitize_title_emoji_filter_allows_override() {
		add_filter( 'remove_emoji_from_slug', '__return_false' );

		$title  = '🚀 Rocket';
		$result = sanitize_title( $title );

		remove_filter( 'remove_emoji_from_slug', '__return_false' );

		$this->assertStringContainsString( '%f0%9f%9a%80', $result );
		$this->assertStringContainsString( 'rocket', $result );
	}
}
