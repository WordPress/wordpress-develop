<?php

/**
 * @group pomo
 */
class Tests_POMO_NOOPTranslations extends WP_UnitTestCase {

	/**
	 * NOOP translations object.
	 *
	 * @var NOOP_Translations
	 */
	private $noop;

	/**
	 * Single translation entry object.
	 *
	 * @var Translation_Entry
	 */
	private $entry;

	/**
	 * Multi translation entries object.
	 *
	 * @var Translation_Entry
	 */
	private $plural_entry;

	public function set_up() {
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

	public function test_get_header() {
		$this->assertFalse( $this->noop->get_header( 'Content-Type' ) );
	}

	public function test_add_entry() {
		$this->noop->add_entry( $this->entry );
		$this->assertSame( array(), $this->noop->entries );
	}

	public function test_set_header() {
		$this->noop->set_header( 'header', 'value' );
		$this->assertSame( array(), $this->noop->headers );
	}

	public function test_translate_entry() {
		$this->noop->add_entry( $this->entry );
		$this->assertFalse( $this->noop->translate_entry( $this->entry ) );
	}

	public function test_translate() {
		$this->noop->add_entry( $this->entry );
		$this->assertSame( 'baba', $this->noop->translate( 'baba' ) );
	}

	public function test_plural() {
		$this->noop->add_entry( $this->plural_entry );
		$this->assertSame( 'dyado', $this->noop->translate_plural( 'dyado', 'dyados', 1 ) );
		$this->assertSame( 'dyados', $this->noop->translate_plural( 'dyado', 'dyados', 11 ) );
		$this->assertSame( 'dyados', $this->noop->translate_plural( 'dyado', 'dyados', 0 ) );
	}
}
