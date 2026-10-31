<?php
/**
 * Unit tests covering custom CSS validation in WP_REST_Global_Styles_Controller.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @covers WP_REST_Global_Styles_Controller::validate_custom_css
 *
 * @group restapi-global-styles
 * @group restapi
 */
class WP_REST_Global_Styles_Controller_CSS_Validation_Test extends WP_UnitTestCase {
	/**
	 * @covers WP_REST_Global_Styles_Controller::validate_custom_css
	 * @ticket 64418
	 *
	 * @dataProvider data_custom_css_allowed
	 */
	public function test_validate_custom_css_allowed( string $custom_css ) {
		$controller = new WP_REST_Global_Styles_Controller();
		$validate   = Closure::bind(
			function ( $css ) {
				return $this->validate_custom_css( $css );
			},
			$controller,
			$controller
		);

		$this->assertTrue( $validate( $custom_css ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_custom_css_allowed(): array {
		return array(
			'@property declaration'   => array(
				'@property --prop { syntax: "<custom-ident>"; inherits: true; initial-value: false; }',
			),
			'Different close tag'     => array( '</stylesheet>' ),
			'Not a style close tag'   => array( '/*</style*/' ),
			'Not a style close tag 2' => array( '/*</style_' ),
			'Empty'                   => array( '' ),
			'Short content'           => array( '/**/' ),
		);
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::validate_custom_css
	 * @ticket 64418
	 *
	 * @dataProvider data_custom_css_disallowed
	 */
	public function test_validate_custom_css( string $custom_css, string $expected_error_message ) {
		$controller = new WP_REST_Global_Styles_Controller();
		$validate   = Closure::bind(
			function ( $css ) {
				return $this->validate_custom_css( $css );
			},
			$controller,
			$controller
		);

		$result = $validate( $custom_css );
		$this->assertWPError( $result );
		$this->assertSame( $expected_error_message, $result->get_error_message() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_custom_css_disallowed(): array {
		return array(
			'style close tag'            => array( 'css…</style>…css', 'The CSS must not contain "&lt;/style&gt;".' ),
			'style close tag upper case' => array( '</STYLE>', 'The CSS must not contain "&lt;/STYLE&gt;".' ),
			'style close tag mixed case' => array( '</sTyLe>', 'The CSS must not contain "&lt;/sTyLe&gt;".' ),
			'style close tag in comment' => array( '/*</style>*/', 'The CSS must not contain "&lt;/style&gt;".' ),
			'style close tag (/)'        => array( '</style/', 'The CSS must not contain "&lt;/style/".' ),
			'style close tag (\t)'       => array( "</style\t", "The CSS must not contain \"&lt;/style\t\"." ),
			'style close tag (\f)'       => array( "</style\f", "The CSS must not contain \"&lt;/style\f\"." ),
			'style close tag (\r)'       => array( "</style\r", "The CSS must not contain \"&lt;/style\r\"." ),
			'style close tag (\n)'       => array( "</style\n", "The CSS must not contain \"&lt;/style\n\"." ),
			'style close tag (" ")'      => array( '</style ', 'The CSS must not contain "&lt;/style ".' ),
			'truncated "<"'              => array( '<', 'The CSS must not end in "&lt;".' ),
			'truncated "</"'             => array( '</', 'The CSS must not end in "&lt;/".' ),
			'truncated "</s"'            => array( '</s', 'The CSS must not end in "&lt;/s".' ),
			'truncated "</ST"'           => array( '</ST', 'The CSS must not end in "&lt;/ST".' ),
			'truncated "</sty"'          => array( '</sty', 'The CSS must not end in "&lt;/sty".' ),
			'truncated "</STYL"'         => array( '</STYL', 'The CSS must not end in "&lt;/STYL".' ),
			'truncated "</stYle"'        => array( '</stYle', 'The CSS must not end in "&lt;/stYle".' ),
		);
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::validate_custom_css
	 * @ticket 65640
	 *
	 * @dataProvider data_custom_css_non_string
	 *
	 * @param mixed $custom_css Non-string value to validate.
	 */
	public function test_validate_custom_css_non_string( $custom_css ) {
		$controller = new WP_REST_Global_Styles_Controller();
		$validate   = Closure::bind(
			function ( $css ) {
				return $this->validate_custom_css( $css );
			},
			$controller,
			$controller
		);

		$result = $validate( $custom_css );
		$this->assertWPError( $result );
		$this->assertSame( 'rest_custom_css_invalid_type', $result->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed[]>
	 */
	public static function data_custom_css_non_string(): array {
		return array(
			'array'   => array( array( 'body { color: red; }' ) ),
			'integer' => array( 123 ),
			'boolean' => array( true ),
			'null'    => array( null ),
			'object'  => array( new stdClass() ),
		);
	}
}
