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
	protected $old_wp_scripts;

	protected $wp_scripts_print_translations_output;

	// Stores a string reference to a default scripts directory name, utilised by certain tests.
	protected $default_scripts_dir = '/directory/';

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		$this->wp_scripts_print_translations_output  = <<<JS
<script type='text/javascript' id='__HANDLE__-js-translations'>
( function( domain, translations ) {
	var localeData = translations.locale_data[ domain ] || translations.locale_data.messages;
	localeData[""].domain = domain;
	wp.i18n.setLocaleData( localeData, domain );
} )( "__DOMAIN__", __JSON_TRANSLATIONS__ );
</script>
JS;
		$this->wp_scripts_print_translations_output .= "\n";
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		parent::tear_down();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 */
	public function test_wp_enqueue_script() {
		wp_enqueue_script( 'no-deps-no-version', 'example.com', array() );
		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );
		wp_enqueue_script( 'empty-deps-version', 'example.com', array(), 1.2 );
		wp_enqueue_script( 'empty-deps-null-version', 'example.com', array(), null );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='http://example.com?ver=$ver' id='no-deps-no-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=$ver' id='empty-deps-no-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=1.2' id='empty-deps-version-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='empty-deps-null-version-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Test standalone and non-standalone inline scripts in the 'after' position of a single main script.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_print_delayed_inline_script_loader
	 */
	public function test_non_standalone_and_standalone_after_script_combined() {
		// If a main script containing a `defer` strategy has an `after` inline script, the expected script type is type='javascript', otherwise type='text/template'.
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-isinsa-1', 'http://example.org/ms-isinsa-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ms-isinsa-1', 'console.log("after one");', 'after', true );
		wp_add_inline_script( 'ms-isinsa-1', 'console.log("after two");', 'after' );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = <<<EXP
<script type="text/javascript" id="wp-executes-after-js">
function wpLoadAfterScripts( handle ) {
	var scripts, newScript, i, len;
	scripts = document.querySelectorAll(
		'[type="text/template"][data-wp-executes-after="' + handle + '"]'
	);
	for ( i = 0, len = scripts.length; i < len; i++ ) {
		newScript = scripts[ i ].cloneNode( true );
		newScript.type = "text/javascript";
		scripts[ i ].parentNode.replaceChild( newScript, scripts[ i ] );
	}
}
</script>
<script type='text/javascript' src='http://example.org/ms-isinsa-1.js' id='ms-isinsa-1-js' defer onload='wpLoadAfterScripts(&quot;ms-isinsa-1&quot;)'></script>
<script type='text/javascript' id='ms-isinsa-1-js-after'>
console.log("after one");
</script>
<script type='text/template' id='ms-isinsa-1-js-after' data-wp-executes-after='ms-isinsa-1'>
console.log("after two");
</script>

EXP;
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test `standalone` inline scripts in the `after` position with deferred main script.
	 *
	 * If the main script with a `defer` loading strategy has an `after` inline script,
	 * the inline script should not be affected.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_print_delayed_inline_script_loader
	 * @covers ::wp_add_inline_script
	 */
	public function test_standalone_after_inline_script_with_defer_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-isa-1', 'http://example.org/ms-isa-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ms-isa-1', 'console.log("after one");', 'after', true );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='http://example.org/ms-isa-1.js' id='ms-isa-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' id='ms-isa-1-js-after'>\n";
		$expected .= "console.log(\"after one\");\n";
		$expected .= "</script>\n";
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test `standalone` inline scripts in the `after` position with async main script.
	 *
	 * If the main script with async strategy has a `after` inline script,
	 * the inline script should not be affected.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_print_delayed_inline_script_loader
	 * @covers ::wp_add_inline_script
	 */
	public function test_standalone_after_inline_script_with_async_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-isa-2', 'http://example.org/ms-isa-2.js', array(), null, array( 'strategy' => 'async' ) );
		wp_add_inline_script( 'ms-isa-2', 'console.log("after one");', 'after', true );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='http://example.org/ms-isa-2.js' id='ms-isa-2-js' async></script>\n";
		$expected .= "<script type='text/javascript' id='ms-isa-2-js-after'>\n";
		$expected .= "console.log(\"after one\");\n";
		$expected .= "</script>\n";
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone inline scripts in the `after` position with deferred main script.
	 *
	 * If a main script with a `defer` loading strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/template'.
	 * The common loader script should also be injected in this case.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_print_delayed_inline_script_loader
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_after_inline_script_with_defer_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-insa-1', 'http://example.org/ms-insa-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ms-insa-1', 'console.log("after one");', 'after' );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = <<<EXP
<script type="text/javascript" id="wp-executes-after-js">
function wpLoadAfterScripts( handle ) {
	var scripts, newScript, i, len;
	scripts = document.querySelectorAll(
		'[type="text/template"][data-wp-executes-after="' + handle + '"]'
	);
	for ( i = 0, len = scripts.length; i < len; i++ ) {
		newScript = scripts[ i ].cloneNode( true );
		newScript.type = "text/javascript";
		scripts[ i ].parentNode.replaceChild( newScript, scripts[ i ] );
	}
}
</script>
<script type='text/javascript' src='http://example.org/ms-insa-1.js' id='ms-insa-1-js' defer onload='wpLoadAfterScripts(&quot;ms-insa-1&quot;)'></script>
<script type='text/template' id='ms-insa-1-js-after' data-wp-executes-after='ms-insa-1'>
console.log("after one");
</script>

EXP;
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone inline scripts in the `after` position with async main script.
	 *
	 * If a main script with an `async` loading strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/template'.
	 * The common loader script should also be injected in this case.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_print_delayed_inline_script_loader
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_after_inline_script_with_async_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-insa-2', 'http://example.org/ms-insa-2.js', array(), null, array( 'strategy' => 'async' ) );
		wp_add_inline_script( 'ms-insa-2', 'console.log("after one");', 'after' );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = <<<EXP
<script type="text/javascript" id="wp-executes-after-js">
function wpLoadAfterScripts( handle ) {
	var scripts, newScript, i, len;
	scripts = document.querySelectorAll(
		'[type="text/template"][data-wp-executes-after="' + handle + '"]'
	);
	for ( i = 0, len = scripts.length; i < len; i++ ) {
		newScript = scripts[ i ].cloneNode( true );
		newScript.type = "text/javascript";
		scripts[ i ].parentNode.replaceChild( newScript, scripts[ i ] );
	}
}
</script>
<script type='text/javascript' src='http://example.org/ms-insa-2.js' id='ms-insa-2-js' async onload='wpLoadAfterScripts(&quot;ms-insa-2&quot;)'></script>
<script type='text/template' id='ms-insa-2-js-after' data-wp-executes-after='ms-insa-2'>
console.log("after one");
</script>

EXP;
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone inline scripts in the `after` position with blocking main script.
	 *
	 * If a main script with a `blocking` strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/javascript'.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_after_inline_script_with_blocking_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-insa-3', 'http://example.org/ms-insa-3.js', array(), null, array( 'strategy' => 'blocking' ) );
		wp_add_inline_script( 'ms-insa-3', 'console.log("after one");', 'after' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ms-insa-3.js' id='ms-insa-3-js'></script>\n";
		$expected .= "<script type='text/javascript' id='ms-insa-3-js-after'>\n";
		$expected .= "console.log(\"after one\");\n";
		$expected .= "</script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone inline scripts in the `after` position with deferred main script.
	 *
	 * If a main script with no loading strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/javascript'.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_after_inline_script_with_main_script_with_no_strategy() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ms-insa-4', 'http://example.org/ms-insa-4.js', array(), null );
		wp_add_inline_script( 'ms-insa-4', 'console.log("after one");', 'after' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ms-insa-4.js' id='ms-insa-4-js'></script>\n";
		$expected .= "<script type='text/javascript' id='ms-insa-4-js-after'>\n";
		$expected .= "console.log(\"after one\");\n";
		$expected .= "</script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone `before` inline scripts attached to deferred main scripts.
	 *
	 * If the main script has a `before` inline script, all dependencies will be blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_before_inline_script_with_defer_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ds-i1-1', 'http://example.org/ds-i1-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-i1-2', 'http://example.org/ds-i1-2.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-i1-3', 'http://example.org/ds-i1-3.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ms-i1-1', 'http://example.org/ms-i1-1.js', array( 'ds-i1-1', 'ds-i1-2', 'ds-i1-3' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ms-i1-1', 'console.log("before one");', 'before' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ds-i1-1.js' id='ds-i1-1-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-2.js' id='ds-i1-2-js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-3.js' id='ds-i1-3-js'></script>\n";
		$expected .= "<script type='text/javascript' id='ms-i1-1-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i1-1.js' id='ms-i1-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone `before` inline scripts attached to a dependency scripts in a all scripts `defer` chain.
	 *
	 * If any of the dependencies in the chain have a `before` inline script, all scripts above it should be blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_before_inline_script_on_dependency_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ds-i2-1', 'http://example.org/ds-i2-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-i2-2', 'http://example.org/ds-i2-2.js', array( 'ds-i2-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-i2-3', 'http://example.org/ds-i2-3.js', array( 'ds-i2-2' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ms-i2-1', 'http://example.org/ms-i2-1.js', array( 'ds-i2-3' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ds-i2-2', 'console.log("before one");', 'before' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ds-i2-1.js' id='ds-i2-1-js'></script>\n";
		$expected .= "<script type='text/javascript' id='ds-i2-2-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i2-2.js' id='ds-i2-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i2-3.js' id='ds-i2-3-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i2-1.js' id='ms-i2-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone `before` inline scripts attached to top most dependency in an all scripts `defer` chain.
	 *
	 * If the top most dependency in the chain has a `before` inline script,
	 * none of the scripts bellow it will be blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_before_inline_script_on_top_most_dependency_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ds-i3-1', 'http://example.org/ds-i3-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-i3-2', 'http://example.org/ds-i3-2.js', array( 'ds-i3-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ms-i3-1', 'http://example.org/ms-i3-1.js', array( 'ds-i3-2' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ds-i3-1', 'console.log("before one");', 'before' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' id='ds-i3-1-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i3-1.js' id='ds-i3-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i3-2.js' id='ds-i3-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i3-1.js' id='ms-i3-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test non-standalone `before` inline scripts attached to one the chain, of the two all scripts `defer` chains.
	 *
	 * If there are two dependency chains, rules are applied to the scripts in the chain that contain a `before` inline script.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_non_standalone_before_inline_script_on_multiple_defer_script_chain() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ch1-ds-i4-1', 'http://example.org/ch1-ds-i4-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ch1-ds-i4-2', 'http://example.org/ch1-ds-i4-2.js', array( 'ch1-ds-i4-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ch2-ds-i4-1', 'http://example.org/ch2-ds-i4-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ch2-ds-i4-2', 'http://example.org/ch2-ds-i4-2.js', array( 'ch2-ds-i4-1' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ch2-ds-i4-2', 'console.log("before one");', 'before' );
		wp_enqueue_script( 'ms-i4-1', 'http://example.org/ms-i4-1.js', array( 'ch2-ds-i4-1', 'ch2-ds-i4-2' ), null, array( 'strategy' => 'defer' ) );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ch1-ds-i4-1.js' id='ch1-ds-i4-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ch1-ds-i4-2.js' id='ch1-ds-i4-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ch2-ds-i4-1.js' id='ch2-ds-i4-1-js'></script>\n";
		$expected .= "<script type='text/javascript' id='ch2-ds-i4-2-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ch2-ds-i4-2.js' id='ch2-ds-i4-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i4-1.js' id='ms-i4-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test `standalone` inline scripts in the `before` position with deferred main script.
	 *
	 * If the main script has a `before` inline script, `standalone` doesn't apply to
	 * any inline script associated with the main script.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_standalone_before_inline_script_with_defer_main_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ds-is1-1', 'http://example.org/ds-is1-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-is1-2', 'http://example.org/ds-is1-2.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-is1-3', 'http://example.org/ds-is1-3.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ms-is1-1', 'http://example.org/ms-is1-1.js', array( 'ds-is1-1', 'ds-is1-2', 'ds-is1-3' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ms-is1-1', 'console.log("before one");', 'before', true );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ds-is1-1.js' id='ds-is1-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-is1-2.js' id='ds-is1-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-is1-3.js' id='ds-is1-3-js' defer></script>\n";
		$expected .= "<script type='text/javascript' id='ms-is1-1-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-is1-1.js' id='ms-is1-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test `standalone` inline scripts in the `before` position with defer main script.
	 *
	 * If one of the deferred dependencies in the chain has a `before` inline `standalone` script associated with it,
	 * strategy of the dependencies above it remains unchanged.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::print_inline_script
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_standalone_before_inline_script_with_defer_dependency_script() {
		unregister_all_script_handles();
		wp_enqueue_script( 'ds-is2-1', 'http://example.org/ds-is2-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-is2-2', 'http://example.org/ds-is2-2.js', array( 'ds-is2-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ds-is2-3', 'http://example.org/ds-is2-3.js', array( 'ds-is2-2' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'ms-is2-1', 'http://example.org/ms-is2-1.js', array( 'ds-is2-3' ), null, array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'ds-is2-2', 'console.log("before one");', 'before', true );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ds-is2-1.js' id='ds-is2-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' id='ds-is2-2-js-before'>\n";
		$expected .= "console.log(\"before one\");\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-is2-2.js' id='ds-is2-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-is2-3.js' id='ds-is2-3-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ms-is2-1.js' id='ms-is2-1-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test valid async loading strategy case.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_valid_async_registration() {
		// No dependents, No dependencies then async.
		wp_enqueue_script( 'main-script-a1', '/main-script-a1.js', array(), null, array( 'strategy' => 'async' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-a1.js' id='main-script-a1-js' async></script>\n";
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test invalid async loading strategy cases.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_invalid_async_registration() {
		// If any dependencies then it's not async. Since dependency is blocking(/defer) final strategy will be defer.
		wp_enqueue_script( 'dependency-script-a2', '/dependency-script-a2.js', array(), null );
		wp_enqueue_script( 'main-script-a2', '/main-script-a2.js', array( 'dependency-script-a2' ), null, array( 'strategy' => 'async' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-a2.js' id='main-script-a2-js' defer></script>";
		$this->assertStringContainsString( $expected, $output, 'Expected defer.' );

		// If any dependent then it's not async. Since dependent is not set to defer the final strategy will be blocking.
		wp_enqueue_script( 'main-script-a3', '/main-script-a3.js', array(), null, array( 'strategy' => 'async' ) );
		wp_enqueue_script( 'dependent-script-a3', '/dependent-script-a3.js', array( 'main-script-a3' ), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-a3.js' id='main-script-a3-js'></script>";
		$this->assertStringContainsString( $expected, $output, 'Expected blocking.' );
	}

	/**
	 * Test valid defer loading strategy cases.
	 *
	 * @ticket 12009
	 * @dataProvider data_loading_strategy_with_valid_defer_registration
	 */
	public function test_loading_strategy_with_valid_defer_registration( $expected, $output, $message ) {
		$this->assertStringContainsString( $expected, $output, $message );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_loading_strategy_with_valid_defer_registration() {
		$data = array();

		// No dependents, No dependencies and defer strategy.
		wp_enqueue_script( 'main-script-d1', 'http://example.com/main-script-d1.js', array(), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='http://example.com/main-script-d1.js' id='main-script-d1-js' defer></script>\n";
		array_push( $data, array( $expected, $output, 'Expected defer, as there is no dependent or dependency' ) );

		// Main script is defer and all dependencies are either defer/blocking.
		wp_enqueue_script( 'dependency-script-d2-1', 'http://example.com/dependency-script-d2-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependency-script-d2-2', 'http://example.com/dependency-script-d2-2.js', array(), null, array( 'strategy' => 'blocking' ) );
		wp_enqueue_script( 'dependency-script-d2-3', 'http://example.com/dependency-script-d2-3.js', array( 'dependency-script-d2-2' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'main-script-d2', 'http://example.com/main-script-d2.js', array( 'dependency-script-d2-1', 'dependency-script-d2-3' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='http://example.com/main-script-d2.js' id='main-script-d2-js' defer></script>\n";
		array_push( $data, array( $expected, $output, 'Expected defer, as all dependencies are either deferred or blocking' ) );

		// Main script is defer and all dependent are defer.
		wp_enqueue_script( 'main-script-d3', 'http://example.com/main-script-d3.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-1', 'http://example.com/dependent-script-d3-1.js', array( 'main-script-d3' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-2', 'http://example.com/dependent-script-d3-2.js', array( 'dependent-script-d3-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-3', 'http://example.com/dependent-script-d3-3.js', array( 'dependent-script-d3-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='http://example.com/main-script-d3.js' id='main-script-d3-js' defer></script>\n";
		array_push( $data, array( $expected, $output, 'Expected defer, as all dependents have defer loading strategy' ) );

		return $data;
	}

	/**
	 * Test valid defer loading with async dependent.
	 *
	 * @ticket 12009
	 */
	public function test_defer_with_async_dependent() {
		// case with one async dependent.
		wp_enqueue_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-1', '/dependent-script-d4-1.js', array( 'main-script-d4' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-2', '/dependent-script-d4-2.js', array( 'dependent-script-d4-1' ), null, array( 'strategy' => 'async' ) );
		wp_enqueue_script( 'dependent-script-d4-3', '/dependent-script-d4-3.js', array( 'dependent-script-d4-2' ), null, array( 'strategy' => 'defer' ) );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='/main-script-d4.js' id='main-script-d4-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-1.js' id='dependent-script-d4-1-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-2.js' id='dependent-script-d4-2-js' defer></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-3.js' id='dependent-script-d4-3-js' defer></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test invalid defer loading strategy case.
	 *
	 * @ticket 12009
	 */
	public function test_loading_strategy_with_invalid_defer_registration() {
		// Main script is defer and all dependent are not defer. Then main script will have blocking(or no) strategy.
		wp_enqueue_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-1', '/dependent-script-d4-1.js', array( 'main-script-d4' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-2', '/dependent-script-d4-2.js', array( 'dependent-script-d4-1' ), null, array( 'strategy' => 'blocking' ) );
		wp_enqueue_script( 'dependent-script-d4-3', '/dependent-script-d4-3.js', array( 'dependent-script-d4-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-d4.js' id='main-script-d4-js'></script>\n";
		$this->assertStringContainsString( $expected, $output );
	}

	/**
	 * Test valid blocking loading strategy cases.
	 *
	 * @ticket 12009
	 */
	public function test_loading_strategy_with_valid_blocking_registration() {
		wp_enqueue_script( 'main-script-b1', '/main-script-b1.js', array(), null, array( 'strategy' => 'blocking' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b1.js' id='main-script-b1-js'></script>\n";
		$this->assertSame( $expected, $output );

		// strategy args not set.
		wp_enqueue_script( 'main-script-b2', '/main-script-b2.js', array(), null, array() );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b2.js' id='main-script-b2-js'></script>\n";
		$this->assertSame( $expected, $output );
	}

	/**
	 * Test old and new in_footer logic.
	 *
	 * @ticket 12009
	 */
	public function test_old_and_new_in_footer_scripts() {
		// Scripts in head.
		wp_register_script( 'header-old', '/header-old.js', array(), null, false );
		wp_register_script( 'header-new', '/header-new.js', array( 'header-old' ), null, array( 'in_footer' => false ) );
		wp_enqueue_script( 'enqueue-header-old', '/enqueue-header-old.js', array( 'header-new' ), null, false );
		wp_enqueue_script( 'enqueue-header-new', '/enqueue-header-new.js', array( 'enqueue-header-old' ), null, array( 'in_footer' => false ) );

		// Scripts in footer.
		wp_register_script( 'footer-old', '/footer-old.js', array(), null, true );
		wp_register_script( 'footer-new', '/footer-new.js', array( 'footer-old' ), null, array( 'in_footer' => true ) );
		wp_enqueue_script( 'enqueue-footer-old', '/enqueue-footer-old.js', array( 'footer-new' ), null, true );
		wp_enqueue_script( 'enqueue-footer-new', '/enqueue-footer-new.js', array( 'enqueue-footer-old' ), null, array( 'in_footer' => true ) );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_scripts' );

		$expected_header  = "<script type='text/javascript' src='/header-old.js' id='header-old-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/header-new.js' id='header-new-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-old.js' id='enqueue-header-old-js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-new.js' id='enqueue-header-new-js'></script>\n";

		$expected_footer  = "<script type='text/javascript' src='/footer-old.js' id='footer-old-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/footer-new.js' id='footer-new-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-old.js' id='enqueue-footer-old-js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-new.js' id='enqueue-footer-new-js'></script>\n";

		$this->assertSame( $expected_header, $header );
		$this->assertSame( $expected_footer, $footer );
	}

	/**
	 * Test normalized script args.
	 *
	 * @ticket 12009
	 */
	public function test_get_normalized_script_args() {
		global $wp_scripts;
		$args = array(
			'in_footer' => true,
			'strategy'  => 'async',
		);
		wp_enqueue_script( 'footer-async', '/footer-async.js', array(), null, $args );
		$this->assertSame( $args, $wp_scripts->get_data( 'footer-async', 'script_args' ) );

		// Test defaults.
		$expected_args = array(
			'in_footer' => true,
			'strategy'  => 'blocking',
		);
		wp_register_script( 'defaults-strategy', '/defaults.js', array(), null, array( 'in_footer' => true ) );
		$this->assertSame( $expected_args, $wp_scripts->get_data( 'defaults-strategy', 'script_args' ) );

		$expected_args = array(
			'in_footer' => false,
			'strategy'  => 'async',
		);
		wp_register_script( 'defaults-in-footer', '/defaults.js', array(), null, array( 'strategy' => 'async' ) );
		$this->assertSame( $expected_args, $wp_scripts->get_data( 'defaults-in-footer', 'script_args' ) );

		// scripts_args not set of args parameter is empty.
		wp_register_script( 'empty-args-array', '/defaults.js', array(), null, array() );
		$this->assertSame( false, $wp_scripts->get_data( 'defaults', 'script_args' ) );

		wp_register_script( 'no-args', '/defaults.js', array(), null );
		$this->assertSame( false, $wp_scripts->get_data( 'defaults-no-args', 'script_args' ) );

		// Test backward compatibility.
		$expected_args = array(
			'in_footer' => true,
			'strategy'  => 'blocking',
		);
		wp_enqueue_script( 'footer-old', '/footer-async.js', array(), null, true );
		$this->assertSame( $expected_args, $wp_scripts->get_data( 'footer-old', 'script_args' ) );
	}

	/**
	 * Test script strategy doing it wrong.
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 */
	public function test_script_strategy_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'WP_Scripts::get_intended_strategy' );

		wp_register_script( 'invalid-strategy', '/defaults.js', array(), null, array( 'strategy' => 'random-strategy' ) );
		wp_enqueue_script( 'invalid-strategy' );

		$output = get_echo( 'wp_print_scripts' );

		$expected = "<script type='text/javascript' src='/defaults.js' id='invalid-strategy-js'></script>\n";

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test script concatenation with deferred main script.
	 *
	 * @ticket 12009
	 */
	public function test_concatenate_with_defer_strategy() {
		global $wp_scripts, $concatenate_scripts;

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

		// reset global before asserting.
		$concatenate_scripts = $old_value;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep,two-concat-dep,three-concat-dep&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='main-defer-script-js' defer></script>\n";

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * Test script concatenation with `async` main script.
	 *
	 * @ticket 12009
	 */
	public function test_concatenate_with_async_strategy() {
		global $wp_scripts, $concatenate_scripts;

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

		// reset global before asserting.
		$concatenate_scripts = $old_value;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep-1,two-concat-dep-1,three-concat-dep-1&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='main-async-script-1-js' async></script>\n";

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * Test script concatenation with blocking scripts before and after a `defer` script.
	 *
	 * @ticket 12009
	 */
	public function test_concatenate_with_blocking_script_before_and_after_script_with_defer_strategy() {
		global $wp_scripts, $concatenate_scripts;

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

		// reset global before asserting.
		$concatenate_scripts = $old_value;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one-concat-dep-2,two-concat-dep-2,three-concat-dep-2,four-concat-dep-2,five-concat-dep-2,six-concat-dep-2&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script.js' id='deferred-script-2-js' defer></script>\n";

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * @ticket 42804
	 */
	public function test_wp_enqueue_script_with_html5_support_does_not_contain_type_attribute() {
		add_theme_support( 'html5', array( 'script' ) );

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<script src='http://example.com?ver=$ver' id='empty-deps-no-version-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Test the different protocol references in wp_enqueue_script
	 *
	 * @global WP_Scripts $wp_scripts
	 * @ticket 16560
	 */
	public function test_protocols() {
		// Init.
		global $wp_scripts;
		$base_url_backup      = $wp_scripts->base_url;
		$wp_scripts->base_url = 'http://example.com/wordpress';
		$expected             = '';
		$ver                  = get_bloginfo( 'version' );

		// Try with an HTTP reference.
		wp_enqueue_script( 'jquery-http', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver' id='jquery-http-js'></script>\n";

		// Try with an HTTPS reference.
		wp_enqueue_script( 'jquery-https', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver' id='jquery-https-js'></script>\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_script( 'jquery-doubleslash', '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver' id='jquery-doubleslash-js'></script>\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/script.js';
		wp_enqueue_script( 'plugin-script', $url );
		$expected .= "<script type='text/javascript' src='$url?ver=$ver' id='plugin-script-js'></script>\n";

		// Try with a bad protocol.
		wp_enqueue_script( 'jquery-ftp', 'ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='{$wp_scripts->base_url}ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver' id='jquery-ftp-js'></script>\n";

		// Go!
		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );

		// Cleanup.
		$wp_scripts->base_url = $base_url_backup;
	}

	/**
	 * Test script concatenation.
	 */
	public function test_script_concatenation() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'script.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'script.js' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two,three&amp;ver={$ver}'></script>\n";

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
		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

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
		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

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

		// Go!
		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with an anvalid key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_invalid_key() {
		// Enqueue and add an invalid key.
		wp_enqueue_script( 'test-invalid', 'example.com', array(), null );
		wp_script_add_data( 'test-invalid', 'invalid', 'testing' );
		$expected = "<script type='text/javascript' src='http://example.com' id='test-invalid-js'></script>\n";

		// Go!
		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected_header, $header );
		$this->assertSame( $expected_footer, $footer );
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

		$this->assertSame( $expected_header, $header );
		$this->assertSame( $expected_footer, $footer );
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

		$this->assertSame( $expected_header, $header );
		$this->assertSame( $expected_footer, $footer );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_before_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected = "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected = "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );
		wp_add_inline_script( 'two', 'console.log("before two");', 'before' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' id='one-js-before'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}one.js?ver={$ver}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' id='two-js-before'>\nconsole.log(\"before two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$ver}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$ver}' id='three-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat2() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' id='one-js-before'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}one.js?ver={$ver}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$ver}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$ver}' id='three-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after_with_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( $this->default_scripts_dir );

		wp_enqueue_script( 'one', $this->default_scripts_dir . 'one.js' );
		wp_enqueue_script( 'two', $this->default_scripts_dir . 'two.js' );
		wp_enqueue_script( 'three', $this->default_scripts_dir . 'three.js' );
		wp_enqueue_script( 'four', $this->default_scripts_dir . 'four.js' );

		wp_add_inline_script( 'two', 'console.log("after two");' );
		wp_add_inline_script( 'three', 'console.log("after three");' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}two.js?ver={$ver}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' id='two-js-after'>\nconsole.log(\"after two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}three.js?ver={$ver}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' id='three-js-after'>\nconsole.log(\"after three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='{$this->default_scripts_dir}four.js?ver={$ver}' id='four-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$expected  = "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";

		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		$this->assertSame( $expected_localized, get_echo( 'wp_print_scripts' ) );
		$this->assertSame( $expected, $wp_scripts->print_html );
		$this->assertTrue( $wp_scripts->do_concat );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_conditional_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-after'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_with_concat_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' id='test-example-js-before'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com' id='test-example-js'></script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertSame( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_after_concat_with_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$suffix    = wp_scripts_get_suffix();
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate,wp-polyfill-inert,regenerator-runtime,wp-polyfill,wp-dom-ready,wp-hooks&amp;ver={$ver}'></script>\n";
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

		$this->assertSameIgnoreEOL( $expected, $print_scripts );
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

		$tail = substr( $print_scripts, strrpos( $print_scripts, "<script type='text/javascript' src='/customize-dependency.js' id='customize-dependency-js'>" ) );
		$this->assertSame( $expected_tail, $tail );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_for_core_scripts_with_concat_is_limited_and_falls_back_to_no_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_add_inline_script( 'one', 'console.log("after one");', 'after' );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-includes/js/script.js?ver={$ver}' id='one-js'></script>\n";
		$expected .= "<script type='text/javascript' id='one-js-after'>\nconsole.log(\"after one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js?ver={$ver}' id='two-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$ver}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$ver}' id='four-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_third_core_script_prints_two_concat_scripts() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_add_inline_script( 'three', 'console.log("before three");', 'before' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' id='three-js-before'>\nconsole.log(\"before three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$ver}' id='three-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$ver}' id='four-js'></script>\n";

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
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

		$this->assertSameIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_enqueue_code_editor` with file path.
	 *
	 * @ticket 41871
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

		$this->assertSame( $expected, get_echo( 'wp_print_scripts' ) );
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
	 * @covers ::wp_set_script_translations
	 */
	public function test_wp_external_wp_i18n_print_order() {
		global $wp_scripts;

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
			function() {
				wp_print_scripts();
				_print_scripts();
			}
		);

		// The non-default script should end concatenation and maintain order.
		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/plugins/wp-i18n.js' id='wp-i18n-js'></script>\n";
		$expected .= "<script type='text/javascript' src='/default/common.js' id='common-js'></script>\n";

		$this->assertSame( $expected, $print_scripts );
	}
}
