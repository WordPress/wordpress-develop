<?php
/**
 * Adds function and class names from MagpieRSS, which is deprecated.
 *
 * @deprecated 3.0.0 Use SimplePie instead.
 */

/**
 * Deprecated. Use SimplePie (class-simplepie.php) instead.
 */
_deprecated_file( basename( __FILE__ ), '3.0.0', WPINC . '/class-simplepie.php' );

/**
 * Fires before MagpieRSS is loaded, to optionally replace it.
 *
 * @since 2.3.0
 * @deprecated 3.0.0
 */
do_action( 'load_feed_engine' );

class MagpieRSS {
	var $parser;
	var $current_item        = array();
	var $items               = array();
	var $channel             = array();
	var $textinput           = array();
	var $image               = array();
	var $feed_type;
	var $feed_version;
	var $stack               = array();
	var $inchannel           = false;
	var $initem              = false;
	var $incontent           = false;
	var $intextinput         = false;
	var $inimage             = false;
	var $current_field       = '';
	var $current_namespace   = false;
	var $_CONTENT_CONSTRUCTS = array();

	/**
	 * PHP5 constructor.
	 */
	function __construct( $source ) {

		# Check if PHP xml isn't compiled
		#
		if ( ! function_exists('xml_parser_create') ) {
			wp_trigger_error( '', "PHP's XML extension is not available. Please contact your hosting provider to enable PHP's XML extension." );
			return;
		}

		$parser = xml_parser_create();

		$this->parser = $parser;

		# pass in parser, and a reference to this object
		# set up handlers
		#
		xml_set_object( $this->parser, $this );
		xml_set_element_handler($this->parser,
				'feed_start_element', 'feed_end_element' );

		xml_set_character_data_handler( $this->parser, 'feed_cdata' );

		$status = xml_parse( $this->parser, $source );

		if (! $status ) {
			$errorcode = xml_get_error_code( $this->parser );
			if ( $errorcode != XML_ERROR_NONE ) {
				$xml_error = xml_error_string( $errorcode );
				$error_line = xml_get_current_line_number($this->parser);
				$error_col = xml_get_current_column_number($this->parser);
				$errormsg = "$xml_error at line $error_line, column $error_col";

				$this->error( $errormsg );
			}
		}

		xml_parser_free( $this->parser );
		unset( $this->parser );

		$this->normalize();
	}

	/**
	 * PHP4 constructor.
	 */
	public function MagpieRSS( $source ) {
		self::__construct( $source );
	}

	function feed_start_element($p, $element, &$attrs) {
		$el = $element = strtolower($element);
		$attrs = array_change_key_case($attrs, CASE_LOWER);

		// check for a namespace, and split if found
		$ns	= false;
		if ( strpos( $element, ':' ) ) {
			list($ns, $el) = explode( ':', $element, 2);
		}
		if ( $ns and $ns != 'rdf' ) {
			$this->current_namespace = $ns;
		}

		# if feed type isn't set, then this is first element of feed
		# identify feed from root element
		#
		if (!isset($this->feed_type) ) {
			if ( $el == 'rdf' ) {
				$this->feed_type = RSS;
				$this->feed_version = '1.0';
			}
			elseif ( $el == 'rss' ) {
				$this->feed_type = RSS;
				$this->feed_version = $attrs['version'];
			}
			elseif ( $el == 'feed' ) {
				$this->feed_type = ATOM;
				$this->feed_version = $attrs['version'];
				$this->inchannel = true;
			}
			return;
		}

		if ( $el == 'channel' )
		{
			$this->inchannel = true;
		}
		elseif ($el == 'item' or $el == 'entry' )
		{
			$this->initem = true;
			if ( isset($attrs['rdf:about']) ) {
				$this->current_item['about'] = $attrs['rdf:about'];
			}
		}

		// if we're in the default namespace of an RSS feed,
		//  record textinput or image fields
		elseif (
			$this->feed_type == RSS and
			$this->current_namespace == '' and
			$el == 'textinput' )
		{
			$this->intextinput = true;
		}

		elseif (
			$this->feed_type == RSS and
			$this->current_namespace == '' and
			$el == 'image' )
		{
			$this->inimage = true;
		}

		# handle atom content constructs
		elseif ( $this->feed_type == ATOM and in_array($el, $this->_CONTENT_CONSTRUCTS) )
		{
			// avoid clashing w/ RSS mod_content
			if ($el == 'content' ) {
				$el = 'atom_content';
			}

			$this->incontent = $el;

		}

		// if inside an Atom content construct (e.g. content or summary) field treat tags as text
		elseif ($this->feed_type == ATOM and $this->incontent )
		{
			// if tags are inlined, then flatten
			$attrs_str = join(' ',
					array_map(array('MagpieRSS', 'map_attrs'),
					array_keys($attrs),
					array_values($attrs) ) );

			$this->append_content( "<$element $attrs_str>"  );

			array_unshift( $this->stack, $el );
		}

		// Atom support many links per containing element.
		// Magpie treats link elements of type rel='alternate'
		// as being equivalent to RSS's simple link element.
		//
		elseif ($this->feed_type == ATOM and $el == 'link' )
		{
			if ( isset($attrs['rel']) and $attrs['rel'] == 'alternate' )
			{
				$link_el = 'link';
			}
			else {
				$link_el = 'link_' . $attrs['rel'];
			}

			$this->append($link_el, $attrs['href']);
		}
		// set stack[0] to current element
		else {
			array_unshift($this->stack, $el);
		}
	}

