<?php
/**
 * Abilities API
 *
 * Defines functions for managing abilities in WordPress.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 */

declare( strict_types = 1 );

/**
 * Registers a new ability using Abilities API.
 *
 * Note: Should only be used on the {@see 'abilities_api_init'} hook.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Registry::register()
 *
 * @param string              $name The name of the ability. The name must be a string containing a namespace
 *                                  prefix, i.e. `my-plugin/my-ability`. It can only contain lowercase
 *                                  alphanumeric characters, dashes and the forward slash.
 * @param array<string,mixed> $args {
 *     An associative array of arguments for the ability.
 *
 *     @type string               $label                 The human-readable label for the ability.
 *     @type string               $description           A detailed description of what the ability does.
 *     @type string               $category              The category slug this ability belongs to.
 *     @type callable             $execute_callback      A callback function to execute when the ability is invoked.
 *                                                       Receives optional mixed input and returns mixed result or WP_Error.
 *     @type callable             $permission_callback   A callback function to check permissions before execution.
 *                                                       Receives optional mixed input and returns bool or WP_Error.
 *     @type array<string,mixed>  $input_schema          Optional. JSON Schema definition for the ability's input.
 *     @type array<string,mixed>  $output_schema         Optional. JSON Schema definition for the ability's output.
 *     @type array<string,mixed>  $meta                  {
 *         Optional. Additional metadata for the ability.
 *
 *         @type array<string,bool|string> $annotations  Optional. Annotation metadata for the ability.
 *         @type bool                      $show_in_rest Optional. Whether to expose this ability in the REST API. Default false.
 *     }
 *     @type string               $ability_class         Optional. Custom class to instantiate instead of WP_Ability.
 * }
 * @return WP_Ability|null An instance of registered ability on success, null on failure.
 *
 * @phpstan-param array{
 *   label?: string,
 *   description?: string,
 *   category?: string,
 *   execute_callback?: callable( mixed $input= ): (mixed|\WP_Error),
 *   permission_callback?: callable( mixed $input= ): (bool|\WP_Error),
 *   input_schema?: array<string,mixed>,
 *   output_schema?: array<string,mixed>,
 *   meta?: array{
 *     annotations?: array<string,(bool|string)>,
 *     show_in_rest?: bool,
 *     ...<string,mixed>,
 *   },
 *   ability_class?: class-string<\WP_Ability>,
 *   ...<string, mixed>
 * } $args
 */
function wp_register_ability( string $name, array $args ): ?WP_Ability {
	if ( ! did_action( 'abilities_api_init' ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: 1: abilities_api_init, 2: string value of the ability name. */
				esc_html__( 'Abilities must be registered on the %1$s action. The ability %2$s was not registered.' ),
				'<code>abilities_api_init</code>',
				'<code>' . esc_html( $name ) . '</code>'
			),
			'6.9.0'
		);
		return null;
	}

	return WP_Abilities_Registry::get_instance()->register( $name, $args );
}

/**
 * Unregisters an ability from the Abilities API.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Registry::unregister()
 *
 * @param string $name The name of the registered ability, with its namespace.
 * @return WP_Ability|null The unregistered ability instance on success, null on failure.
 */
function wp_unregister_ability( string $name ): ?WP_Ability {
	return WP_Abilities_Registry::get_instance()->unregister( $name );
}

/**
 * Retrieves a registered ability using Abilities API.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Registry::get_registered()
 *
 * @param string $name The name of the registered ability, with its namespace.
 * @return WP_Ability|null The registered ability instance, or null if it is not registered.
 */
function wp_get_ability( string $name ): ?WP_Ability {
	return WP_Abilities_Registry::get_instance()->get_registered( $name );
}

/**
 * Retrieves all registered abilities using Abilities API.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Registry::get_all_registered()
 *
 * @return \WP_Ability[] The array of registered abilities.
 */
function wp_get_abilities(): array {
	return WP_Abilities_Registry::get_instance()->get_all_registered();
}

/**
 * Registers a new ability category.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Category_Registry::register()
 *
 * @param string              $slug The unique slug for the category. Must contain only lowercase
 *                                  alphanumeric characters and dashes.
 * @param array<string,mixed> $args {
 *     An associative array of arguments for the category.
 *
 *     @type string               $label       The human-readable label for the category.
 *     @type string               $description A description of the category.
 *     @type array<string,mixed>  $meta        Optional. Additional metadata for the category.
 * }
 * @return WP_Ability_Category|null The registered category instance on success, null on failure.
 *
 * @phpstan-param array{
 *   label: string,
 *   description: string,
 *   meta?: array<string,mixed>,
 *   ...<string, mixed>
 * } $args
 */
function wp_register_ability_category( string $slug, array $args ): ?WP_Ability_Category {
	return WP_Abilities_Category_Registry::get_instance()->register( $slug, $args );
}

/**
 * Unregisters an ability category.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Category_Registry::unregister()
 *
 * @param string $slug The slug of the registered category.
 * @return WP_Ability_Category|null The unregistered category instance on success, null on failure.
 */
function wp_unregister_ability_category( string $slug ): ?WP_Ability_Category {
	return WP_Abilities_Category_Registry::get_instance()->unregister( $slug );
}

/**
 * Retrieves a registered ability category.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Category_Registry::get_registered()
 *
 * @param string $slug The slug of the registered category.
 * @return WP_Ability_Category|null The registered category instance, or null if it is not registered.
 */
function wp_get_ability_category( string $slug ): ?WP_Ability_Category {
	return WP_Abilities_Category_Registry::get_instance()->get_registered( $slug );
}

/**
 * Retrieves all registered ability categories.
 *
 * @since 6.9.0
 *
 * @see WP_Abilities_Category_Registry::get_all_registered()
 *
 * @return \WP_Ability_Category[] The array of registered categories.
 */
function wp_get_ability_categories(): array {
	return WP_Abilities_Category_Registry::get_instance()->get_all_registered();
}
