<?php

if ( version_compare( tests_get_phpunit_version(), '7.0', '>=' ) ) {
	require __DIR__ . '/phpunit7/speed-trap-listener.php';
} else {
	require __DIR__ . '/speed-trap-listener.php';
}
