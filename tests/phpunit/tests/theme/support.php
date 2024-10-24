<?php

/**
 * @group themes
 */
class Tests_Theme_Support extends WP_UnitTestCase {

	public function test_the_basics() {
		add_theme_support( 'automatic-feed-links' );
		$this->assertTrue( current_theme_supports( 'automatic-feed-links' ) );
		remove_theme_support( 'automatic-feed-links' );
		$this->assertFalse( current_theme_supports( 'automatic-feed-links' ) );
		add_theme_support( 'automatic-feed-links' );
		$this->assertTrue( current_theme_supports( 'automatic-feed-links' ) );
	}

	public function test_admin_bar() {
		add_theme_support( 'admin-bar' );
		$this->assertTrue( current_theme_supports( 'admin-bar' ) );
		remove_theme_support( 'admin-bar' );
		$this->assertFalse( current_theme_supports( 'admin-bar' ) );
		add_theme_support( 'admin-bar' );
		$this->assertTrue( current_theme_supports( 'admin-bar' ) );

		add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );
		$this->assertTrue( current_theme_supports( 'admin-bar' ) );

		$this->assertSame(
			array( 0 => array( 'callback' => '__return_false' ) ),
			get_theme_support( 'admin-bar' )
		);
		remove_theme_support( 'admin-bar' );
		$this->assertFalse( current_theme_supports( 'admin-bar' ) );
		$this->assertFalse( get_theme_support( 'admin-bar' ) );
	}

	public function test_post_thumbnails() {
		add_theme_support( 'post-thumbnails' );
		$this->assertTrue( current_theme_supports( 'post-thumbnails' ) );
		remove_theme_support( 'post-thumbnails' );
		$this->assertFalse( current_theme_supports( 'post-thumbnails' ) );
		add_theme_support( 'post-thumbnails' );
		$this->assertTrue( current_theme_supports( 'post-thumbnails' ) );
	}

	public function test_post_thumbnails_flat_array_of_post_types() {
		remove_theme_support( 'post-thumbnails' );

		add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );
		$this->assertTrue( current_theme_supports( 'post-thumbnails', 'post' ) );
		$this->assertFalse( current_theme_supports( 'post-thumbnails', 'book' ) );
		remove_theme_support( 'post-thumbnails' );
		$this->assertFalse( current_theme_supports( 'post-thumbnails' ) );
	}

	/**
	 * @ticket 22080
	 */
	public function test_post_thumbnails_mixed_args() {
		add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );
		add_theme_support( 'post-thumbnails', array( 'page' ) );
		$this->assertTrue( current_theme_supports( 'post-thumbnails', 'post' ) );
		$this->assertFalse( current_theme_supports( 'post-thumbnails', 'book' ) );
		$this->assertSame(
			array( 0 => array( 'post', 'page' ) ),
			get_theme_support( 'post-thumbnails' )
		);

		add_theme_support( 'post-thumbnails' );
		$this->assertTrue( current_theme_supports( 'post-thumbnails', 'any-type' ) );

		// Reset post-thumbnails theme support.
		remove_theme_support( 'post-thumbnails' );
		$this->assertFalse( current_theme_supports( 'post-thumbnails' ) );
	}

	/**
	 * @ticket 24932
	 *
	 * @expectedIncorrectUsage add_theme_support( 'html5' )
	 */
	public function test_supports_html5() {
		remove_theme_support( 'html5' );
		$this->assertFalse( current_theme_supports( 'html5' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'comment-form' ) );

		/*
		 * If the second parameter is not specified, it should throw a _doing_it_wrong() notice
		 * and fall back to `array( 'comment-list', 'comment-form', 'search-form' )` for back-compat.
		 */
		$this->assertNotFalse( add_theme_support( 'html5' ) );
		$this->assertTrue( current_theme_supports( 'html5' ) );
		$this->assertTrue( current_theme_supports( 'html5', 'comment-form' ) );
		$this->assertTrue( current_theme_supports( 'html5', 'comment-list' ) );
		$this->assertTrue( current_theme_supports( 'html5', 'search-form' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'something-else' ) );
	}

	/**
	 * @ticket 24932
	 *
	 * @expectedIncorrectUsage add_theme_support( 'html5' )
	 */
	public function test_supports_html5_subset() {
		remove_theme_support( 'html5' );
		$this->assertFalse( current_theme_supports( 'html5' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'comment-form' ) );

		// The second parameter should be an array.
		$this->assertFalse( add_theme_support( 'html5', 'comment-form' ) );
		$this->assertNotFalse( add_theme_support( 'html5', array( 'comment-form' ) ) );
		$this->assertTrue( current_theme_supports( 'html5', 'comment-form' ) );

		// This will return true, which might help a plugin author decide what markup to serve,
		// but core should never check for it.
		$this->assertTrue( current_theme_supports( 'html5' ) );

		// It appends, rather than replaces.
		$this->assertFalse( current_theme_supports( 'html5', 'comment-list' ) );
		$this->assertNotFalse( add_theme_support( 'html5', array( 'comment-list' ) ) );
		$this->assertTrue( current_theme_supports( 'html5', 'comment-form' ) );
		$this->assertTrue( current_theme_supports( 'html5', 'comment-list' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'search-form' ) );

		// Removal is all or nothing.
		$this->assertTrue( remove_theme_support( 'html5' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'comment-list' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'comment-form' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'search-form' ) );
	}

	/**
	 * @ticket 24932
	 *
	 * @expectedIncorrectUsage add_theme_support( 'html5' )
	 */
	public function test_supports_html5_invalid() {
		remove_theme_support( 'html5' );
		$this->assertFalse( add_theme_support( 'html5', 'comment-form' ) );
		$this->assertFalse( current_theme_supports( 'html5', 'comment-form' ) );
		$this->assertFalse( current_theme_supports( 'html5' ) );
	}

	/**
	 * @ticket 51390
	 *
	 * @expectedIncorrectUsage add_theme_support( 'post-formats' )
	 */
	public function test_supports_post_formats_doing_it_wrong() {
		// The second parameter should be an array.
		$this->assertFalse( add_theme_support( 'post-formats' ) );
	}

	public function supports_foobar( $yesno, $args, $feature ) {
		if ( $args[0] === $feature[0] ) {
			return true;
		}
		return false;
	}

	/**
	 * @ticket 11611
	 */
	public function test_plugin_hook() {
		$this->assertFalse( current_theme_supports( 'foobar' ) );
		add_theme_support( 'foobar' );
		$this->assertTrue( current_theme_supports( 'foobar' ) );

		add_filter( 'current_theme_supports-foobar', array( $this, 'supports_foobar' ), 10, 3 );

		add_theme_support( 'foobar', 'bar' );
		$this->assertFalse( current_theme_supports( 'foobar', 'foo' ) );
		$this->assertTrue( current_theme_supports( 'foobar', 'bar' ) );

		remove_theme_support( 'foobar' );
		$this->assertFalse( current_theme_supports( 'foobar', 'bar' ) );
	}

	/**
	 * @ticket 55219
	 */
	public function test_plugin_hook_with_no_args() {
		add_theme_support( 'foobar' );

		add_filter( 'current_theme_supports-foobar', '__return_false' );

		$this->assertFalse( current_theme_supports( 'foobar' ) );
	}

	/**
	 * @ticket 26900
	 */
	public function test_supports_menus() {
		// Start fresh.
		foreach ( get_registered_nav_menus() as $location => $desc ) {
			unregister_nav_menu( $location );
		}
		_remove_theme_support( 'menus' );
		$this->assertFalse( current_theme_supports( 'menus' ) );

		// Registering a nav menu automatically adds support.
		register_nav_menu( 'primary', 'Primary Navigation' );
		register_nav_menu( 'secondary', 'Secondary Navigation' );
		$this->assertTrue( current_theme_supports( 'menus' ) );

		// Support added internally, can't be removed.
		remove_theme_support( 'menus' );
		$this->assertTrue( current_theme_supports( 'menus' ) );

		// Still supports because of secondary.
		unregister_nav_menu( 'primary' );
		$this->assertTrue( current_theme_supports( 'menus' ) );

		// No longer support because we have no menus.
		unregister_nav_menu( 'secondary' );
		$this->assertEmpty( get_registered_nav_menus() );
		$this->assertFalse( current_theme_supports( 'menus' ) );
	}

	/**
	 * @ticket 45125
	 */
	public function test_responsive_embeds() {
		add_theme_support( 'responsive-embeds' );
		$this->assertTrue( current_theme_supports( 'responsive-embeds' ) );
		remove_theme_support( 'responsive-embeds' );
		$this->assertFalse( current_theme_supports( 'responsive-embeds' ) );
		add_theme_support( 'responsive-embeds' );
		$this->assertTrue( current_theme_supports( 'responsive-embeds' ) );
	}

	/**
	 * @ticket 60826
	 */
	public function test_editor_styles() {
		add_theme_support( 'editor-styles' );
		$this->assertTrue( current_theme_supports( 'editor-styles' ) );

		remove_theme_support( 'editor-styles' );
		$this->assertTrue( current_theme_supports( 'editor-styles' ) );

		remove_editor_styles();
		$this->assertFalse( current_theme_supports( 'editor-styles' ) );

		add_editor_style();
		$this->assertTrue( current_theme_supports( 'editor-styles' ) );
	}
}
