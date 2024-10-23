<?php
/**
 * WordPress Bits system, for replacing placeholder tokens in a document,
 * for semantically independent content types, with externally-sourced data.
 *
 * @package    WordPress
 * @subpackage Bits
 * @since      {WP_VERSION}
 */

declare( strict_types=1 );

/**
 * Hello Dolly Bit Rendering function.
 *
 * @param string     $name        Fully-qualified name of the matched Bit.
 *                                E.g. "core/post-author".
 * @param string     $output_type Either "rich-text" or "plaintext" depending on where the Bit is found.
 *                                E.g. when inside an HTML attribute or TITLE element, only plaintext is allowed,
 *                                But when found inside a P element, rich formatting is allowed.
 * @param array|null $attributes  Configured parameters of the Bit, if provided.
 *                                E.g. `<//wp-bit:hello-dolly year="2024">` produces `array( 'year' => '2024' )`.
 * @param mixed      $context     Context passed into the Bit from the surrounding system.
 *                                This argument is not yet specified and will always be `null`.
 *
 * @return mixed An HTML template for rendering into the page, either as a plain string or in array form.
 */
function core_bit_hello_dolly( string $name, string $output_type, ?array $attributes, mixed $context ): mixed {
	static $vocalists = array(
		'Julie Dahle Aagård',
		'Mindi Abair',
		'Lorez Alexandria',
		'Karrin Allyson',
		'Michelle Amato',
		'Ernestine Anderson',
		'Ivie Anderson',
	);

	$vocalist = $vocalists[ wp_rand( 0, count( $vocalists ) - 1 ) ];

	switch ( $output_type ) {
		case 'plaintext':
			return $vocalist;

		case 'rich-text':
			return array(
				'<span data-vocalist="</%name>"></%name></span>',
				array( 'name' => $vocalist ),
			);
	}
}

class WP_Hello_Dolly_Bit extends BitProvider {
	/**
	 * @inheritDoc
	 */
	public function handle_plaintext( string $bit_name, ?array $attributes ): string {
		return self::get_random_vocalist();
	}

	/**
	 * @inheritDoc
	 */
	public function handle_richtext( string $bit_name, ?array $attributes ): WP_HTML_Template {
		$name = self::get_random_vocalist();

		return new WP_HTML_Template(
			'<span data-vocalist="</%name>"></%name></span>',
			array( 'name' => $name )
		);
	}

	/**
	 * Returns the name of a random Jazz vocalist.
	 *
	 * @return string
	 */
	private function get_random_vocalist(): string {
		static $vocalists = array(
			'Julie Dahle Aagård',
			'Mindi Abair',
			'Lorez Alexandria',
			'Karrin Allyson',
			'Michelle Amato',
			'Ernestine Anderson',
			'Ivie Anderson',
		);

		return $vocalists[ wp_rand( 0, count( $vocalists ) - 1 ) ];
	}
}

abstract class BitProvider {
	/**
	 * Performs initialize of Bit Provider during WordPress bootup.
	 */
	public function register(): void {
		// This is optional.
	};

	/**
	 * Called to source content in plaintext contexts. For example, when a
	 * Bit is found within an HTML attribute, or inside a `TITLE` element.
	 *
	 * @see WP_HTML_Template
	 *
	 * @param string     $bit_name   Full name with namespace of matched Bit, e.g. "core/post-author".
	 * @param array|null $attributes Configured attributes found on Bit, if found, otherwise `null`.
	 *
	 * @return string Plaintext value for provided content.
	 */
	abstract public function handle_plaintext( string $bit_name, ?array $attributes ): string;

	/**
	 * Called to source content in Rich Text (HTML Markup) contexts. For example,
	 * when a Bit is found within the inner content of an HTML tag.
	 *
	 * @see WP_HTML_Template
	 *
	 * @param string     $bit_name   Full name with namespace of matched Bit, e.g. "core/post-author".
	 * @param array|null $attributes Configured attributes found on Bit, if found, otherwise `null`.
	 * @return WP_HTML_Template HTML template for provided content: a string or array.
	 */
	abstract public function handle_richtext( string $bit_name, ?array $attributes ): WP_HTML_Template;
}
