<?php

class WP_HTML_Spec {
	/**
 	 * @see https://html.spec.whatwg.org/#elements-2
 	 */
 	public static function is_void_element( $tag_name ) {
 		switch ( strtoupper( $tag_name ) ) {
 			case 'AREA':
 			case 'BASE':
 			case 'BR':
 			case 'COL':
 			case 'EMBED':
 			case 'HR':
 			case 'IMG':
 			case 'INPUT':
 			case 'LINK':
 			case 'META':
 			case 'SOURCE':
 			case 'TRACK':
 			case 'WBR':
 				return true;

 			default:
 				return false;
 		}
 	}
}
