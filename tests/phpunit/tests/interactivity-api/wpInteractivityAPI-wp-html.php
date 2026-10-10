<?php
/**
 * Tests server rendering of trusted HTML.
 *
 * @group interactivity-api
 * @covers ::wp_interactivity_process_directives
 */
class Tests_WP_Interactivity_API_WP_HTML extends WP_UnitTestCase {
	/** @var WP_Interactivity_API Previous global API instance. */
	private $previous_interactivity;

	/** @var array[] Notices emitted while processing directives. */
	private $notices = array();

	/** Installs a fresh API instance and observes public directive notices. */
	public function set_up() {
		parent::set_up();
		global $wp_interactivity;
		$this->previous_interactivity = $wp_interactivity;
		$wp_interactivity             = new WP_Interactivity_API();
		wp_interactivity_state( 'test', array( 'text' => 'processed' ) );
		add_action( 'doing_it_wrong_run', array( $this, 'record_notice' ), 10, 3 );
	}

	/** Removes the notice observer and restores the global API instance. */
	public function tear_down() {
		remove_action( 'doing_it_wrong_run', array( $this, 'record_notice' ) );
		global $wp_interactivity;
		$wp_interactivity = $this->previous_interactivity;
		parent::tear_down();
	}

	/**
	 * Records the public notice event, including its reported function and version.
	 *
	 * @param string $function_name Reported function.
	 * @param string $message       Notice message.
	 * @param string $version       Version introducing the notice.
	 */
	public function record_notice( $function_name, $message, $version ) {
		$this->notices[] = array( $function_name, $message, $version );
	}

	/**
	 * Asserts exactly one notice identifies the decline reason and host.
	 *
	 * @param string $reason    Reason text.
	 * @param string $tag       Host tag name.
	 * @param string $reference Directive reference, if present.
	 */
	private function assert_decline_notice( string $reason, string $tag, string $reference = 'state.html' ) {
		$this->assertCount( 1, $this->notices );
		$this->assertSame( 'WP_Interactivity_API::data_wp_html_processor', $this->notices[0][0] );
		$this->assertSame( '7.2.0', $this->notices[0][2] );
		$this->assertStringContainsString( $reason, $this->notices[0][1] );
		$this->assertStringContainsString( $tag, $this->notices[0][1] );
		if ( '' !== $reference ) {
			$this->assertStringContainsString( $reference, $this->notices[0][1] );
		}
	}

	/**
	 * Wraps a host with the common interactive region and processed sibling.
	 *
	 * @param string $host Host markup.
	 * @return string Region markup.
	 */
	private function region( string $host ): string {
		return '<div data-wp-interactive="test">' . $host . '<span data-wp-text="state.text">x</span></div>';
	}

	/** Tests direct and derived tokens while retaining the host attributes. */
	public function test_direct_and_derived_tokens() {
		$token  = wp_interactivity_as_dangerous_html( '<p>Hello <em>world</em></p>' );
		$values = array(
			$token,
			static function () use ( $token ) {
				return $token;
			},
		);
		foreach ( $values as $value ) {
			wp_interactivity_state( 'test', array( 'html' => $value ) );
			$this->assertSame(
				str_replace( '>x</span>', '>processed</span>', $this->region( '<div id="host" data-wp-html="state.html"><p>Hello <em>world</em></p></div>' ) ),
				wp_interactivity_process_directives( $this->region( '<div id="host" data-wp-html="state.html">Fallback</div>' ) )
			);
		}
	}

