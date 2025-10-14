<?php
/**
 * Abilities API
 *
 * Defines WP_Ability_Category class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.3.0
 */

declare( strict_types = 1 );

/**
 * Encapsulates the properties and methods related to a specific ability category.
 *
 * @since 0.3.0
 *
 * @see WP_Abilities_Category_Registry
 */
final class WP_Ability_Category {

	/**
	 * The unique slug for the category.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	protected $slug;

	/**
	 * The human-readable category label.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	protected $label;

	/**
	 * The detailed category description.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	protected $description;

	/**
	 * The optional category metadata.
	 *
	 * @since 0.3.0
	 * @var array<string,mixed>
	 */
	protected $meta = array();

	/**
	 * Constructor.
	 *
	 * Do not use this constructor directly. Instead, use the `wp_register_ability_category()` function.
	 *
	 * @access private
	 *
	 * @since 0.3.0
	 *
	 * @see wp_register_ability_category()
	 *
	 * @param string              $slug The unique slug for the category.
	 * @param array<string,mixed> $args An associative array of arguments for the category.
	 */
	public function __construct( string $slug, array $args ) {
		if ( empty( $slug ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The category slug cannot be empty.' )
			);
		}

		$this->slug = $slug;

		$properties = $this->prepare_properties( $args );

		foreach ( $properties as $property_name => $property_value ) {
			if ( ! property_exists( $this, $property_name ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: Property name. */
						esc_html__( 'Property "%1$s" is not a valid property for category "%2$s". Please check the %3$s class for allowed properties.' ),
						'<code>' . esc_html( $property_name ) . '</code>',
						'<code>' . esc_html( $this->slug ) . '</code>',
						'<code>' . esc_html( self::class ) . '</code>'
					),
					'0.3.0'
				);
				continue;
			}

			$this->$property_name = $property_value;
		}
	}

	/**
	 * Prepares and validates the properties used to instantiate the category.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string,mixed> $args An associative array of arguments used to instantiate the class.
	 * @return array<string,mixed> The validated and prepared properties.
	 * @throws \InvalidArgumentException if an argument is invalid.
	 *
	 * @phpstan-return array{
	 *   label: string,
	 *   description: string,
	 *   meta?: array<string,mixed>,
	 *   ...<string, mixed>,
	 * }
	 */
	protected function prepare_properties( array $args ): array {
		// Required args must be present and of the correct type.
		if ( empty( $args['label'] ) || ! is_string( $args['label'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The category properties must contain a `label` string.' )
			);
		}

		if ( empty( $args['description'] ) || ! is_string( $args['description'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The category properties must contain a `description` string.' )
			);
		}

		// Optional args only need to be of the correct type if they are present.
		if ( isset( $args['meta'] ) && ! is_array( $args['meta'] ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'The category properties should provide a valid `meta` array.' )
			);
		}

		return $args;
	}

	/**
	 * Retrieves the slug of the category.
	 *
	 * @since 0.3.0
	 *
	 * @return string The category slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Retrieves the human-readable label for the category.
	 *
	 * @since 0.3.0
	 *
	 * @return string The human-readable category label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Retrieves the detailed description for the category.
	 *
	 * @since 0.3.0
	 *
	 * @return string The detailed description for the category.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Retrieves the metadata for the category.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string,mixed> The metadata for the category.
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.3.0
	 * @throws \LogicException If the category is unserialized. This is a security hardening measure to prevent unserialization of the category.
	 */
	public function __wakeup(): void {
		throw new \LogicException( self::class . ' must not be unserialized.' );
	}

	/**
	 * Serialization magic method.
	 *
	 * @since 0.3.0
	 * @throws \LogicException If the category is serialized. This is a security hardening measure to prevent serialization of the category.
	 */
	public function __sleep(): array {
		throw new \LogicException( self::class . ' must not be serialized.' );
	}
}
