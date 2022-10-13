<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getTerms extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getTerms( array( 1, 'username', 'password', 'category' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_empty_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', '' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', 'not_existing' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getTerms( array( 1, 'subscriber', 'subscriber', 'category' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to assign terms in this taxonomy.' ), $result->message );
	}

	public function test_valid_terms() {
		$this->make_user_by_role( 'editor' );

		// Make sure there's at least one category.
		$cat = wp_insert_term( 'term_' . __FUNCTION__, 'category' );

		$results = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', 'category' ) );
		$this->assertNotIXRError( $results );

		foreach ( $results as $term ) {
			$this->assertIsInt( $term['count'] );

			// Check custom term meta.
			$this->assertIsArray( $term['custom_fields'] );

			// We expect all other IDs to be strings, not integers,
			// so we don't return something larger than an XMLRPC integer can describe.
			$this->assertStringMatchesFormat( '%d', $term['term_id'] );
			$this->assertStringMatchesFormat( '%d', $term['term_group'] );
			$this->assertStringMatchesFormat( '%d', $term['term_taxonomy_id'] );
			$this->assertStringMatchesFormat( '%d', $term['parent'] );
		}
	}

	public function test_custom_taxonomy() {
		$this->make_user_by_role( 'editor' );

		// Create a taxonomy and some terms for it.
		$tax_name  = 'wp_getTerms_custom_taxonomy';
		$num_terms = 12;
		register_taxonomy( $tax_name, 'post' );
		for ( $i = 0; $i < $num_terms; $i++ ) {
			wp_insert_term( "term_{$i}", $tax_name );
		}

		// Test fetching all terms.
		$results = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', $tax_name ) );
		$this->assertNotIXRError( $results );

		$this->assertCount( $num_terms, $results );
		foreach ( $results as $term ) {
			$this->assertSame( $tax_name, $term['taxonomy'] );
		}

		// Test paged results.
		$filter   = array( 'number' => 5 );
		$results2 = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', $tax_name, $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( 5, $results2 );
		$this->assertSame( $results[1]['term_id'], $results2[1]['term_id'] ); // Check one of the terms.

		$filter['offset'] = 10;
		$results3         = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', $tax_name, $filter ) );
		$this->assertNotIXRError( $results3 );
		$this->assertCount( $num_terms - 10, $results3 );
		$this->assertSame( $results[11]['term_id'], $results3[1]['term_id'] );

		// Test hide_empty (since none have been attached to posts yet, all should be hidden.
		$filter   = array( 'hide_empty' => true );
		$results4 = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', $tax_name, $filter ) );
		$this->assertNotIXRError( $results4 );
		$this->assertCount( 0, $results4 );

		unset( $GLOBALS['wp_taxonomies'][ $tax_name ] );
	}

	public function test_term_ordering() {
		$this->make_user_by_role( 'editor' );

		$cat1 = wp_create_category( 'wp.getTerms_' . __FUNCTION__ . '_1' );
		$cat2 = wp_create_category( 'wp.getTerms_' . __FUNCTION__ . '_2' );

		self::factory()->post->create_many( 5, array( 'post_category' => array( $cat1 ) ) );
		self::factory()->post->create_many( 3, array( 'post_category' => array( $cat2 ) ) );

		$filter  = array(
			'orderby' => 'count',
			'order'   => 'DESC',
		);
		$results = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', 'category', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertNotCount( 0, $results );

		foreach ( $results as $term ) {
			if ( $term['term_id'] === $cat1 ) {
				break; // Found cat1 first as expected.
			} elseif ( $term['term_id'] === $cat2 ) {
				$this->assertFalse( false, 'Incorrect category ordering.' );
			}
		}
	}

	public function test_terms_search() {
		$this->make_user_by_role( 'editor' );

		$name    = __FUNCTION__;
		$name_id = wp_create_category( $name );

		// Search by full name.
		$filter  = array( 'search' => $name );
		$results = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', 'category', $filter ) );
		$this->assertNotIXRError( $results );
		$this->assertCount( 1, $results );
		$this->assertSame( $name, $results[0]['name'] );
		$this->assertEquals( $name_id, $results[0]['term_id'] );

		// Search by partial name.
		$filter   = array( 'search' => substr( $name, 0, 10 ) );
		$results2 = $this->myxmlrpcserver->wp_getTerms( array( 1, 'editor', 'editor', 'category', $filter ) );
		$this->assertNotIXRError( $results2 );
		$this->assertCount( 1, $results2 );
		$this->assertSame( $name, $results2[0]['name'] );
		$this->assertEquals( $name_id, $results2[0]['term_id'] );
	}
}
