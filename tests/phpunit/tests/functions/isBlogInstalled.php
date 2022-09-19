<?php

/**
 * @since 6.0.0
 *
 * @group functions.php
 * @covers ::is_blog_installed
 */
class Tests_Functions_IsBlogInstalled extends WP_UnitTestCase {

	/**
	 * Tests the default return value of is_blog_installed().
	 * 
	 * @ticket 54754
	 */
	public function test_is_blog_installed() {
		$this->assertTrue( is_blog_installed() );
	}

	/**
	 * @ticket 54754
	 *
	 * the other routes to true
	 */
	public function test_is_blog_installed_cache() {
		// Prime the cache with true.
		wp_cache_set( 'is_blog_installed', true );
		$this->assertTrue( is_blog_installed(), 'cache set to true' );
		/*
		 * Prime the cache with false.
		 * `is_blog_installed()` should still return true as it then looks at the value in the options table.
		 */
		wp_cache_set( 'is_blog_installed', false );
		$this->assertTrue( is_blog_installed(), 'cache set to false' );

		// Skip an early return when `siteurl` is set.
		$options            = wp_cache_get( 'alloptions', 'options' );
		$options['siteurl'] = '';
		wp_cache_set( 'alloptions', $options, 'options' );
		// still true as it now look for the Tables
		$this->assertTrue( is_blog_installed() );
	}

	/**
	 * @ticket 54754
	 *
	 * the route to false
	 */
	public function test_is_blog_installed_broken() {
		//set cache
		wp_cache_set( 'is_blog_installed', false );
		// set siteurl to empty
		$options            = wp_cache_get( 'alloptions', 'options' );
		$options['siteurl'] = '';
		wp_cache_set( 'alloptions', $options, 'options' );
		// brake the checking of the tables by setting a faulty table name for users table
		define( 'CUSTOM_USER_TABLE', 'not_a_table' );
		// the table checking throws an expected exception if atable is not found
		$this->expectException( 'WPDieException' );
		// and we get a false result
		$this->assertFalse( is_blog_installed(), 'forced to return false' );
	}

}
