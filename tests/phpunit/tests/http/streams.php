<?php

require_once __DIR__ . '/base.php';

/**
 * @group http
 * @group external-http
 */
class Tests_HTTP_streams extends WP_HTTP_UnitTestCase {
	var $transport = 'streams';
}
