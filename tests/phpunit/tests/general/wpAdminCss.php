<?php

/**
 * Tests for the `wp_admin_css()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_admin_css
 */
class Tests_General_WpAdminCss extends WP_UnitTestCase {

	private ?WP_Styles $original_wp_styles = null;

	public function set_up() {
		parent::set_up();

		$this->original_wp_styles = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles']     = null;
	}

	public function tear_down() {
		$GLOBALS['wp_styles'] = $this->original_wp_styles;

		parent::tear_down();
	}

	/**
	 * Tests that an unregistered file echoes a stylesheet link.
	 *
	 * @ticket 66757
	 */
	public function test_wp_admin_css_echoes_link_for_unregistered_file() {
		$file = 'unregistered-admin-css-66757';

		$expected = sprintf(
			"<link rel='stylesheet' href='%s' />\n",
			esc_url( wp_admin_css_uri( $file ) )
		);

		$this->assertSame( $expected, get_echo( 'wp_admin_css', array( $file ) ) );
		$this->assertFalse( wp_style_is( $file, 'enqueued' ) );
	}

	/**
	 * Tests that a registered handle is enqueued when styles have not printed yet.
	 *
	 * @ticket 66757
	 */
	public function test_wp_admin_css_enqueues_registered_handle() {
		$handle = 'registered-admin-css-66757';
		wp_register_style( $handle, '/registered-admin-css-66757.css' );

		$this->assertSame( '', get_echo( 'wp_admin_css', array( $handle ) ) );
		$this->assertTrue( wp_style_is( $handle, 'enqueued' ) );
	}

	/**
	 * Tests that force_echo prints a registered handle immediately.
	 *
	 * @ticket 66757
	 */
	public function test_wp_admin_css_force_echo_prints_registered_handle() {
		$handle = 'force-echo-admin-css-66757';
		wp_register_style( $handle, '/force-echo-admin-css-66757.css' );

		$output = get_echo( 'wp_admin_css', array( $handle, true ) );

		$this->assertNotSame( '', $output );
		$this->assertStringContainsString( 'force-echo-admin-css-66757.css', $output );
		$this->assertTrue( wp_style_is( $handle, 'done' ) );
	}

	/**
	 * Tests that a css/ prefix is stripped when looking up a registered handle.
	 *
	 * @ticket 66757
	 */
	public function test_wp_admin_css_strips_css_prefix_for_registered_handle() {
		$handle = 'prefixed-admin-css-66757';
		wp_register_style( $handle, '/prefixed-admin-css-66757.css' );

		$this->assertSame( '', get_echo( 'wp_admin_css', array( 'css/' . $handle ) ) );
		$this->assertTrue( wp_style_is( $handle, 'enqueued' ) );
	}
}
