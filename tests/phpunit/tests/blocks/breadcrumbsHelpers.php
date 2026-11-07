<?php
/**
 * Tests for the helper functions of the Breadcrumbs block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_BreadcrumbsHelpers extends WP_UnitTestCase {

	/**
	 * Tests whether the view is paginated.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_is_paged
	 *
	 * @dataProvider data_paged
	 *
	 * @param int|string $paged    The value of the paged query variable.
	 * @param bool       $expected Whether the view is paginated.
	 */
	public function test_is_paged( $paged, $expected ) {
		set_query_var( 'paged', $paged );

		$this->assertSame( $expected, block_core_breadcrumbs_is_paged() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_paged() {
		return array(
			'zero'        => array( 0, false ),
			'one'         => array( 1, false ),
			'two'         => array( 2, true ),
			'string'      => array( '3', true ),
			'negative'    => array( -2, false ),
			'empty'       => array( '', false ),
			'non-numeric' => array( 'abc', false ),
		);
	}

	/**
	 * Tests that the view is not paginated without the query variable.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_is_paged
	 */
	public function test_is_paged_without_the_query_variable() {
		$this->go_to( '/' );

		$this->assertFalse( block_core_breadcrumbs_is_paged() );
	}

	/**
	 * Tests the item of the page number.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_create_page_number_item
	 *
	 * @dataProvider data_page_number_items
	 *
	 * @param array       $args     The arguments of the function.
	 * @param string      $query    The query variable to set.
	 * @param int         $number   The page number to set.
	 * @param string      $expected The expected label.
	 */
	public function test_create_page_number_item( $args, $query, $number, $expected ) {
		set_query_var( $query, $number );

		$this->assertSame( array( 'label' => $expected ), block_core_breadcrumbs_create_page_number_item( ...$args ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_page_number_items() {
		return array(
			'default'             => array( array(), 'paged', 2, 'Page 2' ),
			'paged'               => array( array( 'paged' ), 'paged', 5, 'Page 5' ),
			'comments page'       => array( array( 'cpage' ), 'cpage', 3, 'Comments Page 3' ),
			'other variable'      => array( array( 'page' ), 'page', 4, 'Page 4' ),
			'number is formatted' => array( array( 'paged' ), 'paged', 1234, 'Page 1,234' ),
			'comments formatted'  => array( array( 'cpage' ), 'cpage', 12345, 'Comments Page 12,345' ),
		);
	}

	/**
	 * Tests that the page number is read from the query variable that is asked for.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_create_page_number_item
	 */
	public function test_create_page_number_item_reads_the_query_variable_that_is_asked_for() {
		set_query_var( 'paged', 7 );
		set_query_var( 'cpage', 9 );

		$this->assertSame( 'Page 7', block_core_breadcrumbs_create_page_number_item()['label'] );
		$this->assertSame( 'Comments Page 9', block_core_breadcrumbs_create_page_number_item( 'cpage' )['label'] );
	}

	/**
	 * Tests the item that is the current page.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_create_item
	 */
	public function test_create_item_without_a_link() {
		$this->assertSame( array( 'label' => 'Current' ), block_core_breadcrumbs_create_item( 'Current' ) );
		$this->assertSame( array( 'label' => 'Current' ), block_core_breadcrumbs_create_item( 'Current', false ) );
	}

	/**
	 * Tests the item that links to the first page when the view is paginated.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_create_item
	 */
	public function test_create_item_with_a_link_to_the_first_page_when_paginated() {
		$this->go_to( '/?paged=3' );

		$item = block_core_breadcrumbs_create_item( 'Archive', true );

		$this->assertSame( 'Archive', $item['label'] );
		$this->assertSame( get_pagenum_link( 1 ), $item['url'] );
		$this->assertStringNotContainsString( 'paged=', $item['url'] );
	}

	/**
	 * Tests the title of a post.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_post_title
	 */
	public function test_get_post_title() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A title' ) );

		$this->assertSame( 'A title', block_core_breadcrumbs_get_post_title( $post_id ) );
		$this->assertSame( 'A title', block_core_breadcrumbs_get_post_title( get_post( $post_id ) ) );
	}

	/**
	 * Tests the title of a post without a title.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_post_title
	 */
	public function test_get_post_title_without_a_title() {
		$post_id = self::factory()->post->create( array( 'post_title' => '' ) );

		$this->assertSame( '(no title)', block_core_breadcrumbs_get_post_title( $post_id ) );
		$this->assertSame( '(no title)', block_core_breadcrumbs_get_post_title( get_post( $post_id ) ) );
		$this->assertSame( '(no title)', block_core_breadcrumbs_get_post_title( 99999999 ), 'A post that does not exist.' );
	}

	/**
	 * Tests that a title of "0" is a title.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_post_title
	 */
	public function test_get_post_title_with_a_zero_title() {
		$post_id = self::factory()->post->create( array( 'post_title' => '0' ) );

		$this->assertSame( '0', block_core_breadcrumbs_get_post_title( $post_id ) );
	}

	/**
	 * Tests the filtered title of a post.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_post_title
	 */
	public function test_get_post_title_is_filtered() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Plain' ) );

		add_filter(
			'the_title',
			static function ( $title ) {
				return "Filtered $title";
			}
		);

		$this->assertSame( 'Filtered Plain', block_core_breadcrumbs_get_post_title( $post_id ) );
	}

	/**
	 * Tests the breadcrumbs of the ancestors of a post, from the top to the parent.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs
	 */
	public function test_get_hierarchical_post_type_breadcrumbs() {
		$grandparent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Grandparent',
			)
		);
		$parent_id      = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Parent',
				'post_parent' => $grandparent_id,
			)
		);
		$child_id       = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $parent_id,
			)
		);

		$this->assertSame(
			array(
				array(
					'label'      => 'Grandparent',
					'url'        => get_permalink( $grandparent_id ),
					'allow_html' => true,
				),
				array(
					'label'      => 'Parent',
					'url'        => get_permalink( $parent_id ),
					'allow_html' => true,
				),
			),
			block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs( $child_id )
		);
	}

	/**
	 * Tests the breadcrumbs of a post without ancestors.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs
	 */
	public function test_get_hierarchical_post_type_breadcrumbs_without_ancestors() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array(), block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs( $page_id ) );
		$this->assertSame( array(), block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs( 99999999 ) );
	}

	/**
	 * Tests the fallback title of an ancestor without a title.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs
	 */
	public function test_get_hierarchical_post_type_breadcrumbs_with_an_ancestor_without_a_title() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => '',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);

		$items = block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs( $child_id );

		$this->assertCount( 1, $items );
		$this->assertSame( '(no title)', $items[0]['label'] );
	}

	/**
	 * Tests the breadcrumbs of the ancestors of a term, from the top to the parent.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_term_ancestors_items
	 */
	public function test_get_term_ancestors_items() {
		$grandparent_id = self::factory()->category->create( array( 'name' => 'Grandparent' ) );
		$parent_id      = self::factory()->category->create(
			array(
				'name'   => 'Parent',
				'parent' => $grandparent_id,
			)
		);
		$child_id       = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent_id,
			)
		);

		$this->assertSame(
			array(
				array(
					'label' => 'Grandparent',
					'url'   => get_term_link( $grandparent_id, 'category' ),
				),
				array(
					'label' => 'Parent',
					'url'   => get_term_link( $parent_id, 'category' ),
				),
			),
			block_core_breadcrumbs_get_term_ancestors_items( $child_id, 'category' )
		);
	}

	/**
	 * Tests that a term without ancestors has no items.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_term_ancestors_items
	 */
	public function test_get_term_ancestors_items_without_ancestors() {
		$term_id = self::factory()->category->create();

		$this->assertSame( array(), block_core_breadcrumbs_get_term_ancestors_items( $term_id, 'category' ) );
	}

	/**
	 * Tests that a taxonomy that is not hierarchical has no ancestors.
	 *
	 * @ticket 66590
	 *
	 * @covers ::block_core_breadcrumbs_get_term_ancestors_items
	 */
	public function test_get_term_ancestors_items_of_a_taxonomy_that_is_not_hierarchical() {
		$tag_id = self::factory()->tag->create();

		$this->assertSame( array(), block_core_breadcrumbs_get_term_ancestors_items( $tag_id, 'post_tag' ) );
	}
}
