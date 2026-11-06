<?php

/**
 * Tests for the sidebar with the details of the plugin of install_plugin_information().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::install_plugin_information
 */
class Admin_Includes_Plugin_Install_InstallPluginInformationDetails_Test extends WP_UnitTestCase {

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

		// Answer the request to WordPress.org, because the API marks a result that a filter returns as external.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $api ) {
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

				$this->api_requests[] = $query['request']['slug'] ?? '';

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $api ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
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
	 * Returns the output of the list of the details of a plugin.
	 *
	 * @param array $overrides Optional. Values that replace the defaults of the plugin. Default none.
	 * @return string The output.
	 */
	private function render_details( array $overrides = array() ) {
		return $this->render( $this->get_api( $overrides ) );
	}

	/**
	 * Tests the fields that are listed when the plugin has them.
	 *
	 * @ticket 66566
	 */
	public function test_should_list_the_details_of_the_plugin() {
		$output = $this->render_details(
			array(
				'version'      => '2.3',
				'author'       => '<a href="https://example.org/jane">Jane Doe</a>',
				'last_updated' => gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS ) ),
				'requires'     => '5.0',
				'tested'       => '6.5',
				'requires_php' => '7.4',
			)
		);

		$this->assertStringContainsString( '<li><strong>Version:</strong> 2.3</li>', $output );
		$this->assertStringContainsString( '<strong>Author:</strong> <a href="https://example.org/jane" target="_blank">Jane Doe</a>', $output, 'The author should open in a new tab.' );
		$this->assertStringContainsString( '3 days ago', $output );
		$this->assertMatchesRegularExpression( '/<strong>Requires WordPress Version:<\/strong>\s+5\.0 or higher/', $output );
		$this->assertStringContainsString( '<strong>Compatible up to:</strong> 6.5', $output );
		$this->assertMatchesRegularExpression( '/<strong>Requires PHP Version:<\/strong>\s+7\.4 or higher/', $output );
	}

	/**
	 * Tests that the fields that the plugin does not have are not listed.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $field The name of the field of the plugin.
	 * @param string $label The label of the field.
	 */
	public function test_should_not_list_a_detail_that_the_plugin_does_not_have( $field, $label ) {
		$output = $this->render_details( array( $field => '' ) );

		$this->assertStringNotContainsString( "<strong>{$label}", $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields() {
		return array(
			'the version'     => array( 'version', 'Version:' ),
			'the author'      => array( 'author', 'Author:' ),
			'the last update' => array( 'last_updated', 'Last Updated:' ),
			'the requirement' => array( 'requires', 'Requires WordPress Version:' ),
			'the tested'      => array( 'tested', 'Compatible up to:' ),
			'the PHP version' => array( 'requires_php', 'Requires PHP Version:' ),
		);
	}

	/**
	 * Tests the active installations.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_active_installs
	 *
	 * @param int    $installs The number of active installations.
	 * @param string $expected The expected text.
	 */
	public function test_should_list_the_active_installations( $installs, $expected ) {
		$output = $this->render_details( array( 'active_installs' => $installs ) );

		$this->assertMatchesRegularExpression( '/<strong>Active Installations:<\/strong>\s+' . preg_quote( $expected, '/' ) . '\s*<\/li>/', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_active_installs() {
		return array(
			'none'                => array( 0, 'Less Than 10' ),
			'a few'               => array( 9, 'Less Than 10' ),
			'ten'                 => array( 10, '10+' ),
			'thousands'           => array( 5000, '5,000+' ),
			'a million'           => array( 1000000, '1+ Million' ),
			'millions and a half' => array( 3500000, '3+ Million' ),
		);
	}

	/**
	 * Tests that the active installations are not listed when the plugin does not have them.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_list_the_active_installations_when_they_are_not_known() {
		$this->assertStringNotContainsString( 'Active Installations', $this->render_details() );
	}

	/**
	 * Tests the link to the page of the plugin on WordPress.org.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_external
	 *
	 * @param array $overrides The values of the plugin.
	 * @param bool  $expected  Whether the link should be output.
	 */
	public function test_should_link_the_page_of_the_plugin_on_wordpress_org_except_for_external_plugins( array $overrides, $expected ) {
		$output = $this->render_details( $overrides );

		$this->assertSame( $expected, str_contains( $output, '<a target="_blank" href="https://wordpress.org/plugins/my-plugin/">WordPress.org Plugin Page &#187;</a>' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_external() {
		return array(
			'a plugin of the directory' => array( array(), true ),
			'a plugin that is external' => array( array( 'external' => true ), false ),
		);
	}

	/**
	 * Tests that the page of the plugin is not linked when the API returns no slug.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_link_the_page_of_the_plugin_without_a_slug_in_the_response() {
		$output = $this->render( $this->get_api( array( 'slug' => '' ) ), array( 'plugin' => 'my-plugin' ) );

		$this->assertStringNotContainsString( 'WordPress.org Plugin Page', $output );
	}

	/**
	 * Tests the link to the homepage of the plugin.
	 *
	 * @ticket 66566
	 */
	public function test_should_link_the_homepage_of_the_plugin() {
		$this->assertStringContainsString(
			'<a target="_blank" href="https://example.org/my-plugin">Plugin Homepage &#187;</a>',
			$this->render_details( array( 'homepage' => 'https://example.org/my-plugin' ) )
		);
		$this->assertStringNotContainsString( 'Plugin Homepage', $this->render_details() );
	}

	/**
	 * Tests the link to donate, which is in the sidebar when there are no contributors.
	 *
	 * @ticket 66566
	 */
	public function test_should_link_the_donate_page_in_the_list_when_there_are_no_contributors() {
		$output = $this->render_details( array( 'donate_link' => 'https://example.org/donate' ) );

		$this->assertSame( 1, substr_count( $output, 'Donate to this plugin &#187;' ), 'The link should be output once.' );
		$this->assertStringContainsString( '<li><a target="_blank" href="https://example.org/donate">Donate to this plugin &#187;</a></li>', $output );
	}

	/**
	 * Tests the rating of the plugin.
	 *
	 * @ticket 66566
	 *
	 * @dataProvider data_ratings
	 *
	 * @param int    $num_ratings The number of ratings.
	 * @param string $expected    The expected description of the number.
	 */
	public function test_should_output_the_average_rating( $num_ratings, $expected ) {
		$output = $this->render_details(
			array(
				'rating'      => 90,
				'num_ratings' => $num_ratings,
			)
		);

		$this->assertStringContainsString( '<h3>Average Rating</h3>', $output );
		$this->assertStringContainsString( '4.5 rating based on', $output, 'The stars should be output.' );
		$this->assertStringContainsString( $expected, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ratings() {
		return array(
			'one rating'   => array( 1, '(based on 1 rating)' ),
			'many ratings' => array( 1234, '(based on 1,234 ratings)' ),
		);
	}

	/**
	 * Tests that no rating is output without one.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_output_the_average_rating_without_one() {
		$this->assertStringNotContainsString( 'Average Rating', $this->render_details() );
	}

	/**
	 * Tests the counters of the reviews.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_a_counter_for_each_number_of_stars() {
		$output = $this->render_details(
			array(
				'rating'      => 80,
				'num_ratings' => 10,
				'ratings'     => array(
					5 => 6,
					1 => 4,
				),
			)
		);

		$this->assertStringContainsString( '<h3>Reviews</h3>', $output );
		$this->assertStringContainsString( 'Read all reviews on WordPress.org or write your own!', $output );
		$this->assertStringContainsString( '<a href="https://wordpress.org/support/plugin/my-plugin/reviews/?filter=5" target="_blank" aria-label="Reviews with 5 stars: 6. Opens in a new tab.">5 stars</a>', $output );
		$this->assertStringContainsString( '<a href="https://wordpress.org/support/plugin/my-plugin/reviews/?filter=1" target="_blank" aria-label="Reviews with 1 star: 4. Opens in a new tab.">1 star</a>', $output );
		$this->assertStringContainsString( 'style="width: 55.2px;"', $output, 'The bar of 6 of 10 ratings should be 92 pixels wide at most.' );
		$this->assertStringContainsString( 'style="width: 36.8px;"', $output );
		$this->assertStringContainsString( '<span class="counter-count" aria-hidden="true">6</span>', $output );
	}

	/**
	 * Tests that the bars are empty when the number of ratings is zero, instead of dividing by zero.
	 *
	 * @ticket 66566
	 */
	public function test_should_output_empty_bars_when_the_number_of_ratings_is_zero() {
		$output = $this->render_details(
			array(
				'num_ratings' => 0,
				'ratings'     => array( 5 => 3 ),
			)
		);

		$this->assertStringContainsString( '<span class="counter-bar" style="width: 0px;"></span>', $output );
	}

	/**
	 * Tests that the counters are not output without ratings.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_output_the_counters_without_ratings() {
		$this->assertStringNotContainsString( 'counter-container', $this->render_details( array( 'ratings' => array() ) ) );
		$this->assertStringNotContainsString(
			'counter-container',
			$this->render_details(
				array(
					'ratings' => array(
						5 => 0,
						4 => 0,
					),
				)
			),
			'Ratings that are all zero should not be output.'
		);
	}

	/**
	 * Tests the contributors.
	 *
	 * @ticket 66566
	 */
	public function test_should_list_the_contributors() {
		$output = $this->render_details(
			array(
				'contributors' => array(
					'jane'  => array(
						'display_name' => 'Jane <b>Doe</b>',
						'profile'      => 'https://profiles.example.org/jane',
						'avatar'       => 'https://avatars.example.org/jane?d=mm',
					),
					'sam99' => array(
						'display_name' => '',
						'profile'      => 'https://profiles.example.org/sam99',
						'avatar'       => 'https://avatars.example.org/sam99',
					),
				),
			)
		);

		$this->assertStringContainsString( '<h3>Contributors</h3>', $output );
		$this->assertStringContainsString( "<li><a href='https://profiles.example.org/jane' target='_blank'><img src='https://avatars.example.org/jane?d=mm&#038;s=36' width='18' height='18' alt='' />Jane &lt;b&gt;Doe&lt;/b&gt;</a></li>", $output, 'The name should be escaped.' );
		$this->assertStringContainsString( "target='_blank'><img src='https://avatars.example.org/sam99?s=36' width='18' height='18' alt='' />sam99</a></li>", $output, 'The username should be used without a name.' );
	}

	/**
	 * Tests that the link to donate is after the contributors, and not in the list of the details.
	 *
	 * @ticket 66566
	 */
	public function test_should_link_the_donate_page_after_the_contributors() {
		$output = $this->render_details(
			array(
				'donate_link'  => 'https://example.org/donate',
				'contributors' => array(
					'jane' => array(
						'display_name' => 'Jane',
						'profile'      => 'https://profiles.example.org/jane',
						'avatar'       => 'https://avatars.example.org/jane',
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $output, 'Donate to this plugin &#187;' ), 'The link should be output once.' );
		$this->assertStringNotContainsString( '<li><a target="_blank" href="https://example.org/donate">', $output, 'The link should not be in the list.' );
		$this->assertStringContainsString( '<a target="_blank" href="https://example.org/donate">Donate to this plugin &#187;</a>', $output );
	}

	/**
	 * Tests that the contributors are not listed without them.
	 *
	 * @ticket 66566
	 */
	public function test_should_not_list_contributors_without_them() {
		$this->assertStringNotContainsString( 'Contributors', $this->render_details() );
	}
}
