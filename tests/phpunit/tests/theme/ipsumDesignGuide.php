<?php

/**
 * @group themes
 *
 * @covers ::ipsum_get_design_guide
 */
class Tests_Theme_IpsumDesignGuide extends WP_UnitTestCase {

	/**
	 * The block patterns registry before the current test replaced it.
	 *
	 * @var WP_Block_Patterns_Registry
	 */
	private $original_registry;

	/**
	 * The property that holds the block patterns registry instance.
	 *
	 * @var ReflectionProperty
	 */
	private $registry_instance_property;

	/**
	 * The active theme before the current test ran.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	public function set_up() {
		parent::set_up();

		$this->original_stylesheet = get_stylesheet();

		/*
		 * Reading a pattern's content removes its `filePath` from the registry for the rest of
		 * the process, and the design guide used to depend on it. Give each test a registry of
		 * its own, so earlier tests can't change what it starts with. Replace it before the skip
		 * below, which still runs `tear_down()`.
		 */
		$this->original_registry          = WP_Block_Patterns_Registry::get_instance();
		$this->registry_instance_property = new ReflectionProperty( 'WP_Block_Patterns_Registry', 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$this->registry_instance_property->setAccessible( true );
		}
		$this->registry_instance_property->setValue( null, new WP_Block_Patterns_Registry() );

		// Ipsum isn't the active theme in the test suite, so its functions.php isn't loaded.
		$abilities = WP_CONTENT_DIR . '/themes/ipsum/inc/abilities.php';
		if ( ! file_exists( $abilities ) ) {
			$this->markTestSkipped( 'The Ipsum theme is not installed.' );
		}
		require_once $abilities;
	}

	public function tear_down() {
		$this->registry_instance_property->setValue( null, $this->original_registry );
		if ( PHP_VERSION_ID < 80100 ) {
			$this->registry_instance_property->setAccessible( false );
		}

		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

		parent::tear_down();
	}

	/**
	 * Tests that building the guide twice returns every theme pattern both times.
	 *
	 * @ticket 66631
	 */
	public function test_returns_every_theme_pattern_when_called_twice() {
		$expected = $this->switch_theme_and_register_patterns( 'ipsum' );

		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs(), 'The first call should return every theme pattern.' );
		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs(), 'The second call should return every theme pattern.' );
	}

	/**
	 * Tests that every theme pattern is returned after something else reads all patterns.
	 *
	 * @ticket 66631
	 */
	public function test_returns_every_theme_pattern_after_all_patterns_are_read() {
		$expected = $this->switch_theme_and_register_patterns( 'ipsum' );

		WP_Block_Patterns_Registry::get_instance()->get_all_registered();

		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs() );
	}

	/**
	 * Tests that every theme pattern is returned after one pattern is read, as the Pattern block does.
	 *
	 * @ticket 66631
	 */
	public function test_returns_every_theme_pattern_after_one_pattern_is_read() {
		$expected = $this->switch_theme_and_register_patterns( 'ipsum' );

		WP_Block_Patterns_Registry::get_instance()->get_registered( $expected[0] );

		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs() );
	}

	/**
	 * Tests that patterns registered outside the theme are left out.
	 *
	 * @ticket 66631
	 */
	public function test_leaves_out_patterns_from_outside_the_theme() {
		$expected = $this->switch_theme_and_register_patterns( 'ipsum' );

		register_block_pattern(
			'tests/not-from-the-theme',
			array(
				'title'   => 'Not From the Theme',
				'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs() );
	}

	/**
	 * Tests that a child theme gets its own patterns and its parent's.
	 *
	 * @ticket 66631
	 */
	public function test_returns_child_and_parent_theme_patterns() {
		$expected = $this->switch_theme_and_register_patterns( 'block-theme-with-hooked-blocks-child' );

		$this->assertContains( 'block-theme-with-hooked-blocks-child/child-pattern', $expected, 'The child theme should have a pattern of its own.' );
		$this->assertContains( 'block-theme-with-hooked-blocks/footer-default', $expected, 'The parent theme should have a pattern.' );
		$this->assertSame( $expected, $this->get_design_guide_pattern_slugs(), 'The theme pattern slugs to not match.' );
	}

	/**
	 * Switches to a theme and registers its patterns, as the `init` hook does.
	 *
	 * @param string $stylesheet The theme to switch to.
	 * @return string[] The slugs from the pattern files of the theme and its parent, sorted.
	 */
	private function switch_theme_and_register_patterns( $stylesheet ) {
		switch_theme( $stylesheet );
		_register_theme_block_patterns();

		// Read the slugs from the files, so the expected list doesn't depend on the code under test.
		$slugs = array();
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme_directory ) {
			foreach ( glob( $theme_directory . '/patterns/*.php' ) as $pattern_file ) {
				$slugs[] = get_file_data( $pattern_file, array( 'slug' => 'Slug' ) )['slug'];
			}
		}
		sort( $slugs );

		$this->assertNotEmpty( $slugs, "The $stylesheet theme should have pattern files." );

		return $slugs;
	}

	/**
	 * Builds the patterns section of the design guide.
	 *
	 * @return string[] The slugs of the patterns in the guide, sorted.
	 */
	private function get_design_guide_pattern_slugs() {
		$guide = ipsum_get_design_guide( array( 'fields' => array( 'patterns' ) ) );
		$slugs = wp_list_pluck( $guide['patterns'], 'slug' );
		sort( $slugs );

		return $slugs;
	}
}
