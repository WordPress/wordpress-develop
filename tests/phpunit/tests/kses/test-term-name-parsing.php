<?php

/**
 * Class Tests_Term_Name_Parsing
 *
 * @group kses
 */
class Tests_Term_Name_Parsing extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->kses_filters = array(
			'pre_term_name' => 'wp_filter_kses',
		);

		foreach ( $this->kses_filters as $filter => $function ) {
			add_filter( $filter, $function );
		}
	}

	public function tearDown() {
		foreach ( $this->kses_filters as $filter => $function ) {
			remove_filter( $filter, $function );
		}
		parent::tearDown();
	}

	public function test_term_name_not_parsed_as_block_content() {
		$term_name      = 'Term Name <script>alert("xss")</script>';
		$sanitized_name = wp_kses( $term_name, array() );
		$this->assertSame( 'Term Name alert("xss")', $sanitized_name );
	}

	public function test_post_content_parsed_as_block_content() {
		$post_content      = '<!-- wp:paragraph --><p>Some <strong>content</strong></p><!-- /wp:paragraph -->';
		$sanitized_content = wp_kses( $post_content, 'post' );
		$this->assertSame( '<!-- wp:paragraph --><p>Some <strong>content</strong></p><!-- /wp:paragraph -->', $sanitized_content );
	}
}
