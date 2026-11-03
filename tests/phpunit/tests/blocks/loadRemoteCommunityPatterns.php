<?php
/**
 * Unit tests for _load_remote_community_patterns().
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.2.0
 *
 * @ticket 65897
 *
 * @group blocks
 * @group patterns
 *
 * @covers ::_load_remote_community_patterns
 */
class Tests_Blocks_LoadRemoteCommunityPatterns extends WP_UnitTestCase {

	const PATTERN_NAME = 'community/community-hero';

	/**
	 * Admin user ID. The Pattern Directory endpoint requires `edit_posts`.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Original WP_Block_Patterns_Registry instance, restored after the class.
	 *
	 * @var WP_Block_Patterns_Registry
	 */
	protected static $orig_registry;

	/**
	 * Reflected `instance` property of WP_Block_Patterns_Registry.
	 *
	 * @var ReflectionProperty
	 */
	private static $registry_instance_property;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$orig_registry              = WP_Block_Patterns_Registry::get_instance();
		self::$registry_instance_property = new ReflectionProperty( 'WP_Block_Patterns_Registry', 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$registry_instance_property->setAccessible( true );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );

		self::$registry_instance_property->setValue( null, self::$orig_registry );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$registry_instance_property->setAccessible( false );
		}
		self::$registry_instance_property = null;
		self::$orig_registry              = null;
	}

	public function set_up() {
		parent::set_up();

		// Isolate registrations in a fresh registry per test.
		self::$registry_instance_property->setValue( null, new WP_Block_Patterns_Registry() );

		// The loader is gated on this theme support.
		add_theme_support( 'core-block-patterns' );

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		// Restore the shared registry between tests.
		self::$registry_instance_property->setValue( null, self::$orig_registry );

		parent::tear_down();
	}

	/**
	 * Intercepts requests to the Pattern Directory API and returns one
	 * community pattern, so tests never hit the network.
	 */
	private function mock_directory_response() {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( 'api.wordpress.org' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
					return $preempt;
				}

				$pattern = array(
					'id'              => 5001,
					'title'           => array( 'rendered' => 'Community Hero' ),
					'pattern_content' => '<!-- wp:paragraph --><p>Community</p><!-- /wp:paragraph -->',
					'category_slugs'  => array( 'header' ),
					'meta'            => array(
						'wpop_keywords'       => 'hero',
						'wpop_description'    => 'A community hero.',
						'wpop_viewport_width' => 1200,
						'wpop_block_types'    => array( 'core/paragraph' ),
					),
				);

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode( array( $pattern ) ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * By default (no opt-in filter) community patterns are not loaded, and no
	 * request to the directory is made.
	 *
	 * @ticket 65897
	 */
	public function test_not_loaded_by_default() {
		$http_requested = false;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$http_requested ) {
				if ( 'api.wordpress.org' === wp_parse_url( $url, PHP_URL_HOST ) ) {
					$http_requested = true;
				}
				return $preempt;
			},
			10,
			3
		);

		_load_remote_community_patterns();

		$this->assertFalse(
			WP_Block_Patterns_Registry::get_instance()->is_registered( self::PATTERN_NAME ),
			'Community patterns should not be registered unless explicitly enabled.'
		);
		$this->assertFalse(
			$http_requested,
			'No request to the Pattern Directory should be made while the feature is disabled.'
		);
	}

	/**
	 * With the opt-in filter, community patterns are registered under the
	 * `community` category and tagged with the community source.
	 *
	 * @ticket 65897
	 */
	public function test_loaded_when_opted_in() {
		$this->mock_directory_response();
		add_filter( 'load_remote_community_block_patterns', '__return_true' );

		_load_remote_community_patterns();

		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->assertTrue(
			$registry->is_registered( self::PATTERN_NAME ),
			'Community patterns should be registered when the opt-in filter returns true.'
		);

		$pattern = $registry->get_registered( self::PATTERN_NAME );
		$this->assertSame(
			'pattern-directory/community',
			$pattern['source'],
			'The pattern should be tagged with the community source.'
		);
		$this->assertSame(
			array( 'community' ),
			$pattern['categories'],
			'The pattern should be grouped under the community category.'
		);
	}

	/**
	 * The master `should_load_remote_block_patterns` gate still wins even when
	 * community loading is opted in.
	 *
	 * @ticket 65897
	 */
	public function test_respects_should_load_remote_gate() {
		$this->mock_directory_response();
		add_filter( 'load_remote_community_block_patterns', '__return_true' );
		add_filter( 'should_load_remote_block_patterns', '__return_false' );

		_load_remote_community_patterns();

		$this->assertFalse(
			WP_Block_Patterns_Registry::get_instance()->is_registered( self::PATTERN_NAME ),
			'The should_load_remote_block_patterns filter should prevent loading.'
		);
	}
}
