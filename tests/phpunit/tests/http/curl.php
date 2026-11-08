<?php

require_once __DIR__ . '/base.php';

/**
 * @group http
 *
 * @ticket 63914
 */
class Tests_HTTP_curl extends WP_HTTP_UnitTestCase {
	public $transport = 'curl';
}
