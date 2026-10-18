<?php

/**
 * Tests for wp_get_code_editor_settings().
 *
 * @group general
 *
 * @covers ::wp_get_code_editor_settings
 */
class Tests_General_wpGetCodeEditorSettings extends WP_UnitTestCase {

	/**
	 * Tests the CodeMirror settings for each supported MIME type.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_mime_types
	 *
	 * @param string $type     MIME type.
	 * @param array  $expected Expected CodeMirror settings, in addition to the defaults.
	 */
	public function test_codemirror_settings_for_mime_type( $type, $expected ) {
		$this->allow_unfiltered_html( true );

		$codemirror = wp_get_code_editor_settings( array( 'type' => $type ) )['codemirror'];

		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $codemirror, "The {$key} setting should be set." );
			$this->assertSame( $value, $codemirror[ $key ], "The {$key} setting did not match." );
		}

		foreach ( array( 'lint', 'autoCloseBrackets', 'autoCloseTags', 'matchBrackets', 'matchTags', 'highlightFormatting' ) as $key ) {
			if ( ! array_key_exists( $key, $expected ) ) {
				$this->assertArrayNotHasKey( $key, $codemirror, "The {$key} setting should not be set." );
			}
		}

		$this->assertSame(
			empty( $expected['lint'] ) ? array() : array( 'CodeMirror-lint-markers' ),
			$codemirror['gutters'],
			'The lint markers gutter should only be added when linting is enabled.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_mime_types() {
		$css = array(
			'lint'              => false,
			'autoCloseBrackets' => true,
			'matchBrackets'     => true,
		);

		$javascript = array(
			'mode'              => 'javascript',
			'lint'              => true,
			'autoCloseBrackets' => true,
			'matchBrackets'     => true,
		);

		$markup = array(
			'autoCloseBrackets' => true,
			'autoCloseTags'     => true,
			'matchTags'         => array( 'bothTags' => true ),
		);

		return array(
			'CSS'                      => array( 'text/css', array( 'mode' => 'text/css' ) + $css ),
			'SCSS'                     => array( 'text/x-scss', array( 'mode' => 'text/x-scss' ) + $css ),
			'Less'                     => array( 'text/x-less', array( 'mode' => 'text/x-less' ) + $css ),
			'Sass'                     => array( 'text/x-sass', array( 'mode' => 'text/x-sass' ) + $css ),
			'diff'                     => array( 'text/x-diff', array( 'mode' => 'diff' ) ),
			'application/x-patch'      => array( 'application/x-patch', array( 'mode' => 'diff' ) ),
			'text/x-patch'             => array( 'text/x-patch', array( 'mode' => 'diff' ) ),
			'HTML'                     => array(
				'text/html',
				array(
					'mode'              => 'htmlmixed',
					'lint'              => true,
					'autoCloseBrackets' => true,
					'autoCloseTags'     => true,
					'matchTags'         => array( 'bothTags' => true ),
				),
			),
			'GitHub Flavored Markdown' => array(
				'text/x-gfm',
				array(
					'mode'                => 'gfm',
					'highlightFormatting' => true,
				),
			),
			'Markdown'                 => array(
				'text/x-markdown',
				array(
					'mode'                => 'markdown',
					'highlightFormatting' => true,
				),
			),
			'application/javascript'   => array( 'application/javascript', $javascript ),
			'text/javascript'          => array( 'text/javascript', $javascript ),
			'JSON'                     => array(
				'application/json',
				array(
					'mode'              => array(
						'name' => 'javascript',
						'json' => true,
					),
					'lint'              => true,
					'autoCloseBrackets' => true,
					'matchBrackets'     => true,
				),
			),
			'JSON-LD'                  => array(
				'application/ld+json',
				array(
					'mode'              => array(
						'name'   => 'javascript',
						'jsonld' => true,
					),
					'lint'              => true,
					'autoCloseBrackets' => true,
					'matchBrackets'     => true,
				),
			),
			'JSX'                      => array(
				'text/jsx',
				array(
					'mode'              => 'jsx',
					'autoCloseBrackets' => true,
					'matchBrackets'     => true,
				),
			),
			'nginx'                    => array( 'text/nginx', array( 'mode' => 'nginx' ) ),
			'PHP'                      => array(
				'application/x-httpd-php',
				array(
					'mode'          => 'php',
					'matchBrackets' => true,
				) + $markup,
			),
			'SQL'                      => array(
				'text/x-sql',
				array(
					'mode'              => 'sql',
					'autoCloseBrackets' => true,
					'matchBrackets'     => true,
				),
			),
			'MySQL'                    => array(
				'text/x-mysql',
				array(
					'mode'              => 'sql',
					'autoCloseBrackets' => true,
					'matchBrackets'     => true,
				),
			),
			'XML'                      => array( 'text/xml', array( 'mode' => 'xml' ) + $markup ),
			'SVG'                      => array( 'application/svg+xml', array( 'mode' => 'xml' ) + $markup ),
			'YAML'                     => array( 'text/x-yaml', array( 'mode' => 'yaml' ) ),
			'other type'               => array( 'text/x-python', array( 'mode' => 'text/x-python' ) ),
			'plain text'               => array( 'text/plain', array( 'mode' => 'text/plain' ) ),
			'empty type'               => array( '', array( 'mode' => '' ) ),
		);
	}

	/**
	 * Tests the CodeMirror mode detected from the file extension.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_file_names
	 *
	 * @param string       $file     File name.
	 * @param string|array $mode     Expected CodeMirror mode.
	 */
	public function test_codemirror_mode_for_file( $file, $mode ) {
		$this->assertSame( $mode, wp_get_code_editor_settings( array( 'file' => $file ) )['codemirror']['mode'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_file_names() {
		return array(
			// Extensions known to wp_get_mime_types().
			'CSS file'                     => array( 'style.css', 'text/css' ),
			'HTML file'                    => array( 'index.html', 'htmlmixed' ),
			'JavaScript file'              => array( 'script.js', 'javascript' ),
			'text file'                    => array( 'readme.txt', 'text/plain' ),
			'CSV file'                     => array( 'data.csv', 'text/csv' ),

			// Extensions supplied by the fallback list.
			'nginx configuration'          => array( 'nginx.conf', 'nginx' ),
			'diff file'                    => array( 'changes.diff', 'diff' ),
			'patch file'                   => array( 'changes.patch', 'diff' ),
			'HTTP message'                 => array( 'request.http', 'message/http' ),
			'JavaScript module'            => array( 'module.mjs', 'javascript' ),
			'JSX file'                     => array( 'component.jsx', 'jsx' ),
			'Less file'                    => array( 'style.less', 'text/x-less' ),
			'Markdown file'                => array( 'readme.md', 'gfm' ),
			'PHP file'                     => array( 'functions.php', 'php' ),
			'PHTML file'                   => array( 'template.phtml', 'php' ),
			'SCSS file'                    => array( 'style.scss', 'text/x-scss' ),
			'Sass file'                    => array( 'style.sass', 'text/x-sass' ),
			'shell script'                 => array( 'deploy.sh', 'text/x-sh' ),
			'Bash script'                  => array( 'deploy.bash', 'text/x-sh' ),
			'SQL file'                     => array( 'schema.sql', 'sql' ),
			'SVG file'                     => array( 'icon.svg', 'xml' ),
			'XML file'                     => array( 'feed.xml', 'xml' ),
			'YAML file'                    => array( 'config.yml', 'yaml' ),
			'YAML file, long form'         => array( 'config.yaml', 'yaml' ),
			'unknown extension'            => array( 'file.unknown', 'text/plain' ),
			'dot file'                     => array( '.htaccess', 'text/plain' ),

			// Other file names.
			'uppercase extension'          => array( 'STYLE.CSS', 'text/css' ),
			'uppercase fallback extension' => array( 'README.MD', 'gfm' ),
			'path with directories'        => array( 'theme/assets/style.scss', 'text/x-scss' ),
			'file without extension'       => array( 'Makefile', '' ),
			'dot in directory only'        => array( 'theme.v2/Makefile', '' ),
		);
	}

	/**
	 * Tests that the type argument takes precedence over the file name.
	 *
	 * @ticket 65819
	 */
	public function test_type_takes_precedence_over_file() {
		$settings = wp_get_code_editor_settings(
			array(
				'type' => 'text/css',
				'file' => 'functions.php',
			)
		);

		$this->assertSame( 'text/css', $settings['codemirror']['mode'] );
	}

	/**
	 * Tests that JavaScript is parsed as a module only for .mjs files.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_module_files
	 *
	 * @param array $args     Arguments.
	 * @param bool  $expected Whether the script should be parsed as a module.
	 */
	public function test_jshint_module_for_mjs_files( $args, $expected ) {
		$this->assertSame( $expected, wp_get_code_editor_settings( $args )['jshint']['module'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_module_files() {
		return array(
			'.mjs file'  => array( array( 'file' => 'module.mjs' ), true ),
			'.js file'   => array( array( 'file' => 'script.js' ), false ),
			'type only'  => array( array( 'type' => 'text/javascript' ), false ),
			'no file'    => array( array(), false ),
			'empty file' => array( array( 'file' => '' ), false ),
		);
	}

	/**
	 * Tests that the allowed post HTML is passed to HTMLHint for users who cannot use unfiltered HTML.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unfiltered_html
	 *
	 * @param bool $can_use_unfiltered_html Whether the user can use unfiltered HTML.
	 */
	public function test_htmlhint_kses_rules_for_html( $can_use_unfiltered_html ) {
		$this->allow_unfiltered_html( $can_use_unfiltered_html );

		$htmlhint = wp_get_code_editor_settings( array( 'type' => 'text/html' ) )['htmlhint'];

		if ( $can_use_unfiltered_html ) {
			$this->assertArrayNotHasKey( 'kses', $htmlhint, 'No KSES rules should be passed.' );
		} else {
			$this->assertArrayHasKey( 'kses', $htmlhint, 'The KSES rules should be passed.' );
			$this->assertArrayHasKey( 'a', $htmlhint['kses'], 'Links should be allowed.' );
			$this->assertArrayHasKey( 'href', $htmlhint['kses']['a'], 'The href attribute should be allowed on links.' );
			$this->assertArrayNotHasKey( 'script', $htmlhint['kses'], 'Scripts should not be allowed.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unfiltered_html() {
		return array(
			'user can use unfiltered HTML'    => array( true ),
			'user cannot use unfiltered HTML' => array( false ),
		);
	}

	/**
	 * Tests that the KSES rules are only passed for HTML.
	 *
	 * @ticket 65819
	 */
	public function test_htmlhint_kses_rules_only_for_html() {
		$this->allow_unfiltered_html( false );

		$this->assertArrayNotHasKey( 'kses', wp_get_code_editor_settings( array( 'type' => 'text/css' ) )['htmlhint'] );
	}

	/**
	 * Tests that settings passed in the arguments are merged over the defaults.
	 *
	 * @ticket 65819
	 */
	public function test_arguments_override_defaults() {
		$settings = wp_get_code_editor_settings(
			array(
				'type'       => 'text/css',
				'codemirror' => array(
					'indentUnit' => 2,
					'mode'       => 'custom-mode',
				),
				'csslint'    => array( 'outline-none' => false ),
				'jshint'     => array( 'esversion' => 6 ),
				'htmlhint'   => array( 'alt-require' => false ),
				'unknown'    => array( 'key' => 'value' ),
			)
		);

		$this->assertSame( 2, $settings['codemirror']['indentUnit'], 'The CodeMirror setting should be overridden.' );
		$this->assertSame( 'custom-mode', $settings['codemirror']['mode'], 'The detected mode should be overridden.' );
		$this->assertTrue( $settings['codemirror']['indentWithTabs'], 'Other CodeMirror settings should be kept.' );
		$this->assertFalse( $settings['csslint']['outline-none'], 'The CSSLint rule should be overridden.' );
		$this->assertTrue( $settings['csslint']['errors'], 'Other CSSLint rules should be kept.' );
		$this->assertSame( 6, $settings['jshint']['esversion'], 'The JSHint option should be overridden.' );
		$this->assertTrue( $settings['jshint']['curly'], 'Other JSHint options should be kept.' );
		$this->assertFalse( $settings['htmlhint']['alt-require'], 'The HTMLHint rule should be overridden.' );
		$this->assertTrue( $settings['htmlhint']['id-unique'], 'Other HTMLHint rules should be kept.' );
		$this->assertSame( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $settings ), 'Unknown arguments should not be added to the settings.' );
	}

	/**
	 * Tests that the settings are filterable, and that the filter receives the settings and arguments.
	 *
	 * @ticket 65819
	 */
	public function test_wp_code_editor_settings_filter() {
		$args = array( 'type' => 'text/css' );

		$filter = new MockAction();
		add_filter( 'wp_code_editor_settings', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'wp_code_editor_settings', '__return_false', 20 );

		$this->assertFalse( wp_get_code_editor_settings( $args ), 'The filtered value should be returned.' );

		$filter_args = $filter->get_args();

		$this->assertCount( 1, $filter_args, 'The filter should run once.' );
		$this->assertSame( 'text/css', $filter_args[0][0]['codemirror']['mode'], 'The filter should receive the settings.' );
		$this->assertSame( $args, $filter_args[0][1], 'The filter should receive the arguments.' );
	}

	/**
	 * Grants or denies the unfiltered_html capability to the current user.
	 *
	 * @param bool $allow Whether to grant the capability.
	 */
	private function allow_unfiltered_html( $allow ) {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) use ( $allow ) {
				if ( 'unfiltered_html' === $cap ) {
					return $allow ? array( 'exist' ) : array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			2
		);
	}
}
