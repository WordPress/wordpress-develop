<?php

/**
 * Test some helper utility functions of the test framework.
 *
 * @group testsuite
 */
class Tests_Utils extends WP_UnitTestCase {

	/**
	 * @covers ::test_strip_ws
	 */
	public function test_strip_ws() {
		$this->assertSame( '', strip_ws( '' ) );
		$this->assertSame( 'foo', strip_ws( 'foo' ) );
		$this->assertSame( '', strip_ws( "\r\n\t  \n\r\t" ) );

		$in  = "asdf\n";
		$in .= "asdf asdf\n";
		$in .= "asdf     asdf\n";
		$in .= "\tasdf\n";
		$in .= "\tasdf\t\n";
		$in .= "\t\tasdf\n";
		$in .= "foo bar\n\r\n";
		$in .= "foo\n";

		$expected  = "asdf\n";
		$expected .= "asdf asdf\n";
		$expected .= "asdf     asdf\n";
		$expected .= "asdf\n";
		$expected .= "asdf\n";
		$expected .= "asdf\n";
		$expected .= "foo bar\n";
		$expected .= 'foo';

		$this->assertSame( $expected, strip_ws( $in ) );

	}

	/**
	 * @covers ::mask_input_value
	 */
	public function test_mask_input_value() {
		$in = <<<EOF
<h2>Assign Authors</h2>
<p>To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as <code>admin</code>s entries.</p>
<p>If a new user is created by WordPress, the password will be set, by default, to "changeme". Quite suggestive, eh? ;)</p>
        <ol id="authors"><form action="?import=wordpress&amp;step=2&amp;id=" method="post"><input type="hidden" name="_wpnonce" value="855ae98911" /><input type="hidden" name="_wp_http_referer" value="wp-test.php" /><li>Current author: <strong>Alex Shiels</strong><br />Create user  <input type="text" value="Alex Shiels" name="user[]" maxlength="30"> <br /> or map to existing<select name="userselect[0]">
EOF;
		// _wpnonce value should be replaced with 'xxx'.
		$expected = <<<EOF
<h2>Assign Authors</h2>
<p>To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as <code>admin</code>s entries.</p>
<p>If a new user is created by WordPress, the password will be set, by default, to "changeme". Quite suggestive, eh? ;)</p>
        <ol id="authors"><form action="?import=wordpress&amp;step=2&amp;id=" method="post"><input type="hidden" name="_wpnonce" value="***" /><input type="hidden" name="_wp_http_referer" value="wp-test.php" /><li>Current author: <strong>Alex Shiels</strong><br />Create user  <input type="text" value="Alex Shiels" name="user[]" maxlength="30"> <br /> or map to existing<select name="userselect[0]">
EOF;
		$this->assertSame( $expected, mask_input_value( $in ) );
	}
}