	/**
	 * Tests repeated tokens, distinct tokens, and reuse of destroyed object IDs.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_token_identity() {
		for ( $i = 0; $i < 100; ++$i ) {
			$a = wp_interactivity_as_dangerous_html( '<b>A</b>' );
			unset( $a );
			$obj = new stdClass();
			$b   = wp_interactivity_as_dangerous_html( '<i>B</i>' );
		}
		$token = wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' );
		wp_interactivity_state(
			'test',
			array(
				'obj'  => $obj,
				'b'    => $b,
				'html' => $token,
			)
		);
		$host = '<div data-wp-html="state.obj">Fallback</div><div data-wp-html="state.b">Fallback</div><div data-wp-html="state.html">Fallback</div><div data-wp-html="state.html">Fallback</div>';
		$this->assertSame(
			str_replace( '>x</span>', '>processed</span>', $this->region( '<div data-wp-html="state.obj">Fallback</div><div data-wp-html="state.b"><i>B</i></div><div data-wp-html="state.html"><b>marker-7f3a</b></div><div data-wp-html="state.html"><b>marker-7f3a</b></div>' ) ),
			wp_interactivity_process_directives( $this->region( $host ) )
		);
	}

	/**
	 * Tests byte-for-byte insertion without tokenizing even malformed HTML.
	 *
	 * @dataProvider data_raw_html
	 * @param string $html Trusted HTML.
	 */
	public function test_raw_html( string $html ) {
		wp_interactivity_state(
			'test',
			array(
				'html' => wp_interactivity_as_dangerous_html( $html ),
				'list' => array( 1, 2 ),
				'url'  => '/?a=1&b=2',
			)
		);
		$this->assertSame(
			str_replace( '>x</span>', '>processed</span>', $this->region( '<div data-wp-html="state.html">' . $html . '</div>' ) ),
			wp_interactivity_process_directives( $this->region( '<div data-wp-html="state.html">Fallback</div>' ) )
		);
	}

	/**
	 * Supplies valid, malformed, and directive-bearing trusted HTML.
	 *
	 * @return array[] Trusted strings.
	 */
	public static function data_raw_html() {
		return array_map(
			static function ( $html ) {
				return array( $html );
			},
			array(
				'<DIV Class=\'x\'  data-foo="a&amp;b">&lt;tag&gt; &nbsp;é</div>',
				'',
				'a < b',
				'<br>',
				'<img src="a.png">',
				'<ul><li>a</li><li>b</li></ul>',
				'<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>',
				'<script>if (a < b) { x = "</div>"; }</script>',
				'<!-- <div> -->',
				'<body class="x"><p>y</p></body>',
				'<p>x',
				'</div>',
				'<div></span>',
				'<p><b>x</p></b>',
				'<span data-wp-text="state.text">raw</span><div data-wp-interactive="other" data-wp-context=\'{"a":1}\'><template data-wp-each="state.list"><i>t</i></template><a data-wp-bind--href="state.url">raw</a></div>',
			)
		);
	}

