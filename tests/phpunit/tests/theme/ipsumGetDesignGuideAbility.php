<?php

/**
 * @group themes
 * @group abilities-api
 *
 * @covers ::ipsum_register_abilities
 * @covers ::ipsum_get_design_guide
 */
class Tests_Theme_IpsumGetDesignGuideAbility extends WP_UnitTestCase {

	/**
	 * The name of the ability under test.
	 *
	 * @var string
	 */
	const ABILITY_NAME = 'ipsum/get-design-guide';

	/**
	 * An administrator, who can edit theme options.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The ability categories these tests registered, to remove afterwards.
	 *
	 * @var string[]
	 */
	private static $registered_categories = array();

	/**
	 * The active theme before the current test ran.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
	}

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Ipsum isn't the active theme in the test suite, so its functions.php isn't loaded.
		$abilities = WP_CONTENT_DIR . '/themes/ipsum/inc/abilities.php';
		if ( ! file_exists( $abilities ) ) {
			return;
		}
		require_once $abilities;

		global $wp_current_filter;

		/*
		 * The test suite unhooks core's ability categories, and the ability belongs to `site`.
		 * Pop in `finally` so a setup exception can't leave the simulated action on the stack.
		 */
		$categories_before   = array_keys( wp_get_ability_categories() );
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		try {
			if ( ! wp_has_ability_category( 'site' ) ) {
				wp_register_core_ability_categories();
			}
		} finally {
			array_pop( $wp_current_filter );
		}
		self::$registered_categories = array_diff( array_keys( wp_get_ability_categories() ), $categories_before );

