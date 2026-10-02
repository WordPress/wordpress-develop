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

	/** Installs a fresh API instance for public function calls. */
	public function set_up() {
		parent::set_up();
		global $wp_interactivity;
		$this->previous_interactivity = $wp_interactivity;
		$wp_interactivity             = new WP_Interactivity_API();
		wp_interactivity_state( 'test', array( 'text' => 'processed' ) );
	}

	/** Restores the global API instance. */
	public function tear_down() {
		global $wp_interactivity;
		$wp_interactivity = $this->previous_interactivity;
		parent::tear_down();
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

	/** Tests repeated tokens, distinct tokens, and reuse of destroyed object IDs. */
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
}
