<?php

// Thanks WordPress...
function is_ssl() {
	if ( isset($_SERVER['HTTPS']) ) {
		if ( 'on' == strtolower($_SERVER['HTTPS']) )
			return true;
		if ( '1' == $_SERVER['HTTPS'] )
			return true;
	} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
		return true;
	}
	return false;
}

$url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . (!empty($_SERVER['HTTP_POST']) && 80 != $_SERVER['HTTP_POST'] ? ':' . $_SERVER['HTTP_POST'] : '');
if ( strpos($_SERVER['REQUEST_URI'], '?') )
	$url .= substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
else
	$url .= $_SERVER['REQUEST_URI'];

if ( isset($_GET['source']) ) {
	highlight_file(__FILE__ );
	exit;
}

if ( isset($_GET['201-location']) ) {
	header("HTTP/1.1 201 OK");
	if ( isset($_GET['fail']) ) {
		echo "FAIL";
	} else {
		header("Location: $url?201-location&fail=true", true, 201);
		echo "PASS";
	}
	exit;
}
if ( isset($_GET['header-check']) ) {
	$out = array();
	header("Content-Type: text/plain");
	foreach ( $_SERVER as $key => $value ) {
		if ( stripos($key, 'http') === 0 ) {
			$key = strtolower(substr($key, 5));
			echo "$key:$value\n";
		}
	}
	exit;
}
if ( isset($_GET['multiple-headers']) ) {
	header("HeaderName: One", false);
	header("HeaderName: Two", false);
	header("HeaderName: Three", false);
	exit;
}

if ( isset( $_GET['post-redirect-to-method'] ) ) {
	$method = $_SERVER['REQUEST_METHOD'];
	$response_code = $_GET['response_code'] ?? 301;

	if ( 'POST' == $method && ! isset( $_GET['redirection-performed'] ) ) {
		header( "Location: $url?post-redirect-to-method=1&redirection-performed=1", true, $response_code );
		exit;
	}

	echo $method;
	exit;

}

if ( isset( $_GET['location-with-200'] ) ) {
	if ( ! isset( $_GET['redirection-performed'] ) ) {
		header( "HTTP/1.1 200 OK" );
		header( "Location: $url?location-with-200=1&redirection-performed", true, 200 );
		echo 'PASS';
		exit;
	}
	// Redirection was followed.
	echo 'FAIL';
	exit;
}

if ( isset( $_GET['print-pass'] ) ) {
	echo 'PASS';
	exit;
}

if ( isset( $_GET['multiple-location-headers'] ) ) {
	if ( ! isset( $_GET['redirected'] ) ) {
		header( "Location: $url?multiple-location-headers=1&redirected=one", false );
		header( "Location: $url?multiple-location-headers=1&redirected=two", false );
		exit;
	}
	if ( 'two' != $_GET['redirected'] )
		echo 'FAIL';
	else
		echo 'PASS';
	exit;
}

if ( isset( $_GET['cookie-test'] ) ) {
	/*
	 * Live api.wordpress.org keeps the historical Domain/Path attributes, including
	 * parent-domain acceptance via Domain=.wordpress.org.
	 *
	 * The local HTTP fixture (typically 127.0.0.1) cannot set a meaningful parent
	 * Domain attribute, so it only covers host-only / wrong-host / expired cookies.
	 * Parent-domain acceptance is covered by
	 * WP_HTTP_UnitTestCase::test_parent_domain_cookie_is_sent_on_redirect().
	 */
	$request_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
	$request_host = preg_replace( '/:\d+$/', '', $request_host );
	$is_wp_org    = (bool) preg_match( '/(^|\.)wordpress\.org$/i', $request_host );

	if ( 'test-cookie' != $_GET['cookie-test'] ) {
		if ( $is_wp_org ) {
			setcookie( 'api_test_cookie', 'value', time() + 365*24*60*60, '/core/tests/1.0/', 'api.wordpress.org' );
			setcookie( 'api_test_cookie_minimal', 'value' );
			setcookie( 'api_test_cookie_wrong_host', 'value', time() + 365*24*60*60, '/', 'example.com' );
			setcookie( 'api_test_wildcard_domain', 'value', time() + 365*24*60*60, '/', '.wordpress.org' );
			setcookie( 'api_test_cookie_expired', 'value', time() - 365*24*60*60, '/', '.wordpress.org' );
		} else {
			$request_path = parse_url( $url, PHP_URL_PATH );
			$cookie_path  = '/';
			if ( is_string( $request_path ) && '' !== $request_path && '/' !== $request_path ) {
				$cookie_path = rtrim( str_replace( '\\', '/', dirname( $request_path ) ), '/' ) . '/';
				if ( '/' !== $cookie_path[0] ) {
					$cookie_path = '/' . $cookie_path;
				}
			}

			setcookie( 'api_test_cookie', 'value', time() + 365*24*60*60, $cookie_path );
			setcookie( 'api_test_cookie_minimal', 'value' );
			setcookie( 'api_test_cookie_wrong_host', 'value', time() + 365*24*60*60, '/', 'example.com' );
			setcookie( 'api_test_cookie_expired', 'value', time() - 365*24*60*60, '/' );
		}

		header( "Location: $url?cookie-test=test-cookie" );
		exit;
	}

	if ( empty( $_COOKIE['api_test_cookie'] ) || 'value' != $_COOKIE['api_test_cookie'] )
		die( 'FAIL_NO_COOKIE' );
	if ( empty( $_COOKIE['api_test_cookie_minimal'] ) )
		die( 'FAIL_NO_MINIMAL' );
	if ( isset( $_COOKIE['api_test_cookie_wrong_host'] ) )
		die( 'FAIL_WRONG_HOST' );
	if ( $is_wp_org && empty( $_COOKIE['api_test_wildcard_domain'] ) )
		die( 'FAIL_NO_WILDCARD' );
	if ( isset( $_COOKIE['api_test_cookie_expired'] ) )
		die( 'FAIL_EXPIRED_COOKIE' );

	echo 'PASS';
	exit;
}


$rt = $_GET['rt'] ?? 5;
$r = $_GET['r'] ?? 0;

if ( $r < $rt ) {
	$code = isset($_GET['code']) ? (int)$_GET['code'] : 302;
	header("Location: $url?rt=" . $rt . "&r=" . ($r+1), true, $code);
	echo "Redirect $r of $rt";
	exit;
}
echo "Redirect $r of $rt is FINAL.<br/>";
echo "GET['rt'] = Total times to redirect. Defaults to 5.<br />";
echo "GET['r'] = Current redirection. Defaults to 0.<br />";
echo "<a href='$url?source=true'>View Source</a>";

