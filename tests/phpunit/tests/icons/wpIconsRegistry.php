<?php
/**
 * Tests for WP_Icons_Registry::register().
 *
 * @package WordPress
 * @subpackage Icons
 *
 * @group icons
 * @covers WP_Icons_Registry::register
 * @covers WP_Icons_Registry::is_registered
 */
class Tests_Icons_WpIconsRegistry extends WP_UnitTestCase {

	/**
	 * Registry instance for testing.
	 *
	 * @var WP_Icons_Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = WP_Icons_Registry::get_instance();
	}

	public function tear_down() {
		$instance_property = new ReflectionProperty( WP_Icons_Registry::class, 'instance' );

		/*
		 * ReflectionProperty::setAccessible is:
		 * - redundant as of 8.1.0, which made all properties accessible
		 * - deprecated as of 8.5.0
		 * - needed until 8.1.0, as property `instance` is private
		 */
		if ( PHP_VERSION_ID < 80100 ) {
			$instance_property->setAccessible( true );
		}

		$instance_property->setValue( null, null );

		$this->registry = null;
		parent::tear_down();
	}

	/**
	 * Invokes WP_Icons_Registry::register despite it being private
	 *
	 * @param string $icon_name       Icon name including namespace.
	 * @param array  $icon_properties Icon properties (label, content, file_path).
	 * @return bool True if the icon was registered successfully.
	 */
	private function register( $icon_name, $icon_properties ) {
		$method = new ReflectionMethod( $this->registry, 'register' );

		/*
		 * ReflectionMethod::setAccessible is:
		 * - redundant as of 8.1.0, which made all properties accessible
		 * - deprecated as of 8.5.0
		 * - needed until 8.1.0, as property `instance` is private
		 */
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->registry, $icon_name, $icon_properties );
	}

	/**
	 * Should register an icon that provides its content through `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 */
	public function test_register_icon_with_file_path() {
		$file_path = tempnam( get_temp_dir(), 'wp-icon-' );
		file_put_contents( $file_path, '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"></svg>' );

		$name     = 'test-plugin/file-path-icon';
		$settings = array(
			'label'     => 'Icon',
			'file_path' => $file_path,
		);

		$result = $this->register( $name, $settings );
		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( $name ) );

		$registered_icons = $this->registry->get_registered_icons( $name );
		$this->assertCount( 1, $registered_icons );
		$this->assertStringContainsString( '<svg', $registered_icons[0]['content'] );

		unlink( $file_path );
	}

	/**
	 * Should fail to register an icon that provides both `content` and `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_icon_with_content_and_file_path() {
		$name     = 'test-plugin/content-and-file-path';
		$settings = array(
			'label'     => 'Icon',
			'content'   => '<svg></svg>',
			'file_path' => '/path/to/icon.svg',
		);

		$result = $this->register( $name, $settings );
		$this->assertFalse( $result );
		$this->assertFalse( $this->registry->is_registered( $name ) );
	}

	/**
	 * Should fail to register an icon that provides neither `content` nor `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_icon_without_content_or_file_path() {
		$name     = 'test-plugin/no-content';
		$settings = array(
			'label' => 'Icon',
		);

		$result = $this->register( $name, $settings );
		$this->assertFalse( $result );
		$this->assertFalse( $this->registry->is_registered( $name ) );
	}
}
