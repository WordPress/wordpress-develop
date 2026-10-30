<?php
/**
 * Unit tests covering WP_REST_URL_Details_Controller HTML parsing methods.
 *
 * These tests exercise the private parsing methods (get_title, get_icon,
 * get_description, get_image) directly via reflection and do not require
 * REST API infrastructure.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 *
 * @covers WP_REST_URL_Details_Controller
 *
 * @group url-details
 * @group restapi
 */
class Tests_REST_WpRestUrlDetailsController_Parsing extends WP_UnitTestCase {

	/**
	 * @covers WP_REST_URL_Details_Controller::get_title
	 *
	 * @ticket 54358
	 *
	 * @dataProvider data_get_title
	 *
	 * @param string $html     HTML snippet to parse.
	 * @param string $expected Expected title string.
	 */
	public function test_get_title( $html, $expected ) {
		$controller = new WP_REST_URL_Details_Controller();
		$method     = $this->get_reflective_method( 'get_title' );

		$actual = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Returns title parsing test cases.
	 *
	 * @return array
	 */
	public static function data_get_title() {
		return array(

			// Happy path for default.
			'default'                        => array(
				'<title>Testing &lt;title&gt;</title>',
				'Testing',
			),
			'with attributes'                => array(
				'<title data-test-title-attr-one="test" data-test-title-attr-two="test2">Testing &lt;title&gt;</title>',
				'Testing',
			),
			'with text whitespace'           => array(
				'<title data-test-title-attr-one="test" data-test-title-attr-two="test2">   Testing &lt;title&gt;	</title>',
				'Testing',
			),
			'with whitespace in opening tag' => array(
				'<title >Testing &lt;title&gt;: with whitespace in opening tag</title>',
				'Testing : with whitespace in opening tag',
			),
			'when whitespace in closing tag' => array(
				'<title>Testing &lt;title&gt;: with whitespace in closing tag</ title>',
				'Testing : with whitespace in closing tag',
			),
			'with other elements'            => array(
				'<meta name="viewport" content="width=device-width">
				<title>Testing &lt;title&gt;</title>
				<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />',
				'Testing',
			),
			'multiline'                      => array(
				'<title>
					Testing &lt;title&gt;
				</title>',
				'Testing',
			),

			// Unhappy paths.
			'when opening tag is malformed'  => array(
				'< title>Testing &lt;title&gt;: when opening tag is invalid</title>',
				'',
			),
		);
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::get_icon
	 *
	 * @ticket 54358
	 *
	 * @dataProvider data_get_icon
	 *
	 * @param string $html       HTML snippet to parse.
	 * @param string $expected   Expected icon URL.
	 * @param string $target_url Target URL used to resolve relative icon URLs.
	 */
	public function test_get_icon( $html, $expected, $target_url = 'https://wordpress.org' ) {
		$controller = new WP_REST_URL_Details_Controller();
		$method     = $this->get_reflective_method( 'get_icon' );

		$actual = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html ),
			$target_url
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Returns icon parsing test cases.
	 *
	 * @return array
	 */
	public static function data_get_icon() {
		return array(

			// Happy path for default.
			'default'                               => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'default with no closing whitespace'    => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico"/>',
				'https://wordpress.org/favicon.ico',
			),
			'default without self-closing'          => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico">',
				'https://wordpress.org/favicon.ico',
			),
			'default with href first'               => array(
				'<link href="https://wordpress.org/favicon.ico" rel="shortcut icon" />',
				'https://wordpress.org/favicon.ico',
			),
			'default with type last'                => array(
				'<link href="https://wordpress.org/favicon.png" rel="icon" type="image/png" />',
				'https://wordpress.org/favicon.png',
			),
			'default with type first'               => array(
				'<link type="image/png" href="https://wordpress.org/favicon.png" rel="icon" />',
				'https://wordpress.org/favicon.png',
			),
			'default with single quotes'            => array(
				'<link type="image/png" href=\'https://wordpress.org/favicon.png\' rel=\'icon\' />',
				'https://wordpress.org/favicon.png',
			),

			// Happy paths.
			'with query string'                     => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico?somequerystring=foo&another=bar" />',
				'https://wordpress.org/favicon.ico?somequerystring=foo&another=bar',
			),
			'with another link'                     => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" /><link rel="canonical" href="https://example.com">',
				'https://wordpress.org/favicon.ico',
			),
			'with multiple links'                   => array(
				'<link rel="manifest" href="/manifest.56b1cedc.json">
				<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />
				<link rel="canonical" href="https://example.com">',
				'https://wordpress.org/favicon.ico',
			),
			'relative url'                          => array(
				'<link rel="shortcut icon" href="/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'relative url no slash'                 => array(
				'<link rel="shortcut icon" href="favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'relative url with path'                => array(
				'<link rel="shortcut icon" href="favicon.ico" />',
				'https://wordpress.org/favicon.ico',
				'https://wordpress.org/my/path/here/',
			),
			'rel reverse order'                     => array(
				'<link rel="icon shortcut" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'rel icon only'                         => array(
				'<link rel="icon" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'rel icon only with whitespace'         => array(
				'<link rel=" icon " href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes'                  => array(
				'<link
					rel="icon"
					href="https://wordpress.org/favicon.ico"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes in reverse order' => array(
				'<link
					href="https://wordpress.org/favicon.ico"
					rel="icon"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes with type'        => array(
				'<link
					rel="icon"
					href="https://wordpress.org/favicon.ico"
					type="image/x-icon"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline with type first'             => array(
				'<link
					type="image/x-icon"
					rel="icon"
					href="https://wordpress.org/favicon.ico"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'with data URL x-icon type'             => array(
				'<link rel="icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=" type="image/x-icon" />',
				'data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=',
			),
			'with data URL png type'                => array(
				'<link href="data:image/png;base64,iVBORw0KGgo=" rel="icon" type="image/png" />',
				'data:image/png;base64,iVBORw0KGgo=',
			),

			// Unhappy paths.
			'empty rel'                             => array(
				'<link rel="" href="https://wordpress.org/favicon.ico" />',
				'',
			),
			'empty href'                            => array(
				'<link rel="icon" href="" />',
				'',
			),
			'no rel'                                => array(
				'<link href="https://wordpress.org/favicon.ico" />',
				'',
			),
			'link to external stylesheet'           => array(
				'<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'',
				'https://example.com',
			),
			'multiline with no href'                => array(
				'<link
					rel="icon"
					href=""
				/>',
				'',
			),
			'multiline with no rel'                 => array(
				'<link
					rel=""
					href="https://wordpress.org/favicon.ico"
				/>',
				'',
			),
		);
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::get_description
	 *
	 * @ticket 54358
	 *
	 * @dataProvider data_get_description
	 *
	 * @param string $html     HTML snippet to parse.
	 * @param string $expected Expected description string.
	 */
	public function test_get_description( $html, $expected ) {
		$controller = new WP_REST_URL_Details_Controller();

		// Parse the meta elements from the given HTML.
		$method        = $this->get_reflective_method( 'get_meta_with_content_elements' );
		$meta_elements = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);

		$method = $this->get_reflective_method( 'get_description' );
		$actual = $method->invoke( $controller, $meta_elements );
		$this->assertEqualHTML( $expected, $actual, '<body>' );
	}

