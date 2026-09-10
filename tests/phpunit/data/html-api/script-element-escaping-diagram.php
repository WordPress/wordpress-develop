<?php

/**
 * This is the original Graphviz source for the SCRIPT tag
 * parsing behavior, used in the documentation for the HTML API.
 *
 * @see WP_HTML_Tag_Processor::escape_javascript_script_contents()
 *
 * @return string
 */
function wp_html_api_script_element_escaping_diagram_source() {
	return file_get_contents( __DIR__ . '/script-element-escaping-diagram.dot' );
}
