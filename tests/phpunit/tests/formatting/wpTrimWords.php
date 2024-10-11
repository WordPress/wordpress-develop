<?php

/**
 * @group formatting
 *
 * @covers ::wp_trim_words
 */
class Tests_Formatting_wpTrimWords extends WP_UnitTestCase {

	/**
	 * Long Dummy Text.
	 *
	 * @since 5.0.0
	 *
	 * @var string $long_text
	 */
	private $long_text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit mollis viverra. Pellentesque id urna euismod dolor cursus sagittis.';

	public function test_trims_to_55_by_default() {
		$trimmed = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit&hellip;';
		$this->assertSame( $trimmed, wp_trim_words( $this->long_text ) );
	}

	public function test_trims_to_10() {
		$trimmed = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius&hellip;';
		$this->assertSame( $trimmed, wp_trim_words( $this->long_text, 10 ) );
	}

	public function test_trims_to_5_and_uses_custom_more() {
		$trimmed = 'Lorem ipsum dolor sit amet,[...] Read on!';
		$this->assertSame( $trimmed, wp_trim_words( $this->long_text, 5, '[...] Read on!' ) );
	}

	public function test_strips_tags_before_trimming() {
		$text    = 'This text contains a <a href="http://wordpress.org"> link </a> to WordPress.org!';
		$trimmed = 'This text contains a link&hellip;';
		$this->assertSame( $trimmed, wp_trim_words( $text, 5 ) );
	}

	/**
	 * @ticket 18726
	 */
	public function test_strips_script_and_style_content() {
		$trimmed = 'This text contains. It should go.';

		$text = 'This text contains<script>alert(" JavaScript");</script>. It should go.';
		$this->assertSame( $trimmed, wp_trim_words( $text ) );

		$text = 'This text contains<style>#css { width:expression(alert("css")) }</style>. It should go.';
		$this->assertSame( $trimmed, wp_trim_words( $text ) );
	}

	public function test_does_not_trim_short_text() {
		$text = 'This is some short text.';
		$this->assertSame( $text, wp_trim_words( $text ) );
	}

	/**
	 * @ticket 44541
	 */
	public function test_trims_to_20_counted_by_chars() {
		switch_to_locale( 'ja_JP' );
		$expected = substr( $this->long_text, 0, 20 ) . '&hellip;';
		$actual   = wp_trim_words( $this->long_text, 20 );
		restore_previous_locale();
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 44541
	 */
	public function test_trims_to_20_counted_by_chars_with_double_width_chars() {
		switch_to_locale( 'ja_JP' );
		$text     = str_repeat( 'あ', 100 );
		$expected = str_repeat( 'あ', 19 ) . '&hellip;';
		$actual   = wp_trim_words( $text, 19 );
		restore_previous_locale();
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 47867
	 */
	public function test_works_with_non_numeric_num_words() {
		$this->assertSame( '', wp_trim_words( $this->long_text, '', '' ) );
		$this->assertSame( '', wp_trim_words( $this->long_text, 'abc', '' ) );
		$this->assertSame( '', wp_trim_words( $this->long_text, null, '' ) );
		$this->assertSame( 'Lorem ipsum dolor', wp_trim_words( $this->long_text, '3', '' ) );
	}
}
