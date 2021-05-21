<?php

/**
 * @group pomo
 */
class Tests_POMO_TranslationEntry extends WP_UnitTestCase {

	function test_create_entry() {
		// No singular => empty object.
		$entry = new Translation_Entry();
		$this->assertNull( $entry->singular );
		$this->assertNull( $entry->plural );
		$this->assertFalse( $entry->is_plural );
		// args -> members.
		$entry = new Translation_Entry(
			array(
				'singular'     => 'baba',
				'plural'       => 'babas',
				'translations' => array( 'баба', 'баби' ),
				'references'   => 'should be array here',
				'flags'        => 'baba',
			)
		);
		$this->assertSame( 'baba', $entry->singular );
		$this->assertSame( 'babas', $entry->plural );
		$this->assertTrue( $entry->is_plural );
		$this->assertSame( array( 'баба', 'баби' ), $entry->translations );
		$this->assertSame( array(), $entry->references );
		$this->assertSame( array(), $entry->flags );
	}

	function test_key() {
		$entry_baba        = new Translation_Entry( array( 'singular' => 'baba' ) );
		$entry_dyado       = new Translation_Entry( array( 'singular' => 'dyado' ) );
		$entry_baba_ctxt   = new Translation_Entry(
			array(
				'singular' => 'baba',
				'context'  => 'x',
			)
		);
		$entry_baba_plural = new Translation_Entry(
			array(
				'singular' => 'baba',
				'plural'   => 'babas',
			)
		);
		$this->assertSame( $entry_baba->key(), $entry_baba_plural->key() );
		$this->assertNotEquals( $entry_baba->key(), $entry_baba_ctxt->key() );
		$this->assertNotEquals( $entry_baba_plural->key(), $entry_baba_ctxt->key() );
		$this->assertNotEquals( $entry_baba->key(), $entry_dyado->key() );
	}
}
