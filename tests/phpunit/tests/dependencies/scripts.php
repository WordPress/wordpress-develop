<?php
/**
 * @group dependencies
 * @group scripts
 * @covers ::wp_enqueue_script
 * @covers ::wp_register_script
 * @covers ::wp_print_scripts
 * @covers ::wp_script_add_data
 * @covers ::wp_add_inline_script
 * @covers ::wp_set_script_translations
 */
class Tests_Dependencies_Scripts extends WP_UnitTestCase {

	/**
	 * @var WP_Scripts
	 */
	protected $old_wp_scripts;

	/**
	 * @var WP_Styles
	 */
	protected $old_wp_styles;

	protected $wp_scripts_print_translations_output;

	/**
	 * Stores a string reference to a default scripts directory name, utilised by certain tests.
	 *
	 * @var string
	 */
	protected $default_scripts_dir = '/directory/';

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		$this->old_wp_styles  = isset( $GLOBALS['wp_styles'] ) ? $GLOBALS['wp_styles'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );
		$GLOBALS['wp_styles']                   = new WP_Styles();

		$this->wp_scripts_print_translations_output  = <<<JS
<script type='text/javascript' id='__HANDLE__-js-translations'>
/* <![CDATA[ */
( function( domain, translations ) {
	var localeData = translations.locale_data[ domain ] || translations.locale_data.messages;
	localeData[""].domain = domain;
	wp.i18n.setLocaleData( localeData, domain );
} )( "__DOMAIN__", __JSON_TRANSLATIONS__ );
/* ]]> */
</script>
JS;
		$this->wp_scripts_print_translations_output .= "\n";
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		parent::tear_down();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 */
	public function test_wp_enqueue_script() {
		global $wp_version;

		wp_enqueue_script( 'no-deps-no-version', 'example.com', array() );
		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );
		wp_enqueue_script( 'empty-deps-version', 'example.com', array(), 1.2 );
		wp_enqueue_script( 'empty-deps-null-version', 'example.com', array(), null );

		$expected  = "<script type='text/javascript' src='http://example.com?ver={$wp_version}' id='no-deps-no-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver={$wp_version}' id='empty-deps-no-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=1.2' id='empty-deps-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='empty-deps-null-version-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Gets delayed strategies as a data provider.
	 *
	 * @return array[] Delayed strategies.
	 */
	public function data_provider_delayed_strategies() {
		return array(
			'defer' => array( 'defer' ),
			'async' => array( 'async' ),
		);
	}

