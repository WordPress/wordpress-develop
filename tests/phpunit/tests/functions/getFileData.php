<?php
/**
 * @group functions
 * @group file
 *
 * @covers ::get_file_data
 */
class Tests_Functions_GetFileData extends WP_UnitTestCase {

	/**
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data() {
		$theme_headers = array(
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Version'     => 'Version',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
		);

		$actual = get_file_data( DIR_TESTDATA . '/themedir1/default/style.css', $theme_headers );

		$expected = array(
			'Name'        => 'WordPress Default',
			'ThemeURI'    => 'http://wordpress.org/',
			'Description' => 'The default WordPress theme based on the famous <a href="http://binarybonsai.com/kubrick/">Kubrick</a>.',
			'Version'     => '1.6',
			'Author'      => 'Michael Heilemann',
			'AuthorURI'   => 'http://binarybonsai.com/',
		);

		$this->assertNotEmpty( $actual );

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	/**
	 * @ticket 19854
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data_with_cr_line_endings() {
		$headers = array(
			'SomeHeader'  => 'Some Header',
			'Description' => 'Description',
			'Author'      => 'Author',
		);

		$actual   = get_file_data( DIR_TESTDATA . '/formatting/file-header-cr-line-endings.php', $headers );
		$expected = array(
			'SomeHeader'  => 'Some header value!',
			'Description' => 'This file is using CR line endings for a testcase.',
			'Author'      => 'A Very Old Mac',
		);

		$this->assertNotEmpty( $actual );

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	/**
	 * @ticket 47186
	 * @group plugins
	 * @group themes
	 */
	public function test_get_file_data_with_php_open_tag_prefix() {
		$headers = array(
			'TemplateName' => 'Template Name',
		);

		$actual   = get_file_data( DIR_TESTDATA . '/formatting/file-header-php-open-tag-prefix.php', $headers );
		$expected = array(
			'TemplateName' => 'Something',
		);

		$this->assertNotEmpty( $actual );

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}
}
