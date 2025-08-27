<?php
/**
 * Test case for the Plugin Dependencies tests.
 *
 * @package WP_Plugin_Dependencies
 *
 * Abstracts the common properties and tasks for the Plugin Dependencies tests.
 */
abstract class WP_PluginDependencies_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Stores an instance of WP_Plugin_Dependencies
	 * for managing property visibility during tests.
	 *
	 * @var WP_Plugin_Dependencies
	 */
	protected static $instance;

	/**
	 * Stores a list of static properties and their default values.
	 * for resetting after each test runs.
	 *
	 * @var array
	 */
	protected static $static_properties = array(
		'plugins'                     => null,
		'plugin_dirnames'             => null,
		'dependencies'                => null,
		'dependency_slugs'            => null,
		'dependent_slugs'             => null,
		'dependency_api_data'         => null,
		'dependency_filepaths'        => null,
		'circular_dependencies_pairs' => null,
		'circular_dependencies_slugs' => null,
		'initialized'                 => false,
	);

	/**
	 * An array of reflected class members.
	 *
	 * @var ReflectionMethod[]|ReflectionProperty[]
	 */
	protected static $reflected_members = array();

	/**
	 * Sets up the WP_Plugin_Dependencies instance before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$instance = new WP_Plugin_Dependencies();
	}

	/**
	 * Empties the '$reflected_members' property after all tests run.
	 */
	public static function tear_down_after_class() {
		self::$reflected_members = array();

		parent::tear_down_after_class();
	}

	/**
	 * Resets all static properties to a default value after each test.
	 */
	public function tear_down() {
		foreach ( self::$static_properties as $name => $default_value ) {
			$this->set_property_value( $name, $default_value );
		}

		parent::tear_down();
	}

	/**
	 * Temporarily modifies the accessibility of a property to change its value.
	 *
	 * @param string $property The property's name.
	 * @param mixed  $value The new value.
	 */
	public function set_property_value( $property, $value ) {
		if ( ! isset( self::$reflected_members[ $property ] ) ) {
			self::$reflected_members[ $property ] = new ReflectionProperty( self::$instance, $property );
		}

		self::$reflected_members[ $property ]->setAccessible( true );
		self::$reflected_members[ $property ]->setValue( self::$instance, $value );
		self::$reflected_members[ $property ]->setAccessible( false );
	}

	/**
	 * Temporarily modifies the accessibility of a property to get its value.
	 *
	 * @param string $property The property's name.
	 * @return mixed The value of the property.
	 */
	public function get_property_value( $property ) {
		if ( ! isset( self::$reflected_members[ $property ] ) ) {
			self::$reflected_members[ $property ] = new ReflectionProperty( self::$instance, $property );
		}

		self::$reflected_members[ $property ]->setAccessible( true );
		$value = self::$reflected_members[ $property ]->getValue( self::$instance );
		self::$reflected_members[ $property ]->setAccessible( false );

		return $value;
	}

	/**
	 * Temporarily modifies the accessibility of a method to invoke it
	 * and return its result.
	 *
	 * @param string $method  The method's name.
	 * @param mixed  ...$args Arguments for the method.
	 * @return mixed The result of the method call.
	 */
	protected function call_method( $method, ...$args ) {
		if ( ! isset( self::$reflected_members[ $method ] ) ) {
			self::$reflected_members[ $method ] = new ReflectionMethod( self::$instance, $method );
		}

		self::$reflected_members[ $method ]->setAccessible( true );
		$value = self::$reflected_members[ $method ]->invokeArgs( self::$instance, $args );
		self::$reflected_members[ $method ]->setAccessible( false );

		return $value;
	}
}
