<?php
/**
 * Tests for the variations of the Template Part block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group block-templates
 */
class Tests_Blocks_TemplatePartVariations extends WP_UnitTestCase {

	/**
	 * Original value of the features of the theme.
	 *
	 * @var array
	 */
	private $original_theme_features;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$parts = array(
			'wp-test-header'  => array(
				'title'       => 'Test Header',
				'description' => 'The header of the tests',
				'area'        => WP_TEMPLATE_PART_AREA_HEADER,
			),
			'wp-test-plain'   => array(
				'title'       => 'Test Plain',
				'description' => '',
				'area'        => WP_TEMPLATE_PART_AREA_UNCATEGORIZED,
			),
			'wp-test-overlay' => array(
				'title'       => 'Test Overlay',
				'description' => '',
				'area'        => WP_TEMPLATE_PART_AREA_NAVIGATION_OVERLAY,
			),
		);

		foreach ( $parts as $slug => $part ) {
			$post_id = $factory->post->create(
				array(
					'post_type'    => 'wp_template_part',
					'post_name'    => $slug,
					'post_title'   => $part['title'],
					'post_excerpt' => $part['description'],
					'post_content' => 'Content',
				)
			);

			wp_set_post_terms( $post_id, get_stylesheet(), 'wp_theme' );
			wp_set_post_terms( $post_id, $part['area'], 'wp_template_part_area' );
		}
	}

	public function set_up() {
		global $_wp_theme_features;

		parent::set_up();

		$this->original_theme_features = $_wp_theme_features;

		add_theme_support( 'block-templates' );
	}

	public function tear_down() {
		global $_wp_theme_features;

		$_wp_theme_features = $this->original_theme_features;

		wp_installing( false );

		parent::tear_down();
	}

	/**
	 * Replaces the areas of the template parts by a list that is known.
	 */
	private function use_known_areas() {
		add_filter(
			'default_wp_template_part_areas',
			static function () {
				return array(
					array(
						'area'        => 'uncategorized',
						'label'       => 'General',
						'description' => 'General area',
						'icon'        => 'layout',
						'area_tag'    => 'div',
					),
					array(
						'area'        => 'header',
						'label'       => 'Header',
						'description' => 'Header area',
						'icon'        => 'header',
						'area_tag'    => 'header',
					),
					array(
						'area'        => 'footer',
						'label'       => 'Footer',
						'description' => 'Footer area',
						'icon'        => 'footer',
						'area_tag'    => 'footer',
					),
					array(
						'area'        => 'navigation-overlay',
						'label'       => 'Overlay',
						'description' => 'Overlay area',
						'icon'        => 'overlay',
						'area_tag'    => 'div',
					),
					array(
						'area'        => 'custom',
						'label'       => 'Custom',
						'description' => 'Custom area',
						'icon'        => 'star',
						'area_tag'    => 'aside',
					),
				);
			}
		);
	}

	/**
	 * Gets the variations of the template parts that were created by the tests.
	 *
	 * @param array[] $variations Variations.
	 * @return array[] The variations of the template parts of the tests, keyed by name.
	 */
	private function get_test_variations( $variations ) {
		$found = array();

		foreach ( $variations as $variation ) {
			if ( str_starts_with( $variation['name'], 'instance_wp-test-' ) ) {
				$found[ $variation['name'] ] = $variation;
			}
		}

		return $found;
	}

	/**
	 * Tests the variations of the areas.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_area_variations
	 */
	public function test_should_build_a_variation_for_each_area_that_is_not_general_or_an_overlay() {
		$this->use_known_areas();

		$variations = build_template_part_block_area_variations( array() );

		$this->assertSame( array( 'area_header', 'area_footer', 'area_custom' ), wp_list_pluck( $variations, 'name' ) );
		$this->assertSame(
			array(
				'name'        => 'area_custom',
				'title'       => 'Custom',
				'description' => 'Custom area',
				'attributes'  => array( 'area' => 'custom' ),
				'scope'       => array( 'inserter' ),
				'icon'        => 'star',
			),
			$variations[2]
		);
	}

	/**
	 * Tests that an area that has an instance is not in the inserter.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_area_variations
	 */
	public function test_should_not_show_an_area_in_the_inserter_when_it_has_an_instance() {
		$this->use_known_areas();

		$variations = build_template_part_block_area_variations(
			array(
				array( 'attributes' => array( 'area' => 'footer' ) ),
				array( 'attributes' => array( 'area' => 'uncategorized' ) ),
			)
		);

		$scopes = wp_list_pluck( $variations, 'scope', 'name' );

		$this->assertSame( array( 'inserter' ), $scopes['area_header'], 'An area without an instance should be in the inserter.' );
		$this->assertSame( array(), $scopes['area_footer'], 'An area with an instance should not be in the inserter.' );
		$this->assertSame( array( 'inserter' ), $scopes['area_custom'], 'Another area without an instance should be in the inserter.' );
	}

	/**
	 * Tests that there are no variations without areas.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_area_variations
	 */
	public function test_should_build_no_variations_without_areas() {
		add_filter( 'default_wp_template_part_areas', '__return_empty_array' );

		$this->assertSame( array(), build_template_part_block_area_variations( array() ) );
	}

	/**
	 * Tests that there are no instances while WordPress is installing.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_build_no_instances_while_installing() {
		wp_installing( true );

		$this->assertSame( array(), build_template_part_block_instance_variations() );
	}

	/**
	 * Tests that there are no instances when the theme does not support the templates.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_build_no_instances_when_the_theme_does_not_support_the_templates() {
		remove_theme_support( 'block-templates' );
		remove_theme_support( 'block-template-parts' );

		$this->assertSame( array(), build_template_part_block_instance_variations() );
	}

	/**
	 * Tests that the instances are built for a theme that supports the template parts.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 *
	 * @dataProvider data_supports
	 *
	 * @param string $feature Feature of the theme.
	 */
	public function test_should_build_the_instances_when_the_theme_supports_the_templates( $feature ) {
		remove_theme_support( 'block-templates' );
		add_theme_support( $feature );

		$this->assertNotEmpty( $this->get_test_variations( build_template_part_block_instance_variations() ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_supports() {
		return array(
			'block templates'      => array( 'block-templates' ),
			'block template parts' => array( 'block-template-parts' ),
		);
	}

	/**
	 * Tests the variation of an instance.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_build_a_variation_for_each_template_part() {
		$variations = $this->get_test_variations( build_template_part_block_instance_variations() );

		$this->assertSame( array( 'instance_wp-test-header', 'instance_wp-test-plain', 'instance_wp-test-overlay' ), array_keys( $variations ), 'There should be a variation for each template part.' );

		$header = $variations['instance_wp-test-header'];

		$this->assertSame( 'Test Header', $header['title'] );
		$this->assertNotEmpty( $header['description'], 'The description should not be empty.' );
		$this->assertSame(
			array(
				'slug'  => 'wp-test-header',
				'theme' => get_stylesheet(),
				'area'  => WP_TEMPLATE_PART_AREA_HEADER,
			),
			$header['attributes']
		);
		$this->assertSame( array( 'attributes' => $header['attributes'] ), $header['example'], 'The example should use the attributes.' );
		$this->assertSame( array( 'inserter' ), $header['scope'] );
	}

	/**
	 * Tests the description of the variations that is never empty.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_not_have_an_empty_description_for_a_template_part_without_one() {
		$variations = $this->get_test_variations( build_template_part_block_instance_variations() );

		$this->assertNotEmpty( $variations['instance_wp-test-plain']['description'] );
	}

	/**
	 * Tests the scope of the template parts of the navigation overlay.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_not_show_the_navigation_overlay_in_the_inserter() {
		$variations = $this->get_test_variations( build_template_part_block_instance_variations() );

		$this->assertSame( array(), $variations['instance_wp-test-overlay']['scope'], 'The overlay should not be in the inserter.' );
		$this->assertSame( array( 'inserter' ), $variations['instance_wp-test-plain']['scope'], 'The other template parts should be in the inserter.' );
	}

	/**
	 * Tests the icon of the area of the template parts.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_use_the_icon_of_the_area() {
		$this->use_known_areas();

		$variations = $this->get_test_variations( build_template_part_block_instance_variations() );

		$this->assertSame( 'header', $variations['instance_wp-test-header']['icon'] );
		$this->assertSame( 'layout', $variations['instance_wp-test-plain']['icon'] );
		$this->assertSame( 'overlay', $variations['instance_wp-test-overlay']['icon'] );
	}

	/**
	 * Tests that there is no icon for an area that is not defined.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_instance_variations
	 */
	public function test_should_not_have_an_icon_for_an_area_that_is_not_defined() {
		add_filter(
			'default_wp_template_part_areas',
			static function ( $areas ) {
				return array_values(
					array_filter(
						$areas,
						static function ( $area ) {
							return 'header' !== $area['area'];
						}
					)
				);
			}
		);

		$variations = $this->get_test_variations( build_template_part_block_instance_variations() );

		$this->assertNull( $variations['instance_wp-test-header']['icon'] );
	}

	/**
	 * Tests that the variations of the areas come before the ones of the instances.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_variations
	 */
	public function test_should_build_the_variations_of_the_areas_before_the_ones_of_the_instances() {
		$this->use_known_areas();

		$variations = build_template_part_block_variations();
		$names      = wp_list_pluck( $variations, 'name' );

		$this->assertSame( array( 'area_header', 'area_footer', 'area_custom' ), array_slice( $names, 0, 3 ), 'The areas should come first.' );
		$this->assertContains( 'instance_wp-test-header', $names, 'The instances should be in the list.' );
		$this->assertGreaterThan( array_search( 'area_custom', $names, true ), array_search( 'instance_wp-test-header', $names, true ) );
	}

	/**
	 * Tests that the areas that have an instance are not in the inserter.
	 *
	 * @ticket 66533
	 *
	 * @covers ::build_template_part_block_variations
	 */
	public function test_should_pass_the_instances_to_the_variations_of_the_areas() {
		$this->use_known_areas();

		$variations = build_template_part_block_variations();
		$scopes     = wp_list_pluck( $variations, 'scope', 'name' );

		$this->assertSame( array(), $scopes['area_header'], 'The header has an instance.' );
		$this->assertSame( array( 'inserter' ), $scopes['area_footer'], 'The footer has no instance.' );
	}

	/**
	 * Tests that the block is registered with the callbacks.
	 *
	 * @ticket 66533
	 *
	 * @covers ::register_block_core_template_part
	 */
	public function test_should_register_the_block_with_the_callbacks() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/template-part' );

		if ( $was_registered ) {
			unregister_block_type( 'core/template-part' );
		}

		register_block_core_template_part();
		$block_type = $registry->get_registered( 'core/template-part' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/template-part' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_template_part', $block_type->render_callback );
		$this->assertSame( 'build_template_part_block_variations', $block_type->variation_callback );
	}
}
