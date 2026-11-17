<?php
/**
 * Tests for get_term_to_edit().
 *
 * @group taxonomy
 *
 * @covers ::get_term_to_edit
 */
class Tests_Term_GetTermToEdit extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_term_sanitized_for_edit_context() {
		$term_id = self::factory()->category->create(
			array(
				'name' => 'News',
			)
		);

		add_filter(
			'edit_term_name',
			static function ( $value ) {
				return $value . '|edit';
			}
		);

		$term = get_term_to_edit( $term_id, 'category' );

		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( 'News|edit', $term->name );
	}
}
