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
				'descriptor'   => '"ACME\\2c  Sans"',
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
			'a browser keyword'    => array(
				'font_family'  => '"-webkit-body", serif',
				'descriptor'   => '"-webkit-body"',
				'decoded_name' => '-webkit-body',
			),
			'a literal entity'     => array(
				'font_family'  => 'Tom &amp; Jerry',
				'descriptor'   => '"Tom \\26 amp\\3b  Jerry"',
				'decoded_name' => 'Tom &amp; Jerry',
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
			self::parse_descriptor_name( $stored ),
			'The first family of the stored value should keep the name.'
		);

		// Read the face back through REST.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id . '/font-faces/' . $face_id );
		$response = rest_get_server()->dispatch( $request );
		$face     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The face should be readable.' );
		$this->assertSame(
			$decoded_name,
			self::parse_descriptor_name( $face['font_face_settings']['fontFamily'] ),
			'The face should keep the name.'
		);

		// Check the JSON that the posts store.
		$family_json = json_decode( get_post( $family_id )->post_content, true );
		$this->assertSame(
			$decoded_name,
			self::parse_descriptor_name( $family_json['fontFamily'] ),
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
			self::parse_descriptor_name( $matches[1] ),
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
			'"ACME\\2c  Sans", serif, "serif"',
			$settings['typography']['fontFamilies']['theme'][0]['fontFamily'],
			'The list should keep the generic keyword and the quoted name apart.'
		);

		$entries = self::parse_list( $settings['typography']['fontFamilies']['theme'][0]['fontFamily'] );

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
			self::parse_descriptor_name( $previous ),
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
	 * A face with an earlier title format remains a duplicate.
	 *
	 * @dataProvider data_legacy_font_faces
	 *
	 * @param string $font_family Stored font family value.
	 * @param string $old_title   Title from the earlier slug format.
	 * @param string $requested   Font family value of the request.
	 */
	public function test_legacy_font_faces_remain_duplicates( $font_family, $old_title, $requested ) {
		$family_id = $this->create_font_family( 'legacy', $font_family );
		$this->create_legacy_font_face( $family_id, $font_family, $old_title );

		$response = $this->request_font_face( $family_id, $requested );

		$this->assertSame( 400, $response->get_status(), 'The earlier record should remain a duplicate.' );
		$this->assertSame( 'rest_duplicate_font_face', $response->as_error()->get_error_code() );
	}

	/**
	 * Supplies saved settings and literal titles from the earlier slug format.
	 *
	 * @return array[] Test cases.
	 */
	public function data_legacy_font_faces() {
		return array(
			'an ampersand' => array( '"Tom & Jerry"', 'tom & jerry;normal;400;100%;U+0-10FFFF', '"Tom & Jerry"' ),
			'an escape'    => array( '"Tom & Jerry"', 'tom & jerry;normal;400;100%;U+0-10FFFF', '"Tom \\26  Jerry"' ),
			'a percentage' => array( '"50% Gray"', '50% gray;normal;400;100%;U+0-10FFFF', '"50% Gray"' ),
			'a CSS escape' => array( '"\\54 om & Jerry"', '\\54 om & jerry;normal;400;100%;U+0-10FFFF', '"Tom & Jerry"' ),
		);
	}

	/**
	 * A legacy face in another family still prevents a duplicate.
	 */
	public function test_legacy_duplicate_check_includes_other_families() {
		$family_id = $this->create_font_family( 'legacy', '"Tom & Jerry"' );
		$this->create_legacy_font_face( $family_id, '"Tom & Jerry"', 'tom & jerry;normal;400;100%;U+0-10FFFF' );
		$other_id = $this->create_font_family( 'other', '"Tom & Jerry"' );

		$response = $this->request_font_face( $other_id, '"Tom & Jerry"' );

		$this->assertSame( 400, $response->get_status(), 'The other family should still prevent a duplicate.' );
		$this->assertSame( 'rest_duplicate_font_face', $response->as_error()->get_error_code() );
	}

	/**
	 * The compatibility check also reads posts after the first batch.
	 */
	public function test_legacy_duplicate_check_reads_later_batches() {
		$family_id      = $this->create_font_family( 'legacy', '"Tom & Jerry"' );
		$this->post_ids = array_merge(
			$this->post_ids,
			self::factory()->post->create_many(
				100,
				array(
					'post_type'    => 'wp_font_face',
					'post_status'  => 'publish',
					'post_parent'  => $family_id,
					'post_content' => wp_json_encode( array( 'fontFamily' => 'Other' ) ),
				)
			)
		);
		$this->create_legacy_font_face( $family_id, '"Tom & Jerry"', 'tom & jerry;normal;400;100%;U+0-10FFFF' );

		$response = $this->request_font_face( $family_id, '"Tom & Jerry"' );

		$this->assertSame( 400, $response->get_status(), 'The later batch should prevent a duplicate.' );
		$this->assertSame( 'rest_duplicate_font_face', $response->as_error()->get_error_code() );
	}

	/**
	 * The compatibility check permits a face with a different weight.
	 */
	public function test_legacy_duplicate_check_permits_a_different_weight() {
		$family_id              = $this->create_font_family( 'legacy', '"Tom & Jerry"' );
		$face_id                = $this->create_legacy_font_face( $family_id, '"Tom & Jerry"', 'tom & jerry;normal;900;100%;U+0-10FFFF' );
		$settings               = json_decode( get_post( $face_id )->post_content, true );
		$settings['fontWeight'] = '900';
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $face_id,
					'post_content' => wp_json_encode( $settings, JSON_HEX_TAG | JSON_HEX_AMP ),
				)
			)
		);

		$this->create_font_face( $family_id, '"Tom & Jerry"' );
	}

	/**
	 * A title match with different saved settings still permits a new face.
	 */
	public function test_legacy_title_collision_permits_a_different_name() {
		$family_id = $this->create_font_family( 'legacy', '"Tom & Jerry"' );
		$this->create_legacy_font_face( $family_id, '"Tom %26 Jerry"', 'tom %26 jerry;normal;400;100%;U+0-10FFFF' );

		$this->create_font_face( $family_id, '"Tom & Jerry"' );
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
	 * The REST API rejects a font family value with a control character.
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
			'a control character' => array( "A\x01B" ),
			'a delete character'  => array( "A\x7fB" ),
		);
	}

	/**
	 * The REST API stores raw text that is not valid CSS as one inert font name.
	 *
	 * An upload client sends the name from the font file as it is. The name can
	 * hold any text, so the stored value must keep the text and stay inert.
	 *
	 * @dataProvider data_raw_font_family_values
	 *
	 * @param string $font_family Raw font family value, which is also the expected name.
	 */
	public function test_rest_stores_raw_text_as_an_inert_name( $font_family ) {
		$family_id = $this->create_font_family( 'raw-' . md5( $font_family ), $font_family );
		$this->create_font_face( $family_id, $font_family );

		$settings = $this->get_settings_for_family( $family_id );
		$stored   = $settings['typography']['fontFamilies']['theme'][0]['fontFamily'];
		$entries  = self::parse_list( $stored );

		$this->assertSame(
			array(
				array(
					'type'   => 'name',
					'value'  => $font_family,
					'quoted' => true,
				),
			),
			$entries,
			'The stored value should be one name with the same text.'
		);

		$fonts = $this->get_fonts_from_settings( $settings );
		$css   = get_echo( 'wp_print_font_faces', array( $fonts ) );

		$processor = new WP_HTML_Tag_Processor( $css );
		$tags      = array();
		while ( $processor->next_tag() ) {
			$tags[] = $processor->get_tag();
		}

		$this->assertSame( array( 'STYLE' ), $tags, 'The output should hold one style element only.' );
		$this->assertStringContainsString( 'font-family:' . self::call_font_utils( 'serialize_font_family_name', $font_family ) . ';', $css, 'The output should hold the escaped name.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_raw_font_family_values() {
		return array(
			'an asterisk'            => array( 'Bodoni*' ),
			'parentheses'            => array( 'Font (Display)' ),
			'a colon'                => array( 'A:B' ),
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
			self::parse_descriptor_name( $safe['settings']['typography']['fontFamilies']['custom'][0]['fontFamily'] ),
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
	 * The REST API accepts the font name "0", which PHP reads as a false value.
	 */
	public function test_rest_accepts_the_name_zero() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param(
			'font_family_settings',
			wp_json_encode(
				array(
					'name'       => '0',
					'slug'       => '0',
					'fontFamily' => '0',
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'The family should be created.' );
		$this->assertSame( '"0"', $response->get_data()['font_family_settings']['fontFamily'] );

		$family_id        = $response->get_data()['id'];
		$this->post_ids[] = $family_id;

		$this->create_font_face( $family_id, '0' );
	}

	/**
	 * A raw name with a space at the start or the end uses the trimmed name everywhere.
	 *
	 * The display name, the preset, and the face descriptor must use the same
	 * name, so that the preset selects the face.
	 *
	 * @dataProvider data_raw_names_with_outer_spaces
	 *
	 * @param string $raw_name Raw name from the font file.
	 * @param string $expected Trimmed name.
	 */
	public function test_rest_trims_the_outer_spaces_of_a_raw_name( $raw_name, $expected ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param(
			'font_family_settings',
			wp_json_encode(
				array(
					'name'       => $raw_name,
					'slug'       => sanitize_title( $raw_name ),
					'fontFamily' => $raw_name,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'The family should be created.' );

		$family_id        = $response->get_data()['id'];
		$this->post_ids[] = $family_id;
		$this->create_font_face( $family_id, $raw_name );

		$this->assertSame( $expected, $response->get_data()['font_family_settings']['name'], 'The display name should be trimmed.' );

		$settings = $this->get_settings_for_family( $family_id );
		$preset   = self::parse_list( $settings['typography']['fontFamilies']['theme'][0]['fontFamily'] );

		$this->assertSame(
			array(
				array(
					'type'   => 'name',
					'value'  => $expected,
					'quoted' => true,
				),
			),
			$preset,
			'The preset should use the trimmed name.'
		);

		$css = get_echo( 'wp_print_font_faces', array( $this->get_fonts_from_settings( $settings ) ) );

		$this->assertStringContainsString( 'font-family:"' . $expected . '";', $css, 'The face descriptor should use the trimmed name.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_raw_names_with_outer_spaces() {
		return array(
			'a leading space'  => array( ' Leading space', 'Leading space' ),
			'a trailing space' => array( 'Trailing space ', 'Trailing space' ),
			'both ends'        => array( "\t Both ends \n", 'Both ends' ),
		);
	}

	/**
	 * KSES keeps HTML-like text in a font name for a user without `unfiltered_html`.
	 *
	 * KSES filters the `post_content` of the font family post for such a user.
	 * The serializer escapes "&", "<", and ">", so the stored JSON holds no text
	 * that KSES changes, and an entity stays literal text.
	 *
	 * @dataProvider data_html_like_names
	 *
	 * @param string $raw_name Raw name, which is also the expected decoded name.
	 */
	public function test_rest_keeps_html_like_text_without_unfiltered_html( $raw_name ) {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) {
				return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
			},
			10,
			2
		);
		// Add the KSES filters for this user. wp_set_current_user() does not run kses_init() for the same user.
		kses_init();

		$this->assertFalse( current_user_can( 'unfiltered_html' ), 'The user should not have unfiltered_html.' );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), 'KSES should filter the post content.' );

		$family_id = $this->create_font_family( 'html-like', $raw_name );
		$stored    = json_decode( get_post( $family_id )->post_content, true )['fontFamily'];

		$this->assertSame(
			array(
				array(
					'type'   => 'name',
					'value'  => $raw_name,
					'quoted' => true,
				),
			),
			self::parse_list( $stored ),
			'The stored value should keep the name.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_html_like_names() {
		return array(
			'a literal entity'       => array( 'Tom &amp; Jerry' ),
			'an ampersand'           => array( 'Tom & Jerry' ),
			'angle brackets'         => array( 'A<B>' ),
			'escaped angle brackets' => array( '&lt;b&gt;' ),
			'a closing style tag'    => array( 'Test </style> Sans' ),
			'a script element'       => array( '</style><script>alert(1)</script>' ),
			'an HTML comment'        => array( '<!-- x -->' ),
		);
	}

	/**
	 * Each raw name of the font name test guide gives the documented result.
	 *
	 * The case numbers come from the font name test guide in the description of
	 * https://github.com/WordPress/wordpress-develop/pull/13610. The upload
	 * client in core sends the raw name from the font file, so this is the
	 * upload path. Cases 84 and 85 hold invalid UTF-8, which a JSON request
	 * cannot carry. The sanitizer tests cover invalid UTF-8.
	 *
	 * @dataProvider data_font_name_guide
	 *
	 * @param string        $raw_name Raw name from the font file.
	 * @param string[]|null $expected Stored entries as "type:value", or null if the REST API rejects the family.
	 * @param string|null   $face     Decoded name of the face descriptor, or null if the REST API rejects the face.
	 */
	public function test_raw_name_gives_the_documented_result( $raw_name, $expected, $face ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param(
			'font_family_settings',
			wp_json_encode(
				array(
					'name'       => 'Guide',
					'slug'       => 'guide',
					'fontFamily' => $raw_name,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );

		if ( null === $expected ) {
			$this->assertSame( 400, $response->get_status(), 'The family should be rejected.' );
			return;
		}

		$this->assertSame( 201, $response->get_status(), 'The family should be created.' );

		$family_id        = $response->get_data()['id'];
		$this->post_ids[] = $family_id;

		$entries = self::parse_list( $response->get_data()['font_family_settings']['fontFamily'] );
		$stored  = array_map(
			static function ( $entry ) {
				return $entry['type'] . ':' . $entry['value'];
			},
			$entries
		);

		$this->assertSame( $expected, $stored, 'The stored value should hold the documented entries.' );

		$response = $this->request_font_face( $family_id, $raw_name );

		if ( null === $face ) {
			$this->assertSame( 400, $response->get_status(), 'The face should be rejected.' );
			return;
		}

		$this->assertSame( 201, $response->get_status(), 'The face should be created.' );
		$this->post_ids[] = $response->get_data()['id'];

		$this->assertSame(
			$face,
			self::parse_descriptor_name( $response->get_data()['font_face_settings']['fontFamily'] ),
			'The face should use the documented name.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_font_name_guide() {
		return array(
			// Works: the stored value and the face use the exact name.
			'case 01' => array( "O'Reilly Sans", array( "name:O'Reilly Sans" ), "O'Reilly Sans" ),
			'case 02' => array( 'O"Reilly Sans', array( 'name:O"Reilly Sans' ), 'O"Reilly Sans' ),
			'case 03' => array( "O'Reilly \"Sans\"", array( "name:O'Reilly \"Sans\"" ), "O'Reilly \"Sans\"" ),
			'case 04' => array( "Suisse BP Int'l", array( "name:Suisse BP Int'l" ), "Suisse BP Int'l" ),
			'case 05' => array( '‘Curly’ “Quotes”', array( 'name:‘Curly’ “Quotes”' ), '‘Curly’ “Quotes”' ),
			'case 06' => array( "'Leading apostrophe", array( "name:'Leading apostrophe" ), "'Leading apostrophe" ),
			'case 07' => array( 'Trailing quote"', array( 'name:Trailing quote"' ), 'Trailing quote"' ),
			'case 09' => array( 'A;B', array( 'name:A;B' ), 'A;B' ),
			'case 10' => array( 'A{B}', array( 'name:A{B}' ), 'A{B}' ),
			'case 11' => array( 'A=B', array( 'name:A=B' ), 'A=B' ),
			'case 12' => array( 'What?', array( 'name:What?' ), 'What?' ),
			'case 13' => array( 'A:B', array( 'name:A:B' ), 'A:B' ),
			'case 14' => array( 'Font (Display)', array( 'name:Font (Display)' ), 'Font (Display)' ),
			'case 15' => array( 'Font [Beta]', array( 'name:Font [Beta]' ), 'Font [Beta]' ),
			'case 16' => array( 'Font !important', array( 'name:Font !important' ), 'Font !important' ),
			'case 17' => array( 'Dr. Font', array( 'name:Dr. Font' ), 'Dr. Font' ),
			'case 18' => array( 'Font #1', array( 'name:Font #1' ), 'Font #1' ),
			'case 19' => array( 'Font @Home', array( 'name:Font @Home' ), 'Font @Home' ),
			'case 20' => array( 'Font/Slash', array( 'name:Font/Slash' ), 'Font/Slash' ),
			'case 22' => array( 'Bodoni*', array( 'name:Bodoni*' ), 'Bodoni*' ),
			'case 23' => array( 'Jost*', array( 'name:Jost*' ), 'Jost*' ),
			'case 24' => array( 'Rounded M+ 1c', array( 'name:Rounded M+ 1c' ), 'Rounded M+ 1c' ),
			'case 25' => array( 'C++ Mono', array( 'name:C++ Mono' ), 'C++ Mono' ),
			'case 26' => array( '50% Gray', array( 'name:50% Gray' ), '50% Gray' ),
			'case 27' => array( 'Font 50%AB', array( 'name:Font 50%AB' ), 'Font 50%AB' ),
			'case 28' => array( 'Font%2c Sans', array( 'name:Font%2c Sans' ), 'Font%2c Sans' ),
			'case 31' => array( 'Trailing\\', array( 'name:Trailing\\' ), 'Trailing\\' ),
			'case 33' => array( 'Tom & Jerry', array( 'name:Tom & Jerry' ), 'Tom & Jerry' ),
			'case 34' => array( 'Tom &amp; Jerry', array( 'name:Tom &amp; Jerry' ), 'Tom &amp; Jerry' ),
			'case 35' => array( 'A<B>', array( 'name:A<B>' ), 'A<B>' ),
			'case 36' => array( 'Test </style> Sans', array( 'name:Test </style> Sans' ), 'Test </style> Sans' ),
			'case 37' => array( '</style><script>alert(1)</script>', array( 'name:</style><script>alert(1)</script>' ), '</style><script>alert(1)</script>' ),
			'case 38' => array( '<!-- x -->', array( 'name:<!-- x -->' ), '<!-- x -->' ),
			'case 39' => array( 'url(javascript:alert(1))', array( 'name:url(javascript:alert(1))' ), 'url(javascript:alert(1))' ),
			'case 40' => array( 'expression(alert(1))', array( 'name:expression(alert(1))' ), 'expression(alert(1))' ),
			'case 41' => array( 'A"; color: red; x:"', array( 'name:A"; color: red; x:"' ), 'A"; color: red; x:"' ),
			'case 42' => array( 'A} body { color: red', array( 'name:A} body { color: red' ), 'A} body { color: red' ),
			'case 43' => array( '12345', array( 'name:12345' ), '12345' ),
			'case 44' => array( '0', array( 'name:0' ), '0' ),
			'case 45' => array( '-1 Font', array( 'name:-1 Font' ), '-1 Font' ),
			'case 46' => array( '1942 report', array( 'name:1942 report' ), '1942 report' ),
			'case 47' => array( 'Press Start 2P', array( 'name:Press Start 2P' ), 'Press Start 2P' ),
			'case 48' => array( '--custom', array( 'name:--custom' ), '--custom' ),
			'case 49' => array( '-apple-system', array( 'name:-apple-system' ), '-apple-system' ),
			'case 68' => array( "A\u{A0}B", array( "name:A\u{A0}B" ), "A\u{A0}B" ),
			'case 69' => array( "A\u{3000}B", array( "name:A\u{3000}B" ), "A\u{3000}B" ),
			'case 70' => array( "A\u{200B}B", array( "name:A\u{200B}B" ), "A\u{200B}B" ),
			'case 71' => array( '日本語 😀', array( 'name:日本語 😀' ), '日本語 😀' ),
			'case 72' => array( '微软雅黑', array( 'name:微软雅黑' ), '微软雅黑' ),
			'case 73' => array( 'ＭＳ ゴシック', array( 'name:ＭＳ ゴシック' ), 'ＭＳ ゴシック' ),
			'case 74' => array( 'Ñandú', array( 'name:Ñandú' ), 'Ñandú' ),
			'case 75' => array( 'Café', array( 'name:Café' ), 'Café' ),
			'case 76' => array( "Cafe\u{301}", array( "name:Cafe\u{301}" ), "Cafe\u{301}" ),
			'case 77' => array( 'وزیرمتن', array( 'name:وزیرمتن' ), 'وزیرمتن' ),
			'case 78' => array( "A\u{202E}B", array( "name:A\u{202E}B" ), "A\u{202E}B" ),
			'case 79' => array( "Dev 👩\u{200D}💻", array( "name:Dev 👩\u{200D}💻" ), "Dev 👩\u{200D}💻" ),
			'case 80' => array( str_repeat( 'A', 256 ), array( 'name:' . str_repeat( 'A', 256 ) ), str_repeat( 'A', 256 ) ),
			'case 81' => array( str_repeat( 'A\\', 2000 ), array( 'name:' . str_repeat( 'A\\', 2000 ) ), str_repeat( 'A\\', 2000 ) ),
			'case 82' => array( "A\x00B", array( "name:A\u{FFFD}B" ), "A\u{FFFD}B" ),

			// Works: core trims a space at the start or the end of the name.
			'case 64' => array( ' Leading space', array( 'name:Leading space' ), 'Leading space' ),
			'case 65' => array( 'Trailing space ', array( 'name:Trailing space' ), 'Trailing space' ),

			// Limit: CSS reads the raw name in a different way. A client that sends a quoted CSS string fixes these cases.
			'case 08' => array( 'ACME, Sans', array( 'name:ACME', 'name:Sans' ), 'ACME' ),
			'case 21' => array( 'A/*c*/B', array( 'name:A B' ), 'A B' ),
			'case 29' => array( 'Font, Sans', array( 'name:Font', 'name:Sans' ), 'Font' ),
			'case 30' => array( 'A\\B', array( "name:A\x0b" ), "A\x0b" ),
			'case 32' => array( "\\0030", array( 'name:0' ), '0' ),
			'case 50' => array( 'serif', array( 'generic:serif' ), 'serif' ),
			'case 51' => array( 'Serif', array( 'generic:serif' ), 'serif' ),
			'case 52' => array( 'sans-serif', array( 'generic:sans-serif' ), 'sans-serif' ),
			'case 53' => array( 'system-ui', array( 'generic:system-ui' ), 'system-ui' ),
			'case 54' => array( 'emoji', array( 'generic:emoji' ), 'emoji' ),
			'case 55' => array( 'fangsong', array( 'generic:fangsong' ), 'fangsong' ),
			'case 56' => array( 'inherit', array( 'keyword:inherit' ), null ),
			'case 57' => array( 'INHERIT', array( 'keyword:inherit' ), null ),
			'case 58' => array( 'initial', array( 'keyword:initial' ), null ),
			'case 59' => array( 'unset', array( 'keyword:unset' ), null ),
			'case 60' => array( 'revert-layer', array( 'keyword:revert-layer' ), null ),
			'case 61' => array( 'default', array( 'keyword:default' ), null ),
			'case 62' => array( 'generic(kai)', array( 'generic:generic(kai)' ), 'generic(kai)' ),
			'case 63' => array( 'A  B', array( 'name:A B' ), 'A B' ),
			'case 66' => array( "A\x09B", array( 'name:A B' ), 'A B' ),
			'case 67' => array( "A\x0aB", array( 'name:A B' ), 'A B' ),

			// Rejected.
			'case 83' => array( "A\x01B", null, null ),
			'case 86' => array( '', null, null ),
			'case 87' => array( ' ', null, null ),
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
	 * Creates a face with a title from the earlier slug format.
	 *
	 * @param int    $family_id   Parent font family post ID.
	 * @param string $font_family Stored font family value.
	 * @param string $old_title   Earlier font face title.
	 * @return int Font face post ID.
	 */
	private function create_legacy_font_face( $family_id, $font_family, $old_title ) {
		$id               = self::factory()->post->create(
			wp_slash(
				array(
					'post_type'    => 'wp_font_face',
					'post_status'  => 'publish',
					'post_parent'  => $family_id,
					'post_title'   => $old_title,
					'post_content' => wp_json_encode(
						array(
							'fontFamily' => $font_family,
							'fontStyle'  => 'normal',
							'fontWeight' => '400',
							'src'        => home_url( '/wp-content/fonts/legacy.woff2' ),
						),
						JSON_HEX_TAG | JSON_HEX_AMP
					),
				)
			)
		);
		$this->post_ids[] = $id;

		$this->assertSame( $font_family, json_decode( get_post( $id )->post_content, true )['fontFamily'], 'The fixture should keep the saved font name.' );

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

	/**
	 * Calls the private font family parser.
	 *
	 * @param string $value             CSS font family value.
	 * @param bool   $allow_plain_names Whether to accept plain names.
	 * @return array[]|null Parsed entries, or null if the value is invalid.
	 */
	private static function parse_list( $value, $allow_plain_names = false ) {
		return self::call_font_utils( 'parse_font_family_list', $value, $allow_plain_names );
	}

	/**
	 * Returns the decoded name of the first family in a value.
	 *
	 * @param string $value CSS font family value, or a plain font name.
	 * @return string|null The decoded name, or null if the value names no font.
	 */
	private static function parse_descriptor_name( $value ) {
		$entries = self::parse_list( $value, true );

		return null === $entries || 'keyword' === $entries[0]['type'] ? null : $entries[0]['value'];
	}

	/**
	 * Calls a private method of WP_Font_Utils.
	 *
	 * @param string $name    Method name.
	 * @param mixed  ...$args Method arguments.
	 * @return mixed The return value of the method.
	 */
	private static function call_font_utils( $name, ...$args ) {
		$method = new ReflectionMethod( 'WP_Font_Utils', $name );
		$method->setAccessible( true );

		return $method->invokeArgs( null, $args );
	}
}
