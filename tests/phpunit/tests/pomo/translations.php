<?php

/**
 * @group pomo
 */
class Tests_POMO_Translations extends WP_UnitTestCase {

	public function test_add_entry() {
		$entry  = new Translation_Entry( array( 'singular' => 'baba' ) );
		$entry2 = new Translation_Entry( array( 'singular' => 'dyado' ) );
		$empty  = new Translation_Entry();
		$po     = new Translations();
		$po->add_entry( $entry );
		$this->assertSame( array( $entry->key() => $entry ), $po->entries );
		// Add the same entry more than once.
		// We do not need to test proper key generation here, see test_key().
		$po->add_entry( $entry );
		$po->add_entry( $entry );
		$this->assertSame( array( $entry->key() => $entry ), $po->entries );
		$po->add_entry( $entry2 );
		$this->assertSame(
			array(
				$entry->key()  => $entry,
				$entry2->key() => $entry2,
			),
			$po->entries
		);
		// Add empty entry.
		$this->assertFalse( $po->add_entry( $empty ) );
		$this->assertSame(
			array(
				$entry->key()  => $entry,
				$entry2->key() => $entry2,
			),
			$po->entries
		);

		// Give add_entry() the arguments and let it create the entry itself.
		$po = new Translations();
		$po->add_entry( array( 'singular' => 'baba' ) );
		$entries = array_values( $po->entries );
		$this->assertSame( $entry->key(), $entries[0]->key() );
	}

	public function test_translate() {
		$entry1 = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'translations' => array( 'babax' ),
			)
		);
		$entry2 = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'translations' => array( 'babay' ),
				'context'      => 'x',
			)
		);
		$domain = new Translations();
		$domain->add_entry( $entry1 );
		$domain->add_entry( $entry2 );
		$this->assertSame( 'babax', $domain->translate( 'baba' ) );
		$this->assertSame( 'babay', $domain->translate( 'baba', 'x' ) );
		$this->assertSame( 'baba', $domain->translate( 'baba', 'y' ) );
		$this->assertSame( 'babaz', $domain->translate( 'babaz' ) );
	}

	public function test_translate_plural() {
		$entry_incomplete = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'plural'       => 'babas',
				'translations' => array( 'babax' ),
			)
		);
		$entry_toomany    = new Translation_Entry(
			array(
				'singular'     => 'wink',
				'plural'       => 'winks',
				'translations' => array( 'winki', 'winka', 'winko' ),
			)
		);
		$entry_2          = new Translation_Entry(
			array(
				'singular'     => 'dyado',
				'plural'       => 'dyados',
				'translations' => array( 'dyadox', 'dyadoy' ),
			)
		);
		$domain           = new Translations();
		$domain->add_entry( $entry_incomplete );
		$domain->add_entry( $entry_toomany );
		$domain->add_entry( $entry_2 );
		$this->assertSame( 'other', $domain->translate_plural( 'other', 'others', 1 ) );
		$this->assertSame( 'others', $domain->translate_plural( 'other', 'others', 111 ) );
		// Too few translations + cont logic.
		$this->assertSame( 'babas', $domain->translate_plural( 'baba', 'babas', 2 ) );
		$this->assertSame( 'babas', $domain->translate_plural( 'baba', 'babas', 0 ) );
		$this->assertSame( 'babas', $domain->translate_plural( 'baba', 'babas', -1 ) );
		$this->assertSame( 'babas', $domain->translate_plural( 'baba', 'babas', 999 ) );
		// Proper.
		$this->assertSame( 'dyadox', $domain->translate_plural( 'dyado', 'dyados', 1 ) );
		$this->assertSame( 'dyadoy', $domain->translate_plural( 'dyado', 'dyados', 0 ) );
		$this->assertSame( 'dyadoy', $domain->translate_plural( 'dyado', 'dyados', 18881 ) );
		$this->assertSame( 'dyadoy', $domain->translate_plural( 'dyado', 'dyados', -18881 ) );
	}

	public function test_digit_and_merge() {
		$entry_digit_1 = new Translation_Entry(
			array(
				'singular'     => 1,
				'translations' => array( '1' ),
			)
		);
		$entry_digit_2 = new Translation_Entry(
			array(
				'singular'     => 2,
				'translations' => array( '2' ),
			)
		);
		$domain        = new Translations();
		$domain->add_entry( $entry_digit_1 );
		$domain->add_entry( $entry_digit_2 );
		$dummy_translation = new Translations;
		$this->assertSame( '1', $domain->translate( '1' ) );
		$domain->merge_with( $dummy_translation );
		$this->assertSame( '1', $domain->translate( '1' ) );
	}

	/**
	 * @ticket 55941
	 */
	public function test_translate_falsy_key() {
		$entry_empty = new Translation_Entry(
			array(
				'singular'     => '',
				'translations' => array(
					'',
				),
			)
		);
		$entry_zero  = new Translation_Entry(
			array(
				'singular'     => '0',
				'translations' => array(
					'0',
				),
			)
		);
		$po          = new Translations();
		$po->add_entry( $entry_empty );
		$po->add_entry( $entry_zero );

		$this->assertSame( '', $po->translate( '' ) );
		$this->assertSame( '0', $po->translate( '0' ) );
	}
}
