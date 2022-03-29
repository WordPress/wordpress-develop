<?php

/**
 * @group term
 * @group slashes
 * @ticket 21767
 */
class Tests_Term_Slashes extends WP_Ajax_UnitTestCase {
	protected static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$author_id );

		$this->slash_1 = 'String with 1 slash \\';
		$this->slash_2 = 'String with 2 slashes \\\\';
		$this->slash_3 = 'String with 3 slashes \\\\\\';
		$this->slash_4 = 'String with 4 slashes \\\\\\\\';
		$this->slash_5 = 'String with 5 slashes \\\\\\\\\\';
		$this->slash_6 = 'String with 6 slashes \\\\\\\\\\\\';
		$this->slash_7 = 'String with 7 slashes \\\\\\\\\\\\\\';
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_wp_insert_term() {
		$taxonomies = array(
			'category',
			'post_tag',
		);
		foreach ( $taxonomies as $taxonomy ) {
			$insert = wp_insert_term(
				$this->slash_1,
				$taxonomy,
				array(
					'slug'        => 'slash_test_1_' . $taxonomy,
					'description' => $this->slash_3,
				)
			);
			$term   = get_term( $insert['term_id'], $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_1 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_3 ), $term->description );

			$insert = wp_insert_term(
				$this->slash_3,
				$taxonomy,
				array(
					'slug'        => 'slash_test_2_' . $taxonomy,
					'description' => $this->slash_5,
				)
			);
			$term   = get_term( $insert['term_id'], $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_3 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_5 ), $term->description );

			$insert = wp_insert_term(
				$this->slash_2,
				$taxonomy,
				array(
					'slug'        => 'slash_test_3_' . $taxonomy,
					'description' => $this->slash_4,
				)
			);
			$term   = get_term( $insert['term_id'], $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_2 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_4 ), $term->description );
		}
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_wp_update_term() {
		$taxonomies = array(
			'category',
			'post_tag',
		);
		foreach ( $taxonomies as $taxonomy ) {
			$term_id = self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
				)
			);

			$update = wp_update_term(
				$term_id,
				$taxonomy,
				array(
					'name'        => $this->slash_1,
					'description' => $this->slash_3,
				)
			);

			$term = get_term( $term_id, $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_1 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_3 ), $term->description );

			$update = wp_update_term(
				$term_id,
				$taxonomy,
				array(
					'name'        => $this->slash_3,
					'description' => $this->slash_5,
				)
			);
			$term   = get_term( $term_id, $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_3 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_5 ), $term->description );

			$update = wp_update_term(
				$term_id,
				$taxonomy,
				array(
					'name'        => $this->slash_2,
					'description' => $this->slash_4,
				)
			);
			$term   = get_term( $term_id, $taxonomy );
			$this->assertSame( wp_unslash( $this->slash_2 ), $term->name );
			$this->assertSame( wp_unslash( $this->slash_4 ), $term->description );
		}
	}
}
