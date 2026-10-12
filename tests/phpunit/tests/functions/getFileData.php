<?php
/**
 * Test cases for the `get_file_data()` function.
 *
 * @group functions
 * @group file
 * @group plugins
 * @group themes
 *
 * @covers ::get_file_data
 */
class Tests_Functions_GetFileData extends WP_UnitTestCase {


	/**
	 * Tests get_file_data().
	 *
	 * @ticket 19854
	 * @ticket 42517
	 * @ticket 47186
	 *
	 * @dataProvider data_test_get_data_file
	 *
	 * @param string                $file     File path, relative to the test data directory.
	 * @param array<string, string> $headers  Default headers.
	 * @param string                $context  Context.
	 * @param array<string, string> $expected Expected headers.
	 */
	public function test_get_file_data( $file, $headers, $context, $expected ) {
		$actual = get_file_data( DIR_TESTDATA . $file, $headers, $context );

		$this->assertNotEmpty( $actual );

		foreach ( $actual as $header => $value ) {
			$this->assertSame( $expected[ $header ], $value, $header );
		}
	}

	/*
	 * Data provider for test_get_file_data().
	 *
	 * @return array<string, array{
	 *     file:     string,
	 *     headers:  array<string, string>,
	 *     context:  string,
	 *     expected: array<string, string>,
	 * }>
	 */
	public function data_test_get_data_file() {
		return array(
			'theme headers'             => array(
				'file'     => '/themedir1/default/style.css',
				'headers'  => array(
					'Name'        => 'Theme Name',
					'ThemeURI'    => 'Theme URI',
					'Description' => 'Description',
					'Version'     => 'Version',
					'Author'      => 'Author',
					'AuthorURI'   => 'Author URI',
				),
				'context'  => '',
				'expected' => array(
					'Name'        => 'WordPress Default',
					'ThemeURI'    => 'http://wordpress.org/',
					'Description' => 'The default WordPress theme based on the famous <a href="http://binarybonsai.com/kubrick/">Kubrick</a>.',
					'Version'     => '1.6',
					'Author'      => 'Michael Heilemann',
					'AuthorURI'   => 'http://binarybonsai.com/',
				),
			),
			'cr line endings'           => array(
				'file'     => '/formatting/file-header-cr-line-endings.php',
				'headers'  => array(
					'SomeHeader'  => 'Some Header',
					'Description' => 'Description',
					'Author'      => 'Author',
				),
				'context'  => '',
				'expected' => array(
					'SomeHeader'  => 'Some header value!',
					'Description' => 'This file is using CR line endings for a testcase.',
					'Author'      => 'A Very Old Mac',
				),
			),
			'php open tag prefix'       => array(
				'file'     => '/formatting/file-header-php-open-tag-prefix.php',
				'headers'  => array(
					'TemplateName' => 'Template Name',
				),
				'context'  => '',
				'expected' => array(
					'TemplateName' => 'Something',
				),
			),
			'php short open tag prefix' => array(
				'file'     => '/formatting/file-header-php-short-open-tag-prefix.php',
				'headers'  => array(
					'TemplateName' => 'Template Name',
				),
				'context'  => '',
				'expected' => array(
					'TemplateName' => 'Something',
				),
			),
		);
	}
}
