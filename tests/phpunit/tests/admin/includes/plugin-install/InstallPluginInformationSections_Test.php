<?php

/**
 * Tests for the data and the sections of install_plugin_information().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::install_plugin_information
 */
class Admin_Includes_Plugin_Install_InstallPluginInformationSections_Test extends WP_UnitTestCase {

	/**
	 * The message of the exception that stops the screen before it ends the request.
	 */
	const STOP = 'Stop the plugin information screen.';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original values of the globals and the request that the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * The slugs that the Plugin Installation API was asked for.
	 *
	 * @var string[]
	 */
	private $api_requests = array();

	/**
	 * Creates the administrator, and loads the tested files.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin fixture.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Saves the globals and the request.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'tab', 'hook_suffix', 'current_screen' ) as $name ) {
			$this->original['globals'][ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$this->original['request'] = $_REQUEST;
		$this->api_requests        = array();

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the globals and the request.
	 */
	public function tear_down() {
		foreach ( $this->original['globals'] as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$_REQUEST = $this->original['request'];

		parent::tear_down();
	}

	/**
	 * Returns the data of a plugin, as the API returns it.
	 *
	 * @param array $overrides Optional. Values that replace the defaults. Default none.
	 * @return object The data.
	 */
	private function get_api( array $overrides = array() ) {
		return (object) array_merge(
			array(
				'name'     => 'My Plugin',
				'slug'     => 'my-plugin',
				'version'  => '1.0',
				'external' => true,
				'sections' => array(
					'description'  => '<p>The description.</p>',
					'installation' => '<p>The installation.</p>',
					'faq'          => '<p>The questions.</p>',
				),
			),
			$overrides
		);
	}

	/**
	 * Outputs the screen of a plugin, and returns the output.
	 *
	 * The screen ends the request, so an action of the footer stops it before.
	 *
	 * @param object|WP_Error $api     The response of the API.
	 * @param array           $request Optional. The request data besides the plugin. Default none.
	 * @return string The output.
	 */
	private function render( $api, array $request = array() ) {
		$GLOBALS['tab']         = 'plugin-information';
		$GLOBALS['hook_suffix'] = 'plugin-install.php';

		set_current_screen( 'plugin-install' );

		$_REQUEST = array_merge( array( 'plugin' => $api->slug ?? 'my-plugin' ), $request );

		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) use ( $api ) {
				$this->api_requests[] = $args->slug;

				return $api;
			},
			10,
			3
		);
		add_action(
			'admin_footer',
			static function () {
				throw new RuntimeException( self::STOP );
			}
		);

		// The headers have been sent by the output of the tests. Other errors are left to the handler of the tests.
		$previous_handler = set_error_handler(
			static function ( $errno, $errstr, $errfile, $errline ) use ( &$previous_handler ) {
				if ( str_contains( $errstr, 'headers already sent' ) ) {
					return true;
				}

				return $previous_handler ? $previous_handler( $errno, $errstr, $errfile, $errline ) : false;
			},
			E_WARNING
		);

		ob_start();

