<?php

/**
 * @group post
 * @covers ::get_attached_file
 */
class Tests_Post_GetAttachedFile extends WP_UnitTestCase {

	/**
	 * @ticket 36308
	 *
	 * @dataProvider data_get_attached_file_with_windows_paths
	 *
	 * @param string $file     The file path to attach to the post.
	 * @param string $expected The expected attached file path.
	 * @param string $message  The message when an assertion fails.
	 */
	public function test_get_attached_file_with_windows_paths( $file, $expected, $message ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'example-page',
				'post_type'  => 'post',
			)
		);

		$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_parent' => $post->ID,
				'file'        => $file,
			)
		);

		$this->assertSame( $expected, get_attached_file( $attachment->ID ), $message );
	}

	/**
	 * Data provider with Windows paths.
	 *
	 * @return array
	 */
	public function data_get_attached_file_with_windows_paths() {
		return array(
			'a local path'         => array(
				'file'     => 'C:/WWW/Sites/demo/htdocs/wordpress/wp-content/uploads/2016/03/example.jpg',
				'expected' => 'C:/WWW/Sites/demo/htdocs/wordpress/wp-content/uploads/2016/03/example.jpg',
				'message'  => 'Windows local filesystem paths should be equal',
			),
			'a network share path' => array(
				'file'     => '//ComputerName/ShareName/SubfolderName/example.txt',
				'expected' => '//ComputerName/ShareName/SubfolderName/example.txt',
				'message'  => 'Network share paths should be equal',
			),
		);
	}

}
