<?php

/**
 * @group pomo
 */
class Tests_POMO_NOOPTranslations extends WP_UnitTestCase {
	function set_up() {
		parent::set_up();
		$this->noop         = new NOOP_Translations;
		$this->entry        = new Translation_Entry( array( 'singular' => 'baba' ) );
		$this->plural_entry = new Translation_Entry(
			array(
				'singular'     => 'dyado',
				'plural'       => 'dyados',
				'translations' => array( 'dyadox', 'dyadoy' ),
			)
		);
	}

	/**
	 * @covers NOOP_Translations::get_header
	 */
	function test_get_header() {
		$this->assertFalse( $this->noop->get_header( 'Content-Type' ) );
	}

	/**
	 * @covers NOOP_Translations::add_entry
	 */
	function test_add_entry() {
		$this->noop->add_entry( $this->entry );
		$this->assertSame( array(), $this->noop->entries );
	}

	/**
	 * @covers NOOP_Translations::set_header
	 */
	function test_set_header() {
		$this->noop->set_header( 'header', 'value' );
		$this->assertSame( array(), $this->noop->headers );
	}

	/**
	 * @covers NOOP_Translations::translate_entry
	 */
	function test_translate_entry() {
		$this->noop->add_entry( $this->entry );
		$this->assertFalse( $this->noop->translate_entry( $this->entry ) );
	}

	/**
	 * @covers NOOP_Translations::translate
	 */
	function test_translate() {
		$this->noop->add_entry( $this->entry );
		$this->assertSame( 'baba', $this->noop->translate( 'baba' ) );
	}

	/**
	 * @covers NOOP_Translations::translate_plural
	 */
	function test_plural() {
		$this->noop->add_entry( $this->plural_entry );
		$this->assertSame( 'dyado', $this->noop->translate_plural( 'dyado', 'dyados', 1 ) );
		$this->assertSame( 'dyados', $this->noop->translate_plural( 'dyado', 'dyados', 11 ) );
		$this->assertSame( 'dyados', $this->noop->translate_plural( 'dyado', 'dyados', 0 ) );
	}
}
