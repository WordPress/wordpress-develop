<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing _wp_ajax_add_hierarchical_term() functionality.
 *
 * The handler is registered for `wp_ajax_add-{$taxonomy}` by WP_Taxonomy::add_hooks()
 * for hierarchical taxonomies that have a UI.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::_wp_ajax_add_hierarchical_term
 */
class Tests_Admin_Includes_AjaxActions_addHierarchicalTerm extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Populates $_POST for an add request.
	 *
	 * @param string      $taxonomy Taxonomy name.
	 * @param string      $names    Comma-separated term names.
	 * @param array       $extra    Optional. Additional request fields. Default empty array.
	 * @param string|null $nonce    Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( string $taxonomy, string $names, array $extra = array(), ?string $nonce = null ): void {
		$_POST = array_merge(
			array(
				"new{$taxonomy}"              => $names,
				"_ajax_nonce-add-{$taxonomy}" => $nonce ?? wp_create_nonce( "add-{$taxonomy}" ),
			),
			$extra
		);
	}

	/**
	 * Dispatches the request and parses the XML response.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return SimpleXMLElement The first response element.
	 */
	private function get_xml_response( string $taxonomy ): SimpleXMLElement {
		$xml = simplexml_load_string( $this->get_ajax_response( "add-{$taxonomy}" ), 'SimpleXMLElement', LIBXML_NOCDATA );
		$this->assertInstanceOf( SimpleXMLElement::class, $xml, 'The response should be valid XML.' );

		return $xml->response;
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_returns_checklist_and_parent_dropdown(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'category', 'New Category' );

		$response = $this->get_xml_response( 'category' );

		$term = get_term_by( 'name', 'New Category', 'category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 0, $term->parent );
		$this->assertSame( (string) $term->term_id, (string) $response->category['id'] );
		$this->assertStringContainsString( "value=\"{$term->term_id}\" type=\"checkbox\" name=\"post_category[]\"", (string) $response->category->response_data );
		$this->assertStringContainsString( "name='newcategory_parent'", (string) $response->category->supplemental->newcat_parent );
	}

	/**
	 * When a parent is given, the response replaces the checklist of the top-level ancestor.
	 *
	 * @ticket 65252
	 */
	public function test_add_child_category_returns_top_level_ancestor_checklist(): void {
		wp_set_current_user( self::$admin_id );

		$grandparent_id = self::factory()->category->create();
		$parent_id      = self::factory()->category->create( array( 'parent' => $grandparent_id ) );
		$this->set_request( 'category', 'Child Category', array( 'newcategory_parent' => $parent_id ) );

		$response = $this->get_xml_response( 'category' );

		$term = get_term_by( 'name', 'Child Category', 'category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( $parent_id, $term->parent );
		$this->assertSame( (string) $grandparent_id, (string) $response->category['id'] );

		$data = (string) $response->category->response_data;
		$this->assertStringContainsString( "value=\"{$grandparent_id}\"", $data );
		$this->assertStringContainsString( "value=\"{$parent_id}\"", $data );
		$this->assertStringContainsString( "value=\"{$term->term_id}\"", $data );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_clamps_negative_parent_to_zero(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'category', 'Negative Parent', array( 'newcategory_parent' => -5 ) );

		$this->get_xml_response( 'category' );

		$term = get_term_by( 'name', 'Negative Parent', 'category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 0, $term->parent );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_creates_multiple_terms_and_skips_blank_names(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'category', 'Cat A, , Cat B' );

		$response = $this->get_xml_response( 'category' );

		$cat_a = get_term_by( 'name', 'Cat A', 'category' );
		$cat_b = get_term_by( 'name', 'Cat B', 'category' );
		$this->assertInstanceOf( WP_Term::class, $cat_a );
		$this->assertInstanceOf( WP_Term::class, $cat_b );
		$this->assertSame( (string) $cat_b->term_id, (string) $response->category['id'], 'Only the last term should be returned.' );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_skips_existing_term(): void {
		wp_set_current_user( self::$admin_id );

		self::factory()->category->create( array( 'name' => 'Existing' ) );
		$this->set_request( 'category', 'Existing' );

		$response = $this->get_xml_response( 'category' );

		$this->assertCount( 0, $response->category );
		$this->assertCount(
			1,
			get_terms(
				array(
					'taxonomy'   => 'category',
					'name'       => 'Existing',
					'hide_empty' => false,
				)
			)
		);
	}

	/**
	 * Taxonomies other than category read the checked terms from tax_input.
	 *
	 * @ticket 65252
	 */
	public function test_add_term_to_custom_taxonomy_uses_tax_input(): void {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'hierarchical' => true,
				'show_ui'      => true,
			)
		);
		wp_set_current_user( self::$admin_id );

		$parent_id  = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$sibling_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $parent_id,
			)
		);
		$this->set_request(
			'wptests_tax',
			'Custom Child',
			array(
				'newwptests_tax_parent' => $parent_id,
				'tax_input'             => array( 'wptests_tax' => array( $sibling_id ) ),
			)
		);

		$response = $this->get_xml_response( 'wptests_tax' );

		$term = get_term_by( 'name', 'Custom Child', 'wptests_tax' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( (string) $parent_id, (string) $response->wptests_tax['id'] );
		$this->assertMatchesRegularExpression(
			"/value=\"{$sibling_id}\" type=\"checkbox\" name=\"tax_input\[wptests_tax\]\[\]\" id=\"[^\"]+\" checked='checked'/",
			(string) $response->wptests_tax->response_data
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'category', 'Invalid Nonce Category', array(), 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'add-category' ) );
		$this->assertFalse( get_term_by( 'name', 'Invalid Nonce Category', 'category' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_category_rejects_user_without_capability(): void {
		wp_set_current_user( self::$subscriber_id );
		$this->set_request( 'category', 'No Permission Category' );

		$this->assertSame( '-1', $this->get_ajax_response( 'add-category' ) );
		$this->assertFalse( get_term_by( 'name', 'No Permission Category', 'category' ) );
	}
}
