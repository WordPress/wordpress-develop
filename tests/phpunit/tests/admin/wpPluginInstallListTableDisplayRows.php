<?php

/**
 * Tests for WP_Plugin_Install_List_Table::display_rows().
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Install_List_Table::display_rows
 */
class Tests_Admin_wpPluginInstallListTableDisplayRows extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original value of the version global.
	 *
	 * @var string
	 */
	private $original_version;

	/**
	 * Creates the administrator, and loads the tested file.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

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
	 * Uses a stable version, and the administrator.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_version = $GLOBALS['wp_version'];

		$GLOBALS['wp_version'] = '7.0';

		wp_set_current_user( self::$admin_id );
		set_site_transient( 'update_plugins', (object) array() );
	}

	/**
	 * Restores the version.
	 */
	public function tear_down() {
		$GLOBALS['wp_version'] = $this->original_version;

		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Returns the data of a plugin of the directory.
	 *
	 * @param array $overrides Optional. Values that replace the defaults. Default none.
	 * @return array The plugin.
	 */
	private function get_plugin( array $overrides = array() ) {
		return array_merge(
			array(
				'name'              => 'Contact Forms',
				'slug'              => 'contact-forms',
				'version'           => '2.1',
				'author'            => '<a href="https://example.org/jane">Jane Doe</a>',
				'short_description' => 'Build simple forms.',
				'requires'          => '5.0',
				'requires_php'      => '7.0',
				'tested'            => '7.0',
				'rating'            => 90,
				'num_ratings'       => 1234,
				'active_installs'   => 5000,
				'last_updated'      => gmdate( 'Y-m-d H:i:s', time() - ( 3 * DAY_IN_SECONDS ) ),
				'icons'             => array( 'default' => 'https://example.org/default.png' ),
			),
			$overrides
		);
	}

	/**
	 * Renders the rows of plugins.
	 *
	 * @param array[]  $plugins The plugins.
	 * @param string[] $groups  Optional. The names of the groups, by slug. Default none.
	 * @return string The output.
	 */
	private function render( array $plugins, array $groups = array() ) {
		$table         = _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
		$table->items  = $plugins;
		$table->groups = $groups;

		return get_echo( array( $table, 'display_rows' ) );
	}

	/**
	 * Tests that nothing is output without plugins.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_nothing_without_plugins() {
		$this->assertSame( '', $this->render( array() ) );
	}

	/**
	 * Tests the card of a plugin.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_the_card_of_a_plugin() {
		$output = $this->render( array( $this->get_plugin() ) );

		$this->assertStringContainsString( '<div class="plugin-card plugin-card-contact-forms">', $output, 'The card should have the slug as a class.' );
		$this->assertMatchesRegularExpression( '/<h3>\s*<a [^>]*class="thickbox open-plugin-details-modal">\s*Contact Forms\s*<img /', $output, 'The name should be in the heading of the card.' );
		$this->assertStringContainsString( '<p>Build simple forms.</p>', $output, 'The description should be output.' );
		$this->assertStringContainsString( '<cite>By <a href="https://example.org/jane">Jane Doe</a></cite>', $output, 'The author should be output.' );
		$this->assertStringContainsString( 'plugin-install.php?tab=plugin-information&#038;plugin=contact-forms&#038;TB_iframe=true&#038;width=600&#038;height=550', $output, 'The link to the details should be output.' );
		$this->assertStringContainsString( 'data-title="Contact Forms 2.1">More Details</a>', $output, 'The name and the version should name the details.' );
		$this->assertStringContainsString( 'aria-label="More information about Contact Forms 2.1"', $output );
	}

	/**
	 * Tests that the objects of the API are shown like arrays.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_a_plugin_that_is_an_object() {
		$plugin = (object) $this->get_plugin();

		$this->assertStringContainsString( 'plugin-card-contact-forms', $this->render( array( $plugin ) ) );
	}

	/**
	 * Tests that every plugin has a card.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_a_card_for_each_plugin() {
		$output = $this->render(
			array(
				$this->get_plugin(),
				$this->get_plugin(
					array(
						'name' => 'Seo Tools',
						'slug' => 'seo-tools',
					)
				),
			)
		);

		$this->assertSame( 2, substr_count( $output, '<div class="plugin-card ' ) );
		$this->assertStringContainsString( 'plugin-card-seo-tools', $output );
	}

	/**
	 * Tests that the markup of the description and the title is limited.
	 *
	 * @ticket 66554
	 */
	public function test_should_remove_the_markup_that_is_not_allowed() {
		$output = $this->render(
			array(
				$this->get_plugin(
					array(
						'name'              => 'Forms <script>alert(1)</script><strong>Pro</strong>',
						'short_description' => 'Build <b>simple</b> <a href="#">forms</a>.',
						'author'            => 'Jane <img src=x onerror=alert(1)>',
					)
				),
			)
		);

		$this->assertStringNotContainsString( '<script>', $output, 'The script should be removed.' );
		$this->assertStringNotContainsString( 'onerror', $output, 'The attributes that are not allowed should be removed.' );
		$this->assertStringContainsString( '<strong>Pro</strong>', $output, 'The markup that is allowed should be kept in the title.' );
		$this->assertStringContainsString( '<p>Build simple forms.</p>', $output, 'The markup should be removed from the description.' );
	}

	/**
	 * Tests that the description can be filtered.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_description_of_the_plugin_install_description_filter() {
		$received = array();
		add_filter(
			'plugin_install_description',
			static function ( $description, $plugin ) use ( &$received ) {
				$received = array( $description, $plugin['slug'] );

				return 'Filtered description.';
			},
			10,
			2
		);

		$output = $this->render( array( $this->get_plugin() ) );

		$this->assertSame( array( 'Build simple forms.', 'contact-forms' ), $received );
		$this->assertStringContainsString( '<p>Filtered description.</p>', $output );
	}

	/**
	 * Tests that a plugin without an author has no author line.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_output_an_author_without_one() {
		$output = $this->render( array( $this->get_plugin( array( 'author' => '' ) ) ) );

		$this->assertStringNotContainsString( '<cite>', $output );
		$this->assertStringContainsString( '<p class="authors"></p>', $output );
	}

	/**
	 * Tests the groups of plugins.
	 *
	 * @ticket 66554
	 */
	public function test_should_group_the_plugins() {
		$output = $this->render(
			array(
				$this->get_plugin( array( 'group' => 'tools' ) ),
				$this->get_plugin(
					array(
						'slug'  => 'two',
						'group' => 'tools',
					)
				),
				$this->get_plugin(
					array(
						'slug'  => 'three',
						'group' => 'other',
					)
				),
			),
			array(
				'tools' => 'Tools',
				'other' => 'Not Translated',
			)
		);

		$this->assertSame( 2, substr_count( $output, '<div class="plugin-group">' ), 'There should be two groups.' );
		$this->assertSame( 1, substr_count( $output, '<h3>Tools</h3>' ), 'The heading of a group should be output once.' );
		$this->assertStringContainsString( '<h3>Not Translated</h3>', $output, 'The name of the group should be output.' );
		$this->assertSame( 2, substr_count( $output, '<div class="plugin-items">' ) );
		$this->assertStringStartsWith( '<div class="plugin-group"><h3>Tools</h3><div class="plugin-items">', $output, 'The first group should start the output.' );
		$this->assertStringContainsString( '</div></div><div class="plugin-group"><h3>Not Translated</h3><div class="plugin-items">', $output, 'The first group should be closed before the next one.' );
		$this->assertStringEndsWith( '</div></div>', $output, 'The last group should be closed.' );
	}

	/**
	 * Tests that the name of a group that is not listed is its slug.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_slug_of_a_group_that_is_not_listed() {
		$output = $this->render( array( $this->get_plugin( array( 'group' => 'unlisted' ) ) ), array() );

		$this->assertStringContainsString( '<h3>unlisted</h3>', $output );
	}

	/**
	 * Tests that no group is output for plugins without one.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_output_a_group_without_one() {
		$output = $this->render( array( $this->get_plugin() ), array( 'tools' => 'Tools' ) );

		$this->assertStringNotContainsString( 'plugin-group', $output );
	}

	/**
	 * Tests the icon of a plugin.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_icons
	 *
	 * @param array  $icons    The icons.
	 * @param string $expected The expected icon.
	 */
	public function test_should_use_the_best_icon( array $icons, $expected ) {
		$output = $this->render( array( $this->get_plugin( array( 'icons' => $icons ) ) ) );

		$this->assertStringContainsString( '<img src="' . $expected . '" class="plugin-icon" alt="" />', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_icons() {
		$all = array(
			'svg'     => 'https://example.org/icon.svg',
			'2x'      => 'https://example.org/icon-2x.png',
			'1x'      => 'https://example.org/icon-1x.png',
			'default' => 'https://example.org/default.png',
		);

		return array(
			'the SVG'     => array( $all, 'https://example.org/icon.svg' ),
			'the 2x icon' => array( array_diff_key( $all, array( 'svg' => 1 ) ), 'https://example.org/icon-2x.png' ),
			'the 1x icon' => array(
				array_diff_key(
					$all,
					array(
						'svg' => 1,
						'2x'  => 1,
					)
				),
				'https://example.org/icon-1x.png',
			),
			'the default' => array(
				array_diff_key(
					$all,
					array(
						'svg' => 1,
						'2x'  => 1,
						'1x'  => 1,
					)
				),
				'https://example.org/default.png',
			),
		);
	}

	/**
	 * Tests that the action links can be filtered.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_links_of_the_plugin_install_action_links_filter() {
		$received = array();
		add_filter(
			'plugin_install_action_links',
			static function ( $links, $plugin ) use ( &$received ) {
				$received = array( count( $links ), $plugin['slug'] );

				return array( '<a href="#custom">Custom action</a>' );
			},
			10,
			2
		);

		$output = $this->render( array( $this->get_plugin() ) );

		$this->assertSame( array( 2, 'contact-forms' ), $received, 'The filter should receive the button and the link to the details.' );
		$this->assertStringContainsString( '<ul class="plugin-action-buttons"><li><a href="#custom">Custom action</a></li></ul>', $output );
		$this->assertStringNotContainsString( 'More Details', $output, 'The link to the details should be replaced.' );
	}

	/**
	 * Tests that no list of actions is output when the filter removes them.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_output_actions_when_the_filter_removes_them() {
		add_filter( 'plugin_install_action_links', '__return_empty_array' );

		$this->assertStringNotContainsString( 'plugin-action-buttons', $this->render( array( $this->get_plugin() ) ) );
	}

	/**
	 * Tests the rating and the number of ratings.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_the_rating() {
		$output = $this->render( array( $this->get_plugin() ) );

		$this->assertStringContainsString( '4.5 rating based on 1,234 ratings', $output, 'The stars should be output.' );
		$this->assertStringContainsString( '(1,234)', $output, 'The number of ratings should be formatted.' );
	}

	/**
	 * Tests the time of the last update.
	 *
	 * @ticket 66554
	 */
	public function test_should_output_the_time_since_the_last_update() {
		$output = $this->render( array( $this->get_plugin() ) );

		$this->assertStringContainsString( '<strong>Last Updated:</strong>', $output );
		$this->assertStringContainsString( '3 days ago', $output );
	}

	/**
	 * Tests the text of the active installations.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_active_installs
	 *
	 * @param int    $installs The number of active installations.
	 * @param string $expected The expected text.
	 */
	public function test_should_output_the_active_installations( $installs, $expected ) {
		$output = $this->render( array( $this->get_plugin( array( 'active_installs' => $installs ) ) ) );

		$this->assertStringContainsString( $expected . ' Active Installations', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_active_installs() {
		return array(
			'none'                => array( 0, 'Less Than 10' ),
			'a few'               => array( 5, '5+' ),
			'thousands'           => array( 5000, '5,000+' ),
			'a million'           => array( 1000000, '1+ Million' ),
			'millions and a half' => array( 3500000, '3+ Million' ),
		);
	}

	/**
	 * Tests the compatibility with the version of WordPress.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_compatibility
	 *
	 * @param array  $overrides The values of the plugin.
	 * @param string $css_class The expected class of the compatibility.
	 */
	public function test_should_output_the_compatibility( array $overrides, $css_class ) {
		$output = $this->render( array( $this->get_plugin( $overrides ) ) );

		$this->assertStringContainsString( '<span class="' . $css_class . '">', $output );
		$this->assertSame( 1, substr_count( $output, 'compatibility-' ), 'Only one compatibility should be output.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_compatibility() {
		return array(
			'compatible'                  => array( array(), 'compatibility-compatible' ),
			'tested up to the version'    => array( array( 'tested' => '7.0' ), 'compatibility-compatible' ),
			'tested with a newer version' => array( array( 'tested' => '9.0' ), 'compatibility-compatible' ),
			'not tested'                  => array( array( 'tested' => '5.0' ), 'compatibility-untested' ),
			'without a tested version'    => array( array( 'tested' => '' ), 'compatibility-compatible' ),
			'needs a newer WordPress'     => array( array( 'requires' => '99.0' ), 'compatibility-incompatible' ),
		);
	}

	/**
	 * Tests the notice for a plugin that is not compatible, by what the user can update.
	 *
	 * Updating PHP needs the capability to update WordPress, so a filter separates them.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_incompatible_notice
	 *
	 * @param array    $overrides       The values of the plugin.
	 * @param bool     $can_update_core Whether the user can update WordPress.
	 * @param bool     $can_update_php  Whether the user can learn about updating PHP.
	 * @param string[] $expected        The strings that the notice should contain.
	 * @param string[] $unexpected      The strings that the notice should not contain.
	 */
	public function test_should_output_a_notice_for_a_plugin_that_is_not_compatible( array $overrides, $can_update_core, $can_update_php, array $expected, array $unexpected ) {
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) use ( $can_update_core, $can_update_php ) {
				if ( 'update_core' === $cap ) {
					return $can_update_core ? array( 'exist' ) : array( 'do_not_allow' );
				}

				if ( 'update_php' === $cap ) {
					return $can_update_php ? array( 'exist' ) : array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			2
		);

		$output = $this->render( array( $this->get_plugin( $overrides ) ) );

		$this->assertStringContainsString( 'notice-error notice-alt inline', $output, 'The notice should be output.' );

		foreach ( $expected as $string ) {
			$this->assertStringContainsString( $string, $output );
		}

		foreach ( $unexpected as $string ) {
			$this->assertStringNotContainsString( $string, $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incompatible_notice() {
		$both = array(
			'requires'     => '99.0',
			'requires_php' => '99.0',
		);

		return array(
			'both, WordPress and PHP'       => array(
				$both,
				true,
				true,
				array( 'This plugin does not work with your versions of WordPress and PHP.', '/update-core.php">Please update WordPress</a>, and then <a href=', 'learn more about updating PHP</a>.' ),
				array(),
			),
			'both, WordPress only'          => array(
				$both,
				true,
				false,
				array( 'This plugin does not work with your versions of WordPress and PHP.', '/update-core.php">Please update WordPress</a>.' ),
				array( 'pdating PHP' ),
			),
			'both, PHP only'                => array(
				$both,
				false,
				true,
				array( 'This plugin does not work with your versions of WordPress and PHP.', 'Learn more about updating PHP</a>.' ),
				array( 'Please update WordPress' ),
			),
			'both, neither'                 => array(
				$both,
				false,
				false,
				array( 'This plugin does not work with your versions of WordPress and PHP.' ),
				array( 'Please update WordPress', 'pdating PHP' ),
			),
			'WordPress, can update'         => array(
				array( 'requires' => '99.0' ),
				true,
				false,
				array( 'This plugin does not work with your version of WordPress.', 'Please update WordPress</a>.' ),
				array( 'PHP' ),
			),
			'WordPress, cannot update'      => array(
				array( 'requires' => '99.0' ),
				false,
				true,
				array( 'This plugin does not work with your version of WordPress.' ),
				array( 'Please update WordPress' ),
			),
			'PHP, can learn about updating' => array(
				array( 'requires_php' => '99.0' ),
				false,
				true,
				array( 'This plugin does not work with your version of PHP.', 'Learn more about updating PHP</a>.' ),
				array( 'WordPress.' ),
			),
			'PHP, cannot learn'             => array(
				array( 'requires_php' => '99.0' ),
				true,
				false,
				array( 'This plugin does not work with your version of PHP.' ),
				array( 'pdating PHP' ),
			),
		);
	}

	/**
	 * Tests that no notice is output for a plugin that is compatible.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_output_a_notice_for_a_plugin_that_is_compatible() {
		$this->assertStringNotContainsString( 'notice-error', $this->render( array( $this->get_plugin() ) ) );
	}
}
