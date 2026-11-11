<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_add_link_category() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_add_link_category
 */
class Tests_Admin_Includes_AjaxActions_addLinkCategory extends WP_Ajax_UnitTestCase {

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
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		// The manage_links capability is only granted while the Link Manager is enabled.
		update_option( 'link_manager_enabled', 1 );
	}

	/**
	 * Populates $_POST for an add request.
	 *
	 * @param string      $newcat Comma-separated category names.
	 * @param string|null $nonce  Optional. Nonce to send. Default is a valid nonce.
	 */
	private function set_request( string $newcat, ?string $nonce = null ): void {
		$_POST = array(
			'newcat'      => $newcat,
			'_ajax_nonce' => $nonce ?? wp_create_nonce( 'add-link-category' ),
		);
	}

	/**
	 * Dispatches the request and returns the link-category elements from the XML response.
	 *
	 * @return SimpleXMLElement[]
	 */
	private function get_link_category_responses(): array {
		$xml = simplexml_load_string( $this->get_ajax_response( 'add-link-category' ), 'SimpleXMLElement', LIBXML_NOCDATA );
		$this->assertInstanceOf( SimpleXMLElement::class, $xml, 'The response should be valid XML.' );

		$responses = array();
		foreach ( $xml->response as $response ) {
			$responses[] = $response->{'link-category'};
		}

		return $responses;
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_creates_category(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'New Link Category' );

		$responses = $this->get_link_category_responses();

		$term = get_term_by( 'name', 'New Link Category', 'link_category' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertCount( 1, $responses );
		$this->assertSame( (string) $term->term_id, (string) $responses[0]['id'] );
		$this->assertStringContainsString( "id='in-link-category-{$term->term_id}'", (string) $responses[0]->response_data );
		$this->assertStringContainsString( 'New Link Category', (string) $responses[0]->response_data );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_creates_multiple_categories_and_skips_blank_names(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'Cat 1, , Cat 2' );

		$responses = $this->get_link_category_responses();

		$this->assertCount( 2, $responses );
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'name', 'Cat 1', 'link_category' ) );
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'name', 'Cat 2', 'link_category' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_skips_existing_category(): void {
		wp_set_current_user( self::$admin_id );

		self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Existing',
			)
		);
		$this->set_request( 'Existing' );

		$this->assertSame( array(), $this->get_link_category_responses() );
		$this->assertCount(
			1,
			get_terms(
				array(
					'taxonomy'   => 'link_category',
					'name'       => 'Existing',
					'hide_empty' => false,
				)
			)
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_escapes_name_in_response(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( '<script>alert(1)</script>Escaped' );

		$responses = $this->get_link_category_responses();

		$this->assertCount( 1, $responses );
		$data = (string) $responses[0]->response_data;
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;Escaped', $data );
		$this->assertStringNotContainsString( '<script>', $data );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_ignores_empty_name(): void {
		wp_set_current_user( self::$admin_id );

		$count = wp_count_terms( array( 'taxonomy' => 'link_category' ) );
		$this->set_request( '  ' );

		$this->assertSame( array(), $this->get_link_category_responses() );
		$this->assertSame( $count, wp_count_terms( array( 'taxonomy' => 'link_category' ) ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );
		$this->set_request( 'Invalid Nonce Category', 'invalid-nonce' );

		$this->assertSame( '-1', $this->get_ajax_response( 'add-link-category' ) );
		$this->assertFalse( get_term_by( 'name', 'Invalid Nonce Category', 'link_category' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_add_link_category_rejects_user_without_capability(): void {
		wp_set_current_user( self::$subscriber_id );
		$this->set_request( 'Unauthorized Category' );

		$this->assertSame( '-1', $this->get_ajax_response( 'add-link-category' ) );
		$this->assertFalse( get_term_by( 'name', 'Unauthorized Category', 'link_category' ) );
	}
}