		try {
			install_plugin_information();
			$this->fail( 'The screen should stop before it ends the request.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( self::STOP, $e->getMessage() );
		} finally {
			restore_error_handler();
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Tests that nothing is output without a plugin in the request.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_nothing_without_a_plugin() {
		$_REQUEST = array();

		add_filter(
			'plugins_api',
			function () {
				$this->api_requests[] = 'asked';

				return $this->get_api();
			}
		);

		$this->assertSame( '', get_echo( 'install_plugin_information' ) );
		$this->assertSame( array(), $this->api_requests, 'The API should not be asked.' );
	}

	/**
	 * Tests that the screen dies with the error of the API.
	 *
	 * @ticket 66566
	 */
	public function test_should_die_with_the_error_of_the_api() {
		$_REQUEST['plugin'] = 'missing-plugin';

		add_filter(
			'plugins_api',
			static function () {
				return new WP_Error( 'plugins_api_failed', 'Plugin not found.' );
			}
		);

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Plugin not found.' );

		install_plugin_information();
	}

	/**
	 * Tests the slug that the API is asked for.
	 *
	 * @ticket 66566
	 */
	public function test_should_ask_the_api_for_the_unslashed_plugin_of_the_request() {
		$this->render( $this->get_api(), array( 'plugin' => 'my\\\\-plugin' ) );

		$this->assertSame( array( 'my\\-plugin' ), $this->api_requests );
	}

	/**
	 * Tests the title of the screen.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_the_title_of_the_screen_and_the_name_of_the_plugin() {
		$output = $this->render( $this->get_api() );

		$this->assertStringContainsString( '&rsaquo; Plugin Installation', $output, 'The title of the screen should be output.' );
		$this->assertStringContainsString( "<div id='plugin-information-title' class=''><div class='vignette'></div><h2>My Plugin</h2></div>", $output );
		$this->assertStringContainsString( '<div id="plugin-information-scrollable">', str_replace( "'", '"', $output ) );
	}

	/**
	 * Tests that the markup that is not allowed is removed from the sections.
	 *
	 * @ticket 66566
	 */
	public function test_should_remove_the_markup_that_is_not_allowed_from_the_sections() {
		$output = $this->render(
			$this->get_api(
				array(
					'sections' => array(
						'description' => '<p>Safe <strong>text</strong>.</p><script>alert(1)</script><a href="#" onclick="x()">link</a>',
					),
				)
			)
		);

		$this->assertStringNotContainsString( '<script>alert', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringContainsString( '<p>Safe <strong>text</strong>.</p>', $output );
	}

	/**
	 * Tests that the markup that is not allowed is removed from the fields of the plugin.
	 *
	 * @ticket 66566
	 */
	public function test_should_remove_the_markup_that_is_not_allowed_from_the_fields() {
		$output = $this->render(
			$this->get_api(
				array(
					'version' => '1.0<script>alert(2)</script>',
					'author'  => '<a href="https://example.org/">Jane</a><iframe src="x"></iframe>',
				)
			)
		);

		$this->assertStringNotContainsString( '<script>alert(2)', $output );
		$this->assertStringNotContainsString( '<iframe', $output );
		$this->assertStringContainsString( 'Jane</a>', $output );
	}

	/**
	 * Tests the title of the banner of the plugin.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_banners
	 *
	 * @param array  $banners The banners of the plugin.
	 * @param string $low     The expected URL for the screens of a low density, or an empty string for no banner.
	 * @param string $high    The expected URL for the screens of a high density.
	 */
	public function test_should_use_the_banner_of_the_plugin( array $banners, $low, $high ) {
		$output = $this->render( $this->get_api( array( 'banners' => $banners ) ) );

		if ( '' === $low ) {
			$this->assertStringNotContainsString( 'with-banner', $output, 'No banner should be used.' );
			$this->assertStringNotContainsString( '<style>', $output );

			return;
		}

		$this->assertStringContainsString( "<div id='plugin-information-title' class='with-banner'>", $output );
		$this->assertStringContainsString( "<div id='plugin-information-tabs' class='with-banner'>", $output );
		$this->assertStringContainsString( 'background-image: url( ' . $low . ' );', $output );
		$this->assertStringContainsString( 'background-image: url( ' . $high . ' );', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_banners() {
		return array(
			'both'          => array(
				array(
					'low'  => 'https://example.org/low.png',
					'high' => 'https://example.org/high.png',
				),
				'https://example.org/low.png',
				'https://example.org/high.png',
			),
			'only low'      => array(
				array( 'low' => 'https://example.org/low.png' ),
				'https://example.org/low.png',
				'https://example.org/low.png',
			),
			'only high'     => array(
				array( 'high' => 'https://example.org/high.png' ),
				'https://example.org/high.png',
				'https://example.org/high.png',
			),
			'empty banners' => array(
				array(
					'low'  => '',
					'high' => '',
				),
				'',
				'',
			),
			'no banners'    => array( array(), '', '' ),
		);
	}

	/**
	 * Tests the tabs of the sections, with their titles.
	 *
	 * @ticket 66566
	 */
	public function test_should_link_a_tab_for_each_section() {
		$output = $this->render(
			$this->get_api(
				array(
					'sections' => array(
						'description'  => '<p>One</p>',
						'installation' => '<p>Two</p>',
						'faq'          => '<p>Three</p>',
						'changelog'    => '<p>Four</p>',
						'other_notes'  => '<p>Five</p>',
						'my_section'   => '<p>Six</p>',
					),
				)
			)
		);

		foreach ( array(
			'description'  => 'Description',
			'installation' => 'Installation',
			'faq'          => 'FAQ',
			'changelog'    => 'Changelog',
			'other_notes'  => 'Other Notes',
			'my_section'   => 'My Section',
		) as $name => $title ) {
			$this->assertMatchesRegularExpression( "/<a name='{$name}' href='[^']*section={$name}[^']*' [^>]*>{$title}<\\/a>/", $output, "The tab of the $name section should be linked." );
		}
	}

	/**
	 * Tests that the tabs link to the screen of the plugin.
	 *
	 * @ticket 66566
	 */
	public function test_should_link_the_tabs_with_the_tab_and_the_section() {
		$output = $this->render( $this->get_api() );

		$this->assertStringContainsString( "<a name='faq' href='?tab=plugin-information&#038;section=faq'", $output );
	}

	/**
	 * Tests the tab of the reviews.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_reviews
	 *
	 * @param array $ratings  The ratings of the plugin.
	 * @param bool  $expected Whether the tab of the reviews should be output.
	 */
	public function test_should_link_the_tab_of_the_reviews_only_with_ratings( array $ratings, $expected ) {
		$output = $this->render(
			$this->get_api(
				array(
					'sections'    => array(
						'description' => '<p>One</p>',
						'reviews'     => '<p>The reviews.</p>',
					),
					'ratings'     => $ratings,
					'num_ratings' => array_sum( $ratings ),
				)
			)
		);

		$this->assertSame( $expected, str_contains( $output, "<a name='reviews'" ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_reviews() {
		return array(
			'with ratings'    => array(
				array(
					5 => 3,
					4 => 1,
				),
				true,
			),
			'without ratings' => array( array(), false ),
			'zero ratings'    => array(
				array(
					5 => 0,
					4 => 0,
				),
				false,
			),
		);
	}

	/**
	 * Tests the section that is shown.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_current_section
	 *
	 * @param array  $request  The request data.
	 * @param string $expected The expected current section.
	 */
	public function test_should_show_the_requested_section_or_the_first( array $request, $expected ) {
		$output = $this->render( $this->get_api(), $request );

		foreach ( array( 'description', 'installation', 'faq' ) as $name ) {
			$display = $name === $expected ? 'block' : 'none';

			$this->assertStringContainsString( "<div id='section-$name' class='section' style='display: $display;'>", $output, "The $name section should be $display." );
		}

		$this->assertSame( 1, substr_count( $output, 'class="current"' ), 'One tab should be the current one.' );
		$this->assertMatchesRegularExpression( "/<a name='{$expected}' href='[^']*'\\s+class=\"current\">/", $output, 'The tab of the section should be the current one.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_current_section() {
		return array(
			'no section'                  => array( array(), 'description' ),
			'a section'                   => array( array( 'section' => 'faq' ), 'faq' ),
			'an empty section'            => array( array( 'section' => '' ), 'description' ),
			'a section that is not there' => array( array( 'section' => 'changelog' ), 'description' ),
		);
	}

	/**
	 * Tests that the first section is shown when the plugin has no description.
	 *
	 * @ticket 66566
	 */
	public function test_should_show_the_first_section_when_there_is_no_description() {
		$output = $this->render(
			$this->get_api(
				array(
					'sections' => array(
						'installation' => '<p>The installation.</p>',
						'faq'          => '<p>The questions.</p>',
					),
				)
			)
		);

		$this->assertStringContainsString( "<div id='section-installation' class='section' style='display: block;'>", $output );
		$this->assertStringContainsString( "<div id='section-faq' class='section' style='display: none;'>", $output );
	}

	/**
	 * Tests the links of the sections.
	 *
	 * @ticket 66566
	 */
	public function test_should_make_the_links_of_the_sections_absolute_and_open_them_in_a_new_tab() {
		$output = $this->render(
			$this->get_api(
				array(
					'sections' => array( 'description' => '<p><a href="faq/">Read the FAQ</a> or <a href="https://example.org/">go there</a>.</p>' ),
				)
			)
		);

		$this->assertStringContainsString( 'href="https://wordpress.org/plugins/my-plugin/faq/"', $output, 'A relative link should be made absolute.' );
		$this->assertStringContainsString( 'href="https://example.org/"', $output, 'An absolute link should be kept.' );
		$this->assertSame( 2, substr_count( $output, 'target="_blank"' ), 'Both links should open in a new tab.' );
	}
}
