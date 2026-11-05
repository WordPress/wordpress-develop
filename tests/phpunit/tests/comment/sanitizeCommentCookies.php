<?php

/**
 * Tests for sanitize_comment_cookies().
 *
 * @group comment
 *
 * @covers ::sanitize_comment_cookies
 */
class Tests_Comment_SanitizeCommentCookies extends WP_UnitTestCase {

	/**
	 * Cookies before a test.
	 *
	 * @var array
	 */
	private $original_cookies;

	public function set_up() {
		parent::set_up();

		$this->original_cookies = $_COOKIE;

		unset(
			$_COOKIE[ 'comment_author_' . COOKIEHASH ],
			$_COOKIE[ 'comment_author_email_' . COOKIEHASH ],
			$_COOKIE[ 'comment_author_url_' . COOKIEHASH ]
		);

		// Remove the default filters, so that the tests only cover what the function does.
		remove_all_filters( 'pre_comment_author_name' );
		remove_all_filters( 'pre_comment_author_email' );
		remove_all_filters( 'pre_comment_author_url' );
	}

	public function tear_down() {
		$_COOKIE = $this->original_cookies;

		parent::tear_down();
	}

	/**
	 * Tests that the cookies that are not set are not created.
	 *
	 * @ticket 66494
	 */
	public function test_should_not_create_cookies_that_are_not_set() {
		sanitize_comment_cookies();

		$this->assertArrayNotHasKey( 'comment_author_' . COOKIEHASH, $_COOKIE );
		$this->assertArrayNotHasKey( 'comment_author_email_' . COOKIEHASH, $_COOKIE );
		$this->assertArrayNotHasKey( 'comment_author_url_' . COOKIEHASH, $_COOKIE );
	}

	/**
	 * Tests that values that need no change are kept.
	 *
	 * @ticket 66494
	 */
	public function test_should_keep_values_that_need_no_change() {
		$_COOKIE[ 'comment_author_' . COOKIEHASH ]       = 'Bob';
		$_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = 'bob@example.org';
		$_COOKIE[ 'comment_author_url_' . COOKIEHASH ]   = 'https://example.org/';

		sanitize_comment_cookies();

		$this->assertSame( 'Bob', $_COOKIE[ 'comment_author_' . COOKIEHASH ] );
		$this->assertSame( 'bob@example.org', $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] );
		$this->assertSame( 'https://example.org/', $_COOKIE[ 'comment_author_url_' . COOKIEHASH ] );
	}

	/**
	 * Tests that the name and the email address are unslashed and escaped for use in an attribute.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_cookies_for_attributes
	 *
	 * @param string $cookie   Name of the cookie, without the hash.
	 * @param string $value    Value of the cookie.
	 * @param string $expected Expected value.
	 */
	public function test_should_unslash_and_escape_the_name_and_email_for_attributes( $cookie, $value, $expected ) {
		$_COOKIE[ $cookie . COOKIEHASH ] = $value;

		sanitize_comment_cookies();

		$this->assertSame( $expected, $_COOKIE[ $cookie . COOKIEHASH ] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cookies_for_attributes() {
		return array(
			'name with slashed quotes' => array( 'comment_author_', 'Bob \\"The Builder\\"', 'Bob &quot;The Builder&quot;' ),
			'name with an apostrophe'  => array( 'comment_author_', "O\\'Brien", 'O&#039;Brien' ),
			'name with markup'         => array( 'comment_author_', '<b>Bob</b> & Co', '&lt;b&gt;Bob&lt;/b&gt; &amp; Co' ),
			'email with slashed quote' => array( 'comment_author_email_', "o\\'brien@example.org", 'o&#039;brien@example.org' ),
			'email with markup'        => array( 'comment_author_email_', '"><script>@example.org', '&quot;&gt;&lt;script&gt;@example.org' ),
		);
	}

	/**
	 * Tests that the URL is unslashed, and is not escaped.
	 *
	 * @ticket 66494
	 */
	public function test_should_unslash_the_url_without_escaping_it() {
		$_COOKIE[ 'comment_author_url_' . COOKIEHASH ] = 'https://example.org/?a=1&b=\\"2\\"';

		sanitize_comment_cookies();

		$this->assertSame( 'https://example.org/?a=1&b="2"', $_COOKIE[ 'comment_author_url_' . COOKIEHASH ] );
	}

	/**
	 * Tests that the values go through the filters of each field, before they are unslashed and escaped.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_cookie_filters
	 *
	 * @param string $cookie   Name of the cookie, without the hash.
	 * @param string $filter   Filter of the field.
	 * @param string $filtered Value that the filter returns.
	 * @param string $expected Expected value of the cookie.
	 */
	public function test_should_apply_the_filter_of_each_field( $cookie, $filter, $filtered, $expected ) {
		$_COOKIE[ $cookie . COOKIEHASH ] = 'original';

		$received = null;
		add_filter(
			$filter,
			static function ( $value ) use ( &$received, $filtered ) {
				$received = $value;

				return $filtered;
			}
		);

		sanitize_comment_cookies();

		$this->assertSame( 'original', $received, 'The filter should receive the value of the cookie.' );
		$this->assertSame( $expected, $_COOKIE[ $cookie . COOKIEHASH ] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_cookie_filters() {
		return array(
			'name'  => array( 'comment_author_', 'pre_comment_author_name', 'Filtered "Name"', 'Filtered &quot;Name&quot;' ),
			'email' => array( 'comment_author_email_', 'pre_comment_author_email', 'filtered"@example.org', 'filtered&quot;@example.org' ),
			'url'   => array( 'comment_author_url_', 'pre_comment_author_url', 'https://filtered.example.org/?a="b"', 'https://filtered.example.org/?a="b"' ),
		);
	}

	/**
	 * Tests that only the cookies that are set are changed.
	 *
	 * @ticket 66494
	 */
	public function test_should_only_change_the_cookies_that_are_set() {
		$_COOKIE[ 'comment_author_' . COOKIEHASH ] = '<i>Bob</i>';

		sanitize_comment_cookies();

		$this->assertSame( '&lt;i&gt;Bob&lt;/i&gt;', $_COOKIE[ 'comment_author_' . COOKIEHASH ] );
		$this->assertArrayNotHasKey( 'comment_author_email_' . COOKIEHASH, $_COOKIE );
		$this->assertArrayNotHasKey( 'comment_author_url_' . COOKIEHASH, $_COOKIE );
	}
}
