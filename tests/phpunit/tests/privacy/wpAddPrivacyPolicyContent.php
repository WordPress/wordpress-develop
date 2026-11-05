<?php

/**
 * Tests for wp_add_privacy_policy_content().
 *
 * @group admin
 * @group privacy
 * @group plugins
 *
 * @covers ::wp_add_privacy_policy_content
 * @covers WP_Privacy_Policy_Content::add
 */
class Tests_Privacy_wpAddPrivacyPolicyContent extends WP_UnitTestCase {

	/**
	 * The original suggested policy content.
	 *
	 * @var array
	 */
	private $original_policy_content;

	/**
	 * The original current screen.
	 *
	 * @var WP_Screen|null
	 */
	private $original_screen;

	/**
	 * Loads the privacy policy class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
	}

	/**
	 * Empties the suggested policy content and switches to an admin screen.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_policy_content = $this->get_policy_content();
		$this->original_screen         = $GLOBALS['current_screen'] ?? null;

		$this->set_policy_content( array() );
		set_current_screen( 'dashboard' );

		// The hooks are restored after each test.
		unset( $GLOBALS['wp_actions']['admin_init'] );
	}

	/**
	 * Restores the suggested policy content and the current screen.
	 */
	public function tear_down() {
		$this->set_policy_content( $this->original_policy_content );
		$GLOBALS['current_screen'] = $this->original_screen;

		parent::tear_down();
	}

	/**
	 * Tests that the content is rejected outside the admin.
	 *
	 * @ticket 65819
	 *
	 * @expectedIncorrectUsage wp_add_privacy_policy_content
	 */
	public function test_should_reject_content_outside_admin() {
		set_current_screen( 'front' );
		$GLOBALS['wp_actions']['admin_init'] = 1;

		wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );

		$this->assertSame( array(), $this->get_policy_content() );
	}

	/**
	 * Tests that the content is rejected before the admin_init action.
	 *
	 * @ticket 65819
	 *
	 * @expectedIncorrectUsage wp_add_privacy_policy_content
	 */
	public function test_should_reject_content_before_admin_init() {
		wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );

		$this->assertSame( array(), $this->get_policy_content() );
	}

	/**
	 * Tests that the content is added during the admin_init action.
	 *
	 * @ticket 65819
	 */
	public function test_should_add_content_during_admin_init() {
		$GLOBALS['wp_current_filter'][] = 'admin_init';

		try {
			wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );
		} finally {
			array_pop( $GLOBALS['wp_current_filter'] );
		}

		$this->assertSame(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>Policy.</p>',
				),
			),
			$this->get_policy_content()
		);
	}

	/**
	 * Tests that the content is added after the admin_init action.
	 *
	 * @ticket 65819
	 */
	public function test_should_add_content_after_admin_init() {
		$GLOBALS['wp_actions']['admin_init'] = 1;

		wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );

		$this->assertSame(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>Policy.</p>',
				),
			),
			$this->get_policy_content()
		);
	}

	/**
	 * Tests that content without a plugin name or policy text is not added.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_values
	 *
	 * @param mixed $plugin_name The plugin name.
	 * @param mixed $policy_text The policy text.
	 */
	public function test_should_not_add_content_without_name_or_text( $plugin_name, $policy_text ) {
		$GLOBALS['wp_actions']['admin_init'] = 1;

		wp_add_privacy_policy_content( $plugin_name, $policy_text );

		$this->assertSame( array(), $this->get_policy_content() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_values() {
		return array(
			'empty plugin name' => array( '', '<p>Policy.</p>' ),
			'zero plugin name'  => array( '0', '<p>Policy.</p>' ),
			'null plugin name'  => array( null, '<p>Policy.</p>' ),
			'empty policy text' => array( 'My Plugin', '' ),
			'zero policy text'  => array( 'My Plugin', '0' ),
			'false policy text' => array( 'My Plugin', false ),
			'array policy text' => array( 'My Plugin', array() ),
		);
	}

	/**
	 * Tests that identical content is added once, while other content is kept in order.
	 *
	 * @ticket 65819
	 */
	public function test_should_add_identical_content_once() {
		$GLOBALS['wp_actions']['admin_init'] = 1;

		wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );
		wp_add_privacy_policy_content( 'Other Plugin', '<p>Other policy.</p>' );
		wp_add_privacy_policy_content( 'My Plugin', '<p>Policy.</p>' );
		wp_add_privacy_policy_content( 'My Plugin', '<p>Changed policy.</p>' );

		$this->assertSame(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>Policy.</p>',
				),
				array(
					'plugin_name' => 'Other Plugin',
					'policy_text' => '<p>Other policy.</p>',
				),
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>Changed policy.</p>',
				),
			),
			$this->get_policy_content()
		);
	}

	/**
	 * Gets the suggested policy content.
	 *
	 * @return array The suggested policy content.
	 */
	private function get_policy_content() {
		$property = new ReflectionProperty( 'WP_Privacy_Policy_Content', 'policy_content' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue();
	}

	/**
	 * Sets the suggested policy content.
	 *
	 * @param array $value The suggested policy content.
	 */
	private function set_policy_content( $value ) {
		$property = new ReflectionProperty( 'WP_Privacy_Policy_Content', 'policy_content' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, $value );
	}
}
