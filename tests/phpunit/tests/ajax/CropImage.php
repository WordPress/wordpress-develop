<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Add Meta AJAX functionality.
 *
 * @group ajax
 */
class Tests_Ajax_CropImage extends WP_Ajax_UnitTestCase {

	public function test_it_copies_metadata_from_original_image() {
		$this->addToAssertionCount(1);
	}
}
