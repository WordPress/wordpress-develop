<?php

/**
 * @group general
 * @group template
 * @ticket 34292
 * @covers ::wp_resource_hints
 */
class Tests_General_wpResourceHints extends WP_UnitTestCase {
	private $old_wp_scripts;
	private $old_wp_styles;

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		$this->old_wp_styles  = $GLOBALS['wp_styles'] ?? null;

		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_styles', 'wp_default_styles' );

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );
		$GLOBALS['wp_styles']                   = new WP_Styles();
		$GLOBALS['wp_styles']->default_version  = get_bloginfo( 'version' );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		parent::tear_down();
	}

	public function test_dns_prefetching() {
		$expected = "<link rel='dns-prefetch' href='//wordpress.org' />\n" .
					"<link rel='dns-prefetch' href='//google.com' />\n" .
					"<link rel='dns-prefetch' href='//make.wordpress.org' />\n";

		add_filter( 'wp_resource_hints', array( $this, 'add_dns_prefetch_domains' ), 10, 2 );

		$actual = get_echo( 'wp_resource_hints' );

		remove_filter( 'wp_resource_hints', array( $this, 'add_dns_prefetch_domains' ) );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_dns_prefetch_domains( $hints, $method ) {
		if ( 'dns-prefetch' === $method ) {
			$hints[] = 'http://wordpress.org';
			$hints[] = 'https://wordpress.org';
			$hints[] = 'htps://wordpress.org'; // Invalid URLs should be skipped.
			$hints[] = 'https://google.com';
			$hints[] = '//make.wordpress.org';
			$hints[] = 'https://wordpress.org/plugins/';
		}

		return $hints;
	}

	/**
	 * @ticket 37652
	 */
	public function test_preconnect() {
		$expected = "<link rel='preconnect' href='//wordpress.org' />\n" .
					"<link rel='preconnect' href='https://make.wordpress.org' />\n" .
					"<link rel='preconnect' href='http://google.com' />\n" .
					"<link rel='preconnect' href='http://w.org' />\n";

		add_filter( 'wp_resource_hints', array( $this, 'add_preconnect_domains' ), 10, 2 );

		$actual = get_echo( 'wp_resource_hints' );

		remove_filter( 'wp_resource_hints', array( $this, 'add_preconnect_domains' ) );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_preconnect_domains( $hints, $method ) {
		if ( 'preconnect' === $method ) {
			$hints[] = '//wordpress.org';
			$hints[] = 'https://make.wordpress.org';
			$hints[] = 'htps://example.com'; // Invalid URLs should be skipped.
			$hints[] = 'http://google.com';
			$hints[] = 'w.org';
		}

		return $hints;
	}

	public function test_prerender() {
		$expected = "<link rel='prerender' href='https://make.wordpress.org/great-again' />\n" .
					"<link rel='prerender' href='http://jobs.wordpress.net' />\n" .
					"<link rel='prerender' href='//core.trac.wordpress.org' />\n";

		add_filter( 'wp_resource_hints', array( $this, 'add_prerender_urls' ), 10, 2 );

		$actual = get_echo( 'wp_resource_hints' );

		remove_filter( 'wp_resource_hints', array( $this, 'add_prerender_urls' ) );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_prerender_urls( $hints, $method ) {
		if ( 'prerender' === $method ) {
			$hints[] = 'https://make.wordpress.org/great-again';
			$hints[] = 'http://jobs.wordpress.net';
			$hints[] = '//core.trac.wordpress.org';
			$hints[] = 'htps://wordpress.org'; // Invalid URLs should be skipped.
		}

		return $hints;
	}

	public function test_parse_url_dns_prefetch() {
		$expected = "<link rel='dns-prefetch' href='//make.wordpress.org' />\n";

		add_filter( 'wp_resource_hints', array( $this, 'add_dns_prefetch_long_urls' ), 10, 2 );

		$actual = get_echo( 'wp_resource_hints' );

		remove_filter( 'wp_resource_hints', array( $this, 'add_dns_prefetch_long_urls' ) );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_dns_prefetch_long_urls( $hints, $method ) {
		if ( 'dns-prefetch' === $method ) {
			$hints[] = 'http://make.wordpress.org/wp-includes/css/editor.css';
		}

		return $hints;
	}

	public function test_dns_prefetch_styles() {
		$expected = "<link rel='dns-prefetch' href='//fonts.googleapis.com' />\n";

		$args = array(
			'family' => 'Open+Sans:400',
			'subset' => 'latin',
		);

		wp_enqueue_style( 'googlefonts', add_query_arg( $args, '//fonts.googleapis.com/css' ) );

		$actual = get_echo( 'wp_resource_hints' );

		wp_dequeue_style( 'googlefonts' );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function test_dns_prefetch_scripts() {
		$expected = "<link rel='dns-prefetch' href='//fonts.googleapis.com' />\n";

		$args = array(
			'family' => 'Open+Sans:400',
			'subset' => 'latin',
		);

		wp_enqueue_script( 'googlefonts', add_query_arg( $args, '//fonts.googleapis.com/css' ) );

		$actual = get_echo( 'wp_resource_hints' );

		wp_dequeue_style( 'googlefonts' );

		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * @ticket 37385
	 */
	public function test_dns_prefetch_scripts_does_not_include_registered_only() {
		$expected   = '';
		$unexpected = "<link rel='dns-prefetch' href='//wordpress.org' />\n";

		wp_register_script( 'jquery-elsewhere', 'https://wordpress.org/wp-includes/js/jquery/jquery.js' );

		$actual = get_echo( 'wp_resource_hints' );

		wp_deregister_script( 'jquery-elsewhere' );

		$this->assertEqualHTML( $expected, $actual );
		$this->assertStringNotContainsString( $unexpected, $actual );
	}

	/**
	 * @ticket 37502
	 */
	public function test_deregistered_scripts_are_ignored() {
		$expected = '';

		wp_enqueue_script( 'test-script', 'http://example.org/script.js' );
		wp_deregister_script( 'test-script' );

		$actual = get_echo( 'wp_resource_hints' );
		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * @ticket 37652
	 */
	public function test_malformed_urls() {
		$expected = '';

		// Errant colon.
		add_filter( 'wp_resource_hints', array( $this, 'add_malformed_url_errant_colon' ), 10, 2 );
		$actual = get_echo( 'wp_resource_hints' );
		remove_filter( 'wp_resource_hints', array( $this, 'add_malformed_url_errant_colon' ) );
		$this->assertEqualHTML( $expected, $actual );

		// Unsupported Scheme.
		add_filter( 'wp_resource_hints', array( $this, 'add_malformed_url_unsupported_scheme' ), 10, 2 );
		$actual = get_echo( 'wp_resource_hints' );
		remove_filter( 'wp_resource_hints', array( $this, 'add_malformed_url_unsupported_scheme' ) );
		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_malformed_url_errant_colon( $hints, $method ) {
		if ( 'preconnect' === $method ) {
			$hints[] = '://core.trac.wordpress.org/ticket/37652';
		}

		return $hints;
	}

	public function add_malformed_url_unsupported_scheme( $hints, $method ) {
		if ( 'preconnect' === $method ) {
			$hints[] = 'git://develop.git.wordpress.org/';
		}

		return $hints;
	}

	/**
	 * @ticket 38121
	 * @ticket 66435
	 */
	public function test_custom_attributes() {
		$expected = "<link rel='preconnect' href='https://make.wordpress.org' />\n" .
					"<link crossorigin as='image' href='https://example.com/foo.jpeg' rel='prefetch' />\n" .
					"<link crossorigin='use-credentials' as='style' href='https://example.com/foo.css' rel='prefetch' />\n" .
					"<link href='http://wordpress.org' rel='prerender' />\n";

		add_filter( 'wp_resource_hints', array( $this, 'add_url_with_attributes' ), 10, 2 );

		$actual = get_echo( 'wp_resource_hints' );

		remove_filter( 'wp_resource_hints', array( $this, 'add_url_with_attributes' ) );

		$this->assertEqualHTML( $expected, $actual );
	}

	public function add_url_with_attributes( $hints, $method ) {
		// Ignore hints with missing href attributes.
		$hints[] = array(
			'rel' => 'foo',
		);

		if ( 'preconnect' === $method ) {
			// Should ignore rel attributes.
			$hints[] = array(
				'rel'  => 'foo',
				'href' => 'https://make.wordpress.org/great-again',
			);
		} elseif ( 'prefetch' === $method ) {
			$hints[] = array(
				'crossorigin',
				'as'   => 'image',
				'pr'   => 0.5, // Obsolete attribute which should be ignored.
				'href' => 'https://example.com/foo.jpeg',
			);
			$hints[] = array(
				'crossorigin' => 'use-credentials',
				'as'          => 'style',
				'href'        => 'https://example.com/foo.css',
			);
		} elseif ( 'prerender' === $method ) {
			// Ignore invalid attributes.
			$hints[] = array(
				'foo'  => 'bar',
				'bar'  => 'baz',
				'href' => 'http://wordpress.org',
			);
		}

		return $hints;
	}

	/**
	 * Tests that link attributes allowed on resource hints are printed, and that
	 * those the HTML spec restricts to other link types are not.
	 *
	 * @ticket 66435
	 */
	public function test_spec_attributes() {
		add_filter(
			'wp_resource_hints',
			static function ( array $hints, string $relation_type ): array {
				if ( 'prefetch' === $relation_type ) {
					$hints[] = array(
						'href'           => 'https://example.com/foo.html',
						'as'             => 'document',
						'fetchpriority'  => 'low',
						'hreflang'       => 'en',
						'media'          => '(min-width: 600px)',
						'referrerpolicy' => 'no-referrer',
						'title'          => 'Foo',
						'type'           => 'text/html',
						'blocking'       => 'render',
						'color'          => '#000',
						'imagesizes'     => '100vw',
						'imagesrcset'    => 'https://example.com/foo.jpeg 1x',
						'integrity'      => 'sha384-foo',
						'sizes'          => 'any',
						'disabled',
					);
				}
				return $hints;
			},
			10,
			2
		);

		$this->assertEqualHTML(
			"<link href='https://example.com/foo.html' as='document' fetchpriority='low' hreflang='en' media='(min-width: 600px)' referrerpolicy='no-referrer' title='Foo' type='text/html' rel='prefetch' />\n",
			get_echo( 'wp_resource_hints' )
		);
	}

	/**
	 * Tests that boolean values only print attributes that may be valueless.
	 *
	 * @ticket 66435
	 *
	 * @dataProvider data_boolean_attributes
	 *
	 * @param array<int|string, mixed> $hint     Resource hint attributes.
	 * @param string                   $expected Expected markup.
	 */
	public function test_boolean_attributes( array $hint, string $expected ) {
		add_filter(
			'wp_resource_hints',
			static function ( array $hints, string $relation_type ) use ( $hint ): array {
				if ( 'prefetch' === $relation_type ) {
					$hints[] = $hint;
				}
				return $hints;
			},
			10,
			2
		);

		$this->assertEqualHTML( $expected, get_echo( 'wp_resource_hints' ) );
	}

	/**
	 * Data provider for {@see self::test_boolean_attributes()}.
	 *
	 * @return array<non-falsy-string, array{ hint: array<int|string, mixed>, expected: string }>
	 */
	public static function data_boolean_attributes(): array {
		$href = 'https://example.com/foo.css';

		return array(
			'crossorigin as true'      => array(
				'hint'     => array(
					'crossorigin' => true,
					'href'        => $href,
				),
				'expected' => "<link crossorigin href='$href' rel='prefetch' />\n",
			),
			'crossorigin as list item' => array(
				'hint'     => array(
					'crossorigin',
					'href' => $href,
				),
				'expected' => "<link crossorigin href='$href' rel='prefetch' />\n",
			),
			'crossorigin as false'     => array(
				'hint'     => array(
					'crossorigin' => false,
					'href'        => $href,
				),
				'expected' => "<link href='$href' rel='prefetch' />\n",
			),
			'non-boolean as true'      => array(
				'hint'     => array(
					'as'   => true,
					'href' => $href,
				),
				'expected' => "<link href='$href' rel='prefetch' />\n",
			),
			'non-boolean as list item' => array(
				'hint'     => array(
					'as',
					'href' => $href,
				),
				'expected' => "<link href='$href' rel='prefetch' />\n",
			),
			'unknown list item'        => array(
				'hint'     => array(
					'foo',
					'href' => $href,
				),
				'expected' => "<link href='$href' rel='prefetch' />\n",
			),
			'non-string list item'     => array(
				'hint'     => array(
					1.5,
					'href' => $href,
				),
				'expected' => "<link href='$href' rel='prefetch' />\n",
			),
			'list item before value'   => array(
				'hint'     => array(
					'crossorigin',
					'crossorigin' => 'use-credentials',
					'href'        => $href,
				),
				'expected' => "<link crossorigin href='$href' rel='prefetch' />\n",
			),
			'value before list item'   => array(
				'hint'     => array(
					'crossorigin' => 'use-credentials',
					'crossorigin',
					'href'        => $href,
				),
				'expected' => "<link crossorigin='use-credentials' href='$href' rel='prefetch' />\n",
			),
			'repeated list item'       => array(
				'hint'     => array(
					'crossorigin',
					'crossorigin',
					'href' => $href,
				),
				'expected' => "<link crossorigin href='$href' rel='prefetch' />\n",
			),
		);
	}
}
