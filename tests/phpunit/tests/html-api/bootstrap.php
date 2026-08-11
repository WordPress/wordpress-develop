<?php

// CSS Processor
//require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-css.php';

// Tag Processor
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-attribute-token.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-span.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-text-replacement.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-tag-processor.php';

// HTML Processor
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-unsupported-exception.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-active-formatting-elements.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-open-elements.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-token.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-processor-state.php';
require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-processor.php';

// Templating
//require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html-template.php';
//require_once __DIR__ . '/../../../../src/wp-includes/html-api/class-wp-html.php';

if ( ! defined( 'DIR_TESTDATA' ) ) {
	define( 'DIR_TESTDATA', __DIR__ . '/../../data' );
}

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
		public $caught_doing_it_wrong = array();

		public function setExpectedIncorrectUsage( $doing_it_wrong ) {

		}
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return str_replace( [ '<', '>', '"' ], [ '&lt;', '&gt;', '&quot;' ], $s );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return esc_attr( $s );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $s ) {
		return $s;
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( ...$args ) {

	}
}

if ( ! class_exists( 'HTMLProcessorDebugger' ) ) {
	class HTMLProcessorDebugger extends WP_HTML_Tag_Processor {

	}
}
