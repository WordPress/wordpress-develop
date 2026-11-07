<?php

/**
 * Tests for the `get_taxonomies_for_attachments()` function.
 *
 * @group media
 * @group taxonomy
 *
 * @covers ::get_taxonomies_for_attachments
 */
class Tests_Media_GetTaxonomiesForAttachments extends WP_UnitTestCase {

	const TAX_ATTACHMENT = 'wptests_tax_att';
	const TAX_SUBTYPE    = 'wptests_tax_subtype';
	const TAX_NON_ATTACH = 'wptests_tax_post';

	public function set_up() {
		parent::set_up();
		register_taxonomy( self::TAX_ATTACHMENT, 'attachment' );
		register_taxonomy( self::TAX_SUBTYPE, 'attachment:audio' );
		register_taxonomy( self::TAX_NON_ATTACH, 'post' );
	}

	public function tear_down() {
		unregister_taxonomy( self::TAX_ATTACHMENT );
		unregister_taxonomy( self::TAX_SUBTYPE );
		unregister_taxonomy( self::TAX_NON_ATTACH );
		parent::tear_down();
	}

	/**
	 * Tests returning taxonomy names by default.
	 *
	 * @ticket 66622
	 */
	public function test_get_taxonomies_for_attachments_default_names() {
		$taxonomies = get_taxonomies_for_attachments();

		$this->assertContains( self::TAX_ATTACHMENT, $taxonomies );
		$this->assertContains( self::TAX_SUBTYPE, $taxonomies );
		$this->assertNotContains( self::TAX_NON_ATTACH, $taxonomies );
	}

	/**
	 * Tests returning taxonomy objects when output is 'objects'.
	 *
	 * @ticket 66622
	 */
	public function test_get_taxonomies_for_attachments_objects_output() {
		$taxonomies = get_taxonomies_for_attachments( 'objects' );

		$this->assertArrayHasKey( self::TAX_ATTACHMENT, $taxonomies );
		$this->assertInstanceOf( 'WP_Taxonomy', $taxonomies[ self::TAX_ATTACHMENT ] );
		$this->assertSame( self::TAX_ATTACHMENT, $taxonomies[ self::TAX_ATTACHMENT ]->name );

		$this->assertArrayHasKey( self::TAX_SUBTYPE, $taxonomies );
		$this->assertInstanceOf( 'WP_Taxonomy', $taxonomies[ self::TAX_SUBTYPE ] );

		$this->assertArrayNotHasKey( self::TAX_NON_ATTACH, $taxonomies );
	}
}