		$wp_current_filter[] = 'wp_abilities_api_init';
		try {
			ipsum_register_abilities();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	public static function tear_down_after_class(): void {
		if ( wp_has_ability( self::ABILITY_NAME ) ) {
			wp_unregister_ability( self::ABILITY_NAME );
		}
		foreach ( self::$registered_categories as $category ) {
			wp_unregister_ability_category( $category );
		}
		self::$registered_categories = array();

		parent::tear_down_after_class();
	}

	public function set_up() {
		parent::set_up();

		$this->original_stylesheet = get_stylesheet();

		if ( ! function_exists( 'ipsum_register_abilities' ) ) {
			$this->markTestSkipped( 'The Ipsum theme is not installed.' );
		}

		switch_theme( 'ipsum' );
		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the ability is registered as a read-only site ability that the REST API exposes.
	 *
	 * @ticket 66678
	 */
	public function test_ability_is_registered() {
		$ability = wp_get_ability( self::ABILITY_NAME );

		$this->assertInstanceOf( WP_Ability::class, $ability, 'The ability should be registered.' );
		$this->assertSame( 'site', $ability->get_category(), 'The ability should be in the site category.' );
		$this->assertSame( array( 'edit_theme_options' ), $ability->get_required_capabilities(), 'The ability should need edit_theme_options.' );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ), 'The ability should be shown in the REST API.' );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'], 'The ability should be annotated as read-only.' );
		$this->assertFalse( $annotations['destructive'], 'The ability should be annotated as not destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'The ability should be annotated as idempotent.' );
	}

	/**
	 * Tests that the input schema offers every output section, and that every section is described.
	 *
	 * @ticket 66678
	 */
	public function test_schemas_list_and_describe_every_section() {
		$ability       = wp_get_ability( self::ABILITY_NAME );
		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertSame( array(), $input_schema['default'], 'The input should default to an empty object.' );
		$this->assertFalse( $input_schema['additionalProperties'], 'The input should only accept fields.' );
		$this->assertSame( array_keys( $output_schema['properties'] ), $input_schema['properties']['fields']['items']['enum'], 'Fields should offer every output section.' );

		foreach ( $output_schema['properties'] as $section => $section_schema ) {
			$this->assertNotEmpty( $section_schema['title'] ?? '', "The $section section should have a title." );
			$this->assertNotEmpty( $section_schema['description'] ?? '', "The $section section should have a description." );
		}
	}

	/**
	 * Tests that the basic sections are returned when no fields, or no fields at all, are asked for.
	 *
	 * @ticket 66678
	 *
	 * @dataProvider data_inputs_without_fields
	 *
	 * @param array|null $input The ability input.
	 */
	public function test_returns_the_basic_sections_by_default( $input ) {
		$result = wp_get_ability( self::ABILITY_NAME )->execute( $input );

		$this->assertIsArray( $result, 'The ability should return a guide that matches its output schema.' );
		$this->assertSameSets( ipsum_get_design_guide_default_fields(), array_keys( $result ), 'The guide should hold the basic sections and nothing else.' );
		$this->assertArrayNotHasKey( 'patterns', $result, 'The guide should leave out the larger lists by default.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: array|null }>
	 */
	public static function data_inputs_without_fields() {
		return array(
			'no input'     => array( null ),
			'empty input'  => array( array() ),
			'empty fields' => array( array( 'fields' => array() ) ),
		);
	}

	/**
	 * Tests that each section can be asked for on its own, and matches the output schema.
	 *
	 * @ticket 66678
	 */
	public function test_returns_each_section_on_its_own() {
		$ability  = wp_get_ability( self::ABILITY_NAME );
		$sections = $ability->get_input_schema()['properties']['fields']['items']['enum'];

		foreach ( $sections as $section ) {
			$result = $ability->execute( array( 'fields' => array( $section ) ) );

			$this->assertIsArray( $result, "The $section section should match the output schema." );
			$this->assertSame( array( $section ), array_keys( $result ), "Asking for $section should return only that section." );
		}
	}

	/**
	 * Tests that asking for several sections returns just those.
	 *
	 * @ticket 66678
	 */
	public function test_returns_only_the_requested_sections() {
		$result = wp_get_ability( self::ABILITY_NAME )->execute( array( 'fields' => array( 'colors', 'patterns' ) ) );

		$this->assertIsArray( $result, 'The ability should return a guide that matches its output schema.' );
		$this->assertSameSets( array( 'colors', 'patterns' ), array_keys( $result ), 'The guide should hold only the requested sections.' );
	}

	/**
	 * Tests that input the schema doesn't allow is rejected.
	 *
	 * @ticket 66678
	 *
	 * @dataProvider data_invalid_inputs
	 *
	 * @param mixed $input The ability input.
	 */
	public function test_rejects_invalid_input( $input ) {
		$result = wp_get_ability( self::ABILITY_NAME )->execute( $input );

		$this->assertWPError( $result, 'Invalid input should be rejected.' );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code(), 'The error should be about the input.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: mixed }>
	 */
	public static function data_invalid_inputs() {
		return array(
			'unknown section'           => array( array( 'fields' => array( 'not-a-section' ) ) ),
			'unknown property'          => array( array( 'sections' => array( 'colors' ) ) ),
			'unknown section in a list' => array( array( 'fields' => 'colors,not-a-section' ) ),
		);
	}

	/**
	 * Tests that people who can't edit theme options can't get the guide.
	 *
	 * @ticket 66678
	 *
	 * @dataProvider data_roles_without_edit_theme_options
	 *
	 * @param string $role The user's role, or an empty string for a logged-out visitor.
	 */
	public function test_denies_users_who_cannot_edit_theme_options( $role ) {
		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( array( 'role' => $role ) ) );

		$ability = wp_get_ability( self::ABILITY_NAME );
		$result  = $ability->execute();

		$this->assertFalse( $ability->check_permissions(), 'The permission check should fail.' );
		$this->assertWPError( $result, 'The ability should not run.' );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), 'The error should be about permissions.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public static function data_roles_without_edit_theme_options() {
		return array(
			'logged out'  => array( '' ),
			'subscriber'  => array( 'subscriber' ),
			'contributor' => array( 'contributor' ),
			'author'      => array( 'author' ),
			'editor'      => array( 'editor' ),
		);
	}

	/**
	 * Tests that a user given edit_theme_options can get the guide, whatever their role.
	 *
	 * @ticket 66678
	 */
	public function test_allows_users_given_edit_theme_options() {
		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$user->add_cap( 'edit_theme_options' );

		$ability = wp_get_ability( self::ABILITY_NAME );

		$this->assertTrue( $ability->check_permissions(), 'The permission check should pass.' );
		$this->assertIsArray( $ability->execute(), 'The ability should run.' );
	}

	/**
	 * Tests running the ability through the REST API, with and without fields.
	 *
	 * @ticket 66678
	 */
	public function test_runs_through_the_rest_api() {
		$server = $this->set_up_rest_server();

		$request  = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . self::ABILITY_NAME . '/run' );
		$response = $server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'A read-only ability should run with GET.' );
		$this->assertSameSets( ipsum_get_design_guide_default_fields(), array_keys( $response->get_data() ), 'The response should hold the basic sections.' );

		$request->set_query_params( array( 'input' => array( 'fields' => array( 'templates' ) ) ) );
		$response = $server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The ability should run with fields.' );
		$this->assertSame( array( 'templates' ), array_keys( $response->get_data() ), 'The response should hold only the requested section.' );
	}

