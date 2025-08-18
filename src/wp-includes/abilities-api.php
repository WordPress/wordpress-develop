<?php
/**
 * Abilities API
 *
 * Defines functions for managing abilities in WordPress.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.1.0
 */

declare( strict_types = 1 );

/**
 * Registers a new ability using Abilities API.
 *
 * Note: Do not use before the {@see 'abilities_api_init'} hook.
 *
 * @see WP_Abilities_Registry::register()
 *
 * @since 0.1.0
 *
 * @param string|\WP_Ability  $name       The name of the ability, or WP_Ability instance.
 *                                        The name must be a string containing a namespace prefix, i.e. `my-plugin/my-ability`. It can only
 *                                        contain lowercase alphanumeric characters, dashes and the forward slash.
 * @param array<string,mixed> $properties Optional. An associative array of properties for the ability. This should
 *                                        include `label`, `description`, `input_schema`, `output_schema`,
 *                                        `execute_callback`, `permission_callback`, and `meta`.
 * @return ?\WP_Ability An instance of registered ability on success, null on failure.
 */
function wp_register_ability( $name, array $properties = array() ): ?WP_Ability {
	if ( ! did_action( 'abilities_api_init' ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: 1: abilities_api_init, 2: string value of the ability name. */
				esc_html__( 'Abilities must be registered on the %1$s action. The ability %2$s was not registered.' ),
				'<code>abilities_api_init</code>',
				'<code>' . esc_html( $name instanceof WP_Ability ? $name->get_name() : $name ) . '</code>'
			),
			'0.1.0'
		);
		return null;
	}

	return WP_Abilities_Registry::get_instance()->register( $name, $properties );
}

/**
 * Unregisters an ability using Abilities API.
 *
 * @see WP_Abilities_Registry::unregister()
 *
 * @since 0.1.0
 *
 * @param string $name The name of the registered ability, with its namespace.
 * @return ?\WP_Ability The unregistered ability instance on success, null on failure.
 */
function wp_unregister_ability( string $name ): ?WP_Ability {
	return WP_Abilities_Registry::get_instance()->unregister( $name );
}

/**
 * Retrieves a registered ability using Abilities API.
 *
 * @see WP_Abilities_Registry::get_registered()
 *
 * @since 0.1.0
 *
 * @param string $name The name of the registered ability, with its namespace.
 * @return ?\WP_Ability The registered ability instance, or null if it is not registered.
 */
function wp_get_ability( string $name ): ?WP_Ability {
	return WP_Abilities_Registry::get_instance()->get_registered( $name );
}

/**
 * Retrieves all registered abilities using Abilities API.
 *
 * @see WP_Abilities_Registry::get_all_registered()
 *
 * @since 0.1.0
 *
 * @return \WP_Ability[] The array of registered abilities.
 */
function wp_get_abilities(): array {
	return WP_Abilities_Registry::get_instance()->get_all_registered();
}
