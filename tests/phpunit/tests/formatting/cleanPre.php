<?php

/**
 * The clean_pre() removes pararaph and line break
 * tags within `<pre>` elements as part of wpautop().
 *
 * @group formatting
 * @expectedDeprecated clean_pre
 */
class Tests_Formatting_CleanPre extends WP_UnitTestCase {

	public function test_removes_self_closing_br_with_space() {
		$source = 'a b c\n<br />sldfj<br />';
		$res    = 'a b c\nsldfj';

		$this->assertSame( $res, clean_pre( $source ) );
	}

	public function test_removes_self_closing_br_without_space() {
		$source = 'a b c\n<br/>sldfj<br/>';
		$res    = 'a b c\nsldfj';
		$this->assertSame( $res, clean_pre( $source ) );
	}

	// I don't think this can ever happen in production;
	// <br> is changed to <br /> elsewhere. Left in because
	// that replacement shouldn't happen (what if you want
	// HTML 4 output?).
	public function test_removes_html_br() {
		$source = 'a b c\n<br>sldfj<br>';
		$res    = 'a b c\nsldfj';
		$this->assertSame( $res, clean_pre( $source ) );
	}

	public function test_removes_p() {
		$source = "<p>isn't this exciting!</p><p>oh indeed!</p>";
		$res    = "\nisn't this exciting!\noh indeed!";
		$this->assertSame( $res, clean_pre( $source ) );
	}
}
