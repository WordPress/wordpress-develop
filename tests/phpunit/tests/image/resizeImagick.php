<?php

/**
 * @group image
 * @group media
 * @group upload
 * @group resize
 */
require_once __DIR__ . '/resize.php';

class Test_Image_Resize_Imagick extends WP_Tests_Image_Resize_UnitTestCase {

	/**
	 * Use the Imagick image editor engine
	 *
	 * @var string
	 */
	public $editor_engine = 'WP_Image_Editor_Imagick';

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		// This needs to come after the mock image editor class is loaded.
		parent::set_up();
	}
}