	/** Tests own namespace/context, other directives, and scope restoration. */
	public function test_own_scope_and_other_directives() {
		wp_interactivity_state(
			'test',
			array(
				'on'   => true,
				'url'  => '/?a=1&b=2',
				'pick' => static function () {
					return 'a' === wp_interactivity_get_context()['id'] ? wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ) : null;
				},
			)
		);
		$html = '<div data-wp-interactive="outer" data-wp-context=\'{"id":"outer"}\'><a data-wp-interactive="test" data-wp-context=\'{"id":"a"}\' data-wp-class--active="state.on" data-wp-bind--href="state.url" data-wp-html="state.pick">Fallback</a><span data-wp-text="context.id">x</span></div>';
		$this->assertSame(
			str_replace( array( '<a ', '>Fallback</a>', '>x</span>' ), array( '<a class="active" href="/?a=1&#038;b=2" ', '><b>marker-7f3a</b></a>', '>outer</span>' ), $html ),
			wp_interactivity_process_directives( $html )
		);
	}

	/** Tests ignored entries and collision-free placeholder selection. */
	public function test_ignored_entries_and_placeholder_collision() {
		wp_interactivity_state( 'test', array( 'html' => wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ) ) );
		foreach ( array( 'data-wp-html--x="state.html"', 'data-wp-html---id="state.html"', 'data-wp-html=""' ) as $attribute ) {
			$html = $this->region( '<div ' . $attribute . '>Fallback</div>' );
			$this->assertSame( str_replace( '>x</span>', '>processed</span>', $html ), wp_interactivity_process_directives( $html ) );
		}
		$html = $this->region( '<!--wp-interactivity-html:0--><div data-wp-html="state.html">Fallback</div>' );
		$this->assertSame(
			str_replace( array( 'Fallback', '>x</span>' ), array( '<b>marker-7f3a</b>', '>processed</span>' ), $html ),
			wp_interactivity_process_directives( $html )
		);
	}

	/** Tests each item gets its own HTML without affecting child marking. */
	public function test_each_item_renders_its_token() {
		wp_interactivity_state(
			'test',
			array(
				'items'        => array(
					array( 'id' => 'a' ),
					array( 'id' => 'b' ),
				),
				'descriptions' => array(
					'a' => '<b>A</b>',
					'b' => '<i>B</i>',
				),
				'description'  => static function () {
					return wp_interactivity_as_dangerous_html( wp_interactivity_state( 'test' )['descriptions'][ wp_interactivity_get_context()['item']['id'] ] );
				},
			)
		);
		$html = '<div data-wp-interactive="test"><ul><template data-wp-each="state.items"><li data-wp-html="state.description">…</li></template></ul></div>';
		$this->assertSame(
			str_replace( '</template>', '</template><li data-wp-each-child="test::state.items" data-wp-html="state.description"><b>A</b></li><li data-wp-each-child="test::state.items" data-wp-html="state.description"><i>B</i></li>', $html ),
			wp_interactivity_process_directives( $html )
		);
	}

	/**
	 * Tests non-token values preserve fallback directives and sibling processing.
	 *
	 * @dataProvider data_non_tokens
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 * @param string $kind Kind of untrusted value.
	 */
	public function test_non_tokens_decline( string $kind ) {
		$token  = wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' );
		$class  = get_class( $token );
		$values = array(
			'string'       => '<p>Hi</p>',
			'number'       => 1,
			'boolean'      => true,
			'array'        => array( '<p>Hi</p>' ),
			'object'       => new stdClass(),
			'clone'        => clone $token,
			'new'          => new $class(),
			'unserialized' => unserialize( serialize( $token ) ),
		);
		wp_interactivity_state( 'test', array( 'html' => $values[ $kind ] ) );
		$html = $this->region( '<div data-wp-html="state.html">Fallback<span data-wp-text="state.text">x</span></div>' );
		$this->assertSame( str_replace( '>x</span>', '>processed</span>', $html ), wp_interactivity_process_directives( $html ) );
		$this->assert_decline_notice( 'not a token', 'DIV' );
		$this->assertStringNotContainsString( '<p>Hi</p>', $this->notices[0][1] );
	}

	/**
	 * Supplies the unregistered value kinds.
	 *
	 * @return array[] Value kinds.
	 */
	public static function data_non_tokens() {
		return array_map(
			static function ( $kind ) {
				return array( $kind );
			},
			array( 'string', 'number', 'boolean', 'array', 'object', 'clone', 'new', 'unserialized' )
		);
	}

	/** Tests null and unresolved references preserve fallback silently. */
	public function test_null_and_unresolved_references() {
		wp_interactivity_state( 'test', array( 'html' => null ) );
		foreach ( array( 'state.html', 'state.missing' ) as $reference ) {
			$html = $this->region( '<div data-wp-html="' . $reference . '">Fallback</div>' );
			$this->assertSame( str_replace( '>x</span>', '>processed</span>', $html ), wp_interactivity_process_directives( $html ) );
		}
		$this->assertSame( array(), $this->notices );
	}

	/**
	 * Tests JSON context cannot supply a trusted token.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_context_value_declines() {
		$html = '<div data-wp-interactive="test" data-wp-context=\'{"html":{}}\'><div data-wp-html="context.html">Fallback</div><span data-wp-text="state.text">x</span></div>';
		$this->assertSame( str_replace( '>x</span>', '>processed</span>', $html ), wp_interactivity_process_directives( $html ) );
		$this->assert_decline_notice( 'not a token', 'DIV', 'context.html' );
	}

	/**
	 * Tests void, raw-text, and RCDATA hosts decline without changing output.
	 *
	 * @dataProvider data_ineligible_hosts
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 * @param string $tag     Host tag.
	 * @param bool   $is_void Whether the tag is void.
	 */
	public function test_ineligible_hosts( string $tag, bool $is_void ) {
		wp_interactivity_state( 'test', array( 'html' => wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ) ) );
		$html = $this->region( '<' . $tag . ' data-wp-html="state.html">' . ( $is_void ? '' : 'Fallback</' . $tag . '>' ) );
		$this->assertSame( str_replace( '>x</span>', '>processed</span>', $html ), wp_interactivity_process_directives( $html ) );
		$this->assert_decline_notice( 'cannot hold content', strtoupper( $tag ) );
	}

	/**
	 * Supplies all unsupported host categories.
	 *
	 * @return array[] Host tag and void status.
	 */
	public static function data_ineligible_hosts() {
		return array(
			array( 'img', true ),
			array( 'br', true ),
			array( 'textarea', false ),
			array( 'title', false ),
			array( 'script', false ),
			array( 'style', false ),
			array( 'iframe', false ),
			array( 'noembed', false ),
			array( 'noframes', false ),
			array( 'xmp', false ),
		);
	}

	/**
	 * Tests text wins over every HTML entry form without evaluating HTML.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_text_combinations() {
		wp_interactivity_state( 'test', array( 'text' => '<b>processed</b>' ) );
		$values = array( wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ), '<p>Hi</p>', null );
		foreach ( $values as $value ) {
			wp_interactivity_state( 'test', array( 'html' => $value ) );
			foreach ( array( 'data-wp-html="state.html"', 'data-wp-html=""', 'data-wp-html--x="state.html"', 'data-wp-html---id="state.html"', 'data-wp-html="state.html" data-wp-html--x="state.html"' ) as $attribute ) {
				$this->notices = array();
				$html          = $this->region( '<div ' . $attribute . ' data-wp-text="state.text">Fallback</div>' );
				$this->assertSame( str_replace( array( 'Fallback', '>x</span>' ), array( '&lt;b&gt;processed&lt;/b&gt;', '>&lt;b&gt;processed&lt;/b&gt;</span>' ), $html ), wp_interactivity_process_directives( $html ) );
				$this->assert_decline_notice( 'cannot be combined', 'DIV', 'data-wp-html=""' === $attribute ? '' : 'state.html' );
			}
		}
		wp_interactivity_state(
			'test',
			array(
				'html' => static function () {
					throw new Exception( 'The combination must not evaluate this reference.' );
				},
			)
		);
		$this->notices = array();
		wp_interactivity_process_directives( $this->region( '<div data-wp-html="state.html" data-wp-text--x="state.text">Fallback</div>' ) );
		$this->assert_decline_notice( 'cannot be combined', 'DIV' );
	}

	/**
	 * Tests each combinations render the same items as a template without HTML.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_each_combinations() {
		wp_interactivity_state(
			'test',
			array(
				'html' => wp_interactivity_as_dangerous_html( '<b>marker-7f3a</b>' ),
				'list' => array( 'a', 'b' ),
			)
		);
		foreach ( array( 'data-wp-html="state.html"', 'data-wp-html=""', 'data-wp-html--x="state.html"', 'data-wp-html---id="state.html"' ) as $attribute ) {
			$this->notices = array();
			$html          = $this->region( '<ul><template data-wp-each="state.list" ' . $attribute . '><li data-wp-text="context.item">x</li></template></ul>' );
			$baseline      = wp_interactivity_process_directives( str_replace( ' ' . $attribute, '', $html ) );
			$this->assertSame( str_replace( 'data-wp-each="state.list"', 'data-wp-each="state.list" ' . $attribute, $baseline ), wp_interactivity_process_directives( $html ) );
			$this->assertStringContainsString( '>a</li><li data-wp-each-child="test::state.list" data-wp-text="context.item">b</li>', $baseline );
			$this->assert_decline_notice( 'cannot be combined', 'TEMPLATE', 'data-wp-html=""' === $attribute ? '' : 'state.html' );
		}
	}

	/**
	 * Tests delivered decline notices retain the host tag after sanitization.
	 *
	 * @dataProvider data_delivered_decline_notices
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 * @param string $host      Host markup.
	 * @param string $tag       Host tag name.
	 * @param string $reason    Decline reason.
	 * @param string $reference Directive reference, if present.
	 */
	public function test_delivered_decline_notices( string $host, string $tag, string $reason, string $reference ) {
		$this->assertTrue( WP_DEBUG );
		wp_interactivity_state(
			'test',
			array(
				'html' => 'evaluated-value-7f3a',
				'list' => array( 'a' ),
			)
		);
		$delivered_notices = array();
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		set_error_handler(
			/**
			 * Records the message delivered by PHP's notice handler.
			 *
			 * @param int    $severity Error severity.
			 * @param string $message  Delivered message.
			 * @return bool Whether the notice was handled.
			 */
			static function ( $severity, $message ) use ( &$delivered_notices ) {
				$delivered_notices[] = array( $severity, $message );
				return true;
			},
			E_USER_NOTICE
		);
		try {
			wp_interactivity_process_directives( $this->region( $host ) );
		} finally {
			restore_error_handler();
			add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
		$this->assert_decline_notice( $reason, $tag, $reference );
		$this->assertCount( 1, $delivered_notices );
		$this->assertSame( E_USER_NOTICE, $delivered_notices[0][0] );
		$message = $delivered_notices[0][1];
		$this->assertStringContainsString( $tag . ' tag', $message );
		$this->assertStringContainsString( $reason, $message );
		$this->assertStringContainsString( 'WP_Interactivity_API::data_wp_html_processor', $message );
		$this->assertStringContainsString( 'version 7.2.0', $message );
		if ( '' !== $reference ) {
			$this->assertStringContainsString( $reference, $message );
		}
		$this->assertStringNotContainsString( 'evaluated-value-7f3a', $message );
	}

	/**
	 * Supplies all decline reasons, including allowed and disallowed HTML tags.
	 *
	 * @return array[] Host markup, tag, reason, and reference.
	 */
	public static function data_delivered_decline_notices() {
		return array(
			'not a token'       => array( '<div data-wp-html="state.html">Fallback</div>', 'DIV', 'not a token', 'state.html' ),
			'void image'        => array( '<img data-wp-html="state.html">', 'IMG', 'cannot hold content', 'state.html' ),
			'void break'        => array( '<br data-wp-html="state.html">', 'BR', 'cannot hold content', 'state.html' ),
			'text combination'  => array( '<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>', 'DIV', 'cannot be combined', 'state.html' ),
			'each combination'  => array( '<template data-wp-html="state.html" data-wp-each="state.list"><span>x</span></template>', 'TEMPLATE', 'cannot be combined', 'state.html' ),
			'empty combination' => array( '<template data-wp-html="" data-wp-each="state.list"><span>x</span></template>', 'TEMPLATE', 'cannot be combined', '' ),
		);
	}

	/**
	 * Tests the three public notice messages distinguish decline reasons.
	 *
	 * @expectedIncorrectUsage WP_Interactivity_API::data_wp_html_processor
	 */
	public function test_notice_messages_are_distinct() {
		wp_interactivity_state( 'test', array( 'html' => '<p>Hi</p>' ) );
		foreach ( array( '<div data-wp-html="state.html">Fallback</div>', '<br data-wp-html="state.html">', '<div data-wp-html="state.html" data-wp-text="state.text">Fallback</div>' ) as $host ) {
			wp_interactivity_process_directives( $this->region( $host ) );
		}
		$this->assertCount( 3, $this->notices );
		$this->assertCount( 3, array_unique( array_column( $this->notices, 1 ) ) );
	}
}
