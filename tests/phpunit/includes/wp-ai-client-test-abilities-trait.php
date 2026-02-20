<?php
/**
 * Trait for registering test abilities used across AI Client test classes.
 *
 * @package WordPress\Tests
 */

trait WP_AI_Client_Test_Abilities_Trait {

	/**
	 * Registers test ability category and abilities.
	 *
	 * Safe to call multiple times; skips registration if already done.
	 * Must be called from set_up_before_class() after parent::set_up_before_class().
	 */
	private static function register_test_abilities() {
		// Skip if already registered by another test class.
		$category_registry = WP_Ability_Categories_Registry::get_instance();
		if ( null !== $category_registry && $category_registry->is_registered( 'wpaiclienttests' ) ) {
			return;
		}

		global $wp_current_filter;

		// Simulate the init hook for ability categories.
		$wp_current_filter[] = 'wp_abilities_api_categories_init';

		// Register test ability category.
		wp_register_ability_category(
			'wpaiclienttests',
			array(
				'label'       => 'WP AI Client Tests',
				'description' => 'Test abilities for WP AI Client.',
			)
		);

		array_pop( $wp_current_filter );

		// Simulate the abilities init action.
		$wp_current_filter[] = 'wp_abilities_api_init';

		// Register test abilities.
		wp_register_ability(
			'wpaiclienttests/simple',
			array(
				'label'               => 'Simple Test Ability',
				'description'         => 'A simple test ability with no parameters.',
				'category'            => 'wpaiclienttests',
				'execute_callback'    => static function () {
					return array( 'success' => true );
				},
				'permission_callback' => static function () {
					return true;
				},
			)
		);

		wp_register_ability(
			'wpaiclienttests/with-params',
			array(
				'label'               => 'Test Ability With Parameters',
				'description'         => 'A test ability that accepts parameters.',
				'category'            => 'wpaiclienttests',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title' => array(
							'type'        => 'string',
							'description' => 'The title parameter.',
							'required'    => true,
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( array $input ) {
					return array(
						'success' => true,
						'title'   => $input['title'],
					);
				},
				'permission_callback' => static function () {
					return true;
				},
			)
		);

		wp_register_ability(
			'wpaiclienttests/returns-error',
			array(
				'label'               => 'Test Ability That Returns Error',
				'description'         => 'A test ability that returns a WP_Error.',
				'category'            => 'wpaiclienttests',
				'execute_callback'    => static function () {
					return new WP_Error( 'test_error', 'This is a test error message.' );
				},
				'permission_callback' => static function () {
					return true;
				},
			)
		);

		wp_register_ability(
			'wpaiclienttests/hyphen-test',
			array(
				'label'               => 'Test Ability With Hyphens',
				'description'         => 'A test ability to verify hyphenated names.',
				'category'            => 'wpaiclienttests',
				'execute_callback'    => static function () {
					return array( 'hyphenated' => true );
				},
				'permission_callback' => static function () {
					return true;
				},
			)
		);

		array_pop( $wp_current_filter );
	}

	/**
	 * Unregisters test ability category and abilities.
	 *
	 * Safe to call multiple times; skips unregistration if already done.
	 * Must be called from tear_down_after_class() before parent::tear_down_after_class().
	 */
	private static function unregister_test_abilities() {
		// Unregister test abilities.
		wp_unregister_ability( 'wpaiclienttests/simple' );
		wp_unregister_ability( 'wpaiclienttests/with-params' );
		wp_unregister_ability( 'wpaiclienttests/returns-error' );
		wp_unregister_ability( 'wpaiclienttests/hyphen-test' );

		// Unregister test ability category.
		wp_unregister_ability_category( 'wpaiclienttests' );
	}
}
