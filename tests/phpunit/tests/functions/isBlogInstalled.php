<?php

/**
 * @since 6.1.0
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
	public function test_is_blog_installed_should_return_true_by_default() {
		$this->assertTrue( is_blog_installed() );
	}

	/**
	 * Tests whether if is_blog_installed is set true in the cache.
	 *
	 * @ticket 54754
	 */
	public function test_is_blog_installed_cache() {
		/*
		 * Prime the cache with false.
		 * `is_blog_installed()` should still return true as it then looks at the value in the options table.
		 */
		wp_cache_set( 'is_blog_installed', false );
		$this->assertTrue( is_blog_installed(), 'Did not respect the cached value of false' );

		// Skip an early return when `siteurl` is set.
		$options            = wp_cache_get( 'alloptions', 'options' );
		$options['siteurl'] = '';
		wp_cache_set( 'alloptions', $options, 'options' );
		// `is_blog_installed()` should still return `true` as it then looks at the value in options table.
		$this->assertTrue( is_blog_installed() );
	}

	/**
	 * Tests whether if is_blog_installed is set false in the cache.
	 *
	 * @ticket 54754
	 */
	public function test_is_blog_installed_should_return_false_when_a_table_does_not_exist() {
		//set cache
		wp_cache_set( 'is_blog_installed', false );
		// set siteurl to empty
		$options            = wp_cache_get( 'alloptions', 'options' );
		$options['siteurl'] = '';
		wp_cache_set( 'alloptions', $options, 'options' );
		// brake the checking of the tables by setting a faulty table name for users table
		define( 'CUSTOM_USER_TABLE', 'not_a_table' );

		/**
		 * Now we have a broken blog set the cache to true
		 * And check the function  shortcut works.
		 */
		wp_cache_set( 'is_blog_installed', true );
		$this->assertTrue( is_blog_installed(), 'Did not respect the cached value of true' );
		wp_cache_set( 'is_blog_installed', '' );

		// the table checking throws an expected exception if a table is not found
		$this->expectException( 'WPDieException' );
		// and we get a false result
		$this->assertFalse( is_blog_installed(), 'Did not find a blog return false' );
	}

}
