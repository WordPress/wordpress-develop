<?php

/**
 * Test wp_get_inline_script_tag() and wp_print_inline_script_tag().
 *
 * @group dependencies
 * @group scripts
 * @covers ::wp_get_inline_script_tag
 * @covers ::wp_print_inline_script_tag
 */
class Tests_Dependencies_wpInlineScriptTag extends WP_UnitTestCase {

	private $original_theme_features = array();

	public function set_up() {
		global $_wp_theme_features;
		parent::set_up();
		$this->original_theme_features = $_wp_theme_features;
	}

	public function tear_down() {
		global $_wp_theme_features;
		$_wp_theme_features = $this->original_theme_features;
		parent::tear_down();
	}

	private $event_handler = <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	document.getElementById( 'elementID' )
			.addEventListener( 'click', function( event ) {
				event.preventDefault();
			});
});
JS;

	public function get_inline_script_tag_type_set() {
		add_theme_support( 'html5', array( 'script' ) );

		$this->assertSame(
			'<script type="application/javascript" nomodule>' . "\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag(
				$this->event_handler,
				array(
					'type'     => 'application/javascript',
					'async'    => false,
					'nomodule' => true,
				)
			)
		);

		remove_theme_support( 'html5' );

		$this->assertSame(
			'<script type="application/javascript" nomodule>' . "\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag(
				$this->event_handler,
				array(
					'type'     => 'application/javascript',
					'async'    => false,
					'nomodule' => true,
				)
			)
		);
	}

	public function test_get_inline_script_tag_type_not_set() {
		add_theme_support( 'html5', array( 'script' ) );

		$this->assertSame(
			"<script nomodule>\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag(
				$this->event_handler,
				array(
					'async'    => false,
					'nomodule' => true,
				)
			)
		);

		remove_theme_support( 'html5' );
	}

	public function test_get_inline_script_tag_unescaped_src() {
		add_theme_support( 'html5', array( 'script' ) );

		$this->assertSame(
			"<script>\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag( $this->event_handler )
		);

		remove_theme_support( 'html5' );
	}

	public function test_print_script_tag_prints_get_inline_script_tag() {
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes ) {
				if ( isset( $attributes['id'] ) && 'utils-js-extra' === $attributes['id'] ) {
					$attributes['async'] = true;
				}
				return $attributes;
			}
		);

		add_theme_support( 'html5', array( 'script' ) );

		$attributes = array(
			'id'       => 'utils-js-before',
			'nomodule' => true,
		);

		$this->assertSame(
			wp_get_inline_script_tag( $this->event_handler, $attributes ),
			get_echo(
				'wp_print_inline_script_tag',
				array(
					$this->event_handler,
					$attributes,
				)
			)
		);

		remove_theme_support( 'html5' );

		$this->assertSame(
			wp_get_inline_script_tag( $this->event_handler, $attributes ),
			get_echo(
				'wp_print_inline_script_tag',
				array(
					$this->event_handler,
					$attributes,
				)
			)
		);
	}

	public function data_provider_to_test_cdata_wrapper_omitted_for_non_javascript_scripts() {
		return array(
			'no-type'     => array(
				'type'           => null,
				'data'           => 'alert("hello")',
				'expected_cdata' => true,
			),
			'js-type'     => array(
				'type'           => 'text/javascript',
				'data'           => 'alert("hello")',
				'expected_cdata' => true,
			),
			'js-alt-type' => array(
				'type'           => 'application/javascript',
				'data'           => 'alert("hello")',
				'expected_cdata' => true,
			),
			'module'      => array(
				'type'           => 'module',
				'data'           => 'alert("hello")',
				'expected_cdata' => true,
			),
			'importmap'   => array(
				'type'           => 'importmap',
				'data'           => '{"imports":{"bar":"http:\/\/localhost:10023\/bar.js?ver=6.5-alpha-57321"}}',
				'expected_cdata' => false,
			),
			'html'        => array(
				'type'           => 'text/html',
				'data'           => '<div>template code</div>',
				'expected_cdata' => false,
			),
			'json'        => array(
				'type'           => 'application/json',
				'data'           => '{}',
				'expected_cdata' => false,
			),
			'ld'          => array(
				'type'           => 'application/ld+json',
				'data'           => '{}',
				'expected_cdata' => false,
			),
			'specrules'   => array(
				'type'           => 'speculationrules',
				'data'           => '{}',
				'expected_cdata' => false,
			),
		);
	}
}
