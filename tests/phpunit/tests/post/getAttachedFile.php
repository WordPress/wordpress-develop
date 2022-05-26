<?php

/**
 * @group post
 * @covers ::get_attached_file
 */
class Tests_Post_GetAttachedFile extends WP_UnitTestCase {

	/**
	 * @ticket 36308
	 */
	public function test_get_attached_file_with_windows_paths() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'example-page',
				'post_type'  => 'post',
			)
		);

		// Windows local file system path.
		$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_parent' => $post->ID,
				'file'        => 'C:/WWW/Sites/demo/htdocs/wordpress/wp-content/uploads/2016/03/example.jpg',
			)
		);

		$this->assertSame(
			'C:/WWW/Sites/demo/htdocs/wordpress/wp-content/uploads/2016/03/example.jpg',
			get_attached_file( $attachment->ID ),
			'Windows local filesystem paths should be equal'
		);

		// Windows network shares path.
		$attachment = self::factory()->attachment->create_and_get(
			array(
				'post_parent' => $post->ID,
				'file'        => '//ComputerName/ShareName/SubfolderName/example.txt',
			)
		);

		$this->assertSame(
			'//ComputerName/ShareName/SubfolderName/example.txt',
			get_attached_file( $attachment->ID ),
			'Network share paths should be equal'
		);
	}

}