	function feed_cdata ($p, $text) {

		if ($this->feed_type == ATOM and $this->incontent)
		{
			$this->append_content( $text );
		}
		else {
			$current_el = join('_', array_reverse($this->stack));
			$this->append($current_el, $text);
		}
	}

	function feed_end_element ($p, $el) {
		$el = strtolower($el);

		if ( $el == 'item' or $el == 'entry' )
		{
			$this->items[] = $this->current_item;
			$this->current_item = array();
			$this->initem = false;
		}
		elseif ($this->feed_type == RSS and $this->current_namespace == '' and $el == 'textinput' )
		{
			$this->intextinput = false;
		}
		elseif ($this->feed_type == RSS and $this->current_namespace == '' and $el == 'image' )
		{
			$this->inimage = false;
		}
		elseif ($this->feed_type == ATOM and in_array($el, $this->_CONTENT_CONSTRUCTS) )
		{
			$this->incontent = false;
		}
		elseif ($el == 'channel' or $el == 'feed' )
		{
			$this->inchannel = false;
		}
		elseif ($this->feed_type == ATOM and $this->incontent  ) {
			// balance tags properly
			// note: This may not actually be necessary
			if ( $this->stack[0] == $el )
			{
				$this->append_content("</$el>");
			}
			else {
				$this->append_content("<$el />");
			}

			array_shift( $this->stack );
		}
		else {
			array_shift( $this->stack );
		}

		$this->current_namespace = false;
	}

	function concat (&$str1, $str2="") {
		if (!isset($str1) ) {
			$str1="";
		}
		$str1 .= $str2;
	}

	function append_content($text) {
		if ( $this->initem ) {
			$this->concat( $this->current_item[ $this->incontent ], $text );
		}
		elseif ( $this->inchannel ) {
			$this->concat( $this->channel[ $this->incontent ], $text );
		}
	}

	// smart append - field and namespace aware
	function append($el, $text) {
		if (!$el) {
			return;
		}
		if ( $this->current_namespace )
		{
			if ( $this->initem ) {
				$this->concat(
					$this->current_item[ $this->current_namespace ][ $el ], $text);
			}
			elseif ($this->inchannel) {
				$this->concat(
					$this->channel[ $this->current_namespace][ $el ], $text );
			}
			elseif ($this->intextinput) {
				$this->concat(
					$this->textinput[ $this->current_namespace][ $el ], $text );
			}
			elseif ($this->inimage) {
				$this->concat(
					$this->image[ $this->current_namespace ][ $el ], $text );
			}
		}
		else {
			if ( $this->initem ) {
				$this->concat(
					$this->current_item[ $el ], $text);
			}
			elseif ($this->intextinput) {
				$this->concat(
					$this->textinput[ $el ], $text );
			}
			elseif ($this->inimage) {
				$this->concat(
					$this->image[ $el ], $text );
			}
			elseif ($this->inchannel) {
				$this->concat(
					$this->channel[ $el ], $text );
			}

		}
	}

	function normalize () {
		// if atom populate rss fields
		if ( $this->is_atom() ) {
			$this->channel['description'] = $this->channel['tagline'];
			for ( $i = 0; $i < count($this->items); $i++) {
				$item = $this->items[$i];
				if ( isset($item['summary']) )
					$item['description'] = $item['summary'];
				if ( isset($item['atom_content']))
					$item['content']['encoded'] = $item['atom_content'];

				$this->items[$i] = $item;
			}
		}
		elseif ( $this->is_rss() ) {
			$this->channel['tagline'] = $this->channel['description'];
			for ( $i = 0; $i < count($this->items); $i++) {
				$item = $this->items[$i];
				if ( isset($item['description']))
					$item['summary'] = $item['description'];
				if ( isset($item['content']['encoded'] ) )
					$item['atom_content'] = $item['content']['encoded'];

				$this->items[$i] = $item;
			}
		}
	}

