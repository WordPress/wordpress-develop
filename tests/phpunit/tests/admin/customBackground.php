<?php
/**
 * @group admin
 * @group themes
 */

require_once ABSPATH . 'wp-admin/includes/class-custom-background.php';

class Tests_Admin_CustomBackground extends WP_UnitTestCase {
	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
		set_current_screen( 'appearance_page_custom-background' );

		remove_theme_support( 'custom-background' );
		add_theme_support(
			'custom-background',
			array(
				'default-position-x' => 'left',
				'default-position-y' => 'top',
				'default-size'       => 'auto',
				'default-repeat'     => 'repeat',
				'default-attachment' => 'scroll',
			)
		);
	}

	public function tear_down() {
		remove_filter( 'theme_mod_background_position_x', array( $this, 'filter_background_position_x' ) );
		remove_theme_mod( 'background_image' );
		remove_theme_mod( 'background_image_thumb' );
		remove_theme_support( 'custom-background' );
		set_current_screen();
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * @ticket 57268
	 */
	public function test_admin_page_escapes_background_styles() {
		set_theme_mod( 'background_image', 'https://example.org/background.jpg' );
		set_theme_mod( 'background_image_thumb', 'https://example.org/background.jpg' );
		set_theme_mod( 'background_size', 'cover' );
		set_theme_mod( 'background_repeat', 'repeat' );
		set_theme_mod( 'background_attachment', 'scroll' );

		add_filter( 'theme_mod_background_position_x', array( $this, 'filter_background_position_x' ) );

		$custom_background = new Custom_Background();

		ob_start();
		$custom_background->admin_page();
		$output = ob_get_clean();

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( '<html><body>' . $output . '</body></html>' );
		libxml_clear_errors();

		$image = $dom->getElementById( 'custom-background-image' );

		$this->assertInstanceOf( DOMElement::class, $image );
		$this->assertFalse( $image->hasAttribute( 'onmouseover' ) );
	}

	public function filter_background_position_x() {
		return 'left" onmouseover="alert(1)';
	}
}
