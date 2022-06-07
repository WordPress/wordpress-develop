<?php

/**
 * @group formatting
 */
class Tests_Formatting_Slashit extends WP_UnitTestCase {
	public function test_backslashes_middle_numbers() {
		$this->assertSame( "\\a-!9\\a943\\b\\c", backslashit( 'a-!9a943bc' ) );
	}

	public function test_backslashes_alphas() {
		$this->assertSame( "\\a943\\b\\c", backslashit( 'a943bc' ) );
	}

	public function test_double_backslashes_leading_numbers() {
		$this->assertSame( '\\\\95', backslashit( '95' ) );
	}

	public function test_removes_trailing_slashes() {
		$this->assertSame( 'a', untrailingslashit( 'a/' ) );
		$this->assertSame( 'a', untrailingslashit( 'a////' ) );
	}

	/**
	 * @ticket 22267
	 */
	public function test_removes_trailing_backslashes() {
		$this->assertSame( 'a', untrailingslashit( 'a\\' ) );
		$this->assertSame( 'a', untrailingslashit( 'a\\\\\\\\' ) );
	}

	/**
	 * @ticket 22267
	 */
	public function test_removes_trailing_mixed_slashes() {
		$this->assertSame( 'a', untrailingslashit( 'a/\\' ) );
		$this->assertSame( 'a', untrailingslashit( 'a\\/\\///\\\\//' ) );
	}

	public function test_adds_trailing_slash() {
		$this->assertSame( 'a/', trailingslashit( 'a' ) );
	}

	public function test_does_not_add_trailing_slash_if_one_exists() {
		$this->assertSame( 'a/', trailingslashit( 'a/' ) );
	}

	/**
	 * @ticket xxxxx
	 *
	 * Related @ticket 22267
	 *
	 * Do not remove a backslash if it is non windows OS.
	 * On linux, a file or directory name can contain a backslash as a valid character.
	 * E.g. For directory name 'test\', a corresponding path could look like '/var/www/test\\'
	 * trailingslashit() must not remove the backslash or it would change the folder name.
	 */
	public function test_add_trailingslash_depending_on_win_or_nix() {
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN'){
			$this->assertSame( 'a\/', trailingslashit( 'a\\' ) );
		} else {
			$this->assertSame( 'a/', trailingslashit( 'a' ) );
		}
	}
}
