<?php
/**
 * Tests for the server-side rendering of the Login/out block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_loginout
 */
class Tests_Blocks_RenderLoginout extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * The original values of the server variables.
	 *
	 * @var array
	 */
	private $original_server;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render = WP_Block_Supports::$block_to_render;
		$this->original_server          = $_SERVER;

		$_SERVER['HTTP_HOST']   = 'example.org';
		$_SERVER['REQUEST_URI'] = '/current-page/?a=1';

		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/loginout',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$_SERVER                            = $this->original_server;

		parent::tear_down();
	}

	/**
	 * Returns the attributes of the first element of the output.
	 *
	 * @param string $output The output.
	 * @param string $tag    The tag name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the sorted classes of the wrapper of the output.
	 *
	 * @param string $output The output.
	 * @return string[] The classes.
	 */
	private function get_classes( $output ) {
		$classes = explode( ' ', $this->get_element( $output, 'div' )['class'] );

		sort( $classes );

		return $classes;
	}

	/**
	 * Tests the link to log in for a visitor.
	 *
	 * @ticket 66577
	 */
	public function test_should_render_the_login_link_for_a_visitor() {
		wp_set_current_user( 0 );

		$output = render_block_core_loginout( array() );
		$link   = $this->get_element( $output, 'a' );

		$this->assertSame( array( 'logged-out', 'wp-block-loginout' ), $this->get_classes( $output ) );
		$this->assertStringStartsWith( '<div ', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertStringContainsString( '>Log in</a>', $output );
		$this->assertStringContainsString( 'wp-login.php', $link['href'] );
		$this->assertStringNotContainsString( 'redirect_to', $link['href'] );
	}

	/**
	 * Tests the link to log out for a user.
	 *
	 * @ticket 66577
	 */
	public function test_should_render_the_logout_link_for_a_user() {
		wp_set_current_user( self::factory()->user->create() );

		$output = render_block_core_loginout( array() );
		$link   = $this->get_element( $output, 'a' );

		$this->assertSame( array( 'logged-in', 'wp-block-loginout' ), $this->get_classes( $output ) );
		$this->assertStringContainsString( '>Log out</a>', $output );
		$this->assertStringContainsString( 'action=logout', $link['href'] );
		$this->assertStringContainsString( '_wpnonce=', $link['href'] );
	}

	/**
	 * Tests the redirect to the current page.
	 *
	 * @ticket 66577
	 *
	 * @dataProvider data_users
	 *
	 * @param bool $logged_in Whether the user is logged in.
	 */
	public function test_should_redirect_to_the_current_page( $logged_in ) {
		wp_set_current_user( $logged_in ? self::factory()->user->create() : 0 );

		$link = $this->get_element( render_block_core_loginout( array( 'redirectToCurrent' => true ) ), 'a' );

		parse_str( (string) wp_parse_url( $link['href'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'http://example.org/current-page/?a=1', $query['redirect_to'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_users() {
		return array(
			'visitor' => array( false ),
			'user'    => array( true ),
		);
	}

	/**
	 * Tests that the redirect to the current page uses the protocol of the request.
	 *
	 * @ticket 66577
	 */
	public function test_should_redirect_to_the_current_page_with_https() {
		wp_set_current_user( 0 );

		$_SERVER['HTTPS'] = 'on';

		$link = $this->get_element( render_block_core_loginout( array( 'redirectToCurrent' => true ) ), 'a' );

		parse_str( (string) wp_parse_url( $link['href'], PHP_URL_QUERY ), $query );

		$this->assertSame( 'https://example.org/current-page/?a=1', $query['redirect_to'] );
	}

	/**
	 * Tests that there is no redirect when the setting is off.
	 *
	 * @ticket 66577
	 *
	 * @dataProvider data_no_redirect_attributes
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_not_redirect_without_the_setting( $attributes ) {
		wp_set_current_user( 0 );

		$link = $this->get_element( render_block_core_loginout( $attributes ), 'a' );

		$this->assertStringNotContainsString( 'redirect_to', $link['href'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_redirect_attributes() {
		return array(
			'not set' => array( array() ),
			'false'   => array( array( 'redirectToCurrent' => false ) ),
		);
	}

	/**
	 * Tests the login form for a visitor.
	 *
	 * @ticket 66577
	 */
	public function test_should_render_the_login_form_for_a_visitor() {
		wp_set_current_user( 0 );

		$output = render_block_core_loginout( array( 'displayLoginAsForm' => true ) );

		$this->assertSame( array( 'has-login-form', 'logged-out', 'wp-block-loginout' ), $this->get_classes( $output ) );
		$this->assertStringContainsString( '<form name="loginform"', $output );
		$this->assertStringNotContainsString( '>Log in</a>', $output );
	}

	/**
	 * Tests that the login form is not shown for a user.
	 *
	 * @ticket 66577
	 */
	public function test_should_not_render_the_login_form_for_a_user() {
		wp_set_current_user( self::factory()->user->create() );

		$output = render_block_core_loginout( array( 'displayLoginAsForm' => true ) );

		$this->assertSame( array( 'logged-in', 'wp-block-loginout' ), $this->get_classes( $output ) );
		$this->assertStringNotContainsString( '<form', $output );
		$this->assertStringContainsString( '>Log out</a>', $output );
	}

	/**
	 * Tests the classes of the button of the form for a block theme.
	 *
	 * @ticket 66577
	 */
	public function test_should_add_the_button_classes_to_the_form_of_a_block_theme() {
		switch_theme( 'block-theme' );
		wp_set_current_user( 0 );

		$output = render_block_core_loginout( array( 'displayLoginAsForm' => true ) );

		$processor = new WP_HTML_Tag_Processor( $output );
		$button    = null;

		while ( $processor->next_tag( 'input' ) ) {
			if ( 'wp-submit' === $processor->get_attribute( 'name' ) ) {
				$button = $processor->get_attribute( 'class' );
			}
		}

		$this->assertStringContainsString( 'wp-block-button__link', $button );
		$this->assertStringContainsString( wp_theme_get_element_class_name( 'button' ), $button );
	}

	/**
	 * Tests that the button of the form has no extra class for a theme that is not a block theme.
	 *
	 * @ticket 66577
	 */
	public function test_should_not_add_the_button_classes_to_the_form_of_a_classic_theme() {
		switch_theme( 'default' );
		wp_set_current_user( 0 );

		$this->assertStringNotContainsString( 'wp-block-button__link', render_block_core_loginout( array( 'displayLoginAsForm' => true ) ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66577
	 *
	 * @covers ::register_block_core_loginout
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/loginout' );

		if ( $original_block ) {
			unregister_block_type( 'core/loginout' );
		}

		try {
			register_block_core_loginout();
			$block_type = $registry->get_registered( 'core/loginout' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_loginout', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/loginout' ) ) {
				unregister_block_type( 'core/loginout' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
