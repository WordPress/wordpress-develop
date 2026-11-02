<?php
/**
 * Tests for get_taxonomies_for_attachments().
 *
 * @group media
 * @group taxonomy
 *
 * @covers ::get_taxonomies_for_attachments
 */
class Tests_Media_GetTaxonomiesForAttachments extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		unregister_taxonomy( 'wptests_tax' );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_taxonomy_registered_for_attachment() {
		register_taxonomy( 'wptests_tax', 'attachment' );

		$this->assertContains( 'wptests_tax', get_taxonomies_for_attachments( 'names' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_taxonomy_registered_for_attachment_subtype() {
		register_taxonomy( 'wptests_tax', 'attachment:image' );

		$this->assertContains( 'wptests_tax', get_taxonomies_for_attachments( 'names' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_taxonomy_objects() {
		register_taxonomy( 'wptests_tax', 'attachment' );

		$found = get_taxonomies_for_attachments( 'objects' );

		$this->assertArrayHasKey( 'wptests_tax', $found );
		$this->assertInstanceOf( 'WP_Taxonomy', $found['wptests_tax'] );
	}
}
