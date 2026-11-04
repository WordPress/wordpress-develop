<?php

/**
 * Tests for the insert_with_markers() function.
 *
 * @group admin
 *
 * @covers ::insert_with_markers
 */
class Tests_insert_with_markers extends WP_UnitTestCase {

	/**
	 * Path to the temporary file used for testing.
	 */
	private string $temp_file;

	public function set_up() {
		parent::set_up();
		$this->temp_file = wp_tempnam( 'markers.txt' );
	}

	public function tear_down() {
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		parent::tear_down();
	}

	/**
	 * Tests that insert_with_markers() correctly inserts or replaces content.
	 *
	 * @ticket 65137
	 *
	 * @dataProvider data_insert_with_markers
	 *
	 * @param string       $initial_content The initial content of the file.
	 * @param string       $marker          The marker to use.
	 * @param array|string $insertion       The content to insert.
	 * @param string       $expected        The expected final content of the file.
	 */
	public function test_insert_with_markers( $initial_content, $marker, $insertion, $expected ) {
		if ( '' !== $initial_content ) {
			file_put_contents( $this->temp_file, str_replace( "\n", PHP_EOL, $initial_content ) );
		}

		$result = insert_with_markers( $this->temp_file, $marker, $insertion );

		$this->assertTrue( $result, 'insert_with_markers should return true on success.' );

		$actual_content = file_get_contents( $this->temp_file );
		$actual_content = str_replace( array( "\r\n", "\r" ), "\n", $actual_content );
		$expected       = str_replace( array( "\r\n", "\r" ), "\n", $expected );

		$this->assertSame( $expected, $actual_content );
	}

 /**
  * Data provider for test_insert_with_markers.
  *
  * @return array<string, array{
  *     initial_content: string,
  *     marker:          string,
  *     insertion:       string|string[],
  *     expected:        string,
  * }>
  */
	public function data_insert_with_markers(): array {
		$wp_comments = array(
			'# The directives (lines) between "BEGIN %s" and "END %s" are',
			'# dynamically generated, and should only be modified via WordPress filters.',
			'# Any changes to the directives between these markers will be overwritten.',
		);

		$wordpress_desc = sprintf( $wp_comments[0], 'WordPress', 'WordPress' ) . "\n" . $wp_comments[1] . "\n" . $wp_comments[2];
		$test_desc      = sprintf( $wp_comments[0], 'Test', 'Test' ) . "\n" . $wp_comments[1] . "\n" . $wp_comments[2];
		$string_desc    = sprintf( $wp_comments[0], 'StringTest', 'StringTest' ) . "\n" . $wp_comments[1] . "\n" . $wp_comments[2];

		return array(
			'New file creation'                          => array(
				'initial_content' => '',
				'marker'          => 'WordPress',
				'insertion'       => array( 'Line 1', 'Line 2' ),
				'expected'        => "\n# BEGIN WordPress\n{$wordpress_desc}\nLine 1\nLine 2\n# END WordPress",
			),
			'Insertion into existing file without block' => array(
				'initial_content' => "Existing content\n",
				'marker'          => 'WordPress',
				'insertion'       => array( 'New Line' ),
				'expected'        => "Existing content\n\n# BEGIN WordPress\n{$wordpress_desc}\nNew Line\n# END WordPress",
			),
			'Replacement of existing block'              => array(
				'initial_content' => "Top\n# BEGIN WordPress\nOld\n# END WordPress\nBottom",
				'marker'          => 'WordPress',
				'insertion'       => array( 'New' ),
				'expected'        => "Top\n# BEGIN WordPress\n{$wordpress_desc}\nNew\n# END WordPress\nBottom",
			),
			'Empty insertion removes content but keeps markers' => array(
				'initial_content' => "# BEGIN Test\nContent\n# END Test",
				'marker'          => 'Test',
				'insertion'       => array(),
				'expected'        => "# BEGIN Test\n{$test_desc}\n# END Test",
			),
			'String insertion instead of array'          => array(
				'initial_content' => '',
				'marker'          => 'StringTest',
				'insertion'       => 'Single Line',
				'expected'        => "\n# BEGIN StringTest\n{$string_desc}\nSingle Line\n# END StringTest",
			),
		);
	}

	/**
	 * Tests that insert_with_markers() returns false if the file is not writable.
	 *
	 * @ticket 65137
	 */
	public function test_insert_with_markers_non_writable() {
		$non_writable = '/non/existent/dir/file.txt';
		$this->assertFalse( insert_with_markers( $non_writable, 'Test', 'Content' ) );
	}
}
