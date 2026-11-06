<?php

/**
 * Tests for the Translations::add_entry_or_merge() method.
 *
 * @group pomo
 *
 * @covers Translations::add_entry_or_merge
 */
class Tests_POMO_TranslationsAddEntryOrMerge extends WP_UnitTestCase {

	/**
	 * Tests that an entry is added.
	 *
	 * @ticket 66511
	 */
	public function test_should_add_an_entry() {
		$entry        = new Translation_Entry( array( 'singular' => 'baba' ) );
		$translations = new Translations();

		$this->assertTrue( $translations->add_entry_or_merge( $entry ) );
		$this->assertSame( array( 'baba' => $entry ), $translations->entries );
	}

	/**
	 * Tests that an array is converted to an entry.
	 *
	 * @ticket 66511
	 */
	public function test_should_convert_an_array_to_an_entry() {
		$translations = new Translations();

		$this->assertTrue(
			$translations->add_entry_or_merge(
				array(
					'singular'     => 'baba',
					'translations' => array( 'dyado' ),
				)
			)
		);

		$this->assertInstanceOf( Translation_Entry::class, $translations->entries['baba'] );
		$this->assertSame( array( 'dyado' ), $translations->entries['baba']->translations );
	}

	/**
	 * Tests that the context is part of the key.
	 *
	 * @ticket 66511
	 */
	public function test_should_use_the_context_in_the_key() {
		$translations = new Translations();

		$translations->add_entry_or_merge(
			array(
				'singular' => 'baba',
				'context'  => 'family',
			)
		);
		$translations->add_entry_or_merge( array( 'singular' => 'baba' ) );

		$this->assertSame( array( "family\4baba", 'baba' ), array_keys( $translations->entries ) );
	}

	/**
	 * Tests that an entry that has the same key is merged with the existing one, and does not replace it.
	 *
	 * @ticket 66511
	 */
	public function test_should_merge_an_entry_with_the_same_key() {
		$first  = new Translation_Entry(
			array(
				'singular'   => 'baba',
				'references' => array( 'a.php:1' ),
				'flags'      => array( 'php-format' ),
			)
		);
		$second = new Translation_Entry(
			array(
				'singular'   => 'baba',
				'references' => array( 'b.php:2' ),
				'flags'      => array( 'fuzzy' ),
			)
		);

		$translations = new Translations();
		$translations->add_entry_or_merge( $first );

		$this->assertTrue( $translations->add_entry_or_merge( $second ) );

		$this->assertCount( 1, $translations->entries, 'There should be one entry.' );
		$this->assertSame( $first, $translations->entries['baba'], 'The first entry should be kept.' );
		$this->assertSame( array( 'a.php:1', 'b.php:2' ), array_values( $first->references ), 'The references should be merged.' );
		$this->assertSame( array( 'php-format', 'fuzzy' ), array_values( $first->flags ), 'The flags should be merged.' );
	}

	/**
	 * Tests that an entry without a key is not added.
	 *
	 * @ticket 66511
	 */
	public function test_should_return_false_for_an_entry_without_a_key() {
		$translations = new Translations();

		$this->assertFalse( $translations->add_entry_or_merge( new Translation_Entry() ), 'An entry without a singular should not be added.' );
		$this->assertFalse( $translations->add_entry_or_merge( array( 'context' => 'only a context' ) ), 'An array without a singular should not be added.' );
		$this->assertSame( array(), $translations->entries );
	}
}
