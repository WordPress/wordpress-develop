<?php

/**
 * @group image
 * @group media
 * @group upload
 * @group resize
 * @group wp-image-editor-vips
 */
require_once __DIR__ . '/resize.php';

class Test_Image_Resize_Vips extends WP_Tests_Image_Resize_UnitTestCase {

	/**
	 * Use the VIPS image editor engine.
	 *
	 * @var string
	 */
	public $editor_engine = 'WP_Image_Editor_Vips';

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-vips.php';

		parent::set_up();
	}
}
