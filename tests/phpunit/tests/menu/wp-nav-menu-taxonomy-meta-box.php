<?php

/**
 * @group menu
 */
class Tests_Menu_WpNavMenuTaxonomyMetaBox extends WP_UnitTestCase {

	private $term_ids = array();

	private $meta_box_filter;

	private $added_default_filter = false;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		if ( false === has_filter( 'nav_menu_meta_box_object', '_wp_nav_menu_meta_box_object' ) ) {
			add_filter( 'nav_menu_meta_box_object', '_wp_nav_menu_meta_box_object' );
			$this->added_default_filter = true;
		}

		$this->meta_box_filter = static function ( $taxonomy ) {
			if ( 'category' === $taxonomy->name ) {
				$taxonomy->_default_query['number'] = -1;
			}

			return $taxonomy;
		};

		add_filter( 'nav_menu_meta_box_object', $this->meta_box_filter, 9 );
	}

	public function tear_down() {
		remove_filter( 'nav_menu_meta_box_object', $this->meta_box_filter, 9 );

		if ( $this->added_default_filter ) {
			remove_filter( 'nav_menu_meta_box_object', '_wp_nav_menu_meta_box_object' );
		}

		foreach ( $this->term_ids as $term_id ) {
			wp_delete_term( $term_id, 'category' );
		}

		parent::tear_down();
	}

	/**
	 * Tests that taxonomy menu meta boxes use query defaults set by the filter.
	 *
	 * @ticket 54135
	 *
	 * @covers ::_wp_nav_menu_meta_box_object
	 * @covers ::wp_nav_menu_item_taxonomy_meta_box
	 */
	public function test_taxonomy_meta_box_should_use_filtered_default_query() {
		$first_term_name = 'Ticket 54135 term 001';
		$last_term_name  = 'Ticket 54135 term 051';

		for ( $i = 1; $i <= 51; $i++ ) {
			$this->term_ids[] = self::factory()->term->create(
				array(
					'name'     => sprintf( 'Ticket 54135 term %03d', $i ),
					'taxonomy' => 'category',
				)
			);
		}

		$taxonomy          = get_taxonomy( 'category' );
		$had_default_query = isset( $taxonomy->_default_query );
		$default_query     = $taxonomy->_default_query ?? null;

		try {
			$taxonomy = apply_filters( 'nav_menu_meta_box_object', $taxonomy );
			$output   = get_echo(
				'wp_nav_menu_item_taxonomy_meta_box',
				array(
					null,
					array( 'args' => $taxonomy ),
				)
			);
		} finally {
			if ( $had_default_query ) {
				$taxonomy->_default_query = $default_query;
			} else {
				unset( $taxonomy->_default_query );
			}
		}

		$this->assertStringContainsString( $first_term_name, $output );
		$this->assertStringContainsString( $last_term_name, $output );
		$this->assertStringNotContainsString( 'add-menu-item-pagelinks', $output );
	}
}
