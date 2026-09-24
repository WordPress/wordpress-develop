<?php
/**
 * Integration tests for the font family data path of Trac #63568.
 *
 * The tests move a font name through the REST API, the post storage, the
 * theme.json settings, the font face resolver, and the generated CSS. They
 * check that the decoded name does not change on the way.
 *
 * @package WordPress
 * @subpackage Fonts
 *
 * @group restapi
 * @group fonts
 * @group font-library
 *
 * @ticket 63568
 */
class Tests_Fonts_FontFamilyDataPath extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Post IDs to delete after each test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		parent::tear_down();
	}

	/**
	 * Data provider with font family values that the defect changed.
	 *
	 * @return array
	 */
	public function data_font_family_values() {
		return array(
			'an apostrophe'        => array(
				'font_family'  => '"O\'Reilly Sans", sans-serif',
				'descriptor'   => '"O\'Reilly Sans"',
				'decoded_name' => "O'Reilly Sans",
			),
			'a plain apostrophe'   => array(
				'font_family'  => "O'Reilly Sans",
				'descriptor'   => '"O\'Reilly Sans"',
				'decoded_name' => "O'Reilly Sans",
			),
			'a comma in a name'    => array(
				'font_family'  => '"ACME, Sans", sans-serif',
				'descriptor'   => '"ACME, Sans"',
				'decoded_name' => 'ACME, Sans',
			),
			'a double quote'       => array(
				'font_family'  => '\'O"Reilly Sans\', serif',
				'descriptor'   => '"O\\"Reilly Sans"',
				'decoded_name' => 'O"Reilly Sans',
			),
			'a hexadecimal escape' => array(
				'font_family'  => '"Tom \\26  Jerry", serif',
				'descriptor'   => '"Tom \\26  Jerry"',
				'decoded_name' => 'Tom & Jerry',
			),
			'a numeric name'       => array(
				'font_family'  => '"12345", monospace',
				'descriptor'   => '"12345"',
				'decoded_name' => '12345',
			),
			'a percent sequence'   => array(
				'font_family'  => '"Font 50%AB"',
				'descriptor'   => '"Font 50%AB"',
				'decoded_name' => 'Font 50%AB',
			),
			'two spaces'           => array(
				'font_family'  => '"A  B"',
				'descriptor'   => '"A  B"',
				'decoded_name' => 'A  B',
			),
		);
	}

	/**
	 * The REST API stores and returns the font family value without loss.
	 *
	 * @dataProvider data_font_family_values
	 *
	 * @param string $font_family  Font family value to send.
	 * @param string $descriptor   Expected `@font-face` descriptor.
	 * @param string $decoded_name Expected decoded font name.
	 */
	public function test_rest_preserves_the_font_family( $font_family, $descriptor, $decoded_name ) {
		$family_id = $this->create_font_family( 'test-family', $font_family );
		$face_id   = $this->create_font_face( $family_id, $descriptor );

		// Read the family back through REST.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The family should be readable.' );

		$stored = $data['font_family_settings']['fontFamily'];
		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $stored ),
			'The first family of the stored value should keep the name.'
		);

		// Read the face back through REST.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id . '/font-faces/' . $face_id );
		$response = rest_get_server()->dispatch( $request );
		$face     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The face should be readable.' );
		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $face['font_face_settings']['fontFamily'] ),
			'The face should keep the name.'
		);

		// Check the JSON that the posts store.
		$family_json = json_decode( get_post( $family_id )->post_content, true );
		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $family_json['fontFamily'] ),
			'The stored family JSON should keep the name.'
		);

		$face_json = json_decode( get_post( $face_id )->post_content, true );
		$this->assertSame( $descriptor, $face_json['fontFamily'], 'The stored face JSON should hold the descriptor.' );
	}

	/**
	 * The generated preset CSS and `@font-face` CSS identify the same name.
	 *
	 * @dataProvider data_font_family_values
	 *
	 * @param string $font_family  Font family value to send.
	 * @param string $descriptor   Expected `@font-face` descriptor.
	 * @param string $decoded_name Expected decoded font name.
	 */
	public function test_generated_css_identifies_the_same_name( $font_family, $descriptor, $decoded_name ) {
		$family_id = $this->create_font_family( 'test-family', $font_family );
		$this->create_font_face( $family_id, $descriptor );

		$settings = $this->get_settings_for_family( $family_id );

		// The preset CSS keeps the complete family list.
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'typography' => array(
						'fontFamilies' => $settings['typography']['fontFamilies']['theme'],
					),
				),
			)
		);
		$variables  = $theme_json->get_stylesheet( array( 'variables' ) );

		$this->assertMatchesRegularExpression(
			'/--wp--preset--font-family--test-family: (.+?);/',
			$variables,
			'The preset CSS should declare the font family.'
		);
		preg_match( '/--wp--preset--font-family--test-family: (.+);\}/', $variables, $matches );

		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $matches[1] ),
			'The preset CSS should keep the name.'
		);

		// The @font-face CSS names the same family.
		$fonts = $this->get_fonts_from_settings( $settings );
		$css   = get_echo( 'wp_print_font_faces', array( $fonts ) );

		$this->assertStringContainsString(
			'font-family:' . $descriptor . ';',
			$css,
			'The @font-face CSS should hold the quoted descriptor.'
		);
	}

	/**
	 * A generic fallback keeps its type and its position in a list.
	 */
	public function test_generic_fallbacks_keep_their_type_and_order() {
		$family_id = $this->create_font_family( 'acme', '"ACME, Sans", serif, "serif"' );
		$settings  = $this->get_settings_for_family( $family_id );

		$this->assertSame(
			'"ACME, Sans", serif, "serif"',
			$settings['typography']['fontFamilies']['theme'][0]['fontFamily'],
			'The list should keep the generic keyword and the quoted name apart.'
		);

		$entries = WP_CSS_Font_Family::parse_list( $settings['typography']['fontFamilies']['theme'][0]['fontFamily'] );

		$this->assertSame( 'name', $entries[0]['type'], 'The first entry should be a name.' );
		$this->assertSame( 'generic', $entries[1]['type'], 'The second entry should be a generic family.' );
		$this->assertSame( 'name', $entries[2]['type'], 'The third entry should be a name.' );
	}

	/**
	 * Repeated saves produce stable CSS and create no duplicate face.
	 *
	 * @dataProvider data_font_family_values
	 *
	 * @param string $font_family  Font family value to send.
	 * @param string $descriptor   Expected `@font-face` descriptor.
	 * @param string $decoded_name Expected decoded font name.
	 */
	public function test_repeated_saves_are_stable( $font_family, $descriptor, $decoded_name ) {
		$family_id = $this->create_font_family( 'test-family', $font_family );
		$this->create_font_face( $family_id, $descriptor );

		$previous = null;

		for ( $cycle = 1; $cycle <= 3; $cycle++ ) {
			$request  = new WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id );
			$response = rest_get_server()->dispatch( $request );
			$current  = $response->get_data()['font_family_settings']['fontFamily'];

			if ( null !== $previous ) {
				$this->assertSame( $previous, $current, "Cycle $cycle should return the same value." );
			}

			// Send the returned value back, as an editor client does.
			$request = new WP_REST_Request( 'POST', '/wp/v2/font-families/' . $family_id );
			$request->set_param(
				'font_family_settings',
				wp_json_encode(
					array(
						'name'       => 'Test Family',
						'fontFamily' => $current,
					)
				)
			);
			$response = rest_get_server()->dispatch( $request );

			$this->assertSame( 200, $response->get_status(), "Cycle $cycle should save." );

			// A second face with the same settings is a duplicate.
			$duplicate = $this->request_font_face( $family_id, $descriptor );
			$this->assertSame( 400, $duplicate->get_status(), "Cycle $cycle should reject a duplicate face." );
			$this->assertSame( 'rest_duplicate_font_face', $duplicate->as_error()->get_error_code() );

			$previous = $current;
		}

		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $previous ),
			'The name should survive three cycles.'
		);
	}

	/**
	 * Values that write the same name with different escapes are duplicates.
	 */
	public function test_equivalent_escapes_are_duplicate_faces() {
		$family_id = $this->create_font_family( 'tom-and-jerry', '"Tom & Jerry"' );
		$this->create_font_face( $family_id, '"Tom \\26  Jerry"' );

		$response = $this->request_font_face( $family_id, '"Tom & Jerry"' );

		$this->assertSame( 400, $response->get_status(), 'An equivalent escape should be a duplicate.' );
		$this->assertSame( 'rest_duplicate_font_face', $response->as_error()->get_error_code() );
	}

	/**
	 * A name with a comma is not the same face as a list of two families.
	 */
	public function test_a_comma_in_a_name_is_not_a_list() {
		$family_id = $this->create_font_family( 'acme', '"ACME, Sans"' );
		$this->create_font_face( $family_id, '"ACME, Sans"' );

		$response = $this->request_font_face( $family_id, '"ACME", "Sans"' );

		$this->assertSame( 201, $response->get_status(), 'A list is a different face.' );
		$this->post_ids[] = $response->get_data()['id'];
	}

	/**
	 * The REST API rejects an invalid font family value.
	 *
	 * @dataProvider data_invalid_font_family_values
	 *
	 * @param string $font_family Invalid font family value.
	 */
	public function test_rest_rejects_an_invalid_font_family( $font_family ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param(
			'font_family_settings',
			wp_json_encode(
				array(
					'name'       => 'Invalid',
					'slug'       => 'invalid',
					'fontFamily' => $font_family,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'The family should be rejected.' );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_invalid_font_family_values() {
		return array(
			'generic injection'      => array( 'generic(\\29\\3b color\\3a red)' ),
			'a second declaration'   => array( '"A"; color:red' ),
			'a javascript url'       => array( 'url(javascript:alert(1))' ),
			'an expression function' => array( 'expression(alert(1))' ),
			'markup'                 => array( "Rock 3D</style><script>alert('XSS');</script>" ),
			'a rule injection'       => array( 'Inter}body{color:red}' ),
			'an unterminated string' => array( '"Inter' ),
		);
	}

	/**
	 * A font family value with markup cannot create an HTML element in the output.
	 */
	public function test_a_name_with_markup_stays_inert() {
		$family_id = $this->create_font_family( 'inert', '"</style><script>alert(1)</script>"' );
		$this->create_font_face( $family_id, '"</style><script>alert(1)</script>"' );

		$settings = $this->get_settings_for_family( $family_id );
		$fonts    = $this->get_fonts_from_settings( $settings );
		$css      = get_echo( 'wp_print_font_faces', array( $fonts ) );

		$this->assertStringNotContainsString( '<script', $css, 'The output should not contain a script start tag.' );
		$this->assertStringContainsString( '\\3c /style\\3e ', $css, 'The name should use a CSS escape for "<".' );

		$processor = new WP_HTML_Tag_Processor( $css );
		$tags      = array();
		while ( $processor->next_tag() ) {
			$tags[] = $processor->get_tag();
		}

		$this->assertSame( array( 'STYLE' ), $tags, 'The output should hold one style element only.' );
	}

	/**
	 * Theme JSON keeps a valid font family preset for a user without `unfiltered_html`.
	 *
	 * @dataProvider data_font_family_values
	 *
	 * @param string $font_family  Font family value to send.
	 * @param string $descriptor   Expected `@font-face` descriptor.
	 * @param string $decoded_name Expected decoded font name.
	 */
	public function test_theme_json_keeps_a_valid_preset( $font_family, $descriptor, $decoded_name ) {
		$sanitized = WP_Font_Utils::sanitize_font_family( $font_family );

		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'typography' => array(
						'fontFamilies' => array(
							array(
								'name'       => 'Test Family',
								'slug'       => 'test-family',
								'fontFamily' => $sanitized,
							),
						),
					),
				),
			),
			'custom'
		);

		$safe = WP_Theme_JSON::remove_insecure_properties( $theme_json->get_raw_data(), 'custom' );

		$this->assertArrayHasKey( 'settings', $safe, 'The settings should survive the security filter.' );
		$this->assertSame(
			$sanitized,
			$safe['settings']['typography']['fontFamilies']['custom'][0]['fontFamily'],
			'The preset value should not change.'
		);
		$this->assertSame(
			$decoded_name,
			WP_CSS_Font_Family::parse_descriptor_name( $safe['settings']['typography']['fontFamilies']['custom'][0]['fontFamily'] ),
			'The preset should keep the name.'
		);
	}

	/**
	 * A record that an earlier WordPress version wrote still resolves.
	 */
	public function test_a_legacy_record_still_resolves() {
		// WordPress 6.5.0 wrote this value for the plain name `O'Reilly Sans`.
		$family_id = self::factory()->post->create(
			wp_slash(
				array(
					'post_type'    => 'wp_font_family',
					'post_status'  => 'publish',
					'post_title'   => "O'Reilly Sans",
					'post_name'    => 'oreilly-sans',
					'post_content' => wp_json_encode( array( 'fontFamily' => '"O\'Reilly Sans"' ) ),
				)
			)
		);

		$this->post_ids[] = $family_id;

		$face_settings    = array(
			'fontFamily' => "O'Reilly Sans",
			'fontWeight' => '400',
			'fontStyle'  => 'normal',
			'src'        => home_url( '/wp-content/fonts/oreilly-sans.woff2' ),
		);
		$title            = WP_Font_Utils::get_font_face_slug( $face_settings );
		$face_id          = self::factory()->post->create(
			wp_slash(
				array(
					'post_type'    => 'wp_font_face',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => sanitize_title( $title ),
					'post_content' => wp_json_encode( $face_settings ),
					'post_parent'  => $family_id,
				)
			)
		);
		$this->post_ids[] = $face_id;

		$settings = $this->get_settings_for_family( $family_id );
		$fonts    = $this->get_fonts_from_settings( $settings );
		$css      = get_echo( 'wp_print_font_faces', array( $fonts ) );

		$this->assertStringContainsString(
			'font-family:"O\'Reilly Sans";',
			$css,
			'The legacy record should produce a valid quoted descriptor.'
		);
	}

	/**
	 * Creates a font family through the REST API.
	 *
	 * @param string $slug        Font family slug.
	 * @param string $font_family Font family value.
	 * @return int The font family post ID.
	 */
	private function create_font_family( $slug, $font_family ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param(
			'font_family_settings',
			wp_json_encode(
				array(
					'name'       => 'Test Family',
					'slug'       => $slug,
					'fontFamily' => $font_family,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'The family should be created.' );

		$id               = $response->get_data()['id'];
		$this->post_ids[] = $id;

		return $id;
	}

	/**
	 * Creates a font face through the REST API.
	 *
	 * @param int    $family_id   Parent font family post ID.
	 * @param string $font_family Font family value of the face.
	 * @return int The font face post ID.
	 */
	private function create_font_face( $family_id, $font_family ) {
		$response = $this->request_font_face( $family_id, $font_family );

		$this->assertSame( 201, $response->get_status(), 'The face should be created.' );

		$id               = $response->get_data()['id'];
		$this->post_ids[] = $id;

		return $id;
	}

	/**
	 * Sends a font face create request.
	 *
	 * @param int    $family_id   Parent font family post ID.
	 * @param string $font_family Font family value of the face.
	 * @return WP_REST_Response The response.
	 */
	private function request_font_face( $family_id, $font_family ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families/' . $family_id . '/font-faces' );
		$request->set_param(
			'font_face_settings',
			wp_json_encode(
				array(
					'fontFamily' => $font_family,
					'fontWeight' => '400',
					'fontStyle'  => 'normal',
					'src'        => home_url( '/wp-content/fonts/test-font.woff2' ),
				)
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Builds theme.json settings from the stored font family and its faces.
	 *
	 * @param int $family_id Font family post ID.
	 * @return array The theme.json settings.
	 */
	private function get_settings_for_family( $family_id ) {
		$family = get_post( $family_id );
		$json   = json_decode( $family->post_content, true );

		$font_faces = array();
		foreach ( get_children(
			array(
				'post_parent' => $family_id,
				'post_type'   => 'wp_font_face',
			)
		) as $face ) {
			$font_faces[] = json_decode( $face->post_content, true );
		}

		$definition = array(
			'name'       => $family->post_title,
			'slug'       => $family->post_name,
			'fontFamily' => $json['fontFamily'],
		);

		if ( ! empty( $font_faces ) ) {
			$definition['fontFace'] = $font_faces;
		}

		return array(
			'typography' => array(
				'fontFamilies' => array(
					'theme' => array( $definition ),
				),
			),
		);
	}

	/**
	 * Resolves the font faces of the given settings through the normal core path.
	 *
	 * @param array $settings The theme.json settings.
	 * @return array The resolved fonts.
	 */
	private function get_fonts_from_settings( $settings ) {
		$filter = static function ( $theme_json ) use ( $settings ) {
			$data = $theme_json->get_data();
			$data['settings']['typography']['fontFamilies']['theme'] = $settings['typography']['fontFamilies']['theme'];

			return new WP_Theme_JSON_Data( $data );
		};

		add_filter( 'wp_theme_json_data_theme', $filter );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		remove_filter( 'wp_theme_json_data_theme', $filter );
		WP_Theme_JSON_Resolver::clean_cached_data();

		return $fonts;
	}
}
