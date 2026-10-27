<?php

/**
 * @group themes
 */
class Tests_Theme_Support extends WP_UnitTestCase {

	/**
	 * Theme features registered before the test ran.
	 *
	 * @var array
	 */
	private $orig_theme_features;

	/**
	 * Nav menu locations registered before the test ran.
	 *
	 * @var array
	 */
	private $orig_registered_nav_menus;

	/**
	 * Editor stylesheets registered before the test ran.
	 *
	 * @var array
	 */
	private $orig_editor_styles;

	public function set_up() {
		parent::set_up();

		$this->orig_theme_features       = $GLOBALS['_wp_theme_features'];
		$this->orig_registered_nav_menus = $GLOBALS['_wp_registered_nav_menus'] ?? array();
		$this->orig_editor_styles        = $GLOBALS['editor_styles'] ?? array();

		// Start without support or stylesheets from the bootstrapped theme.
		$GLOBALS['_wp_theme_features'] = array();
		$GLOBALS['editor_styles']      = array();
	}

	public function tear_down() {
		try {
			parent::tear_down();
		} finally {
			// Restore after the parent removes HTML5 theme support.
			$GLOBALS['_wp_theme_features']       = $this->orig_theme_features;
			$GLOBALS['_wp_registered_nav_menus'] = $this->orig_registered_nav_menus;
			$GLOBALS['editor_styles']            = $this->orig_editor_styles;
		}
	}

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
	 * Ensure adding classic editor styles does not add block editor styles support.
	 *
	 * @ticket 60826
	 *
	 * @covers ::add_editor_style
	 */
	public function test_adding_classic_editor_styles_does_not_add_block_editor_styles_support() {
		add_editor_style( 'style.css' );

		$this->assertTrue( current_theme_supports( 'editor-style' ), 'The theme should support classic editor styles.' );
		$this->assertFalse( current_theme_supports( 'editor-styles' ), 'The theme should not support block editor styles.' );
	}

	/**
	 * Ensure adding block editor styles support does not add classic editor styles support.
	 *
	 * @ticket 60826
	 *
	 * @covers ::add_theme_support
	 */
	public function test_adding_block_editor_styles_support_does_not_add_classic_editor_styles() {
		add_theme_support( 'editor-styles' );

		$this->assertTrue( current_theme_supports( 'editor-styles' ), 'The theme should support block editor styles.' );
		$this->assertFalse( current_theme_supports( 'editor-style' ), 'The theme should not support classic editor styles.' );
		$this->assertSame( array(), get_block_editor_theme_styles(), 'Block editor styles support should only load stylesheets if they are defined.' );
	}

	/**
	 * Ensure removing classic editor styles does not remove block editor styles support.
	 *
	 * @ticket 60826
	 *
	 * @covers ::remove_editor_styles
	 */
	public function test_removing_classic_editor_styles_does_not_remove_block_editor_styles_support() {
		add_editor_style( 'style.css' );
		add_theme_support( 'editor-styles' );

		$this->assertTrue( remove_editor_styles(), 'Removing classic editor styles should succeed.' );
		$this->assertFalse( current_theme_supports( 'editor-style' ), 'The theme should no longer support classic editor styles.' );
		$this->assertTrue( current_theme_supports( 'editor-styles' ), 'The theme should still support block editor styles.' );
	}

	/**
	 * Ensure removing block editor styles support does not remove classic editor styles.
	 *
	 * @ticket 60826
	 *
	 * @covers ::remove_theme_support
	 */
	public function test_removing_block_editor_styles_support_does_not_remove_classic_editor_styles() {
		add_editor_style( 'style.css' );
		add_theme_support( 'editor-styles' );

		$this->assertTrue( remove_theme_support( 'editor-styles' ), 'Removing block editor styles support should succeed.' );
		$this->assertFalse( current_theme_supports( 'editor-styles' ), 'The theme should no longer support block editor styles.' );
		$this->assertTrue( current_theme_supports( 'editor-style' ), 'The theme should still support classic editor styles.' );
	}

	/**
	 * Ensure adding block editor styles support loads registered stylesheets in the block editor.
	 *
	 * @ticket 60826
	 *
	 * @covers ::get_block_editor_theme_styles
	 */
	public function test_adding_block_editor_styles_support_loads_stylesheets_in_block_editor() {
		add_editor_style( 'style.css' );
		$this->assertSame( array(), get_block_editor_theme_styles(), 'Stylesheets should not load in the block editor without block editor styles support.' );

		add_theme_support( 'editor-styles' );
		$this->assertCount( 1, get_block_editor_theme_styles(), 'Stylesheets should load in the block editor with block editor styles support.' );
	}

	/**
	 * Ensure classic editor styles cannot be removed with remove_theme_support().
	 *
	 * @ticket 60826
	 *
	 * @covers ::remove_theme_support
	 */
	public function test_classic_editor_styles_cannot_be_removed_with_remove_theme_support() {
		add_editor_style( 'style.css' );

		$this->assertFalse( remove_theme_support( 'editor-style' ), 'Removing classic editor styles with remove_theme_support() should fail.' );
		$this->assertTrue( current_theme_supports( 'editor-style' ), 'The theme should still support classic editor styles.' );
	}

	/**
	 * Ensure classic editor styles can be removed with remove_editor_styles().
	 *
	 * @ticket 60826
	 *
	 * @covers ::remove_editor_styles
	 */
	public function test_classic_editor_styles_can_be_removed_with_remove_editor_styles() {
		add_editor_style( 'style.css' );

		$this->assertTrue( remove_editor_styles(), 'Removing classic editor styles with remove_editor_styles() should succeed.' );
		$this->assertFalse( current_theme_supports( 'editor-style' ), 'The theme should no longer support classic editor styles.' );
	}
}