	/**
	 * Tests that the REST API doesn't run the ability for a logged-out visitor.
	 *
	 * @ticket 66678
	 */
	public function test_rest_api_denies_logged_out_visitors() {
		$server = $this->set_up_rest_server();
		wp_set_current_user( 0 );

		$response = $server->dispatch( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . self::ABILITY_NAME . '/run' ) );

		$this->assertSame( 401, $response->get_status(), 'A logged-out visitor should not run the ability.' );
	}

	/**
	 * Tests that the guide follows the styles saved on the site, such as a chosen color variation.
	 *
	 * @ticket 66678
	 */
	public function test_follows_the_saved_global_styles() {
		$before = wp_get_ability( self::ABILITY_NAME )->execute( array( 'fields' => array( 'colorScheme', 'colors' ) ) );
		$this->assertSame( 'light', $before['colorScheme'], 'The default palette should be light.' );

		// Save the Evening variation as the site's styles, as choosing it in the Site Editor does.
		$variation = wp_json_file_decode( get_theme_file_path( 'styles/colors/06-evening.json' ), array( 'associative' => true ) );
		$this->assertSame( 'dark', $variation['settings']['custom']['controlScheme'], 'Evening should be a dark variation.' );

		$user_styles = array(
			'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
			'isGlobalStylesUserThemeJSON' => true,
			'settings'                    => $variation['settings'],
		);
		// Saved styles key the palette by origin.
		$user_styles['settings']['color']['palette'] = array( 'theme' => $variation['settings']['color']['palette'] );
		unset( $user_styles['settings']['color']['duotone'] );

		self::factory()->post->create(
			array(
				'post_content' => wp_json_encode( $user_styles ),
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_type'    => 'wp_global_styles',
				'post_name'    => 'wp-global-styles-ipsum',
				'tax_input'    => array( 'wp_theme' => 'ipsum' ),
			)
		);
		WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();

		$after = wp_get_ability( self::ABILITY_NAME )->execute( array( 'fields' => array( 'colorScheme', 'colors' ) ) );

		$this->assertSame( 'dark', $after['colorScheme'], 'The guide should follow the saved variation.' );
		$this->assertSame(
			wp_list_pluck( $variation['settings']['color']['palette'], 'color', 'slug' ),
			wp_list_pluck( $after['colors'], 'color', 'slug' ),
			'The colors should be the saved variation\'s.'
		);
	}

	/**
	 * Tests that the guide marks a template saved on the site as customized.
	 *
	 * @ticket 66678
	 */
	public function test_marks_customized_templates() {
		$before = $this->get_template( 'index' );
		$this->assertFalse( $before['customized'], 'The theme\'s index template should not be customized.' );
		$this->assertSame( 'ipsum/hidden-index', $before['pattern'], 'The theme\'s index template should hold its pattern.' );

		$template_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'index',
				'post_title'   => 'Index',
				'post_content' => '<!-- wp:paragraph --><p>A customized index.</p><!-- /wp:paragraph -->',
				'tax_input'    => array( 'wp_theme' => array( 'ipsum' ) ),
			)
		);
		wp_set_post_terms( $template_id, 'ipsum', 'wp_theme' );

		$after = $this->get_template( 'index' );
		$this->assertTrue( $after['customized'], 'The saved index template should be customized.' );
		$this->assertNull( $after['pattern'], 'The saved index template no longer holds a single pattern.' );
	}

	/**
	 * Sets up a REST server with the routes registered.
	 *
	 * @return WP_REST_Server The server.
	 */
	private function set_up_rest_server() {
		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		return $wp_rest_server;
	}

	/**
	 * Returns one template from the templates section of the guide.
	 *
	 * @param string $slug The template slug.
	 * @return array<string, mixed> The template.
	 */
	private function get_template( $slug ) {
		$guide     = wp_get_ability( self::ABILITY_NAME )->execute( array( 'fields' => array( 'templates' ) ) );
		$templates = array_column( $guide['templates'], null, 'slug' );

		$this->assertArrayHasKey( $slug, $templates, "The guide should list the $slug template." );

		return $templates[ $slug ];
	}
}