	/**
	 * Returns description parsing test cases.
	 *
	 * @return array
	 */
	public static function data_get_description() {
		return array(

			// Happy paths.
			'default'                                    => array(
				'<meta name="description" content="This is a description.">',
				'This is a description.',
			),
			'with whitespace'                            => array(
				'<meta  name=" description "   content=" This is a description.  "   >',
				'This is a description.',
			),
			'with self-closing'                          => array(
				'<meta name="description" content="This is a description."/>',
				'This is a description.',
			),
			'with self-closing and whitespace'           => array(
				'<meta  name=" description "   content=" This is a description.  "   />',
				'This is a description.',
			),
			'with content first'                         => array(
				'<meta content="Content is first" name="description">',
				'Content is first',
			),
			'with single quotes'                         => array(
				'<meta name=\'description\' content=\'with single quotes\'>',
				'with single quotes',
			),
			'with another element'                       => array(
				'<meta name="description" content="This is a description."><meta name="viewport" content="width=device-width, initial-scale=1">',
				'This is a description.',
			),
			'with multiple elements'                     => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />
				<meta name="description" content="This is a description.">
				<meta name="viewport" content="width=device-width, initial-scale=1">',
				'This is a description.',
			),
			'with other attributes'                      => array(
				'<meta first="first" name="description" third="third" content="description with other attributes" fifth="fifth">',
				'description with other attributes',
			),
			'with open graph'                            => array(
				'<meta name="og:description" content="This is a OG description." />
				<meta name="description" content="This is a description.">',
				'This is a OG description.',
			),

			// Happy paths with multiline attributes.
			'with multiline attributes'                  => array(
				'<meta
					name="description"
					content="with multiline attributes"
				>',
				'with multiline attributes',
			),
			'with multiline attributes in reverse order' => array(
				'<meta
					content="with multiline attributes in reverse order"
					name="description"
				>',
				'with multiline attributes in reverse order',
			),
			'with multiline attributes and another element' => array(
				'<meta
					name="description"
					content="with multiline attributes"
				>
				<meta name="viewport" content="width=device-width, initial-scale=1">',
				'with multiline attributes',
			),
			'with multiline and other attributes'        => array(
				'<meta
					first="first"
					name="description"
					third="third"
					content="description with multiline and other attributes"
					fifth="fifth"
				>',
				'description with multiline and other attributes',
			),

			// Happy paths with HTML tags or entities in the description.
			'with HTML tags'                             => array(
				'<meta name="description" content="<strong>Description</strong>: has <em>HTML</em> tags">',
				'Description: has HTML tags',
			),
			'with content first and HTML tags'           => array(
				'<meta content="<strong>Description</strong>: has <em>HTML</em> tags" name="description">',
				'Description: has HTML tags',
			),
			// Intentionally malformed: the fifth attribute is missing its closing
			// quote and the tag's closing bracket, testing parser resilience.
			'with HTML tags, other attributes, and malformed closing' => array(
				'<meta first="first" name="description" third="third" content="<strong>Description</strong>: has <em>HTML</em> tags" fifth="fifth>',
				'Description: has HTML tags',
			),
			'with HTML entities'                         => array(
				'<meta name="description" content="The &lt;strong&gt;description&lt;/strong&gt; meta &amp; its attribute value"',
				'The description meta & its attribute value',
			),

			// Unhappy paths.
			'with empty content'                         => array(
				'<meta name="description" content="">',
				'',
			),
			'with empty name'                            => array(
				'<meta name="" content="name is empty">',
				'',
			),
			'without a name attribute'                   => array(
				'<meta content="without a name attribute">',
				'',
			),
			'without a content attribute'                => array(
				'<meta name="description">',
				'',
			),
		);
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::get_image
	 *
	 * @ticket 54358
	 *
	 * @dataProvider data_get_image
	 *
	 * @param string $html       HTML snippet to parse.
	 * @param string $expected   Expected image URL.
	 * @param string $target_url Target URL used to resolve relative image URLs.
	 */
	public function test_get_image( $html, $expected, $target_url = 'https://wordpress.org' ) {
		$controller = new WP_REST_URL_Details_Controller();

		// Parse the meta elements from the given HTML.
		$method        = $this->get_reflective_method( 'get_meta_with_content_elements' );
		$meta_elements = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);

