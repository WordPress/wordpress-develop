<?php

/**
 * @group rewrite
 *
 * @covers ::add_permastruct
 * @covers ::remove_permastruct
 */
class Tests_Rewrite_Permastructs extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		remove_permastruct( 'wptests_cert' );
		unregister_taxonomy( 'wptests_cert' );

		parent::tear_down();
	}

	public function test_add_permastruct() {
		global $wp_rewrite;

		add_permastruct( 'foo', 'bar/%foo%' );
		$this->assertSameSetsWithIndex(
			array(
				'with_front'  => true,
				'ep_mask'     => EP_NONE,
				'paged'       => true,
				'feed'        => true,
				'walk_dirs'   => true,
				'endpoints'   => true,
				'forcomments' => false,
				'struct'      => '/bar/%foo%',
			),
			$wp_rewrite->extra_permastructs['foo']
		);
		remove_permastruct( 'foo' );
	}

	public function test_remove_permastruct() {
		global $wp_rewrite;

		add_permastruct( 'foo', 'bar/%foo%' );
		$this->assertIsArray( $wp_rewrite->extra_permastructs['foo'] );
		$this->assertSame( '/bar/%foo%', $wp_rewrite->extra_permastructs['foo']['struct'] );

		remove_permastruct( 'foo' );
		$this->assertArrayNotHasKey( 'foo', $wp_rewrite->extra_permastructs );
	}

	/**
	 * Tests that a URL-encoded Unicode taxonomy slug is decoded in the permastruct and
	 * that the generated rewrite rules correctly map the taxonomy query var to $matches[1].
	 *
	 * @ticket 41791
	 */
	public function test_add_permastruct_with_url_encoded_unicode_slug() {
		global $wp_rewrite;

		register_taxonomy(
			'wptests_cert',
			'post',
			array(
				'rewrite' => array(
					'slug' => urlencode( 'Сертификат' ),
				),
			)
		);

		$stored_struct = $wp_rewrite->extra_permastructs['wptests_cert']['struct'];

		// The struct must store decoded Unicode, not percent-encoded sequences.
		$this->assertStringContainsString( 'Сертификат', $stored_struct );
		$this->assertStringNotContainsString( urlencode( 'Сертификат' ), $stored_struct );

		// The generated rules must map the taxonomy var to $matches[1], not a shifted index.
		$rules = $wp_rewrite->generate_rewrite_rules( $stored_struct );
		$this->assertContains( 'index.php?wptests_cert=$matches[1]', array_values( $rules ) );
	}

	/**
	 * Tests that percent-encoded sequences that do not decode to valid UTF-8 are left intact
	 * so that plugin-registered tag placeholders shaped like hex sequences are never silently mangled.
	 *
	 * @ticket 41791
	 */
	public function test_add_permastruct_preserves_non_utf8_encoded_sequences() {
		global $wp_rewrite;

		// %80%81 decodes to two lone continuation bytes — invalid UTF-8.
		$invalid_sequence = '%80%81';
		add_permastruct( 'foo_invalid', "bar/{$invalid_sequence}/%foo_invalid%" );

		$stored_struct = $wp_rewrite->extra_permastructs['foo_invalid']['struct'];

		// The invalid sequence must be preserved as-is.
		$this->assertStringContainsString( $invalid_sequence, $stored_struct );

		remove_permastruct( 'foo_invalid' );
	}
}
