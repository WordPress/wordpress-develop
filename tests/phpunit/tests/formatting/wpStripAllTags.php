<?php
/**
 * Test wp_strip_all_tags()
 *
 * @group formatting
 *
 * @covers ::wp_strip_all_tags
 */
class Tests_Formatting_wpStripAllTags extends WP_UnitTestCase {

	public function test_wp_strip_all_tags() {

		$text = 'lorem<br />ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		$text = "lorem<br />\nipsum";
		$this->assertSame( "lorem\nipsum", wp_strip_all_tags( $text ) );

		// Test removing breaks is working.
		$text = 'lorem<br />ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text, true ) );

		// Test script / style tag's contents is removed.
		$text = 'lorem<script>alert(document.cookie)</script>ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		$text = "lorem<style>* { display: 'none' }</style>ipsum";
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		// Test "marlformed" markup of contents.
		$text = "lorem<style>* { display: 'none' }<script>alert( document.cookie )</script></style>ipsum";
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );
	}
}