		$method = $this->get_reflective_method( 'get_image' );
		$actual = $method->invoke( $controller, $meta_elements, $target_url );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Returns image parsing test cases.
	 *
	 * @return array
	 */
	public static function data_get_image() {
		return array(

			// Happy paths.
			'default'                                      => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with whitespace'                              => array(
				'<meta  property=" og:image "   content="  https://wordpress.org/images/myimage.jpg "  >',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with self-closing'                            => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg"/>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with self-closing and whitespace'             => array(
				'<meta  property=" og:image "   content="  https://wordpress.org/images/myimage.jpg "  />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with single quotes'                           => array(
				"<meta property='og:image' content='https://wordpress.org/images/myimage.jpg'>",
				'https://wordpress.org/images/myimage.jpg',
			),
			'without quotes'                               => array(
				'<meta property=og:image content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with url modifier'                            => array(
				'<meta property="og:image:url" content="https://wordpress.org/images/url-modifier.jpg" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/url-modifier.jpg',
			),
			'with query string'                            => array(
				'<meta property="og:image" content="https://wordpress.org/images/withquerystring.jpg?foo=bar&bar=foo" />',
				'https://wordpress.org/images/withquerystring.jpg?foo=bar&bar=foo',
			),

			// Happy paths with changing attributes order or adding attributes.
			'with content first'                           => array(
				'<meta content="https://wordpress.org/images/myimage.jpg" property="og:image">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with other attributes'                        => array(
				'<meta first="first" property="og:image" third="third" content="https://wordpress.org/images/myimage.jpg" fifth="fifth">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with other og meta'                           => array(
				'<meta property="og:image:height" content="720" />
				<meta property="og:image:alt" content="Ignore this please" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Happy paths with relative url.
			'with relative url'                            => array(
				'<meta property="og:image" content="/images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with relative url without starting slash'     => array(
				'<meta property="og:image" content="images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with relative url and path'                   => array(
				'<meta property="og:image" content="images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
				'https://wordpress.org/my/path/here/',
			),

			// Happy paths with multiline attributes.
			'with multiline attributes'                    => array(
				'<meta
					property="og:image"
					content="https://wordpress.org/images/myimage.jpg"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline attributes in reverse order'   => array(
				'<meta
					content="https://wordpress.org/images/myimage.jpg"
					property="og:image"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline attributes and other elements' => array(
				'<meta
					property="og:image:height"
					content="720"
				/>
				<meta
					property="og:image:alt"
					content="Ignore this please"
				/>
				<meta
					property="og:image"
					content="https://wordpress.org/images/myimage.jpg"
				>
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline and other attributes'          => array(
				'<meta
					first="first"
					property="og:image:url"
					third="third"
					content="https://wordpress.org/images/myimage.jpg"
					fifth="fifth"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Happy paths with HTML tags in the content.
			'with other og meta and HTML content'          => array(
				'<meta property="og:image:height" content="720" />
				<meta property="og:image:alt" content="<em>ignore this please</em>" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Unhappy paths.
			'with empty content'                           => array(
				'<meta property="og:image" content="">',
				'',
			),
			'without a property attribute'                 => array(
				'<meta content="https://wordpress.org/images/myimage.jpg">',
				'',
			),
			'without a content attribute empty property'   => array(
				'<meta property="og:image" href="https://wordpress.org/images/myimage.jpg">',
				'',
			),
		);
	}

	/**
	 * Wraps an HTML snippet in a minimal HTML document structure.
	 *
	 * The returned document includes a DOCTYPE, opening `<html>` and `<head>`
	 * tags, and a charset `<meta>` element. When `$with_body` is false the
	 * document omits `<body>` and the closing `</html>` tag, relying on the
	 * HTML parser to auto-close them. This mirrors real-world pages whose
	 * markup may be incomplete.
	 *
	 * @param string $html      The HTML to wrap.
	 * @param bool   $with_body Whether to include a body element.
	 * @return string
	 */
	private function wrap_html_in_doc( $html, $with_body = false ) {
		$doc = '<!DOCTYPE html>
				<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
				<head>
				<meta charset="utf-8" />' . $html . "\n" . '</head>';

		if ( $with_body ) {
			$doc .= '
				<body>
					<h1>Example Website</h1>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
				</body>
			</html>';
		}

		return $doc;
	}

	/**
	 * Gets reflective access to a private/protected method on
	 * the WP_REST_URL_Details_Controller class.
	 *
	 * @param string $method_name Method name for which to gain access.
	 * @return ReflectionMethod
	 * @throws ReflectionException Throws an exception if method does not exist.
	 */
	protected function get_reflective_method( $method_name ) {
		$class  = new ReflectionClass( WP_REST_URL_Details_Controller::class );
		$method = $class->getMethod( $method_name );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method;
	}
}