	/**
	 * Tests that inline scripts in the `after` position, attached to delayed main scripts, remain unaffected.
	 *
	 * If the main script with delayed loading strategy has an `after` inline script,
	 * the inline script should not be affected.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy Strategy.
	 */
	public function test_after_inline_script_with_delayed_main_script( $strategy ) {
		wp_enqueue_script( 'ms-isa-1', 'http://example.org/ms-isa-1.js', array(), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ms-isa-1', 'console.log("after one");', 'after' );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='http://example.org/ms-isa-1.js' id='ms-isa-1-js' data-wp-strategy='{$strategy}'></script>\n";
		$expected .= wp_get_inline_script_tag(
			'console.log("after one");',
			array(
				'id' => 'ms-isa-1-js-after',
			)
		);
		$this->assertEqualMarkup( $expected, $output, 'Inline scripts in the "after" position, that are attached to a deferred main script, are failing to print/execute.' );
	}

	/**
	 * Tests that inline scripts in the `after` position, attached to a blocking main script, are rendered as javascript.
	 *
	 * If a main script with a `blocking` strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/javascript'.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_after_inline_script_with_blocking_main_script() {
		wp_enqueue_script( 'ms-insa-3', 'http://example.org/ms-insa-3.js', array(), null );
		wp_add_inline_script( 'ms-insa-3', 'console.log("after one");', 'after' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ms-insa-3.js' id='ms-insa-3-js'></script>\n";
		$expected .= wp_get_inline_script_tag(
			'console.log("after one");',
			array(
				'id' => 'ms-insa-3-js-after',
			)
		);

		$this->assertEqualMarkup( $expected, $output, 'Inline scripts in the "after" position, that are attached to a blocking main script, are failing to print/execute.' );
	}

	/**
	 * Tests that inline scripts in the `before` position, attached to a delayed inline main script, results in all
	 * dependents being delayed.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy
	 */
	public function test_before_inline_scripts_with_delayed_main_script( $strategy ) {
		wp_enqueue_script( 'ds-i1-1', 'http://example.org/ds-i1-1.js', array(), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ds-i1-1', 'console.log("before first");', 'before' );
		wp_enqueue_script( 'ds-i1-2', 'http://example.org/ds-i1-2.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'ds-i1-3', 'http://example.org/ds-i1-3.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'ms-i1-1', 'http://example.org/ms-i1-1.js', array( 'ds-i1-1', 'ds-i1-2', 'ds-i1-3' ), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ms-i1-1', 'console.log("before last");', 'before' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = wp_get_inline_script_tag(
			'console.log("before first");',
			array(
				'id' => 'ds-i1-1-js-before',
			)
		);
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-1.js' id='ds-i1-1-js' $strategy data-wp-strategy='{$strategy}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-2.js' id='ds-i1-2-js' $strategy data-wp-strategy='{$strategy}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-3.js' id='ds-i1-3-js' $strategy data-wp-strategy='{$strategy}'></script>\n";
		$expected .= wp_get_inline_script_tag(
			'console.log("before last");',
			array(
				'id'   => 'ms-i1-1-js-before',
				'type' => 'text/javascript',
			)
		);
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i1-1.js' id='ms-i1-1-js' {$strategy} data-wp-strategy='{$strategy}'></script>\n";

		$this->assertEqualMarkup( $expected, $output, 'Inline scripts in the "before" position, that are attached to a deferred main script, are failing to print/execute.' );
	}

	/**
	 * Tests that scripts registered with an async strategy print with the async attribute.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_valid_async_registration() {
		// No dependents, No dependencies then async.
		wp_enqueue_script( 'main-script-a1', '/main-script-a1.js', array(), null, array( 'strategy' => 'async' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-a1.js' id='main-script-a1-js' async data-wp-strategy='async'></script>\n";
		$this->assertEqualMarkup( $expected, $output, 'Scripts enqueued with an async loading strategy are failing to have the async attribute applied to the script handle when being printed.' );
	}

	/**
	 * Tests that dependents of a blocking dependency script are free to contain any strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy Strategy.
	 */
	public function test_delayed_dependent_with_blocking_dependency( $strategy ) {
		wp_enqueue_script( 'dependency-script-a2', '/dependency-script-a2.js', array(), null );
		wp_enqueue_script( 'main-script-a2', '/main-script-a2.js', array( 'dependency-script-a2' ), null, compact( 'strategy' ) );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script id='dependency-script-a2-js' src='/dependency-script-a2.js'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script-a2.js' id='main-script-a2-js' {$strategy} data-wp-strategy='{$strategy}'></script>";
		$this->assertEqualMarkup( $expected, $output, 'Dependents of a blocking dependency are free to have any strategy.' );
	}

	/**
	 * Tests that blocking dependents force delayed dependencies to become blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 * @param string $strategy Strategy.
	 */
	public function test_blocking_dependent_with_delayed_dependency( $strategy ) {
		wp_enqueue_script( 'main-script-a3', '/main-script-a3.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'dependent-script-a3', '/dependent-script-a3.js', array( 'main-script-a3' ), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = <<<JS
			<script type='text/javascript' src='/main-script-a3.js' id='main-script-a3-js' data-wp-strategy='{$strategy}'></script>
			<script id="dependent-script-a3-js" src="/dependent-script-a3.js" type="text/javascript"></script>
JS;
		$this->assertEqualMarkup( $expected, $output, 'Blocking dependents must force delayed dependencies to become blocking.' );
	}

	/**
	 * Tests that only enqueued dependents effect the eligible loading strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 * @param string $strategy Strategy.
	 */
	public function test_delayed_dependent_with_blocking_dependency_not_enqueued( $strategy ) {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-a4', '/main-script-a4.js', array(), null, compact( 'strategy' ) );
		// This dependent is registered but not enqueued, so it should not factor into the eligible loading strategy.
		wp_register_script( 'dependent-script-a4', '/dependent-script-a4.js', array( 'main-script-a4' ), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script src='/main-script-a4.js' id='main-script-a4-js' {$strategy} data-wp-strategy='{$strategy}'></script>" );
		$this->assertStringContainsString( $expected, $output, 'Only enqueued dependents should affect the eligible strategy.' );
	}

	/**
	 * Data provider for test_filter_eligible_strategies.
	 *
	 * @return array
	 */
	public function get_data_to_filter_eligible_strategies() {
		return array(
			'no_dependents'                       => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'one_delayed_dependent'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'one_blocking_dependent'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'one_blocking_dependent_not_enqueued' => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_register_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array( 'defer' ), // Because bar was not enqueued, only foo was.
			),
			'two_delayed_dependents'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'baz', 'https://example.com/baz.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'recursion_not_delayed'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'recursion_yes_delayed'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'recursion_triple_level'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'baz' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'baz', 'https://example.com/bar.js', array( 'bar' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_only_with_async_dependency'    => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'async' ) );
					return 'foo';
				},
				'expected' => array( 'defer', 'async' ),
			),
			'async_only_with_defer_dependency'    => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_only_with_blocking_dependency' => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'defer_with_inline_after_script'      => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'after' );
					return 'foo';
				},
				'expected' => array(),
			),
			'defer_with_inline_before_script'     => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'before' );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_with_inline_after_script'      => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'after' );
					return 'foo';
				},
				'expected' => array(),
			),
			'async_with_inline_before_script'     => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'before' );
					return 'foo';
				},
				'expected' => array( 'defer', 'async' ),
			),
		);
	}

	/**
	 * Tests that the filter_eligible_strategies method works as expected and returns the correct value.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::filter_eligible_strategies
	 *
	 * @dataProvider get_data_to_filter_eligible_strategies
	 *
	 * @param callable $set_up     Set up.
	 * @param bool     $async_only Async only.
	 * @param bool     $expected   Expected return value.
	 */
	public function test_filter_eligible_strategies( $set_up, $expected ) {
		$handle = $set_up();

		$wp_scripts_reflection      = new ReflectionClass( WP_Scripts::class );
		$filter_eligible_strategies = $wp_scripts_reflection->getMethod( 'filter_eligible_strategies' );
		$filter_eligible_strategies->setAccessible( true );
		$this->assertSame( $expected, $filter_eligible_strategies->invokeArgs( wp_scripts(), array( $handle ) ), 'Expected return value of WP_Scripts::filter_eligible_strategies to match.' );
	}

	/**
	 * Register test script.
	 *
	 * @param string   $handle    Dependency handle to enqueue.
	 * @param string   $strategy  Strategy to use for dependency.
	 * @param string[] $deps      Dependencies for the script.
	 * @param bool     $in_footer Whether to print the script in the footer.
	 */
	protected function register_test_script( $handle, $strategy, $deps = array(), $in_footer = false ) {
		wp_register_script(
			$handle,
			add_query_arg(
				array(
					'script_event_log' => "$handle: script",
				),
				'https://example.com/external.js'
			),
			$deps,
			null
		);
		if ( 'blocking' !== $strategy ) {
			wp_script_add_data( $handle, 'strategy', $strategy );
		}
	}

	/**
	 * Enqueue test script.
	 *
	 * @param string   $handle    Dependency handle to enqueue.
	 * @param string   $strategy  Strategy to use for dependency.
	 * @param string[] $deps      Dependencies for the script.
	 * @param bool     $in_footer Whether to print the script in the footer.
	 */
	protected function enqueue_test_script( $handle, $strategy, $deps = array(), $in_footer = false ) {
		$this->register_test_script( $handle, $strategy, $deps, $in_footer );
		wp_enqueue_script( $handle );
	}

	/**
	 * Adds test inline script.
	 *
	 * @param string $handle   Dependency handle to enqueue.
	 * @param string $position Position.
	 */
	protected function add_test_inline_script( $handle, $position ) {
		wp_add_inline_script( $handle, sprintf( 'scriptEventLog.push( %s )', wp_json_encode( "{$handle}: {$position} inline" ) ), $position );
	}

	/**
	 * Data provider to test various strategy dependency chains.
	 *
	 * @return array[]
	 */
	public function data_provider_to_test_various_strategy_dependency_chains() {
		$wp_tests_domain = WP_TESTS_DOMAIN;

		return array(
			'async-dependent-with-one-blocking-dependency' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-not-async-without-dependency';
					$handle2 = 'async-with-blocking-dependency';
					$this->enqueue_test_script( $handle1, 'blocking', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="blocking-not-async-without-dependency-js-before" type="text/javascript">
scriptEventLog.push( "blocking-not-async-without-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-not-async-without-dependency:%20script' id='blocking-not-async-without-dependency-js'></script>
<script id="blocking-not-async-without-dependency-js-after" type="text/javascript">
scriptEventLog.push( "blocking-not-async-without-dependency: after inline" )
</script>
<script id="async-with-blocking-dependency-js-before" type="text/javascript">
scriptEventLog.push( "async-with-blocking-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-blocking-dependency:%20script' id='async-with-blocking-dependency-js' data-wp-strategy='async'></script>
<script id="async-with-blocking-dependency-js-after" type="text/javascript">
scriptEventLog.push( "async-with-blocking-dependency: after inline" )
</script>
HTML
				,
				/*
				 * Note: The above comma must be on its own line in PHP<7.3 and not after the `HTML` identifier
				 * terminating the heredoc. Otherwise, a syntax error is raised with the line number being wildly wrong:
				 *
				 * PHP Parse error:  syntax error, unexpected '' (T_ENCAPSED_AND_WHITESPACE), expecting '-' or identifier (T_STRING) or variable (T_VARIABLE) or number (T_NUM_STRING)
				 */
			),
			'async-with-async-dependencies'                => array(
				'set_up'          => function () {
					$handle1 = 'async-no-dependency';
					$handle2 = 'async-one-async-dependency';
					$handle3 = 'async-two-async-dependencies';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					$this->enqueue_test_script( $handle3, 'async', array( $handle1, $handle2 ) );
					foreach ( array( $handle1, $handle2, $handle3 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="async-no-dependency-js-before" type="text/javascript">
scriptEventLog.push( "async-no-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-no-dependency:%20script' id='async-no-dependency-js' data-wp-strategy='async'></script>
<script id="async-no-dependency-js-after" type="text/javascript">
scriptEventLog.push( "async-no-dependency: after inline" )
</script>
<script id="async-one-async-dependency-js-before" type="text/javascript">
scriptEventLog.push( "async-one-async-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-one-async-dependency:%20script' id='async-one-async-dependency-js' data-wp-strategy='async'></script>
<script id="async-one-async-dependency-js-after" type="text/javascript">
scriptEventLog.push( "async-one-async-dependency: after inline" )
</script>
<script id="async-two-async-dependencies-js-before" type="text/javascript">
scriptEventLog.push( "async-two-async-dependencies: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-two-async-dependencies:%20script' id='async-two-async-dependencies-js' data-wp-strategy='async'></script>
<script id="async-two-async-dependencies-js-after" type="text/javascript">
scriptEventLog.push( "async-two-async-dependencies: after inline" )
</script>
HTML
				,
			),
			'async-with-blocking-dependency'               => array(
				'set_up'          => function () {
					$handle1 = 'async-with-blocking-dependent';
					$handle2 = 'blocking-dependent-of-async';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'blocking', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="async-with-blocking-dependent-js-before" type="text/javascript">
scriptEventLog.push( "async-with-blocking-dependent: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-blocking-dependent:%20script' id='async-with-blocking-dependent-js' data-wp-strategy='async'></script>
<script id="async-with-blocking-dependent-js-after" type="text/javascript">
scriptEventLog.push( "async-with-blocking-dependent: after inline" )
</script>
<script id="blocking-dependent-of-async-js-before" type="text/javascript">
scriptEventLog.push( "blocking-dependent-of-async: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependent-of-async:%20script' id='blocking-dependent-of-async-js'></script>
<script id="blocking-dependent-of-async-js-after" type="text/javascript">
scriptEventLog.push( "blocking-dependent-of-async: after inline" )
</script>
HTML
				,
			),
			'defer-with-async-dependency'                  => array(
				'set_up'          => function () {
					$handle1 = 'async-with-defer-dependent';
					$handle2 = 'defer-dependent-of-async';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="async-with-defer-dependent-js-before" type="text/javascript">
scriptEventLog.push( "async-with-defer-dependent: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-defer-dependent:%20script' id='async-with-defer-dependent-js' data-wp-strategy='async'></script>
<script id="async-with-defer-dependent-js-after" type="text/javascript">
scriptEventLog.push( "async-with-defer-dependent: after inline" )
</script>
<script id="defer-dependent-of-async-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-async: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-async:%20script' id='defer-dependent-of-async-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-async-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-async: after inline" )
</script>
HTML
				,
			),
			'blocking-bundle-of-none-with-inline-scripts-and-defer-dependent' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-bundle-of-none';
					$handle2 = 'defer-dependent-of-blocking-bundle-of-none';

					wp_register_script( $handle1, false, array(), null );
					$this->add_test_inline_script( $handle1, 'before' );
					$this->add_test_inline_script( $handle1, 'after' );

					// Note: the before script for this will be blocking because the dependency is blocking.
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					$this->add_test_inline_script( $handle2, 'before' );
					$this->add_test_inline_script( $handle2, 'after' );
				},
				'expected_markup' => <<<HTML
<script id="blocking-bundle-of-none-js-before" type="text/javascript">
scriptEventLog.push( "blocking-bundle-of-none: before inline" )
</script>
<script id="blocking-bundle-of-none-js-after" type="text/javascript">
scriptEventLog.push( "blocking-bundle-of-none: after inline" )
</script>
<script id="defer-dependent-of-blocking-bundle-of-none-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-none: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-bundle-of-none:%20script' id='defer-dependent-of-blocking-bundle-of-none-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-blocking-bundle-of-none-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-none: after inline" )
</script>
HTML
				,
			),
			'blocking-bundle-of-two-with-defer-dependent'  => array(
				'set_up'          => function () {
					$handle1 = 'blocking-bundle-of-two';
					$handle2 = 'blocking-bundle-member-one';
					$handle3 = 'blocking-bundle-member-two';
					$handle4 = 'defer-dependent-of-blocking-bundle-of-two';

					wp_register_script( $handle1, false, array( $handle2, $handle3 ), null );
					$this->enqueue_test_script( $handle2, 'blocking' );
					$this->enqueue_test_script( $handle3, 'blocking' );
					$this->enqueue_test_script( $handle4, 'defer', array( $handle1 ) );

					foreach ( array( $handle2, $handle3, $handle4 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="blocking-bundle-member-one-js-before" type="text/javascript">
scriptEventLog.push( "blocking-bundle-member-one: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-bundle-member-one:%20script' id='blocking-bundle-member-one-js'></script>
<script id="blocking-bundle-member-one-js-after" type="text/javascript">
scriptEventLog.push( "blocking-bundle-member-one: after inline" )
</script>
<script id="blocking-bundle-member-two-js-before" type="text/javascript">
scriptEventLog.push( "blocking-bundle-member-two: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-bundle-member-two:%20script' id='blocking-bundle-member-two-js'></script>
<script id="blocking-bundle-member-two-js-after" type="text/javascript">
scriptEventLog.push( "blocking-bundle-member-two: after inline" )
</script>
<script id="defer-dependent-of-blocking-bundle-of-two-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-two: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-bundle-of-two:%20script' id='defer-dependent-of-blocking-bundle-of-two-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-blocking-bundle-of-two-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-two: after inline" )
</script>
HTML
				,
			),
			'defer-bundle-of-none-with-inline-scripts-and-defer-dependents' => array(
				'set_up'          => function () {
					$handle1 = 'defer-bundle-of-none';
					$handle2 = 'defer-dependent-of-defer-bundle-of-none';

					// The eligible loading strategy for this will be forced to be blocking when rendered since $src = false.
					wp_register_script( $handle1, false, array(), null );
					wp_scripts()->registered[ $handle1 ]->extra['strategy'] = 'defer'; // Bypass wp_script_add_data() which should no-op with _doing_it_wrong() because of $src=false.
					$this->add_test_inline_script( $handle1, 'before' );
					$this->add_test_inline_script( $handle1, 'after' );

					// Note: the before script for this will be blocking because the dependency is blocking.
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					$this->add_test_inline_script( $handle2, 'before' );
					$this->add_test_inline_script( $handle2, 'after' );
				},
				'expected_markup' => <<<HTML
<script id="defer-bundle-of-none-js-before" type="text/javascript">
scriptEventLog.push( "defer-bundle-of-none: before inline" )
</script>
<script id="defer-bundle-of-none-js-after" type="text/javascript">
scriptEventLog.push( "defer-bundle-of-none: after inline" )
</script>
<script id="defer-dependent-of-defer-bundle-of-none-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-defer-bundle-of-none: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-defer-bundle-of-none:%20script' id='defer-dependent-of-defer-bundle-of-none-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-defer-bundle-of-none-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-defer-bundle-of-none: after inline" )
</script>
HTML
				,
			),
			'defer-dependent-with-blocking-and-defer-dependencies' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-dependency-with-defer-following-dependency';
					$handle2 = 'defer-dependency-with-blocking-preceding-dependency';
					$handle3 = 'defer-dependent-of-blocking-and-defer-dependencies';
					$this->enqueue_test_script( $handle1, 'blocking', array() );
					$this->enqueue_test_script( $handle2, 'defer', array() );
					$this->enqueue_test_script( $handle3, 'defer', array( $handle1, $handle2 ) );

					foreach ( array( $handle1, $handle2, $handle3 ) as $dep ) {
						$this->add_test_inline_script( $dep, 'before' );
						$this->add_test_inline_script( $dep, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="blocking-dependency-with-defer-following-dependency-js-before" type="text/javascript">
scriptEventLog.push( "blocking-dependency-with-defer-following-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependency-with-defer-following-dependency:%20script' id='blocking-dependency-with-defer-following-dependency-js'></script>
<script id="blocking-dependency-with-defer-following-dependency-js-after" type="text/javascript">
scriptEventLog.push( "blocking-dependency-with-defer-following-dependency: after inline" )
</script>
<script id="defer-dependency-with-blocking-preceding-dependency-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependency-with-blocking-preceding-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependency-with-blocking-preceding-dependency:%20script' id='defer-dependency-with-blocking-preceding-dependency-js' data-wp-strategy='defer'></script>
<script id="defer-dependency-with-blocking-preceding-dependency-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependency-with-blocking-preceding-dependency: after inline" )
</script>
<script id="defer-dependent-of-blocking-and-defer-dependencies-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-and-defer-dependencies: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-and-defer-dependencies:%20script' id='defer-dependent-of-blocking-and-defer-dependencies-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-blocking-and-defer-dependencies-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-blocking-and-defer-dependencies: after inline" )
</script>
HTML
				,
			),
			'defer-dependent-with-defer-and-blocking-dependencies' => array(
				'set_up'          => function () {
					$handle1 = 'defer-dependency-with-blocking-following-dependency';
					$handle2 = 'blocking-dependency-with-defer-preceding-dependency';
					$handle3 = 'defer-dependent-of-defer-and-blocking-dependencies';
					$this->enqueue_test_script( $handle1, 'defer', array() );
					$this->enqueue_test_script( $handle2, 'blocking', array() );
					$this->enqueue_test_script( $handle3, 'defer', array( $handle1, $handle2 ) );

					foreach ( array( $handle1, $handle2, $handle3 ) as $dep ) {
						$this->add_test_inline_script( $dep, 'before' );
						$this->add_test_inline_script( $dep, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="defer-dependency-with-blocking-following-dependency-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependency-with-blocking-following-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependency-with-blocking-following-dependency:%20script' id='defer-dependency-with-blocking-following-dependency-js' data-wp-strategy='defer'></script>
<script id="defer-dependency-with-blocking-following-dependency-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependency-with-blocking-following-dependency: after inline" )
</script>
<script id="blocking-dependency-with-defer-preceding-dependency-js-before" type="text/javascript">
scriptEventLog.push( "blocking-dependency-with-defer-preceding-dependency: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependency-with-defer-preceding-dependency:%20script' id='blocking-dependency-with-defer-preceding-dependency-js'></script>
<script id="blocking-dependency-with-defer-preceding-dependency-js-after" type="text/javascript">
scriptEventLog.push( "blocking-dependency-with-defer-preceding-dependency: after inline" )
</script>
<script id="defer-dependent-of-defer-and-blocking-dependencies-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-defer-and-blocking-dependencies: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-defer-and-blocking-dependencies:%20script' id='defer-dependent-of-defer-and-blocking-dependencies-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-defer-and-blocking-dependencies-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-defer-and-blocking-dependencies: after inline" )
</script>
HTML
				,
			),
			'async-with-defer-dependency'                  => array(
				'set_up'          => function () {
					$handle1 = 'defer-with-async-dependent';
					$handle2 = 'async-dependent-of-defer';
					$this->enqueue_test_script( $handle1, 'defer', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script id="defer-with-async-dependent-js-before" type="text/javascript">
scriptEventLog.push( "defer-with-async-dependent: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-async-dependent:%20script' id='defer-with-async-dependent-js' data-wp-strategy='defer'></script>
<script id="defer-with-async-dependent-js-after" type="text/javascript">
scriptEventLog.push( "defer-with-async-dependent: after inline" )
</script>
<script id="async-dependent-of-defer-js-before" type="text/javascript">
scriptEventLog.push( "async-dependent-of-defer: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-dependent-of-defer:%20script' id='async-dependent-of-defer-js' data-wp-strategy='async'></script>
<script id="async-dependent-of-defer-js-after" type="text/javascript">
scriptEventLog.push( "async-dependent-of-defer: after inline" )
</script>
HTML
				,
			),
			'defer-with-before-inline-script'              => array(
				'set_up'          => function () {
					// Note this should NOT result in no delayed-inline-script-loader script being added.
					$handle = 'defer-with-before-inline';
					$this->enqueue_test_script( $handle, 'defer', array() );
					$this->add_test_inline_script( $handle, 'before' );
				},
				'expected_markup' => <<<HTML
<script id="defer-with-before-inline-js-before" type="text/javascript">
scriptEventLog.push( "defer-with-before-inline: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-before-inline:%20script' id='defer-with-before-inline-js' defer data-wp-strategy='defer'></script>
HTML
				,
			),
			'defer-with-after-inline-script'               => array(
				'set_up'          => function () {
					// Note this SHOULD result in delayed-inline-script-loader script being added.
					$handle = 'defer-with-after-inline';
					$this->enqueue_test_script( $handle, 'defer', array() );
					$this->add_test_inline_script( $handle, 'after' );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-after-inline:%20script' id='defer-with-after-inline-js' data-wp-strategy='defer'></script>
<script id="defer-with-after-inline-js-after" type="text/javascript">
scriptEventLog.push( "defer-with-after-inline: after inline" )
</script>
HTML
				,
			),
			'jquery-deferred'                              => array(
				'set_up'          => function () {
					$wp_scripts = wp_scripts();
					wp_default_scripts( $wp_scripts );
					foreach ( $wp_scripts->registered['jquery']->deps as $jquery_dep ) {
						$wp_scripts->registered[ $jquery_dep ]->add_data( 'strategy', 'defer' );
						$wp_scripts->registered[ $jquery_dep ]->ver = null; // Just to avoid markup changes in the test when jQuery is upgraded.
					}
					wp_enqueue_script( 'theme-functions', 'https://example.com/theme-functions.js', array( 'jquery' ), null, array( 'strategy' => 'defer' ) );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='http://$wp_tests_domain/wp-includes/js/jquery/jquery.js' id='jquery-core-js' defer data-wp-strategy='defer'></script>
<script type='text/javascript' src='http://$wp_tests_domain/wp-includes/js/jquery/jquery-migrate.js' id='jquery-migrate-js' defer data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/theme-functions.js' id='theme-functions-js' defer data-wp-strategy='defer'></script>
HTML
				,
			),
			'nested-aliases'                               => array(
				'set_up'          => function () {
					$outer_alias_handle = 'outer-bundle-of-two';
					$inner_alias_handle = 'inner-bundle-of-two';

					// The outer alias contains a blocking member, as well as a nested alias that contains defer scripts.
					wp_register_script( $outer_alias_handle, false, array( $inner_alias_handle, 'outer-bundle-leaf-member' ), null );
					$this->register_test_script( 'outer-bundle-leaf-member', 'blocking', array() );

					// Inner alias only contains delay scripts.
					wp_register_script( $inner_alias_handle, false, array( 'inner-bundle-member-one', 'inner-bundle-member-two' ), null );
					$this->register_test_script( 'inner-bundle-member-one', 'defer', array() );
					$this->register_test_script( 'inner-bundle-member-two', 'defer', array() );

					$this->enqueue_test_script( 'defer-dependent-of-nested-aliases', 'defer', array( $outer_alias_handle ) );
					$this->add_test_inline_script( 'defer-dependent-of-nested-aliases', 'before' );
					$this->add_test_inline_script( 'defer-dependent-of-nested-aliases', 'after' );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=inner-bundle-member-one:%20script' id='inner-bundle-member-one-js' data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=inner-bundle-member-two:%20script' id='inner-bundle-member-two-js' data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=outer-bundle-leaf-member:%20script' id='outer-bundle-leaf-member-js'></script>
<script id="defer-dependent-of-nested-aliases-js-before" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-nested-aliases: before inline" )
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-nested-aliases:%20script' id='defer-dependent-of-nested-aliases-js' data-wp-strategy='defer'></script>
<script id="defer-dependent-of-nested-aliases-js-after" type="text/javascript">
scriptEventLog.push( "defer-dependent-of-nested-aliases: after inline" )
</script>
HTML
				,
			),

			'async-alias-members-with-defer-dependency'    => array(
				'set_up'          => function () {
					$alias_handle = 'async-alias';
					$async_handle1 = 'async1';
					$async_handle2 = 'async2';

					wp_register_script( $alias_handle, false, array( $async_handle1, $async_handle2 ), null );
					$this->register_test_script( $async_handle1, 'async', array() );
					$this->register_test_script( $async_handle2, 'async', array() );

					$this->enqueue_test_script( 'defer-dependent-of-async-aliases', 'defer', array( $alias_handle ) );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async1:%20script' id='async1-js' defer data-wp-strategy='async'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async2:%20script' id='async2-js' defer data-wp-strategy='async'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-async-aliases:%20script' id='defer-dependent-of-async-aliases-js' defer data-wp-strategy='defer'></script>
HTML
				,
			),
		);
	}

	/**
	 * Tests that various loading strategy dependency chains function as expected.
	 *
	 * @covers ::wp_enqueue_script()
	 * @covers ::wp_add_inline_script()
	 * @covers ::wp_print_scripts()
	 * @covers WP_Scripts::get_inline_script_tag
	 *
	 * @dataProvider data_provider_to_test_various_strategy_dependency_chains
	 *
	 * @param callable $set_up          Set up.
	 * @param string   $expected_markup Expected markup.
	 */
	public function test_various_strategy_dependency_chains( $set_up, $expected_markup ) {
		$set_up();
		$actual_markup = get_echo( 'wp_print_scripts' );
		$this->assertEqualMarkup( trim( $expected_markup ), trim( $actual_markup ), "Actual markup:\n{$actual_markup}" );
	}

	/**
	 * Tests that defer is the final strategy when registering a script using defer, that has no dependents/dependencies.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_defer_having_no_dependents_nor_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-d1', 'http://example.com/main-script-d1.js', array(), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script src='http://example.com/main-script-d1.js' id='main-script-d1-js' defer data-wp-strategy='defer'></script>\n" );
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as there is no dependent or dependency' );
	}

	/**
	 * Tests that a script registered with defer remains deferred when all dependencies are either deferred or blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_defer_dependent_and_varied_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'dependency-script-d2-1', 'http://example.com/dependency-script-d2-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependency-script-d2-2', 'http://example.com/dependency-script-d2-2.js', array(), null );
		wp_enqueue_script( 'dependency-script-d2-3', 'http://example.com/dependency-script-d2-3.js', array( 'dependency-script-d2-2' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'main-script-d2', 'http://example.com/main-script-d2.js', array( 'dependency-script-d2-1', 'dependency-script-d2-3' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = '<script src="http://example.com/main-script-d2.js" id="main-script-d2-js" defer data-wp-strategy="defer"></script>';
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as all dependencies are either deferred or blocking' );
	}

	/**
	 * Tests that scripts registered with defer remain deferred when all dependents are also deferred.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_all_defer_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-d3', 'http://example.com/main-script-d3.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-1', 'http://example.com/dependent-script-d3-1.js', array( 'main-script-d3' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-2', 'http://example.com/dependent-script-d3-2.js', array( 'dependent-script-d3-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-3', 'http://example.com/dependent-script-d3-3.js', array( 'dependent-script-d3-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = '<script src="http://example.com/main-script-d3.js" id="main-script-d3-js" defer data-wp-strategy="defer"></script>';
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as all dependents have defer loading strategy' );
	}

	/**
	 * Tests that dependents that are async but attached to a deferred main script, print with defer as opposed to async.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_defer_with_async_dependent() {
		// case with one async dependent.
		wp_enqueue_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-1', '/dependent-script-d4-1.js', array( 'main-script-d4' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-2', '/dependent-script-d4-2.js', array( 'dependent-script-d4-1' ), null, array( 'strategy' => 'async' ) );
		wp_enqueue_script( 'dependent-script-d4-3', '/dependent-script-d4-3.js', array( 'dependent-script-d4-2' ), null, array( 'strategy' => 'defer' ) );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='/main-script-d4.js' id='main-script-d4-js' defer data-wp-strategy='defer'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-1.js' id='dependent-script-d4-1-js' defer data-wp-strategy='defer'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-2.js' id='dependent-script-d4-2-js' defer data-wp-strategy='async'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-3.js' id='dependent-script-d4-3-js' defer data-wp-strategy='defer'></script>\n";

		$this->assertEqualMarkup( $expected, $output, 'Scripts registered as defer but that have dependents that are async are expected to have said dependents deferred.' );
	}

	/**
	 * Tests that scripts registered as defer become blocking when their dependents chain are all blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_invalid_defer_registration() {
		// Main script is defer and all dependent are not defer. Then main script will have blocking(or no) strategy.
		wp_enqueue_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-1', '/dependent-script-d4-1.js', array( 'main-script-d4' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-2', '/dependent-script-d4-2.js', array( 'dependent-script-d4-1' ), null );
		wp_enqueue_script( 'dependent-script-d4-3', '/dependent-script-d4-3.js', array( 'dependent-script-d4-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script type='text/javascript' src='/main-script-d4.js' id='main-script-d4-js' data-wp-strategy='defer'></script>\n" );
		$this->assertStringContainsString( $expected, $output, 'Scripts registered as defer but that have all dependents with no strategy, should become blocking (no strategy).' );
	}

	/**
	 * Tests that scripts registered as default/blocking remain as such when they have no dependencies.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_valid_blocking_registration() {
		wp_enqueue_script( 'main-script-b1', '/main-script-b1.js', array(), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b1.js' id='main-script-b1-js'></script>\n";
		$expected = str_replace( "'", '"', $expected );
		$this->assertSame( $expected, $output, 'Scripts registered with a "blocking" strategy, and who have no dependencies, should have no loading strategy attributes printed.' );

		// strategy args not set.
		wp_enqueue_script( 'main-script-b2', '/main-script-b2.js', array(), null, array() );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b2.js' id='main-script-b2-js'></script>\n";
		$expected = str_replace( "'", '"', $expected );
		$this->assertSame( $expected, $output, 'Scripts registered with no strategy assigned, and who have no dependencies, should have no loading strategy attributes printed.' );
	}

	/**
	 * Tests that scripts registered for the head do indeed end up there.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_scripts_targeting_head() {
		wp_register_script( 'header-old', '/header-old.js', array(), null, false );
		wp_register_script( 'header-new', '/header-new.js', array( 'header-old' ), null, array( 'in_footer' => false ) );
		wp_enqueue_script( 'enqueue-header-old', '/enqueue-header-old.js', array( 'header-new' ), null, false );
		wp_enqueue_script( 'enqueue-header-new', '/enqueue-header-new.js', array( 'enqueue-header-old' ), null, array( 'in_footer' => false ) );

		$actual_header = get_echo( 'wp_print_head_scripts' );
		$actual_footer = get_echo( 'wp_print_scripts' );

		$expected_header  = "<script type='text/javascript' src='/header-old.js' id='header-old-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/header-new.js' id='header-new-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-old.js' id='enqueue-header-old-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-new.js' id='enqueue-header-new-js'></script>\n";

		$this->assertEqualMarkup( $expected_header, $actual_header, 'Scripts registered/enqueued using the older $in_footer parameter or the newer $args parameter should have the same outcome.' );
		$this->assertEmpty( $actual_footer, 'Expected footer to be empty since all scripts were for head.' );
	}

	/**
	 * Test that scripts registered for the footer do indeed end up there.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_scripts_targeting_footer() {
		wp_register_script( 'footer-old', '/footer-old.js', array(), null, true );
		wp_register_script( 'footer-new', '/footer-new.js', array( 'footer-old' ), null, array( 'in_footer' => true ) );
		wp_enqueue_script( 'enqueue-footer-old', '/enqueue-footer-old.js', array( 'footer-new' ), null, true );
		wp_enqueue_script( 'enqueue-footer-new', '/enqueue-footer-new.js', array( 'enqueue-footer-old' ), null, array( 'in_footer' => true ) );

		$actual_header = get_echo( 'wp_print_head_scripts' );
		$actual_footer = get_echo( 'wp_print_scripts' );

		$expected_footer  = "<script type='text/javascript' src='/footer-old.js' id='footer-old-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/footer-new.js' id='footer-new-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-old.js' id='enqueue-footer-old-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-new.js' id='enqueue-footer-new-js'></script>\n";

		$this->assertEmpty( $actual_header, 'Expected header to be empty since all scripts targeted footer.' );
		$this->assertEqualMarkup( $expected_footer, $actual_footer, 'Scripts registered/enqueued using the older $in_footer parameter or the newer $args parameter should have the same outcome.' );
	}

	/**
	 * Data provider for test_setting_in_footer_and_strategy.
	 *
	 * @return array[]
	 */
	public function get_data_for_test_setting_in_footer_and_strategy() {
		return array(
			// Passing in_footer and strategy via args array.
			'async_footer_in_args_array'    => array(
				'set_up'   => static function ( $handle ) {
					$args = array(
						'in_footer' => true,
						'strategy'  => 'async',
					);
					wp_enqueue_script( $handle, '/footer-async.js', array(), null, $args );
				},
				'group'    => 1,
				'strategy' => 'async',
			),

			// Passing in_footer=true but no strategy.
			'blocking_footer_in_args_array' => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array( 'in_footer' => true ) );
				},
				'group'    => 1,
				'strategy' => false,
			),

			// Passing async strategy in script args array.
			'async_in_args_array'           => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array( 'strategy' => 'async' ) );
				},
				'group'    => false,
				'strategy' => 'async',
			),

			// Passing empty array as 5th arg.
			'empty_args_array'              => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array() );
				},
				'group'    => false,
				'strategy' => false,
			),

			// Passing no value as 5th arg.
			'undefined_args_param'          => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null );
				},
				'group'    => false,
				'strategy' => false,
			),

			// Test backward compatibility, passing $in_footer=true as 5th arg.
			'passing_bool_as_args_param'    => array(
				'set_up'   => static function ( $handle ) {
					wp_enqueue_script( $handle, '/footer-async.js', array(), null, true );
				},
				'group'    => 1,
				'strategy' => false,
			),

			// Test backward compatibility, passing $in_footer=true as 5th arg and setting strategy via wp_script_add_data().
			'bool_as_args_and_add_data'     => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/footer-async.js', array(), null, true );
					wp_script_add_data( $handle, 'strategy', 'defer' );
				},
				'group'    => 1,
				'strategy' => 'defer',
			),
		);
	}

	/**
	 * Tests that scripts print in the correct group (head/footer) when using in_footer and assigning a strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_script_add_data
	 *
	 * @dataProvider get_data_for_test_setting_in_footer_and_strategy
	 *
	 * @param callable     $set_up            Set up.
	 * @param int|false    $expected_group    Expected group.
	 * @param string|false $expected_strategy Expected strategy.
	 */
	public function test_setting_in_footer_and_strategy( $set_up, $expected_group, $expected_strategy ) {
		$handle = 'foo';
		$set_up( $handle );
		$this->assertSame( $expected_group, wp_scripts()->get_data( $handle, 'group' ) );
		$this->assertSame( $expected_strategy, wp_scripts()->get_data( $handle, 'strategy' ) );
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed during wp_register_script.
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_register() {
		wp_register_script( 'invalid-strategy', '/defaults.js', array(), null, array( 'strategy' => 'random-strategy' ) );
		wp_enqueue_script( 'invalid-strategy' );

		$this->assertEqualMarkup(
			"<script type='text/javascript' src='/defaults.js' id='invalid-strategy-js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed via wp_script_add_data().
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_script_add_data
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_add_data() {
		wp_register_script( 'invalid-strategy', '/defaults.js', array(), null );
		wp_script_add_data( 'invalid-strategy', 'strategy', 'random-strategy' );
		wp_enqueue_script( 'invalid-strategy' );

		$this->assertEqualMarkup(
			"<script type='text/javascript' src='/defaults.js' id='invalid-strategy-js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed during wp_enqueue_script.
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_enqueue() {
		wp_enqueue_script( 'invalid-strategy', '/defaults.js', array(), null, array( 'strategy' => 'random-strategy' ) );

		$this->assertEqualMarkup(
			"<script type='text/javascript' src='/defaults.js' id='invalid-strategy-js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * Tests that scripts registered with a deferred strategy are not included in the script concat loading query.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_concatenate_with_defer_strategy() {
		global $wp_scripts, $concatenate_scripts, $wp_version;

		$old_value           = $concatenate_scripts;
		$concatenate_scripts = true;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_register_script( 'one-concat-dep', $this->default_scripts_dir . 'script.js' );
		wp_register_script( 'two-concat-dep', $this->default_scripts_dir . 'script.js' );
		wp_register_script( 'three-concat-dep', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'main-defer-script', '/main-script.js', array( 'one-concat-dep', 'two-concat-dep', 'three-concat-dep' ), null, array( 'strategy' => 'defer' ) );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		// Reset global before asserting.
		$concatenate_scripts = $old_value;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep,two-concat-dep,three-concat-dep&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='main-defer-script-js' defer data-wp-strategy='defer'></script>\n";

		$this->assertEqualMarkup( $expected, $print_scripts, 'Scripts are being incorrectly concatenated when a main script is registered with a "defer" loading strategy. Deferred scripts should not be part of the script concat loading query.' );
	}

	/**
	 * Test script concatenation with `async` main script.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_concatenate_with_async_strategy() {
		global $wp_scripts, $concatenate_scripts, $wp_version;

		$old_value           = $concatenate_scripts;
		$concatenate_scripts = true;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one-concat-dep-1', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'two-concat-dep-1', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'three-concat-dep-1', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'main-async-script-1', '/main-script.js', array(), null, array( 'strategy' => 'async' ) );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		// Reset global before asserting.
		$concatenate_scripts = $old_value;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep-1,two-concat-dep-1,three-concat-dep-1&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='main-async-script-1-js' async data-wp-strategy='async'></script>\n";

		$this->assertEqualMarkup( $expected, $print_scripts, 'Scripts are being incorrectly concatenated when a main script is registered with an "async" loading strategy. Async scripts should not be part of the script concat loading query.' );
	}

	/**
	 * Tests that script concatenation remains correct when a main script is registered as deferred after other blocking
	 * scripts are registered.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_concatenate_with_blocking_script_before_and_after_script_with_defer_strategy() {
		global $wp_scripts, $concatenate_scripts, $wp_version;

		$old_value           = $concatenate_scripts;
		$concatenate_scripts = true;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one-concat-dep-2', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'two-concat-dep-2', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'three-concat-dep-2', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'deferred-script-2', '/main-script.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'four-concat-dep-2', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'five-concat-dep-2', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'six-concat-dep-2', $this->default_scripts_dir . 'script.js' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		// Reset global before asserting.
		$concatenate_scripts = $old_value;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep-2,two-concat-dep-2,three-concat-dep-2,four-concat-dep-2,five-concat-dep-2,six-concat-dep-2&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='deferred-script-2-js' defer data-wp-strategy='defer'></script>\n";

		$this->assertEqualMarkup( $expected, $print_scripts, 'Scripts are being incorrectly concatenated when a main script is registered as deferred after other blocking scripts are registered. Deferred scripts should not be part of the script concat loader query string. ' );
	}

	/**
	 * @ticket 42804
	 */
	public function test_wp_enqueue_script_with_html5_support_does_not_contain_type_attribute() {
		global $wp_version;

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );

		$expected = "<script src='http://example.com?ver={$wp_version}' id='empty-deps-no-version-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Test the different protocol references in wp_enqueue_script
	 *
	 * @ticket 16560
	 *
	 * @global WP_Scripts $wp_scripts
	 */
	public function test_protocols() {
		// Init.
		global $wp_scripts, $wp_version;
		$base_url_backup      = $wp_scripts->base_url;
		$wp_scripts->base_url = 'http://example.com/wordpress';
		$expected             = '';

		// Try with an HTTP reference.
		wp_enqueue_script( 'jquery-http', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver={$wp_version}' id='jquery-http-js'></script>\n";

		// Try with an HTTPS reference.
		wp_enqueue_script( 'jquery-https', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver={$wp_version}' id='jquery-https-js'></script>\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_script( 'jquery-doubleslash', '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver={$wp_version}' id='jquery-doubleslash-js'></script>\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/script.js';
		wp_enqueue_script( 'plugin-script', $url );
		$expected .= "<script type='text/javascript' src='$url?ver={$wp_version}' id='plugin-script-js'></script>\n";

		// Try with a bad protocol.
		wp_enqueue_script( 'jquery-ftp', 'ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='{$wp_scripts->base_url}ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver={$wp_version}' id='jquery-ftp-js'></script>\n";

		// Go!
		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );

		// Cleanup.
		$wp_scripts->base_url = $base_url_backup;
	}

	/**
	 * Test script concatenation.
	 */
	public function test_script_concatenation() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'script.js' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$expected = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two,three&amp;ver={$wp_version}'></script>\n";

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * Testing `wp_script_add_data` with the data key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_data_key() {
		// Enqueue and add data.
		wp_enqueue_script( 'test-only-data', 'example.com', array(), null );
		wp_script_add_data( 'test-only-data', 'data', 'testing' );
		$expected  = "<script type='text/javascript' id='test-only-data-js-extra'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-only-data-js'></script>\n";

		// Go!
		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with the conditional key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_conditional_key() {
		// Enqueue and add conditional comments.
		wp_enqueue_script( 'test-only-conditional', 'example.com', array(), null );
		wp_script_add_data( 'test-only-conditional', 'conditional', 'gt IE 7' );
		$expected = "<!--[if gt IE 7]>\n<script type='text/javascript' src='http://example.com' id='test-only-conditional-js'></script>\n<![endif]-->\n";

		// Go!
		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with both the data & conditional keys.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_data_and_conditional_keys() {
		// Enqueue and add data plus conditional comments for both.
		wp_enqueue_script( 'test-conditional-with-data', 'example.com', array(), null );
		wp_script_add_data( 'test-conditional-with-data', 'data', 'testing' );
		wp_script_add_data( 'test-conditional-with-data', 'conditional', 'lt IE 9' );
		$expected  = "<!--[if lt IE 9]>\n<script type='text/javascript' id='test-conditional-with-data-js-extra'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n<![endif]-->\n";
		$expected .= "<!--[if lt IE 9]>\n<script type='text/javascript' src='http://example.com' id='test-conditional-with-data-js'></script>\n<![endif]-->\n";
		$expected  = str_replace( "'", '"', $expected );

		// Go!
		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with an invalid key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_invalid_key() {
		// Enqueue and add an invalid key.
		wp_enqueue_script( 'test-invalid', 'example.com', array(), null );
		wp_script_add_data( 'test-invalid', 'invalid', 'testing' );
		$expected = "<script type='text/javascript' src='http://example.com' id='test-invalid-js'></script>\n";

		// Go!
		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEqualMarkup( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing 'wp_register_script' return boolean success/failure value.
	 *
	 * @ticket 31126
	 */
	public function test_wp_register_script() {
		$this->assertTrue( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
		$this->assertFalse( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
	}

	/**
	 * @ticket 35229
	 */
	public function test_wp_register_script_with_handle_without_source() {
		$expected  = "<script type='text/javascript' src='http://example.com?ver=1' id='handle-one-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=2' id='handle-two-js'></script>\n";

		wp_register_script( 'handle-one', 'http://example.com', array(), 1 );
		wp_register_script( 'handle-two', 'http://example.com', array(), 2 );
		wp_register_script( 'handle-three', false, array( 'handle-one', 'handle-two' ) );

		wp_enqueue_script( 'handle-three' );

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 35643
	 */
	public function test_wp_enqueue_script_footer_alias() {
		wp_register_script( 'foo', false, array( 'bar', 'baz' ), '1.0', true );
		wp_register_script( 'bar', home_url( 'bar.js' ), array(), '1.0', true );
		wp_register_script( 'baz', home_url( 'baz.js' ), array(), '1.0', true );

		wp_enqueue_script( 'foo' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$this->assertEmpty( $header );
		$this->assertStringContainsString( home_url( 'bar.js' ), $footer );
		$this->assertStringContainsString( home_url( 'baz.js' ), $footer );
	}

	/**
	 * Test mismatch of groups in dependencies outputs all scripts in right order.
	 *
	 * @ticket 35873
	 *
	 * @covers WP_Dependencies::add
	 * @covers WP_Dependencies::enqueue
	 * @covers WP_Dependencies::do_items
	 */
	public function test_group_mismatch_in_deps() {
		$scripts = new WP_Scripts();
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ) );
		$scripts->add( 'three', 'three', array( 'two' ), 'v1', 1 );

		$scripts->enqueue( array( 'three' ) );

		$this->expectOutputRegex( '/^(?:<script[^>]+><\/script>\\n){7}$/' );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertNotContains( 'three', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );

		$scripts = new WP_Scripts();
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ), 'v1', 1 );
		$scripts->add( 'three', 'three', array( 'one' ) );
		$scripts->add( 'four', 'four', array( 'two', 'three' ), 'v1', 1 );

		$scripts->enqueue( array( 'four' ) );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertNotContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertNotContains( 'four', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertContains( 'four', $scripts->done );
	}

	/**
	 * @ticket 35873
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer() {
		wp_register_script( 'parent', '/parent.js', array( 'child-head' ), null, true );            // In footer.
		wp_register_script( 'child-head', '/child-head.js', array( 'child-footer' ), null, false ); // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), null, true );              // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-footer.js' id='child-footer-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child-head.js' id='child-head-js'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/parent.js' id='parent-js'></script>\n";

		$this->assertEqualMarkup( $expected_header, $header, 'Expected same header markup.' );
		$this->assertEqualMarkup( $expected_footer, $footer, 'Expected same footer markup.' );
	}

	/**
	 * @ticket 35956
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order() {
		wp_register_script( 'child-head', '/child-head.js', array(), null, false );                      // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), null, true );                   // In footer.
		wp_register_script( 'parent', '/parent.js', array( 'child-head', 'child-footer' ), null, true ); // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js' id='child-head-js'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/child-footer.js' id='child-footer-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent.js' id='parent-js'></script>\n";

		$this->assertEqualMarkup( $expected_header, $header, 'Expected same header markup.' );
		$this->assertEqualMarkup( $expected_footer, $footer, 'Expected same footer markup.' );
	}

	/**
	 * @ticket 35956
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order_and_two_parent_scripts() {
		wp_register_script( 'grandchild-head', '/grandchild-head.js', array(), null, false );             // In head.
		wp_register_script( 'child-head', '/child-head.js', array(), null, false );                       // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array( 'grandchild-head' ), null, true ); // In footer.
		wp_register_script( 'child2-head', '/child2-head.js', array(), null, false );                     // In head.
		wp_register_script( 'child2-footer', '/child2-footer.js', array(), null, true );                  // In footer.
		wp_register_script( 'parent-footer', '/parent-footer.js', array( 'child-head', 'child-footer', 'child2-head', 'child2-footer' ), null, true ); // In footer.
		wp_register_script( 'parent-header', '/parent-header.js', array( 'child-head' ), null, false );   // In head.

		wp_enqueue_script( 'parent-footer' );
		wp_enqueue_script( 'parent-header' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js' id='child-head-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/grandchild-head.js' id='grandchild-head-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child2-head.js' id='child2-head-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/parent-header.js' id='parent-header-js'></script>\n";

		$expected_footer  = "<script type='text/javascript' src='/child-footer.js' id='child-footer-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/child2-footer.js' id='child2-footer-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent-footer.js' id='parent-footer-js'></script>\n";

		$this->assertEqualMarkup( $expected_header, $header, 'Expected same header markup.' );
		$this->assertEqualMarkup( $expected_footer, $footer, 'Expected same footer markup.' );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_returns_bool() {
		$this->assertFalse( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		$this->assertTrue( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_unknown_handle() {
		$this->assertFalse( wp_add_inline_script( 'test-invalid', 'console.log("before");', 'before' ) );
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected  = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_and_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_before_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected = "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_before_and_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_multiple() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_localized_data_is_added_first() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' id='test-example-js-extra'>\n/* <![CDATA[ */\nvar testExample = {\"foo\":\"bar\"};\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );
		wp_add_inline_script( 'two', 'console.log("before two");', 'before' );

		$expected  = "<script type='text/javascript' id='one-js-before'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}one.js?ver={$wp_version}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' id='two-js-before'>\nconsole.log(\"before two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$wp_version}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$wp_version}' id='three-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat2() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );

		$expected  = "<script type='text/javascript' id='one-js-before'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}one.js?ver={$wp_version}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$wp_version}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$wp_version}' id='three-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after_with_concat() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );
		wp_enqueue_script( 'four', $this->default_scripts_dir . 'four.js' );

		wp_add_inline_script( 'two', 'console.log("after two");' );
		wp_add_inline_script( 'three', 'console.log("after three");' );

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$wp_version}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' id='two-js-after'>\nconsole.log(\"after two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$wp_version}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' id='three-js-after'>\nconsole.log(\"after three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}four.js?ver={$wp_version}' id='four-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after_and_before_with_concat_and_conditional() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		$expected_localized  = "<!--[if gte IE 9]>\n";
		$expected_localized .= "<script type='text/javascript' id='test-example-js-extra'>\n/* <![CDATA[ */\nvar testExample = {\"foo\":\"bar\"};\n/* ]]> */\n</script>\n";
		$expected_localized .= "<![endif]-->\n";
		$expected_localized  = str_replace( "'", '"', $expected_localized );

		$expected  = "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";
		$expected  = str_replace( "'", '"', $expected );

		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		$this->assertSame( $expected_localized, get_echo( 'wp_print_scripts' ) );
		$this->assertEqualMarkup( $expected, $wp_scripts->print_html );
		$this->assertTrue( $wp_scripts->do_concat );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_core_dependency() {
		global $wp_scripts, $wp_version;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEqualMarkup( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_conditional_and_core_dependency() {
		global $wp_scripts, $wp_version;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$wp_version}'></script>\n";
		$expected .= "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEqualMarkup( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_with_concat_and_core_dependency() {
		global $wp_scripts, $wp_version;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEqualMarkup( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_after_concat_with_core_dependency() {
		global $wp_scripts, $wp_version;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate,wp-dom-ready,wp-hooks&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/dist/i18n.min.js' id='wp-i18n-js'></script>\n";
		$expected .= "<script type='text/javascript' id='wp-i18n-js-after'>\n";
		$expected .= "wp.i18n.setLocaleData( { 'text direction\u0004ltr': [ 'ltr' ] } );\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/dist/a11y.min.js' id='wp-a11y-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example2.com' id='test-example2-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example2-js-after'>\nconsole.log(\"after\");\n</script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_enqueue_script( 'test-example2', 'http://example2.com', array( 'wp-a11y' ), null );
		wp_add_inline_script( 'test-example2', 'console.log("after");', 'after' );

		// Effectively ignore the output until retrieving it later via `getActualOutput()`.
		$this->expectOutputRegex( '`.`' );

		wp_print_scripts();
		_print_scripts();
		$print_scripts = $this->getActualOutput();

		/*
		 * We've replaced wp-a11y.js with @wordpress/a11y package (see #45066),
		 * and `wp-polyfill` is now a dependency of the packaged wp-a11y.
		 * The packaged scripts contain various version numbers, which are not exposed,
		 * so we will remove all version args from the output.
		 */
		$print_scripts = preg_replace(
			'~js\?ver=([^"\']*)~', // Matches `js?ver=X.X.X` and everything to single or double quote.
			'js',                  // The replacement, `js` without the version arg.
			$print_scripts         // Printed scripts.
		);

		$this->assertEqualMarkup( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_customize_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected_tail  = "<script type='text/javascript' src='/customize-dependency.js' id='customize-dependency-js'></script>\n";
		$expected_tail .= "<script type='text/javascript' id='customize-dependency-js-after'>\n";
		$expected_tail .= "tryCustomizeDependency()\n";
		$expected_tail .= "</script>\n";

		$handle = 'customize-dependency';
		wp_enqueue_script( $handle, '/customize-dependency.js', array( 'customize-controls' ), null );
		wp_add_inline_script( $handle, 'tryCustomizeDependency()' );

		// Effectively ignore the output until retrieving it later via `getActualOutput()`.
		$this->expectOutputRegex( '`.`' );

		wp_print_scripts();
		_print_scripts();
		$print_scripts = $this->getActualOutput();

		$tail = substr( $print_scripts, strrpos( $print_scripts, '<script type="text/javascript" src="/customize-dependency.js" id="customize-dependency-js">' ) );

		$this->assertEqualMarkup( $expected_tail, $tail );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_for_core_scripts_with_concat_is_limited_and_falls_back_to_no_concat() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_add_inline_script( 'one', 'console.log("after one");', 'after' );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/script.js?ver={$wp_version}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' id='one-js-after'>\nconsole.log(\"after one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js?ver={$wp_version}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$wp_version}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$wp_version}' id='four-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_third_core_script_prints_two_concat_scripts() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_add_inline_script( 'three', 'console.log("before three");', 'before' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' id='three-js-before'>\nconsole.log(\"before three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$wp_version}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$wp_version}' id='four-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Data provider to test get_inline_script_data and get_inline_script_tag.
	 *
	 * @return array[]
	 */
	public function data_provider_to_test_get_inline_script() {
		return array(
			'before-blocking' => array(
				'position'       => 'before',
				'inline_scripts' => array(
					'/*before foo 1*/',
				),
				'delayed'        => false,
				'expected_data'  => '/*before foo 1*/',
				'expected_tag'   => "<script id='foo-js-before' type='text/javascript'>\n/*before foo 1*/\n</script>\n",
			),
			'after-blocking'  => array(
				'position'       => 'after',
				'inline_scripts' => array(
					'/*after foo 1*/',
					'/*after foo 2*/',
				),
				'delayed'        => false,
				'expected_data'  => "/*after foo 1*/\n/*after foo 2*/",
				'expected_tag'   => "<script id='foo-js-after' type='text/javascript'>\n/*after foo 1*/\n/*after foo 2*/\n</script>\n",
			),
			'before-delayed'  => array(
				'position'       => 'before',
				'inline_scripts' => array(
					'/*before foo 1*/',
				),
				'delayed'        => true,
				'expected_data'  => '/*before foo 1*/',
				'expected_tag'   => "<script id='foo-js-before' type='text/javascript'>\n/*before foo 1*/\n</script>\n",
			),
			'after-delayed'   => array(
				'position'       => 'after',
				'inline_scripts' => array(
					'/*after foo 1*/',
					'/*after foo 2*/',
				),
				'delayed'        => true,
				'expected_data'  => "/*after foo 1*/\n/*after foo 2*/",
				'expected_tag'   => "<script id='foo-js-after' type='text/javascript'>\n/*after foo 1*/\n/*after foo 2*/\n</script>\n",
			),
		);
	}

	/**
	 * Test getting inline scripts.
	 *
	 * @covers WP_Scripts::get_inline_script_data
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers WP_Scripts::print_inline_script
	 *
	 * @expectedDeprecated WP_Scripts::print_inline_script
	 *
	 * @dataProvider data_provider_to_test_get_inline_script
	 *
	 * @param string   $position       Position.
	 * @param string[] $inline_scripts Inline scripts.
	 * @param bool     $delayed        Delayed.
	 * @param string   $expected_data  Expected data.
	 * @param string   $expected_tag   Expected tag.
	 */
	public function test_get_inline_script( $position, $inline_scripts, $delayed, $expected_data, $expected_tag ) {
		global $wp_scripts;

		$deps = array();
		if ( $delayed ) {
			$wp_scripts->add( 'dep', 'https://example.com/dependency.js', array(), false ); // TODO: Cannot pass strategy to $args e.g. array( 'strategy' => 'defer' )
			$wp_scripts->add_data( 'dep', 'strategy', 'defer' );
			$deps[] = 'dep';
		}

		$handle = 'foo';
		$wp_scripts->add( $handle, 'https://example.com/foo.js', $deps );
		if ( $delayed ) {
			$wp_scripts->add_data( $handle, 'strategy', 'defer' );
		}

		$this->assertSame( '', $wp_scripts->get_inline_script_data( $handle, $position ) );
		$this->assertSame( '', $wp_scripts->get_inline_script_tag( $handle, $position ) );
		$this->assertFalse( $wp_scripts->print_inline_script( $handle, $position, false ) );
		ob_start();
		$output = $wp_scripts->print_inline_script( $handle, $position, true );
		$this->assertSame( '', ob_get_clean() );
		$this->assertFalse( $output );

		foreach ( $inline_scripts as $inline_script ) {
			$wp_scripts->add_inline_script( $handle, $inline_script, $position );
		}

		$this->assertSame( $expected_data, $wp_scripts->get_inline_script_data( $handle, $position ) );
		$this->assertSame( $expected_data, $wp_scripts->print_inline_script( $handle, $position, false ) );
		$this->assertEqualMarkup(
			$expected_tag,
			$wp_scripts->get_inline_script_tag( $handle, $position )
		);
		ob_start();
		$output = $wp_scripts->print_inline_script( $handle, $position, true );
		$this->assertEqualMarkup( $expected_tag, ob_get_clean() );
		$this->assertEquals( $expected_data, $output );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-example',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_plugin() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'plugin-example', '/wp-content/plugins/my-plugin/js/script.js', array(), null );
		wp_set_script_translations( 'plugin-example', 'internationalized-plugin', DIR_TESTDATA . '/languages/plugins' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-plugin',
				'plugin-example',
				file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/plugins/my-plugin/js/script.js' id='plugin-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_theme() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'theme-example', '/wp-content/themes/my-theme/js/script.js', array(), null );
		wp_set_script_translations( 'theme-example', 'internationalized-theme', DIR_TESTDATA . '/languages/themes' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-theme',
				'theme-example',
				file_get_contents( DIR_TESTDATA . '/languages/themes/internationalized-theme-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/themes/my-theme/js/script.js' id='theme-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_with_handle_file() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'script-handle', '/wp-admin/js/script.js', array(), null );
		wp_set_script_translations( 'script-handle', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'admin',
				'script-handle',
				file_get_contents( DIR_TESTDATA . '/languages/admin-en_US-script-handle.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js' id='script-handle-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_i18n_dependency() {
		global $wp_scripts;

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages/' );

		$script = $wp_scripts->registered['test-example'];

		$this->assertContains( 'wp-i18n', $script->deps );
	}

	/**
	 * @ticket 45103
	 * @ticket 55250
	 */
	public function test_wp_set_script_translations_when_translation_file_does_not_exist() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-admin/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_after_register() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_register_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-example',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_dependency() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_register_script( 'test-dependency', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-dependency', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example', '/wp-includes/js/script2.js', array( 'test-dependency' ), null );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-dependency',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js' id='test-dependency-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_enqueue_code_editor` with file path.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_php_file_will_be_passed() {
		$real_file              = WP_PLUGIN_DIR . '/hello.php';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( array( 'file' => $real_file ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'autoCloseTags',
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'matchBrackets',
				'matchTags',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `compact`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_compact_will_be_passed() {
		$file                   = '';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( compact( 'file' ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array_merge`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_array_merge_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array_merge(
				array(
					'type'       => 'text/css',
					'codemirror' => array(
						'indentUnit' => 2,
						'tabSize'    => 2,
					),
				),
				array()
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_simple_array_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/css',
				'codemirror' => array(
					'indentUnit' => 2,
					'tabSize'    => 2,
				),
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * @ticket 52534
	 *
	 * @covers ::wp_localize_script
	 *
	 * @dataProvider data_wp_localize_script_data_formats
	 *
	 * @param mixed  $l10n_data Localization data passed to wp_localize_script().
	 * @param string $expected  Expected transformation of localization data.
	 */
	public function test_wp_localize_script_data_formats( $l10n_data, $expected ) {
		if ( ! is_array( $l10n_data ) ) {
			$this->setExpectedIncorrectUsage( 'WP_Scripts::localize' );
		}

		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', $l10n_data );

		$expected  = "<script type='text/javascript' id='test-example-js-extra'>\n/* <![CDATA[ */\nvar testExample = {$expected};\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";

		$this->assertEqualMarkup( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Data provider for test_wp_localize_script_data_formats().
	 *
	 * @return array[] {
	 *     Array of arguments for test.
	 *
	 *     @type mixed  $l10n_data Localization data passed to wp_localize_script().
	 *     @type string $expected  Expected transformation of localization data.
	 * }
	 */
	public function data_wp_localize_script_data_formats() {
		return array(
			// Officially supported formats.
			array( array( 'array value, no key' ), '["array value, no key"]' ),
			array( array( 'foo' => 'bar' ), '{"foo":"bar"}' ),
			array( array( 'foo' => array( 'bar' => 'foobar' ) ), '{"foo":{"bar":"foobar"}}' ),
			array( array( 'foo' => 6.6 ), '{"foo":"6.6"}' ),
			array( array( 'foo' => 6 ), '{"foo":"6"}' ),
			array( array(), '[]' ),

			// Unofficially supported format.
			array( 'string', '"string"' ),

			// Unsupported formats.
			array( 1.5, '1.5' ),
			array( 1, '1' ),
			array( false, '[""]' ),
			array( null, 'null' ),
		);
	}

	/**
	 * @ticket 55628
	 *
	 * @covers ::wp_set_script_translations
	 */
	public function test_wp_external_wp_i18n_print_order() {
		global $wp_scripts, $wp_version;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/default/' );

		// wp-i18n script in a non-default directory.
		wp_register_script( 'wp-i18n', '/plugins/wp-i18n.js', array(), null );
		// Script in default dir that's going to be concatenated.
		wp_enqueue_script( 'jquery-core', '/default/jquery-core.js', array(), null );
		// Script in default dir that depends on wp-i18n.
		wp_enqueue_script( 'common', '/default/common.js', array(), null );
		wp_set_script_translations( 'common' );

		$print_scripts = get_echo(
			static function () {
				wp_print_scripts();
				_print_scripts();
			}
		);

		// The non-default script should end concatenation and maintain order.
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core&amp;ver={$wp_version}'></script>\n";
		$expected .= "<script type='text/javascript' src='/plugins/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/default/common.js' id='common-js'></script>\n";

		$this->assertEqualMarkup( $expected, $print_scripts );
	}

	/**
	 * Ensure tinymce scripts aren't loading async.
	 *
	 * @ticket 58648
	 */
	public function test_printing_tinymce_scripts() {
		global $wp_scripts;

		wp_register_tinymce_scripts( $wp_scripts, true );

		$actual = get_echo( 'wp_print_scripts', array( array( 'wp-tinymce' ) ) );

		$this->assertStringNotContainsString( 'async', $actual, 'TinyMCE should not have an async attribute.' );
		$this->assertStringNotContainsString( 'defer', $actual, 'TinyMCE should not have a defer attribute.' );
	}

	/**
	 * Make sure scripts with a loading strategy that are printed
	 * without being enqueued are handled properly.
	 *
	 * @ticket 58648
	 *
	 * @dataProvider data_provider_delayed_strategies
	 */
	public function test_printing_non_enqueued_scripts( $strategy ) {
		wp_register_script( 'test-script', 'test-script.js', array(), false, array( 'strategy' => $strategy ) );

		$actual = get_echo( 'wp_print_scripts', array( array( 'test-script' ) ) );

		$this->assertStringContainsString( $strategy, $actual );
	}

	/**
	 * Parse an HTML markup fragment.
	 *
	 * @param string $markup Markup.
	 * @return DOMDocument Document containing the normalized markup fragment.
	 */
	protected function parse_markup_fragment( $markup ) {
		$dom = new DOMDocument();
		$dom->loadHTML(
			"<!DOCTYPE html><html><head><meta charset=utf8></head><body>{$markup}</body></html>"
		);

		/** @var DOMElement $body */
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );

		// Trim whitespace nodes added before/after which can be added when parsing.
		foreach ( array( $body->firstChild, $body->lastChild ) as $node ) {
			if ( $node instanceof DOMText && '' === trim( $node->data ) ) {
				$body->removeChild( $node );
			}
		}

		// Normalize other whitespace nodes.
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()' ) as $node ) {
			/** @var DOMText $node */
			if ( preg_match( '/^\s+$/', $node->nodeValue ) ) {
				$node->nodeValue = ' ';
			}
		}

		return $dom;
	}

	/**
	 * Assert markup is equal after normalizing script tags.
	 *
	 * @param string $expected Expected markup.
	 * @param string $actual   Actual markup.
	 * @param string $message  Message.
	 */
	protected function assertEqualMarkup( $expected, $actual, $message = '' ) {
		$expected_dom = $this->parse_markup_fragment( $expected );
		$actual_dom   = $this->parse_markup_fragment( $actual );
		foreach ( array( $expected_dom, $actual_dom ) as $dom ) {
			$xpath = new DOMXPath( $dom );
			/** @var DOMElement $script */

			// Normalize type attribute. When missing, it defaults to text/javascript.
			foreach ( $xpath->query( '//script[ not( @type ) ]' ) as $script ) {
				$script->setAttribute( 'type', 'text/javascript' );
			}

			// Normalize script contents to remove CDATA wrapper.
			foreach ( $xpath->query( '//script[ contains( text(), "<![CDATA[" ) ]' ) as $script ) {
				$script->textContent = str_replace(
					array(
						"/* <![CDATA[ */\n",
						"\n/* ]]> */",
					),
					'',
					$script->textContent
				);
			}

			// Normalize XHTML-compatible boolean attributes to HTML5 ones.
			foreach ( array( 'async', 'defer' ) as $attribute ) {
				foreach ( iterator_to_array( $xpath->query( "//script[ @{$attribute} = '{$attribute}' ]" ) ) as $script ) {
					$script->removeAttribute( $attribute );
					$script->setAttributeNode( $dom->createAttribute( $attribute ) );
				}
			}
		}

		$this->assertEquals(
			$expected_dom->getElementsByTagName( 'body' )->item( 0 ),
			$actual_dom->getElementsByTagName( 'body' )->item( 0 ),
			$message
		);
	}

	/**
	 * Adds html5 script theme support.
	 */
	protected function add_html5_script_theme_support() {
		add_theme_support( 'html5', array( 'script' ) );
	}

	/**
	 * Test that a script is moved to the footer if it is made non-deferrable, was in the header and
	 * all scripts that depend on it are in the footer.
	 *
	 * @ticket 58599
	 *
	 * @dataProvider data_provider_script_move_to_footer
	 *
	 * @param callable $set_up             Test setup.
	 * @param string   $expected_header    Expected output for header.
	 * @param string   $expected_footer    Expected output for footer.
	 * @param string[] $expected_in_footer Handles expected to be in the footer.
	 * @param array    $expected_groups    Expected groups.
	 */
	public function test_wp_scripts_move_to_footer( $set_up, $expected_header, $expected_footer, $expected_in_footer, $expected_groups ) {
		$set_up();

		// Get the header output.
		ob_start();
		wp_scripts()->do_head_items();
		$header = ob_get_clean();

		// Print a script in the body just to make sure it doesn't cause problems.
		ob_start();
		wp_print_scripts( array( 'jquery' ) );
		ob_end_clean();

		// Get the footer output.
		ob_start();
		wp_scripts()->do_footer_items();
		$footer = ob_get_clean();

		$this->assertEqualMarkup( $expected_header, $header, 'Expected header script markup to match.' );
		$this->assertEqualMarkup( $expected_footer, $footer, 'Expected footer script markup to match.' );
		$this->assertEqualSets( $expected_in_footer, wp_scripts()->in_footer, 'Expected to have the same handles for in_footer.' );
		$this->assertEquals( $expected_groups, wp_scripts()->groups, 'Expected groups to match.' );
	}

	/**
	 * Test that get_script_polyfill() returns the correct polyfill.
	 *
	 * @ticket 60348
	 *
	 * @covers ::wp_get_script_polyfill
	 *
	 * @global WP_Scripts $wp_scripts WP_Scripts instance.
	 */
	public function test_wp_get_script_polyfill() {
		global $wp_scripts;
		$script_name = 'tmp-polyfill-foo';
		$test_script = 'HTMLScriptElement.supports && HTMLScriptElement.supports("foo")';
		$script_url  = 'https://example.com/polyfill-foo.js';
		wp_register_script( $script_name, $script_url );

		$polyfill = wp_get_script_polyfill(
			$wp_scripts,
			array(
				$test_script => $script_name,
			)
		);

		wp_deregister_script( $script_name );

		$expected = '( ' . $test_script . ' ) || document.write( \'<script src="' . $script_url . '"></scr\' + \'ipt>\' );';

		$this->assertSame( $expected, $polyfill );
	}

	/**
	 * Data provider for test_wp_scripts_move_to_footer.
	 *
	 * @return array[]
	 */
	public function data_provider_script_move_to_footer() {
		return array(
			'footer-blocking-dependent-of-defer-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => true ) );
				},
				'expected_header'    => '',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js"></script>
				',
				'expected_in_footer' => array(
					'script-a',
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'footer-blocking-dependent-of-async-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => true ) );
				},
				'expected_header'    => '',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" data-wp-strategy="async"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js"></script>
				',
				'expected_in_footer' => array(
					'script-a',
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'head-blocking-dependent-of-delayed-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => false ) );
				},
				'expected_header'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js"></script>
				',
				'expected_footer'    => '',
				'expected_in_footer' => array(),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'jquery'   => 0,
				),
			),

			'delayed-footer-dependent-of-delayed-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_in_footer' => array(
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'delayed-dependent-in-header-and-delayed-dependents-in-footer' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => false,
						)
					);
					wp_enqueue_script(
						'script-c',
						'https://example.com/script-c.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" defer="defer" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-c.js" id="script-c-js" defer="defer" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-d.js" id="script-d-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_in_footer' => array(
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,
				),
			),

			'all-dependents-in-footer-with-one-blocking' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
					wp_enqueue_script( 'script-c', 'https://example.com/script-c.js', array( 'script-a' ), null, true );
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    => '',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js" defer="defer" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-c.js" id="script-c-js"></script>
					<script type="text/javascript" src="https://example.com/script-d.js" id="script-d-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_in_footer' => array(
					'script-a',
					'script-b',
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,

				),
			),

			'blocking-dependents-in-head-and-footer'     => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => false,
						)
					);
					wp_enqueue_script( 'script-c', 'https://example.com/script-c.js', array( 'script-a' ), null, true );
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    => '
					<script type="text/javascript" src="https://example.com/script-a.js" id="script-a-js" data-wp-strategy="defer"></script>
					<script type="text/javascript" src="https://example.com/script-b.js" id="script-b-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_footer'    => '
					<script type="text/javascript" src="https://example.com/script-c.js" id="script-c-js"></script>
					<script type="text/javascript" src="https://example.com/script-d.js" id="script-d-js" defer="defer" data-wp-strategy="defer"></script>
				',
				'expected_in_footer' => array(
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,
				),
			),

		);
	}

	/**
	 * Tests default scripts are registered with the correct versions.
	 *
	 * Ensures that vendor scripts registered in wp_default_scripts() and
	 * wp_default_packages_vendor() are registered with the correct version
	 * number from package.json.
	 *
	 * @ticket 61855
	 * @ticket 60048
	 *
	 * @covers ::wp_default_scripts
	 * @covers ::wp_default_packages_vendor
	 *
	 * @dataProvider data_vendor_script_versions_registered_manually
	 *
	 * @param string $script Script name as defined in package.json.
	 * @param string $handle Optional. Handle to check for. Defaults to the script name.
	 */
	public function test_vendor_script_versions_registered_manually( $script, $handle = null ) {
		global $wp_scripts;
		wp_default_packages_vendor( $wp_scripts );
		wp_default_scripts( $wp_scripts );

		$package_json = $this->_scripts_from_package_json();
		if ( ! $handle ) {
			$handle = $script;
		}

		$script_query = $wp_scripts->query( $handle, 'registered' );

		$this->assertNotFalse( $script_query, "The script '{$handle}' should be registered." );
		$this->assertArrayHasKey( $script, $package_json, "The dependency '{$script}' should be included in package.json." );
		$this->assertSame( $package_json[ $script ], $wp_scripts->query( $handle, 'registered' )->ver, "The script '{$handle}' should be registered with version {$package_json[ $script ]}." );
	}

	/**
	 * Data provider for test_vendor_script_versions_registered_manually.
	 *
	 * @return array[]
	 */
	public function data_vendor_script_versions_registered_manually() {
		return array(
			'backbone'                         => array( 'backbone' ),
			'clipboard'                        => array( 'clipboard' ),
			'core-js-url-browser'              => array( 'core-js-url-browser', 'wp-polyfill-url' ),
			'element-closest'                  => array( 'element-closest', 'wp-polyfill-element-closest' ),
			'formdata-polyfill'                => array( 'formdata-polyfill', 'wp-polyfill-formdata' ),
			'imagesloaded'                     => array( 'imagesloaded' ),
			'jquery-color'                     => array( 'jquery-color' ),
			'jquery-core'                      => array( 'jquery', 'jquery-core' ),
			'jquery-form'                      => array( 'jquery-form' ),
			'jquery-hoverintent'               => array( 'jquery-hoverintent', 'hoverIntent' ),
			'lodash'                           => array( 'lodash' ),
			'masonry'                          => array( 'masonry-layout', 'masonry' ),
			'moment'                           => array( 'moment' ),
			'objectFitPolyfill'                => array( 'objectFitPolyfill', 'wp-polyfill-object-fit' ),
			'polyfill-library (dom rect)'      => array( 'polyfill-library', 'wp-polyfill-dom-rect' ),
			'polyfill-library (node contains)' => array( 'polyfill-library', 'wp-polyfill-node-contains' ),
			'react (jsx-runtime)'              => array( 'react', 'react-jsx-runtime' ),
			'react (React)'                    => array( 'react' ),
			'react-dom'                        => array( 'react-dom' ),
			'regenerator-runtime'              => array( 'regenerator-runtime' ),
			'underscore'                       => array( 'underscore' ),
			'vanilla-js-hoverintent'           => array( 'hoverintent', 'hoverintent-js' ),
			'whatwg-fetch'                     => array( 'whatwg-fetch', 'wp-polyfill-fetch' ),
			'wicg-inert'                       => array( 'wicg-inert', 'wp-polyfill-inert' ),
		);
	}

	/**
	 * Ensures that all the scripts in the package.json are included in the data provider.
	 *
	 * This is a test the tests to ensure the data provider includes all the scripts in package.json.
	 *
	 * @ticket 61855
	 */
	public function test_vendor_script_data_provider_includes_all_packages() {
		$package_json_dependencies  = array_keys( $this->_scripts_from_package_json() );
		$data_provider_dependencies = $this->data_vendor_script_versions_registered_manually();

		/*
		 * Exclude `@wordpress/*` packages from the packages in package.json.
		 *
		 * The version numbers for these packages is generated by the build
		 * process based on a hash of the file contents.
		 */
		$package_json_dependencies = array_filter(
			$package_json_dependencies,
			static function ( $dependency ) {
				return 0 !== strpos( $dependency, '@wordpress/' );
			}
		);

		// Get the script names from the data provider.
		$data_provider_dependencies = array_map(
			static function ( $dependency ) {
				return $dependency[0];
			},
			$data_provider_dependencies
		);

		// Exclude packages that are not registered in WordPress.
		$exclude                   = array( 'react-is', 'json2php' );
		$package_json_dependencies = array_diff( $package_json_dependencies, $exclude );

		/*
		 * Ensure the arrays are unique.
		 *
		 * This is for the react package as it is included in the data provider
		 * as both `react` and `react-jsx-runtime`.
		 */
		$package_json_dependencies  = array_unique( $package_json_dependencies );
		$data_provider_dependencies = array_unique( $data_provider_dependencies );

		$this->assertSameSets( $package_json_dependencies, $data_provider_dependencies );
	}

	/**
	 * Helper to return dependencies from package.json.
	 */
	private function _scripts_from_package_json() {
		$package = file_get_contents( ABSPATH . '../package.json' );
		$data    = json_decode( $package, true );

		$provider = array();
		return $data['dependencies'];
	}
}
