<?php

/**
 * Tests for the Translations::merge_originals_with() method.
 *
 * @group pomo
 *
 * @covers Translations::merge_originals_with
 */
class Tests_POMO_TranslationsMergeOriginalsWith extends WP_UnitTestCase {

	/**
	 * Tests that the entries that do not exist are added.
	 *
	 * @ticket 66511
	 */
	public function test_should_add_the_entries_that_do_not_exist() {
		$first  = new Translations();
		$second = new Translations();

		$first->add_entry( new Translation_Entry( array( 'singular' => 'one' ) ) );
		$second->add_entry( new Translation_Entry( array( 'singular' => 'two' ) ) );

		$first->merge_originals_with( $second );

		$this->assertSame( array( 'one', 'two' ), array_keys( $first->entries ) );
		$this->assertSame( array( 'two' ), array_keys( $second->entries ), 'The other object should not change.' );
	}

	/**
	 * Tests that the entries that exist are merged and keep their translations.
	 *
	 * @ticket 66511
	 */
	public function test_should_merge_the_entries_that_exist_and_keep_their_translations() {
		$first  = new Translations();
		$second = new Translations();

		$first->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'one',
					'translations' => array( 'first translation' ),
					'references'   => array( 'a.php:1' ),
				)
			)
		);
		$second->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'one',
					'translations' => array( 'second translation' ),
					'references'   => array( 'b.php:2' ),
				)
			)
		);

		$first->merge_originals_with( $second );

		$this->assertCount( 1, $first->entries, 'There should be one entry.' );
		$this->assertSame( array( 'first translation' ), $first->entries['one']->translations, 'The translation of the first object should be kept.' );
		$this->assertSame( array( 'a.php:1', 'b.php:2' ), array_values( $first->entries['one']->references ), 'The references should be merged.' );
	}

	/**
	 * Tests that the entries with a different context are not merged.
	 *
	 * @ticket 66511
	 */
	public function test_should_not_merge_the_entries_with_a_different_context() {
		$first  = new Translations();
		$second = new Translations();

		$first->add_entry( new Translation_Entry( array( 'singular' => 'one' ) ) );
		$second->add_entry(
			new Translation_Entry(
				array(
					'singular' => 'one',
					'context'  => 'other',
				)
			)
		);

		$first->merge_originals_with( $second );

		$this->assertSame( array( 'one', "other\4one" ), array_keys( $first->entries ) );
	}

	/**
	 * Tests that merging with an empty object does not change the entries.
	 *
	 * @ticket 66511
	 */
	public function test_should_not_change_the_entries_when_the_other_object_is_empty() {
		$first = new Translations();
		$first->add_entry( new Translation_Entry( array( 'singular' => 'one' ) ) );
		$empty = new Translations();

		$first->merge_originals_with( $empty );

		$this->assertSame( array( 'one' ), array_keys( $first->entries ) );
	}

	/**
	 * Tests that, unlike merge_with(), the entries that exist are not replaced.
	 *
	 * @ticket 66511
	 */
	public function test_should_not_replace_the_entries_that_exist() {
		$original    = new Translation_Entry(
			array(
				'singular'     => 'one',
				'translations' => array( 'original' ),
			)
		);
		$replacement = new Translation_Entry(
			array(
				'singular'     => 'one',
				'translations' => array( 'replacement' ),
			)
		);

		$first  = new Translations();
		$second = new Translations();
		$first->add_entry( $original );
		$second->add_entry( $replacement );

		$first->merge_originals_with( $second );

		$this->assertSame( $original, $first->entries['one'] );
	}
}