	function is_rss() {
		return false;
	}

	function is_atom() {
		return false;
	}

	function map_attrs($k, $v) {
		return "$k=\"$v\"";
	}

	function error( $errormsg, $lvl = E_USER_WARNING ) {
		if ( MAGPIE_DEBUG ) {
			wp_trigger_error('', $errormsg, $lvl);
		} else {
			error_log( $errormsg, 0);
		}
	}

}

if ( ! function_exists( 'fetch_rss' ) ) {
	function fetch_rss() {
		return false;
	}
}

/**
 * Retrieve URL headers and content using WP HTTP Request API.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param string $url URL to retrieve
 * @param array $headers Optional. Headers to send to the URL. Default empty string.
 * @return Snoopy style response
 */
function _fetch_remote_file($url, $headers = "" ) {
	$resp = wp_safe_remote_request( $url, array( 'headers' => $headers, 'timeout' => MAGPIE_FETCH_TIME_OUT ) );
	if ( is_wp_error($resp) ) {
		$error = array_shift($resp->errors);

		$resp = new stdClass;
		$resp->status = 500;
		$resp->response_code = 500;
		$resp->error = $error[0] . "\n"; //\n = Snoopy compatibility
		return $resp;
	}

	// Snoopy returns headers unprocessed.
	// Also note, WP_HTTP lowercases all keys, Snoopy did not.
	$return_headers = array();
	foreach ( wp_remote_retrieve_headers( $resp ) as $key => $value ) {
		if ( !is_array($value) ) {
			$return_headers[] = "$key: $value";
		} else {
			foreach ( $value as $v )
				$return_headers[] = "$key: $v";
		}
	}

	$response = new stdClass;
	$response->status = wp_remote_retrieve_response_code( $resp );
	$response->response_code = wp_remote_retrieve_response_code( $resp );
	$response->headers = $return_headers;
	$response->results = wp_remote_retrieve_body( $resp );

	return $response;
}

function _response_to_rss() {
	return false;
}

function init() {
	return;
}

function is_info() {
	return false;
}

function is_success() {
	return false;
}

function is_redirect() {
	return false;
}

function is_error() {
	return false;
}

function is_client_error() {
	return false;
}

function is_server_error() {
	return false;
}

class RSSCache {
	var $BASE_CACHE;
	var $MAX_AGE    = 43200;
	var $ERROR      = '';

	/**
	 * PHP5 constructor.
	 */
	function __construct( $base = '', $age = '' ) {
		$this->BASE_CACHE = WP_CONTENT_DIR . '/cache';
		if ( $base ) {
			$this->BASE_CACHE = $base;
		}
		if ( $age ) {
			$this->MAX_AGE = $age;
		}

	}

	/**
	 * PHP4 constructor.
	 */
	public function RSSCache( $base = '', $age = '' ) {
		self::__construct( $base, $age );
	}

	function set() {
		return '';
	}

	function get() {
		return 0;
	}

	function check_cache() {
		return 'MISS';
	}

/*=======================================================================*\
	Function:	serialize
\*=======================================================================*/
	function serialize ( $rss ) {
		return serialize( $rss );
	}

/*=======================================================================*\
	Function:	unserialize
\*=======================================================================*/
	function unserialize ( $data ) {
		return unserialize( $data );
	}

/*=======================================================================*\
	Function:	file_name
	Purpose:	map url to location in cache
	Input:		url from which the rss file was fetched
	Output:		a file name
\*=======================================================================*/
	function file_name ($url) {
		return md5( $url );
	}

/*=======================================================================*\
	Function:	error
	Purpose:	register error
\*=======================================================================*/
	function error ($errormsg, $lvl=E_USER_WARNING) {
		$this->ERROR = $errormsg;
		if ( MAGPIE_DEBUG ) {
			wp_trigger_error( '', $errormsg, $lvl);
		}
		else {
			error_log( $errormsg, 0);
		}
	}
			function debug ($debugmsg, $lvl=E_USER_NOTICE) {
		if ( MAGPIE_DEBUG ) {
			$this->error("MagpieRSS [debug] $debugmsg", $lvl);
		}
	}
}

if ( ! function_exists( 'parse_w3cdtf' ) ) {
	function parse_w3cdtf() {
		return -1;
	}
}

if ( ! function_exists( 'wp_rss' ) ) {
	function wp_rss() {
		echo '';
	}
}

if ( ! function_exists( 'get_rss' ) ) {
	function get_rss() {
		return false;
	}
}
