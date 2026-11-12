<?php

/**
 * @group comment
 *
 * @covers ::wp_check_comment_disallowed_list
 */
class Tests_Comment_wpCheckCommentDisallowedList extends WP_UnitTestCase {

	public function test_should_return_true_when_content_matches_disallowed_keys() {
		$author       = 'Sting';
		$author_email = 'sting@example.com';
		$author_url   = 'http://example.com';
		$comment      = "There's a hole in my heart. As deep as a well. For that poor little boy. Who's stuck halfway to Hell.";
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "well\nfoo" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	/**
	 * @ticket 37208
	 */
	public function test_should_return_true_when_content_with_html_matches_disallowed_keys() {
		$author       = 'Sting';
		$author_email = 'sting@example.com';
		$author_url   = 'http://example.com';
		$comment      = "There's a hole in my heart. As deep as a well. For that poor little boy. Who's stuck <b>half</b>way to Hell.";
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "halfway\nfoo" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	/**
	 * @ticket 57207
	 */
	public function test_should_return_true_when_content_with_non_latin_words_matches_disallowed_keys() {
		$author       = 'Setup';
		$author_email = 'setup@example.com';
		$author_url   = 'http://example.com';
		$comment      = 'Установка';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "установка\nfoo" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	public function test_should_return_true_when_author_matches_disallowed_keys() {
		$author       = 'Sideshow Mel';
		$author_email = 'mel@example.com';
		$author_url   = 'http://example.com';
		$comment      = "Though we can't get him out. We'll do the next best thing.";
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "sideshow\nfoo" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	public function test_should_return_true_when_url_matches_disallowed_keys() {
		$author       = 'Rainier Wolfcastle';
		$author_email = 'rainier@wolfcastle.com';
		$author_url   = 'http://example.com';
		$comment      = 'We go on TV and sing, sing, sing.';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "example\nfoo" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	/**
	 * @ticket 37208
	 */
	public function test_should_return_true_when_link_matches_disallowed_keys() {
		$author       = 'Rainier Wolfcastle';
		$author_email = 'rainier@wolfcastle.com';
		$author_url   = 'http://example.com';
		$comment      = 'We go on TV and sing, <a href="http://example.com/spam/>sing</a>, sing.';
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', '/spam/' );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertTrue( $result );
	}

	public function test_should_return_false_when_no_match() {
		$author       = 'Krusty the Clown';
		$author_email = 'krusty@example.com';
		$author_url   = 'http://example.com';
		$comment      = "And we're sending our love down the well.";
		$author_ip    = '192.168.0.1';
		$user_agent   = '';

		update_option( 'disallowed_keys', "sideshow\nfoobar" );

		$result = wp_check_comment_disallowed_list( $author, $author_email, $author_url, $comment, $author_ip, $user_agent );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 66459
	 *
	 * @dataProvider data_unicode_compatibility_characters
	 *
	 * @param string $comment Comment content using alternative Unicode characters.
	 */
	public function test_should_return_true_when_unicode_compatibility_characters_match_disallowed_keys( $comment ) {
		if ( ! class_exists( 'Normalizer' ) ) {
			$this->markTestSkipped( 'The intl extension is not available.' );
		}

		update_option( 'disallowed_keys', 'example.com' );

		$result = wp_check_comment_disallowed_list( 'Author', 'author@test.org', '', $comment, '192.168.0.1', '' );

		$this->assertTrue( $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_unicode_compatibility_characters() {
		return array(
			'mathematical bold' => array( '<a href="http://𝐞𝐱𝐚𝐦𝐩𝐥𝐞.𝐜𝐨𝐦/">Click me</a>' ),
			'fullwidth'         => array( '<a href="http://ｅｘａｍｐｌｅ.ｃｏｍ/">Click me</a>' ),
		);
	}
}
