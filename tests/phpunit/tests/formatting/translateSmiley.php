<?php

/**
 * Tests for the `translate_smiley()` function.
 *
 * @group formatting
 *
 * @covers ::translate_smiley
 */
class Tests_Formatting_TranslateSmiley extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'use_smilies', 1 );
		smilies_init();
	}

	/**
	 * Tests that empty matches return an empty string.
	 *
	 * @ticket 66611
	 */
	public function test_translate_smiley_empty_matches() {
		$this->assertSame( '', translate_smiley( array() ) );
	}

	/**
	 * Tests converting an image-based smiley into an img element.
	 *
	 * @ticket 66611
	 */
	public function test_translate_smiley_converts_image_smiley() {
		$actual = translate_smiley( array( ':mrgreen:' ) );

		$this->assertStringStartsWith( '<img src="', $actual );
		$this->assertStringContainsString( 'alt=":mrgreen:"', $actual );
		$this->assertStringContainsString( 'class="wp-smiley"', $actual );
		$this->assertStringContainsString( 'style="height: 1em; max-height: 1em;"', $actual );
		$this->assertStringContainsString( 'mrgreen.png', $actual );
	}

	/**
	 * Tests that smilies representing unicode emoji are returned as the emoji string.
	 *
	 * @ticket 66611
	 */
	public function test_translate_smiley_returns_emoji_unmodified() {
		$actual = translate_smiley( array( ':-)' ) );

		$this->assertSame( "\xf0\x9f\x99\x82", $actual );
	}

	/**
	 * Tests that the smilies_src filter modifies the smiley image URL.
	 *
	 * @ticket 66611
	 */
	public function test_translate_smiley_filter_smilies_src() {
		add_filter(
			'smilies_src',
			static function ( $src, $img ) {
				return 'https://cdn.example.com/smilies/' . $img;
			},
			10,
			2
		);

		$actual = translate_smiley( array( ':mrgreen:' ) );

		$this->assertStringContainsString( 'src="https://cdn.example.com/smilies/mrgreen.png"', $actual );
	}
}
