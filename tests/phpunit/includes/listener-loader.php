<?php

if ( version_compare( tests_get_phpunit_version(), '7.0', '>=' ) ) {
	require dirname( __FILE__ ) . '/phpunit7/speed-trap-listener.php';
} else {
	require dirname( __FILE__ ) . '/speed-trap-listener.php';
}
