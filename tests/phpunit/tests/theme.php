<?php

/**
 * test wp-includes/theme.php
 *
 * @group themes
 */
class Tests_Theme extends WP_UnitTestCase {
	protected $theme_slug     = 'twentyeleven';
	protected $theme_name     = 'Twenty Eleven';
	protected $default_themes = array(
		'twentyten',
		'twentyeleven',
		'twentytwelve',
		'twentythirteen',
		'twentyfourteen',
		'twentyfifteen',
		'twentysixteen',
		'twentyseventeen',
		'twentynineteen',
		'twentytwenty',
		'twentytwentyone',
		'twentytwentytwo',
		'twentytwentythree',
		'twentytwentyfour',
		'twentytwentyfive',
	);

	/**
	 * Original theme directory.
	 *
	 * @var string[]
	 */
	private $orig_theme_dir;

	public function set_up() {
		global $wp_theme_directories;

		parent::set_up();

		// Sets up the `wp-content/themes/` directory to ensure consistency when running tests.
		$this->orig_theme_dir = $wp_theme_directories;
		$wp_theme_directories = array( WP_CONTENT_DIR . '/themes', realpath( DIR_TESTDATA . '/themedir1' ) );

		add_filter( 'extra_theme_headers', array( $this, 'theme_data_extra_headers' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		global $wp_theme_directories;

		$wp_theme_directories = $this->orig_theme_dir;

		remove_filter( 'extra_theme_headers', array( $this, 'theme_data_extra_headers' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		parent::tear_down();
	}

	public function test_wp_get_themes_default() {
		$themes = wp_get_themes();
		$this->assertInstanceOf( 'WP_Theme', $themes[ $this->theme_slug ] );
		$this->assertSame( $this->theme_name, $themes[ $this->theme_slug ]->get( 'Name' ) );

		$single_theme = wp_get_theme( $this->theme_slug );
		$this->assertSame( $single_theme->get( 'Name' ), $themes[ $this->theme_slug ]->get( 'Name' ) );
		$this->assertEquals( $themes[ $this->theme_slug ], $single_theme );
	}

	/**
	 * @expectedDeprecated get_theme
	 * @expectedDeprecated get_themes
	 */
	public function test_get_themes_default() {
		$themes = get_themes();
		$this->assertInstanceOf( 'WP_Theme', $themes[ $this->theme_name ] );
		$this->assertSame( $themes[ $this->theme_name ], get_theme( $this->theme_name ) );

		$this->assertSame( $this->theme_name, $themes[ $this->theme_name ]['Name'] );
		$this->assertSame( $this->theme_name, $themes[ $this->theme_name ]->Name );
		$this->assertSame( $this->theme_name, $themes[ $this->theme_name ]->name );
	}

	/**
	 * @expectedDeprecated get_theme
	 * @expectedDeprecated get_themes
	 */
	public function test_get_theme() {
		$themes = get_themes();
		foreach ( array_keys( $themes ) as $name ) {
			$theme = get_theme( $name );
			// WP_Theme implements ArrayAccess. Even ArrayObject returns false for is_array().
			$this->assertFalse( is_array( $theme ) );
			$this->assertInstanceOf( 'WP_Theme', $theme );
			$this->assertSame( $theme, $themes[ $name ] );
		}
	}

	public function test_wp_get_theme() {
		$themes = wp_get_themes();
		foreach ( $themes as $theme ) {
			$this->assertInstanceOf( 'WP_Theme', $theme );
			$this->assertFalse( $theme->errors() );
			$_theme = wp_get_theme( $theme->get_stylesheet() );
			// This primes internal WP_Theme caches for the next assertion (headers_sanitized, textdomain_loaded).
			$this->assertSame( $theme->get( 'Name' ), $_theme->get( 'Name' ) );
			$this->assertEquals( $theme, $_theme );
		}
	}

	/**
	 * @expectedDeprecated get_themes
	 */
	public function test_get_themes_contents() {
		$themes = get_themes();
		// Generic tests that should hold true for any theme.
		foreach ( $themes as $k => $theme ) {
			// Don't run these checks for custom themes.
			if ( empty( $theme['Author'] ) || false === strpos( $theme['Author'], 'WordPress' ) ) {
				continue;
			}

			$this->assertSame( $theme['Name'], $k );
			$this->assertNotEmpty( $theme['Title'] );

			// Important attributes should all be set.
			$default_headers = array(
				'Title'          => 'Theme Title',
				'Version'        => 'Version',
				'Parent Theme'   => 'Parent Theme',
				'Template Dir'   => 'Template Dir',
				'Stylesheet Dir' => 'Stylesheet Dir',
				'Template'       => 'Template',
				'Stylesheet'     => 'Stylesheet',
				'Screenshot'     => 'Screenshot',
				'Description'    => 'Description',
				'Author'         => 'Author',
				'Tags'           => 'Tags',
				// Introduced in WordPress 2.9.
				'Theme Root'     => 'Theme Root',
				'Theme Root URI' => 'Theme Root URI',
			);
			foreach ( $default_headers as $name => $value ) {
				$this->assertArrayHasKey( $name, $theme );
			}

			// Make the tests work both for WordPress 2.8.5 and WordPress 2.9-rare.
			$dir = isset( $theme['Theme Root'] ) ? '' : WP_CONTENT_DIR;

			// Important attributes should all not be empty as well.
			$this->assertNotEmpty( $theme['Description'] );
			$this->assertNotEmpty( $theme['Author'] );
			$this->assertGreaterThan( 0, version_compare( $theme['Version'], 0 ) );
			$this->assertNotEmpty( $theme['Template'] );
			$this->assertNotEmpty( $theme['Stylesheet'] );

			// Template files should all exist.
			$this->assertIsArray( $theme['Template Files'] );
			$this->assertNotEmpty( $theme['Template Files'] );
			foreach ( $theme['Template Files'] as $file ) {
				$this->assertFileIsReadable( $dir . $file );
			}

			// CSS files should all exist.
			$this->assertIsArray( $theme['Stylesheet Files'] );
			$this->assertNotEmpty( $theme['Stylesheet Files'] );
			foreach ( $theme['Stylesheet Files'] as $file ) {
				$this->assertFileIsReadable( $dir . $file );
			}

			$this->assertDirectoryExists( $dir . $theme['Template Dir'] );
			$this->assertDirectoryExists( $dir . $theme['Stylesheet Dir'] );

			$this->assertSame( 'publish', $theme['Status'] );

			$this->assertFileIsReadable( $dir . $theme['Stylesheet Dir'] . '/' . $theme['Screenshot'] );
		}
	}

	public function test_wp_get_theme_contents() {
		$theme = wp_get_theme( $this->theme_slug );

		$this->assertSame( $this->theme_name, $theme->get( 'Name' ) );
		$this->assertNotEmpty( $theme->get( 'Description' ) );
		$this->assertNotEmpty( $theme->get( 'Author' ) );
		$this->assertNotEmpty( $theme->get( 'Version' ) );
		$this->assertNotEmpty( $theme->get( 'AuthorURI' ) );
		$this->assertNotEmpty( $theme->get( 'ThemeURI' ) );
		$this->assertSame( $this->theme_slug, $theme->get_stylesheet() );
		$this->assertSame( $this->theme_slug, $theme->get_template() );

		$this->assertSame( 'publish', $theme->get( 'Status' ) );

		$this->assertSame( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_stylesheet_directory(), 'get_stylesheet_directory' );
		$this->assertSame( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_template_directory(), 'get_template_directory' );
		$this->assertSame( content_url( 'themes/' . $this->theme_slug ), $theme->get_stylesheet_directory_uri(), 'get_stylesheet_directory_uri' );
		$this->assertSame( content_url( 'themes/' . $this->theme_slug ), $theme->get_template_directory_uri(), 'get_template_directory_uri' );
	}

	/**
	 * Make sure we update the default theme list to include the latest default theme.
	 *
	 * @ticket 29925
	 */
	public function test_default_theme_in_default_theme_list() {
		$latest_default_theme = WP_Theme::get_core_default_theme();
		if ( ! $latest_default_theme->exists() || 'twenty' !== substr( $latest_default_theme->get_stylesheet(), 0, 6 ) ) {
			$this->fail( 'No Twenty* series default themes are installed.' );
		}
		$this->assertContains( $latest_default_theme->get_stylesheet(), $this->default_themes );
	}

	/**
	 * Tests the default themes list in the test suite matches the runtime default themes.
	 *
	 * @ticket 62103
	 *
	 * @coversNothing
	 */
	public function test_default_default_theme_list_match_in_test_suite_and_at_runtime() {
		// Use a reflection to make WP_THEME::$default_themes accessible.
		$reflection = new ReflectionClass( 'WP_Theme' );
		$property   = $reflection->getProperty( 'default_themes' );
		$property->setAccessible( true );

		/*
		 * `default` and `classic` are included in `WP_Theme::$default_themes` but not included
		 * in the test suite default themes list. These are excluded from the comparison.
		 */
		$default_themes = array_keys( $property->getValue() );
		$default_themes = array_diff( $default_themes, array( 'default', 'classic' ) );

		$this->assertSameSets( $default_themes, $this->default_themes, 'Test suite default themes should match the runtime default themes.' );
	}

	/**
	 * Test the default theme in WP_Theme matches the WP_DEFAULT_THEME constant.
	 *
	 * @ticket 62103
	 *
	 * @covers WP_Theme::get_core_default_theme
	 */
	public function test_default_theme_matches_constant() {
		$latest_default_theme = WP_Theme::get_core_default_theme();

		/*
		 * The test suite sets the constant to `default` while this is intended to
		 * test the value defined in default-constants.php.
		 *
		 * Therefore this reads the file in via file_get_contents to extract the value.
		 */
		$default_constants = file_get_contents( ABSPATH . WPINC . '/default-constants.php' );
		preg_match( '/define\( \'WP_DEFAULT_THEME\', \'(.*)\' \);/', $default_constants, $matches );
		$wp_default_theme_constant = $matches[1];

		$this->assertSame( $wp_default_theme_constant, $latest_default_theme->get_stylesheet(), 'WP_DEFAULT_THEME should match the latest default theme.' );
	}

	/**
	 * Ensure that the default themes are included in the new bundled files.
	 *
	 * @ticket 62103
	 *
	 * @coversNothing
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_default_themes_are_included_in_new_files() {
		require_once ABSPATH . 'wp-admin/includes/update-core.php';
		global $_new_bundled_files;
		// Limit new bundled files to the default themes.
		$new_theme_files = array_keys( $_new_bundled_files );
		$new_theme_files = array_filter(
			$new_theme_files,
			static function ( $file ) {
				return str_starts_with( $file, 'themes/' );
			}
		);

		$tested_themes = $this->default_themes;
		// Convert the tested themes to directory names.
		$tested_themes = array_map(
			static function ( $theme ) {
				return "themes/{$theme}/";
			},
			$tested_themes
		);

		$this->assertSameSets( $tested_themes, $new_theme_files, 'New bundled files should include the default themes.' );
	}

	public function test_default_themes_have_textdomain() {
		foreach ( $this->default_themes as $theme ) {
			if ( wp_get_theme( $theme )->exists() ) {
				$this->assertSame( $theme, wp_get_theme( $theme )->get( 'TextDomain' ) );
			}
		}
	}

	/**
	 * @ticket 48566
	 */
	public function test_year_in_readme() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		foreach ( $this->default_themes as $theme ) {
			$wp_theme = wp_get_theme( $theme );

			$path_to_readme_txt = $wp_theme->get_theme_root() . '/' . $wp_theme->get_stylesheet() . '/readme.txt';
			$this->assertFileExists( $path_to_readme_txt );

			$readme    = file_get_contents( $path_to_readme_txt );
			$this_year = gmdate( 'Y' );

			preg_match( '#Copyright (\d+) WordPress.org#', $readme, $matches );
			if ( $matches ) {
				$readme_year = trim( $matches[1] );

				$this->assertSame( $this_year, $readme_year, "Bundled themes readme.txt's year needs to be updated to $this_year." );
			}

			preg_match( '#Copyright 20\d\d-(\d+) WordPress.org#', $readme, $matches );
			if ( $matches ) {
				$readme_year = trim( $matches[1] );

				$this->assertSame( $this_year, $readme_year, "Bundled themes readme.txt's year needs to be updated to $this_year." );
			}
		}
	}

	/**
	 * @ticket 20897
	 * @expectedDeprecated get_theme_data
	 */
	public function test_extra_theme_headers() {
		$wp_theme = wp_get_theme( $this->theme_slug );
		$this->assertNotEmpty( $wp_theme->get( 'License' ) );
		$path_to_style_css = $wp_theme->get_theme_root() . '/' . $wp_theme->get_stylesheet() . '/style.css';
		$this->assertFileExists( $path_to_style_css );
		$theme_data = get_theme_data( $path_to_style_css );
		$this->assertArrayHasKey( 'License', $theme_data );
		$this->assertArrayNotHasKey( 'Not a Valid Key', $theme_data );
		$this->assertNotEmpty( $theme_data['License'] );
		$this->assertSame( $theme_data['License'], $wp_theme->get( 'License' ) );
	}

	public function theme_data_extra_headers() {
		return array( 'License' );
	}

	/**
	 * @expectedDeprecated get_themes
	 * @expectedDeprecated get_current_theme
	 */
	public function test_switch_theme() {
		$themes = get_themes();

		// Switch to each theme in sequence.
		// Do it twice to make sure we switch to the first theme, even if it's our starting theme.
		// Do it a third time to ensure switch_theme() works with one argument.

		for ( $i = 0; $i < 3; $i++ ) {
			foreach ( $themes as $name => $theme ) {
				// Skip invalid theme directory names (such as `block_theme-[0.4.0]`).
				if ( ! preg_match( '/^[a-z0-9-]+$/', $theme['Stylesheet'] ) ) {
					continue;
				}

				// Switch to this theme.
				if ( 2 === $i ) {
					switch_theme( $theme['Template'], $theme['Stylesheet'] );
				} else {
					switch_theme( $theme['Stylesheet'] );
				}

				$this->assertSame( $theme['Name'], get_current_theme() );

				// Make sure the various get_* functions return the correct values.
				$this->assertSame( $theme['Template'], get_template() );
				$this->assertSame( $theme['Stylesheet'], get_stylesheet() );

				$root_fs = $theme->get_theme_root();
				$this->assertTrue( is_dir( $root_fs ) );

				$root_uri = $theme->get_theme_root_uri();
				$this->assertNotEmpty( $root_uri );

				$this->assertSame( $root_fs . '/' . get_stylesheet(), get_stylesheet_directory() );
				$this->assertSame( $root_uri . '/' . get_stylesheet(), get_stylesheet_directory_uri() );
				$this->assertSame( $root_uri . '/' . get_stylesheet() . '/style.css', get_stylesheet_uri() );
				// $this->assertSame( $root_uri . '/' . get_stylesheet(), get_locale_stylesheet_uri() );

				$this->assertSame( $root_fs . '/' . get_template(), get_template_directory() );
				$this->assertSame( $root_uri . '/' . get_template(), get_template_directory_uri() );

				// Skip block themes for get_query_template() tests since this test is focused on classic templates.
				if ( wp_is_block_theme() || wp_theme_has_theme_json() ) {
					continue;
				}

				// Template file that doesn't exist.
				$this->assertSame( '', get_query_template( 'nonexistent' ) );

				// Template files that do exist.
				foreach ( $theme['Template Files'] as $path ) {
					$file = basename( $path, '.php' );

					// The functions.php file is not a template.
					if ( 'functions' === $file ) {
						continue;
					}

					// Underscores are not supported by `locate_template()`.
					if ( 'taxonomy-post_format' === $file ) {
						$file = 'taxonomy';
					}

					$child_theme_file  = get_stylesheet_directory() . '/' . $file . '.php';
					$parent_theme_file = get_template_directory() . '/' . $file . '.php';
					if ( file_exists( $child_theme_file ) ) {
						$this->assertSame( $child_theme_file, get_query_template( $file ) );
					} elseif ( file_exists( $parent_theme_file ) ) {
						$this->assertSame( $parent_theme_file, get_query_template( $file ) );
					} else {
						$this->assertSame( '', get_query_template( $file ) );
					}
				}

				// These are kind of tautologies but at least exercise the code.
				$this->assertSame( get_404_template(), get_query_template( '404' ) );
				$this->assertSame( get_archive_template(), get_query_template( 'archive' ) );
				$this->assertSame( get_author_template(), get_query_template( 'author' ) );
				$this->assertSame( get_category_template(), get_query_template( 'category' ) );
				$this->assertSame( get_date_template(), get_query_template( 'date' ) );
				$this->assertSame( get_home_template(), get_query_template( 'home', array( 'home.php', 'index.php' ) ) );
				$this->assertSame( get_privacy_policy_template(), get_query_template( 'privacy_policy', array( 'privacy-policy.php' ) ) );
				$this->assertSame( get_page_template(), get_query_template( 'page' ) );
				$this->assertSame( get_search_template(), get_query_template( 'search' ) );
				$this->assertSame( get_single_template(), get_query_template( 'single' ) );
				$this->assertSame( get_attachment_template(), get_query_template( 'attachment' ) );

				$this->assertSame( get_tag_template(), get_query_template( 'tag' ) );

				// nb: This probably doesn't run because WP_INSTALLING is defined.
				$this->assertTrue( validate_current_theme() );
			}
		}
	}

	public function test_switch_theme_bogus() {
		// Try switching to a theme that doesn't exist.
		$template = 'some_template';
		$style    = 'some_style';
		update_option( 'template', $template );
		update_option( 'stylesheet', $style );

		$theme = wp_get_theme();
		$this->assertSame( $style, (string) $theme );
		$this->assertNotFalse( $theme->errors() );
		$this->assertFalse( $theme->exists() );

		// These return the bogus name - perhaps not ideal behavior?
		$this->assertSame( $template, get_template() );
		$this->assertSame( $style, get_stylesheet() );
	}

	/**
	 * Test _wp_keep_alive_customize_changeset_dependent_auto_drafts.
	 *
	 * @covers ::_wp_keep_alive_customize_changeset_dependent_auto_drafts
	 */
	public function test_wp_keep_alive_customize_changeset_dependent_auto_drafts() {
		$nav_created_post_ids = self::factory()->post->create_many(
			2,
			array(
				'post_status' => 'auto-draft',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			)
		);
		$data                 = array(
			'nav_menus_created_posts' => array(
				'value' => $nav_created_post_ids,
			),
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$wp_customize = new WP_Customize_Manager();
		do_action( 'customize_register', $wp_customize );

		// The post_date for auto-drafts is bumped to match the changeset post_date whenever it is modified
		// to keep them from from being garbage collected by wp_delete_auto_drafts().
		$wp_customize->save_changeset_post(
			array(
				'data' => $data,
			)
		);
		$this->assertSame( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[0] )->post_date );
		$this->assertSame( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[1] )->post_date );
		$this->assertSame( 'auto-draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'auto-draft', get_post_status( $nav_created_post_ids[1] ) );

		// Stubs transition to drafts when changeset is saved as a draft.
		$wp_customize->save_changeset_post(
			array(
				'status' => 'draft',
				'data'   => $data,
			)
		);
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[1] ) );

		// Status remains unchanged for stub that the user broke out of the changeset.
		wp_update_post(
			array(
				'ID'          => $nav_created_post_ids[1],
				'post_status' => 'private',
			)
		);
		$wp_customize->save_changeset_post(
			array(
				'status' => 'draft',
				'data'   => $data,
			)
		);
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'private', get_post_status( $nav_created_post_ids[1] ) );

		// Draft stub is trashed when the changeset is trashed.
		$wp_customize->trash_changeset_post( $wp_customize->changeset_post_id() );
		$this->assertSame( 'trash', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'private', get_post_status( $nav_created_post_ids[1] ) );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults() {
		$registered = register_theme_feature( 'test-feature' );
		$this->assertTrue( $registered );

		$expected = array(
			'type'         => 'boolean',
			'variadic'     => false,
			'description'  => '',
			'show_in_rest' => false,
		);
		$this->assertSameSets( $expected, get_registered_theme_feature( 'test-feature' ) );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_explicit() {
		$args = array(
			'type'         => 'array',
			'variadic'     => true,
			'description'  => 'My Feature',
			'show_in_rest' => array(
				'schema' => array(
					'items' => array(
						'type' => 'string',
					),
				),
			),
		);

		register_theme_feature( 'test-feature', $args );
		$actual = get_registered_theme_feature( 'test-feature' );

		$this->assertSame( 'array', $actual['type'] );
		$this->assertTrue( $actual['variadic'] );
		$this->assertSame( 'My Feature', $actual['description'] );
		$this->assertSame( array( 'type' => 'string' ), $actual['show_in_rest']['schema']['items'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_upgrades_show_in_rest() {
		register_theme_feature( 'test-feature', array( 'show_in_rest' => true ) );

		$expected = array(
			'schema'           => array(
				'description' => '',
				'type'        => 'boolean',
				'default'     => false,
			),
			'name'             => 'test-feature',
			'prepare_callback' => null,
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_fills_schema() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'items'    => array(
							'type' => 'string',
						),
						'minItems' => 1,
					),
				),
			)
		);

		$expected = array(
			'description' => 'Cool Feature',
			'type'        => array( 'boolean', 'array' ),
			'items'       => array(
				'type' => 'string',
			),
			'minItems'    => 1,
			'default'     => false,
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_does_not_add_boolean_type_if_non_bool_default() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items'   => array(
							'type' => 'string',
						),
						'default' => array( 'standard' ),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['type'];
		$this->assertSame( 'array', $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults_additional_properties_to_false() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'object',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'properties' => array(
							'a' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema'];

		$this->assertArrayHasKey( 'additionalProperties', $actual );
		$this->assertFalse( $actual['additionalProperties'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_with_additional_properties() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'object',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'properties'           => array(),
						'additionalProperties' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		$expected = array(
			'type' => 'string',
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['additionalProperties'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults_additional_properties_to_false_in_array() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'a' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['items'];

		$this->assertArrayHasKey( 'additionalProperties', $actual );
		$this->assertFalse( $actual['additionalProperties'] );
	}

	/**
	 * @ticket 49406
	 *
	 * @dataProvider data_register_theme_support_validation
	 *
	 * @param string $error_code The error code expected.
	 * @param array  $args       The args to register.
	 */
	public function test_register_theme_support_validation( $error_code, $args ) {
		$registered = register_theme_feature( 'test-feature', $args );

		$this->assertWPError( $registered );
		$this->assertSame( $error_code, $registered->get_error_code() );
	}

	public function data_register_theme_support_validation() {
		return array(
			array(
				'invalid_type',
				array(
					'type' => 'float',
				),
			),
			array(
				'invalid_type',
				array(
					'type' => array( 'string' ),
				),
			),
			array(
				'variadic_must_be_array',
				array(
					'variadic' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'object',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'array',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema_items',
				array(
					'type'         => 'array',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'array',
						),
					),
				),
			),
			array(
				'missing_schema_properties',
				array(
					'type'         => 'object',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'object',
						),
					),
				),
			),
			array(
				'invalid_rest_prepare_callback',
				array(
					'show_in_rest' => array(
						'prepare_callback' => 'this is not a valid function',
					),
				),
			),
		);
	}


	/**
	 * Tests that block themes support a feature by default.
	 *
	 * @ticket 54597
	 * @ticket 54731
	 * @ticket 59732
	 *
	 * @dataProvider data_block_theme_has_default_support
	 *
	 * @covers ::_add_default_theme_supports
	 *
	 * @param array $support {
	 *     The feature to check.
	 *
	 *     @type string $feature     The feature to check.
	 *     @type string $sub_feature Optional. The sub-feature to check.
	 * }
	 */
	public function test_block_theme_has_default_support( $support ) {
		$this->helper_requires_block_theme();

		$support_data     = array_values( $support );
		$support_data_str = implode( ': ', $support_data );

		// Remove existing support.
		if ( current_theme_supports( ...$support_data ) ) {
			remove_theme_support( ...$support_data );
		}

		$this->assertFalse(
			current_theme_supports( ...$support_data ),
			"Could not remove support for $support_data_str."
		);

		do_action( 'after_setup_theme' );

		$this->assertTrue(
			current_theme_supports( ...$support_data ),
			"Does not have default support for $support_data_str."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_block_theme_has_default_support() {
		return array(
			'post-thumbnails'      => array(
				'support' => array(
					'feature' => 'post-thumbnails',
				),
			),
			'responsive-embeds'    => array(
				'support' => array(
					'feature' => 'responsive-embeds',
				),
			),
			'editor-styles'        => array(
				'support' => array(
					'feature' => 'editor-styles',
				),
			),
			'html5: comment-list'  => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'comment-list',
				),
			),
			'html5: comment-form'  => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'comment-form',
				),
			),
			'html5: search-form'   => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'search-form',
				),
			),
			'html5: gallery'       => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'gallery',
				),
			),
			'html5: caption'       => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'caption',
				),
			),
			'html5: style'         => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'style',
				),
			),
			'html5: script'        => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'script',
				),
			),
			'automatic-feed-links' => array(
				'support' => array(
					'feature' => 'automatic-feed-links',
				),
			),
		);
	}

	/**
	 * Tests that block themes load separate core block assets by default.
	 *
	 * @ticket 54597
	 * @ticket 59732
	 *
	 * @covers ::_add_default_theme_supports
	 * @covers ::wp_should_load_separate_core_block_assets
	 *
	 * @expectedDeprecated wp_should_load_separate_core_block_assets
	 * @expectedDeprecated should_load_separate_core_block_assets
	 */
	public function test_block_theme_should_load_separate_core_block_assets_by_default() {
		$this->helper_requires_block_theme();

		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		$this->assertFalse(
			wp_should_load_separate_core_block_assets(),
			'Could not disable loading separate core block assets.'
		);

		do_action( 'after_setup_theme' );

		$this->assertTrue(
			wp_should_load_separate_core_block_assets(),
			'Block themes do not load separate core block assets by default.'
		);
	}

	/**
	 * Tests that a theme in the custom test data theme directory is recognized.
	 *
	 * @ticket 18298
	 */
	public function test_theme_in_custom_theme_dir_is_valid() {
		switch_theme( 'block-theme' );
		$this->assertTrue( wp_get_theme()->exists() );
	}

	/**
	 * Tests that `is_child_theme()` returns true for child theme.
	 *
	 * @ticket 18298
	 *
	 * @covers ::is_child_theme
	 */
	public function test_is_child_theme_true() {
		switch_theme( 'block-theme-child' );
		$this->assertTrue( is_child_theme() );
	}

	/**
	 * Tests that `is_child_theme()` returns false for parent theme.
	 *
	 * @ticket 18298
	 *
	 * @covers ::is_child_theme
	 */
	public function test_is_child_theme_false() {
		switch_theme( 'block-theme' );
		$this->assertFalse( is_child_theme() );
	}

	/**
	 * Tests that the child theme directory is correctly detected.
	 *
	 * @ticket 18298
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory() {
		switch_theme( 'block-theme-child' );
		$this->assertSamePathIgnoringDirectorySeparators( realpath( DIR_TESTDATA ) . '/themedir1/block-theme-child', get_stylesheet_directory() );
	}

	/**
	 * Tests that the parent theme directory is correctly detected.
	 *
	 * @ticket 18298
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory() {
		switch_theme( 'block-theme-child' );
		$this->assertSamePathIgnoringDirectorySeparators( realpath( DIR_TESTDATA ) . '/themedir1/block-theme', get_template_directory() );
	}

	/**
	 * Tests that get_stylesheet_directory() behaves correctly with filters.
	 *
	 * @ticket 18298
	 * @dataProvider data_get_stylesheet_directory_with_filter
	 *
	 * @covers ::get_stylesheet_directory
	 *
	 * @param string   $theme     Theme slug / directory name.
	 * @param string   $hook_name Filter hook name.
	 * @param callable $callback  Filter callback.
	 * @param string   $expected  Expected stylesheet directory with the filter active.
	 */
	public function test_get_stylesheet_directory_with_filter( $theme, $hook_name, $callback, $expected ) {
		switch_theme( $theme );

		// Add filter, then call get_stylesheet_directory() to compute value.
		add_filter( $hook_name, $callback );
		$this->assertSame( $expected, get_stylesheet_directory(), 'Stylesheet directory returned incorrect result not considering filters' );

		// Remove filter again, then ensure result is recalculated and not the same as before.
		remove_filter( $hook_name, $callback );
		$this->assertNotSame( $expected, get_stylesheet_directory(), 'Stylesheet directory returned previous value even though filters were removed' );
	}

	/**
	 * Data provider for `test_get_stylesheet_directory_with_filter()`.
	 *
	 * @return array[]
	 */
	public function data_get_stylesheet_directory_with_filter() {
		return array(
			'with stylesheet_directory filter' => array(
				'block-theme',
				'stylesheet_directory',
				static function ( $dir ) {
					return str_replace( realpath( DIR_TESTDATA ) . DIRECTORY_SEPARATOR . 'themedir1', '/fantasy-dir', $dir );
				},
				'/fantasy-dir/block-theme',
			),
			'with theme_root filter'           => array(
				'block-theme',
				'theme_root',
				static function () {
					return '/fantasy-dir';
				},
				'/fantasy-dir/block-theme',
			),
			'with stylesheet filter'           => array(
				'block-theme',
				'stylesheet',
				static function () {
					return 'another-theme';
				},
				// Because the theme does not exist, `get_theme_root()` returns the default themes directory.
				WP_CONTENT_DIR . '/themes/another-theme',
			),
		);
	}

	/**
	 * Tests that get_template_directory() behaves correctly with filters.
	 *
	 * @ticket 18298
	 * @dataProvider data_get_template_directory_with_filter
	 *
	 * @covers ::get_template_directory
	 *
	 * @param string   $theme     Theme slug / directory name.
	 * @param string   $hook_name Filter hook name.
	 * @param callable $callback  Filter callback.
	 * @param string   $expected  Expected template directory with the filter active.
	 */
	public function test_get_template_directory_with_filter( $theme, $hook_name, $callback, $expected ) {
		switch_theme( $theme );

		// Add filter, then call get_template_directory() to compute value.
		add_filter( $hook_name, $callback );
		$this->assertSame( $expected, get_template_directory(), 'Template directory returned incorrect result not considering filters' );

		// Remove filter again, then ensure result is recalculated and not the same as before.
		remove_filter( $hook_name, $callback );
		$this->assertNotSame( $expected, get_template_directory(), 'Template directory returned previous value even though filters were removed' );
	}

	/**
	 * Data provider for `test_get_template_directory_with_filter()`.
	 *
	 * @return array[]
	 */
	public function data_get_template_directory_with_filter() {
		return array(
			'with template_directory filter' => array(
				'block-theme',
				'template_directory',
				static function ( $dir ) {
					return str_replace( realpath( DIR_TESTDATA ) . DIRECTORY_SEPARATOR . 'themedir1', '/fantasy-dir', $dir );
				},
				'/fantasy-dir/block-theme',
			),
			'with theme_root filter'         => array(
				'block-theme',
				'theme_root',
				static function () {
					return '/fantasy-dir';
				},
				'/fantasy-dir/block-theme',
			),
			'with template filter'           => array(
				'block-theme',
				'template',
				static function () {
					return 'another-theme';
				},
				// Because the theme does not exist, `get_theme_root()` returns the default themes directory.
				WP_CONTENT_DIR . '/themes/another-theme',
			),
		);
	}

	/**
	 * Tests whether a switched site retrieves the correct stylesheet directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_with_switched_site() {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'stylesheet', 'switched_stylesheet' );

		// Prime global storage with the current site's data.
		get_stylesheet_directory();

		switch_to_blog( $blog_id );
		$switched_stylesheet = get_stylesheet_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/switched_stylesheet', $switched_stylesheet );
	}

	/**
	 * Tests whether a switched site retrieves the correct template directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_with_switched_site() {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'template', 'switched_template' );

		// Prime global storage with the current site's data.
		get_template_directory();

		switch_to_blog( $blog_id );
		$switched_template = get_template_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/switched_template', $switched_template );
	}

	/**
	 * Tests whether a restored site retrieves the correct stylesheet directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_with_restored_site() {
		$blog_id = self::factory()->blog->create();

		update_option( 'stylesheet', 'original_stylesheet' );
		update_blog_option( $blog_id, 'stylesheet', 'switched_stylesheet' );

		$stylesheet = get_stylesheet_directory();

		switch_to_blog( $blog_id );

		// Prime global storage with the restored site's data.
		get_stylesheet_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/original_stylesheet', $stylesheet );
	}

	/**
	 * Tests whether a restored site retrieves the correct template directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_with_restored_site() {
		$blog_id = self::factory()->blog->create();

		update_option( 'template', 'original_template' );
		update_blog_option( $blog_id, 'template', 'switched_template' );

		$template = get_template_directory();

		switch_to_blog( $blog_id );

		// Prime global storage with the switched site's data.
		get_template_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/original_template', $template );
	}

	/**
	 * Helper function to ensure that a block theme is available and active.
	 */
	private function helper_requires_block_theme() {
		// No need to switch if we're already on a block theme.
		if ( wp_is_block_theme() ) {
			return;
		}

		$block_theme = 'twentytwentytwo';

		// Skip if the block theme is not available.
		if ( ! wp_get_theme( $block_theme )->exists() ) {
			$this->markTestSkipped( "$block_theme must be available." );
		}

		switch_theme( $block_theme );

		// Skip if we could not switch to the block theme.
		if ( wp_get_theme()->stylesheet !== $block_theme ) {
			$this->markTestSkipped( "Could not switch to $block_theme." );
		}
	}

	/**
	 * Make sure filters added after the initial call are fired.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_filters_apply() {
		// Call the function prior to the filter being added.
		get_stylesheet_directory();

		$expected = 'test_root/dir';

		// Add the filer.
		add_filter(
			'stylesheet_directory',
			function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, get_stylesheet_directory() );
	}

	/**
	 * Make sure filters added after the initial call are fired.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_filters_apply() {
		// Call the function prior to the filter being added.
		get_template_directory();

		$expected = 'test_root/dir';

		// Add the filer.
		add_filter(
			'template_directory',
			function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, get_template_directory() );
	}

	/**
	 * Make sure get_stylesheet_directory uses the correct path when the root theme dir changes.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_uses_registered_theme_dir() {
		$old_theme = wp_get_theme();

		switch_theme( 'test' );

		$old_root = get_theme_root( 'test' );
		$path1    = get_stylesheet_directory();

		$new_root = DIR_TESTDATA . '/themedir2';
		register_theme_directory( $new_root );

		// Mock the stylesheet root option to mimic that the active root has changed.
		add_filter(
			'pre_option_stylesheet_root',
			function () use ( $new_root ) {
				return $new_root;
			}
		);

		$path2 = get_stylesheet_directory();

		// Cleanup.
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $old_root . '/test', $path1, 'The original stylesheet path is not correct' );
		$this->assertSame( $new_root . '/test', $path2, 'The new stylesheet path is not correct' );
	}

	/**
	 * Make sure get_template_directory uses the correct path when the root theme dir changes.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_uses_registered_theme_dir() {
		$old_theme = wp_get_theme();

		switch_theme( 'test' );

		// Mock parent theme to be returned as the template.
		add_filter(
			'pre_option_template',
			function () {
				return 'test-parent';
			}
		);

		$old_root = get_theme_root( 'test' );
		$path1    = get_template_directory();

		$new_root = DIR_TESTDATA . '/themedir2';
		register_theme_directory( $new_root );

		// Mock the template root option to mimic that the active root has changed.
		add_filter(
			'pre_option_template_root',
			function () use ( $new_root ) {
				return $new_root;
			}
		);

		$path2 = get_template_directory();

		// Cleanup.
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $old_root . '/test-parent', $path1, 'The original template path is not correct' );
		$this->assertSame( $new_root . '/test-parent', $path2, 'The new template path is not correct' );
	}

	/**
	 * Tests that switch_to_blog() uses the original template path.
	 *
	 * @ticket 60290
	 *
	 * @group ms-required
	 *
	 * @covers ::locate_template
	 */
	public function test_switch_to_blog_uses_original_template_path() {
		$old_theme     = wp_get_theme();
		$template_path = locate_template( 'index.php' );

		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );

		switch_theme( 'block-theme' );
		$new_template_path = locate_template( 'index.php' );

		// Cleanup.
		restore_current_blog();
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $template_path, $new_template_path, 'Switching blogs switches the template path' );
	}
}
