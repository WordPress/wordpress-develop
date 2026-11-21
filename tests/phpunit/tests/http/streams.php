<?php

require_once __DIR__ . '/base.php';

/**
 * @group http
 *
 * @ticket 63914
 */
class Tests_HTTP_streams extends WP_HTTP_UnitTestCase {
	public $transport = 'streams';
}
