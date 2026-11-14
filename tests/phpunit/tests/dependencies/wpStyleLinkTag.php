<?php

/**
 * Test wp_get_style_link_tag() and wp_print_style_link_tag().
 *
 * @group dependencies
 * @group styles
 */
class Tests_Dependencies_wpStyleLinkTag extends WP_UnitTestCase {

	/**
	 * The `href` attribute is escaped with esc_url(), which encodes an ampersand as `&#038;`.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 * @covers ::_wp_build_style_link_tag
	 */
	public function test_get_style_link_tag_escapes_uri_and_supports_boolean_attributes() {
		$this->assertSame(
			"<link rel=\"stylesheet\" href=\"https://example.com/style.css?one=1&#038;two=2\" integrity=\"sha384-example\" crossorigin />\n",
			wp_get_style_link_tag(
				array(
					'rel'         => 'stylesheet',
					'href'        => 'https://example.com/style.css?one=1&two=2',
					'integrity'   => 'sha384-example',
					'crossorigin' => true,
				)
			)
		);
	}

	/**
	 * Only the name is printed for a value of `true` or `null`, an empty string is printed as an empty value,
	 * and an attribute whose value is `false` is omitted.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 * @covers ::_wp_build_style_link_tag
	 */
	public function test_get_style_link_tag_attribute_value_types() {
		$this->assertSame(
			"<link enabled null-value empty-value=\"\" zero-string=\"0\" />\n",
			wp_get_style_link_tag(
				array(
					'enabled'     => true,
					'disabled'    => false,
					'null-value'  => null,
					'empty-value' => '',
					'zero-string' => '0',
				)
			)
		);
	}

	/**
	 * Case-insensitive duplicate attributes should keep the first value.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_ignores_duplicate_attributes() {
		$this->assertSame(
			"<link test=\"first\" />\n",
			wp_get_style_link_tag(
				array(
					'test'     => 'first',
					'TEST'     => 'second',
					'disabled' => false,
					'DISABLED' => true,
				)
			)
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_applies_wp_style_attributes_filter() {
		$filter = static function ( $attributes ) {
			$attributes['nonce'] = 'filtered-nonce';
			return $attributes;
		};

		add_filter( 'wp_style_attributes', $filter );

		$tag = wp_get_style_link_tag( array( 'rel' => 'stylesheet' ) );

		remove_filter( 'wp_style_attributes', $filter );

		$this->assertSame(
			"<link rel=\"stylesheet\" nonce=\"filtered-nonce\" />\n",
			$tag
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_prints_attributes_in_the_order_given() {
		$this->assertSame(
			"<link rel=\"preload\" as=\"style\" id=\"test-css\" href=\"https://example.com/style.css?one=1&#038;two=2\" media=\"print\" crossorigin />\n",
			wp_get_style_link_tag(
				array(
					'rel'         => 'preload',
					'as'          => 'style',
					'id'          => 'test-css',
					'href'        => 'https://example.com/style.css?one=1&two=2',
					'media'       => 'print',
					'crossorigin' => true,
				)
			)
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_get_style_link_tag_rejects_invalid_attribute_names() {
		$this->assertSame(
			"<link rel=\"stylesheet\" />\n",
			wp_get_style_link_tag(
				array(
					'rel'                => 'stylesheet',
					'onload="alert(1)"'  => true,
					'x><script>alert(1)' => 'y',
					"crossorigin\n"      => true,
					"onload\n"           => 'alert(1)',
				)
			)
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_keeps_the_order_of_unusual_attribute_names() {
		$this->assertSame(
			"<link rel=\"stylesheet\" @load=\"ready\" x-bind:media=\"query\" href=\"https://example.com/style.css\" />\n",
			wp_get_style_link_tag(
				array(
					'rel'          => 'stylesheet',
					'@load'        => 'ready',
					'x-bind:media' => 'query',
					'href'         => 'https://example.com/style.css',
				)
			)
		);
	}

	/**
	 * A rejected URL leaves out the `href` attribute, rather than an empty one, and leaves no extra space.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 * @covers ::_wp_build_style_link_tag
	 */
	public function test_get_style_link_tag_rejects_disallowed_href_protocols() {
		$this->assertSame(
			"<link rel=\"stylesheet\" media=\"all\" />\n",
			wp_get_style_link_tag(
				array(
					'rel'   => 'stylesheet',
					'href'  => 'javascript:alert(1)',
					'media' => 'all',
				)
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: Closure, 1: string }>
	 */
	public static function data_style_attributes_filter_changing_id(): array {
		return array(
			'changed'           => array(
				static function ( array $attributes ): array {
					$attributes['id'] = 'changed-css';
					return $attributes;
				},
				"<link rel=\"stylesheet\" id=\"test-css\" href=\"https://example.com/style.css\" />\n",
			),
			'removed'           => array(
				static function ( array $attributes ): array {
					unset( $attributes['id'] );
					return $attributes;
				},
				"<link rel=\"stylesheet\" href=\"https://example.com/style.css\" id=\"test-css\" />\n",
			),
			'lexical_variation' => array(
				static function ( array $attributes ): array {
					unset( $attributes['id'] );
					$attributes['ID'] = 'changed-css';
					return $attributes;
				},
				"<link rel=\"stylesheet\" href=\"https://example.com/style.css\" id=\"test-css\" />\n",
			),
		);
	}

	/**
	 * The `id` attribute may be used to locate the tag, as the user profile screen does for `colors-css`.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 *
	 * @dataProvider data_style_attributes_filter_changing_id
	 *
	 * @param Closure $filter   Filter callback for the attributes.
	 * @param string  $expected Expected markup.
	 */
	public function test_style_attributes_filter_cannot_change_id( Closure $filter, string $expected ) {
		$this->setExpectedIncorrectUsage( 'wp_get_style_link_tag' );

		add_filter( 'wp_style_attributes', $filter );

		$this->assertSame(
			$expected,
			wp_get_style_link_tag(
				array(
					'rel'  => 'stylesheet',
					'id'   => 'test-css',
					'href' => 'https://example.com/style.css',
				)
			)
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_style_attributes_filter_can_add_id() {
		add_filter(
			'wp_style_attributes',
			static function ( array $attributes ): array {
				$attributes['id'] = 'added-css';
				return $attributes;
			}
		);

		$this->assertSame(
			"<link rel=\"stylesheet\" id=\"added-css\" />\n",
			wp_get_style_link_tag( array( 'rel' => 'stylesheet' ) )
		);
	}

	/**
	 * A lexical variation of the `id` name added alongside the supplied ID is dropped without a notice.
	 *
	 * @ticket 66342
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_style_attributes_filter_cannot_override_id_with_lexical_variation() {
		add_filter(
			'wp_style_attributes',
			static function ( array $attributes ): array {
				$attributes['ID'] = 'changed-css';
				return $attributes;
			}
		);

		$this->assertSame(
			"<link rel=\"stylesheet\" id=\"test-css\" />\n",
			wp_get_style_link_tag(
				array(
					'rel' => 'stylesheet',
					'id'  => 'test-css',
				)
			)
		);
	}

	/**
	 * @ticket 66342
	 *
	 * @covers ::wp_print_style_link_tag
	 */
	public function test_print_style_link_tag_prints_get_style_link_tag() {
		$attributes = array(
			'rel'  => 'stylesheet',
			'href' => 'https://example.com/style.css',
		);

		$this->assertSame(
			wp_get_style_link_tag( $attributes ),
			get_echo( 'wp_print_style_link_tag', array( $attributes ) )
		);
	}
}
