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
		remove_permastruct( 'foo' );
		remove_rewrite_tag( '%41%' );
		remove_rewrite_tag( '%a.b#%' );
		remove_rewrite_tag( '%custom_item%' );
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
	 * Tests that encoded taxonomy slugs do not shift rewrite capture groups.
	 *
	 * @ticket 41791
	 * @dataProvider data_encoded_taxonomy_slugs
	 *
	 * @param string $slug Taxonomy rewrite slug.
	 */
	public function test_add_permastruct_with_encoded_taxonomy_slug( $slug ) {
		global $wp_rewrite;

		register_taxonomy(
			'wptests_cert',
			'post',
			array(
				'rewrite' => array( 'slug' => $slug ),
			)
		);

		$stored_struct = $wp_rewrite->extra_permastructs['wptests_cert']['struct'];
		$this->assertSame( '/' . $slug . '/%wptests_cert%', $stored_struct );

		$rules = $wp_rewrite->generate_rewrite_rules( $stored_struct );
		$this->assertCount( 5, $rules, 'The encoded prefix should not generate its own archive rules.' );
		$this->assertSame( 'index.php?wptests_cert=$matches[1]', $rules[ $slug . '/([^/]+)/?$' ] );
		$this->assertSame( 'index.php?wptests_cert=$matches[1]&paged=$matches[2]', $rules[ $slug . '/([^/]+)/page/?([0-9]{1,})/?$' ] );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'wptests_cert' ) );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, $term_id, 'wptests_cert' );
		$wp_rewrite->flush_rules();
		$this->go_to( get_term_link( $term_id, 'wptests_cert' ) );

		$this->assertQueryTrue( 'is_tax', 'is_archive' );
		$this->assertSame( $term_id, get_queried_object_id() );
		$this->assertSame( array( $post_id ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * Data provider for test_add_permastruct_with_encoded_taxonomy_slug().
	 *
	 * @return array[]
	 */
	public static function data_encoded_taxonomy_slugs() {
		return array(
			'uppercase Cyrillic' => array( rawurlencode( 'Сертификат' ) ),
			'lowercase Cyrillic' => array( strtolower( rawurlencode( 'Сертификат' ) ) ),
			'Chinese'            => array( rawurlencode( '证书' ) ),
			'four-byte Unicode'  => array( rawurlencode( '📚' ) ),
			'encoded regex'      => array( 'cert%28test%29' ),
			'encoded slash'      => array( 'cert%2Ftest' ),
			'invalid UTF-8'      => array( '%80%81' ),
		);
	}

	/**
	 * Tests that custom rewrite tags remain intact and retain their capture groups.
	 *
	 * @ticket 41791
	 * @dataProvider data_custom_rewrite_tags
	 *
	 * @param string $tag Registered rewrite tag.
	 */
	public function test_add_permastruct_preserves_custom_rewrite_tag( $tag ) {
		global $wp_rewrite;

		add_rewrite_tag( $tag, '([^/]+)', 'hex_tag=' );
		add_rewrite_tag( '%custom_item%', '([^/]+)', 'item=' );
		add_permastruct( 'foo', 'bar/' . $tag . '/%custom_item%' );

		$stored_struct = $wp_rewrite->extra_permastructs['foo']['struct'];
		$this->assertSame( '/bar/' . $tag . '/%custom_item%', $stored_struct );

		$rules = $wp_rewrite->generate_rewrite_rules( $stored_struct );
		$this->assertContains( 'index.php?hex_tag=$matches[1]&item=$matches[2]', $rules );
	}

	/**
	 * Data provider for test_add_permastruct_preserves_custom_rewrite_tag().
	 *
	 * @return array[]
	 */
	public static function data_custom_rewrite_tags() {
		return array(
			'hex-like tag'         => array( '%41%' ),
			'regex metacharacters' => array( '%a.b#%' ),
		);
	}

	/**
	 * Tests rule generation when no rewrite tags are registered.
	 *
	 * @ticket 41791
	 * @covers WP_Rewrite::generate_rewrite_rules
	 */
	public function test_generate_rewrite_rules_without_registered_tags() {
		$rewrite              = new WP_Rewrite();
		$rewrite->rewritecode = array();
		$rules                = $rewrite->generate_rewrite_rules( '/archive/' );

		$this->assertSame( 'index.php?&paged=$1', $rules['archive/page/?([0-9]{1,})/?$'] );
	}

	/**
	 * Tests that unused rewrite tags do not exceed the regular expression size limit.
	 *
	 * @ticket 41791
	 * @covers WP_Rewrite::generate_rewrite_rules
	 */
	public function test_generate_rewrite_rules_with_many_registered_tags() {
		$rewrite = new WP_Rewrite();

		for ( $i = 0; $i < 1000; ++$i ) {
			$rewrite->add_rewrite_tag( '%' . sprintf( 'taxonomy_%023d', $i ) . '%', '([^/]+)', 'term=' );
		}

		$rules = $rewrite->generate_rewrite_rules( '/%postname%/' );

		$this->assertSame( 'index.php?name=$1&page=$2', $rules['([^/]+)(?:/([0-9]+))?/?$'] );
	}

	/**
	 * Tests that token matching follows the same order as rewrite tag replacement.
	 *
	 * @ticket 41791
	 * @covers WP_Rewrite::generate_rewrite_rules
	 */
	public function test_generate_rewrite_rules_with_overlapping_tags() {
		$rewrite = new WP_Rewrite();
		$rewrite->add_rewrite_tag( '%a%', '([^/]+)', 'a=' );
		$rewrite->add_rewrite_tag( '%b%', '([^/]+)', 'b=' );
		$rewrite->add_rewrite_tag( '%a%%b%', '([^/]+)', 'combined=' );

		$rules = $rewrite->generate_rewrite_rules( '/%a%%b%/' );

		$this->assertSame( 'index.php?a=$1&b=$2', $rules['([^/]+)([^/]+)/?$'] );
	}

	/**
	 * Tests that a repeated encoded prefix is preserved after the first rewrite tag.
	 *
	 * @ticket 41791
	 * @covers WP_Rewrite::generate_rewrite_rules
	 */
	public function test_generate_rewrite_rules_with_repeated_encoded_prefix() {
		$rewrite = new WP_Rewrite();
		$prefix  = rawurlencode( 'Сертификат' );
		$rules   = $rewrite->generate_rewrite_rules( '/' . $prefix . '/%year%/' . $prefix . '/%monthnum%/' );

		$this->assertArrayHasKey( $prefix . '/([0-9]{4})/' . $prefix . '/([0-9]{1,2})/?$', $rules );
		$this->assertSame( 'index.php?year=$1&monthnum=$2', $rules[ $prefix . '/([0-9]{4})/' . $prefix . '/([0-9]{1,2})/?$' ] );
	}
}
