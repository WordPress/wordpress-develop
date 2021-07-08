<?php

/**
 * Tests for the WP_Customize_Section class.
 *
 * @group customize
 */
class Tests_WP_Customize_Section extends WP_UnitTestCase {
	protected static $admin_id;
	protected static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id   = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids[] = self::$admin_id;
	}

	/**
	 * @var WP_Customize_Manager
	 */
	protected $manager;

	function setUp() {
		parent::setUp();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->manager           = $GLOBALS['wp_customize'];
		$this->undefined         = new stdClass();
	}

	function tearDown() {
		$this->manager = null;
		unset( $GLOBALS['wp_customize'] );
		parent::tearDown();
	}

	/**
	 * @see WP_Customize_Section::__construct()
	 */
	function test_construct_default_args() {
		$section = new WP_Customize_Section( $this->manager, 'foo' );
		$this->assertIsInt( $section->instance_number );
		$this->assertSame( $this->manager, $section->manager );
		$this->assertSame( 'foo', $section->id );
		$this->assertSame( 160, $section->priority );
		$this->assertSame( 'edit_theme_options', $section->capability );
		$this->assertSame( '', $section->theme_supports );
		$this->assertSame( '', $section->title );
		$this->assertSame( '', $section->description );
		$this->assertEmpty( $section->panel );
		$this->assertSame( 'default', $section->type );
		$this->assertSame( array( $section, 'active_callback' ), $section->active_callback );
	}

	/**
	 * @see WP_Customize_Section::__construct()
	 */
	function test_construct_custom_args() {
		$args = array(
			'priority'        => 200,
			'capability'      => 'edit_posts',
			'theme_supports'  => 'html5',
			'title'           => 'Hello World',
			'description'     => 'Lorem Ipsum',
			'type'            => 'horizontal',
			'active_callback' => '__return_true',
			'panel'           => 'bar',
		);

		$this->manager->add_panel( 'bar' );

		$section = new WP_Customize_Section( $this->manager, 'foo', $args );
		foreach ( $args as $key => $value ) {
			$this->assertSame( $value, $section->$key );
		}
	}

	/**
	 * @see WP_Customize_Section::__construct()
	 */
	function test_construct_custom_type() {
		$section = new Custom_Section_Test( $this->manager, 'foo' );
		$this->assertSame( 'titleless', $section->type );
	}

	/**
	 * @see WP_Customize_Section::active()
	 * @see WP_Customize_Section::active_callback()
	 */
	function test_active() {
		$section = new WP_Customize_Section( $this->manager, 'foo' );
		$this->assertTrue( $section->active() );

		$section = new WP_Customize_Section(
			$this->manager,
			'foo',
			array(
				'active_callback' => '__return_false',
			)
		);
		$this->assertFalse( $section->active() );
		add_filter( 'customize_section_active', array( $this, 'filter_active_test' ), 10, 2 );
		$this->assertTrue( $section->active() );
	}

	/**
	 * @param bool $active
	 * @param WP_Customize_Section $section
	 * @return bool
	 */
	function filter_active_test( $active, $section ) {
		$this->assertFalse( $active );
		$this->assertInstanceOf( 'WP_Customize_Section', $section );
		$active = true;
		return $active;
	}

	/**
	 * @see WP_Customize_Section::json()
	 */
	function test_json() {
		$args = array(
			'priority'        => 200,
			'capability'      => 'edit_posts',
			'theme_supports'  => 'html5',
			'title'           => 'Hello World',
			'description'     => 'Lorem Ipsum',
			'type'            => 'horizontal',
			'panel'           => 'bar',
			'active_callback' => '__return_true',
		);

		$this->manager->add_panel( 'bar' );

		$section = new WP_Customize_Section( $this->manager, 'foo', $args );
		$data    = $section->json();
		$this->assertSame( 'foo', $data['id'] );
		foreach ( array( 'title', 'description', 'priority', 'panel', 'type' ) as $key ) {
			$this->assertSame( $args[ $key ], $data[ $key ] );
		}
		$this->assertEmpty( $data['content'] );
		$this->assertTrue( $data['active'] );
		$this->assertIsInt( $data['instanceNumber'] );
	}

	/**
	 * @see WP_Customize_Section::check_capabilities()
	 */
	function test_check_capabilities() {
		wp_set_current_user( self::$admin_id );

		$section = new WP_Customize_Section( $this->manager, 'foo' );
		$this->assertTrue( $section->check_capabilities() );
		$old_cap             = $section->capability;
		$section->capability = 'do_not_allow';
		$this->assertFalse( $section->check_capabilities() );
		$section->capability = $old_cap;
		$this->assertTrue( $section->check_capabilities() );
		$section->theme_supports = 'impossible_feature';
		$this->assertFalse( $section->check_capabilities() );
	}

	/**
	 * @see WP_Customize_Section::get_content()
	 */
	function test_get_content() {
		$section = new WP_Customize_Section( $this->manager, 'foo' );
		$this->assertEmpty( $section->get_content() );
	}

	/**
	 * @see WP_Customize_Section::maybe_render()
	 */
	function test_maybe_render() {
		wp_set_current_user( self::$admin_id );
		$section                        = new WP_Customize_Section( $this->manager, 'bar' );
		$customize_render_section_count = did_action( 'customize_render_section' );
		add_action( 'customize_render_section', array( $this, 'action_customize_render_section_test' ) );
		ob_start();
		$section->maybe_render();
		$content = ob_get_clean();
		$this->assertTrue( $section->check_capabilities() );
		$this->assertEmpty( $content );
		$this->assertSame( $customize_render_section_count + 1, did_action( 'customize_render_section' ), 'Unexpected did_action count for customize_render_section' );
		$this->assertSame( 1, did_action( "customize_render_section_{$section->id}" ), "Unexpected did_action count for customize_render_section_{$section->id}" );
	}

	/**
	 * @see WP_Customize_Section::maybe_render()
	 * @param WP_Customize_Section $section
	 */
	function action_customize_render_section_test( $section ) {
		$this->assertInstanceOf( 'WP_Customize_Section', $section );
	}

	/**
	 * @see WP_Customize_Section::print_template()
	 */
	function test_print_templates_standard() {
		wp_set_current_user( self::$admin_id );

		$section = new WP_Customize_Section( $this->manager, 'baz' );
		ob_start();
		$section->print_template();
		$content = ob_get_clean();
		$this->assertContains( '<script type="text/html" id="tmpl-customize-section-default">', $content );
		$this->assertContains( 'accordion-section-title', $content );
		$this->assertContains( 'accordion-section-content', $content );
	}

	/**
	 * @see WP_Customize_Section::print_template()
	 */
	function test_print_templates_custom() {
		wp_set_current_user( self::$admin_id );

		$section = new Custom_Section_Test( $this->manager, 'baz' );
		ob_start();
		$section->print_template();
		$content = ob_get_clean();
		$this->assertContains( '<script type="text/html" id="tmpl-customize-section-titleless">', $content );
		$this->assertNotContains( 'accordion-section-title', $content );
		$this->assertContains( 'accordion-section-content', $content );
	}
}

require_once ABSPATH . WPINC . '/class-wp-customize-section.php';
class Custom_Section_Test extends WP_Customize_Section {
	public $type = 'titleless';

	protected function render_template() {
		?>
		<li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }}">
			<ul class="accordion-section-content">
				<# if ( data.description ) { #>
					<li class="customize-section-description-container">
						<p class="description customize-section-description">{{{ data.description }}}</p>
					</li>
				<# } #>
			</ul>
		</li>
		<?php
	}

}
