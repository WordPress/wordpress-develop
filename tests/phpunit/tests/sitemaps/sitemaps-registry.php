<?php

/**
 * @group sitemaps
 */
class Test_WP_Sitemaps_Registry extends WP_UnitTestCase {
	public function test_add_sitemap() {
		$provider = new WP_Sitemaps_Test_Provider();
		$registry = new WP_Sitemaps_Registry();

		$actual   = $registry->add_sitemap( 'foo', $provider );
		$sitemaps = $registry->get_sitemaps();

		$this->assertTrue( $actual );
		$this->assertCount( 1, $sitemaps );
		$this->assertSame( $sitemaps['foo'], $provider, 'Can not confirm sitemap registration is working.' );
	}

	public function test_add_sitemap_prevent_duplicates() {
		$provider1 = new WP_Sitemaps_Test_Provider();
		$provider2 = new WP_Sitemaps_Test_Provider();
		$registry = new WP_Sitemaps_Registry();

		$actual1  = $registry->add_sitemap( 'foo', $provider1 );
		$actual2  = $registry->add_sitemap( 'foo', $provider2 );
		$sitemaps = $registry->get_sitemaps();

		$this->assertTrue( $actual1 );
		$this->assertFalse( $actual2 );
		$this->assertCount( 1, $sitemaps );
		$this->assertSame( $sitemaps['foo'], $provider1, 'Can not confirm sitemap registration is working.' );
	}

	public function test_add_sitemap_invalid_type() {
		$provider = null;
		$registry = new WP_Sitemaps_Registry();

		$actual   = $registry->add_sitemap( 'foo', $provider );
		$sitemaps = $registry->get_sitemaps();

		$this->assertFalse( $actual );
		$this->assertCount( 0, $sitemaps );
	}
}
