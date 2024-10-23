<?php

declare( strict_types=1 );

class WP_HTML_Template {
	/**
	 * HTML Template indicating where to mix a static HTML template
	 * and placeholders for dynamic values.
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Stores data necessary to render a template, if any required.
	 *
	 * @var array|null
	 */
	public $data;

	/**
	 * Constructor function.
	 *
	 * Example:
	 *
	 *     // No placeholders are required, only a template string.
	 *     new WP_HTML_Template( '<p>Hello, World!</p>' );
	 *
	 *     // Placeholders for simple substitution.
	 *     new WP_HTML_Template( '<p>Hello, </%name>!</p>', array( 'name' => $name ) );
	 *
	 *     // Spread-operator for sets of attributes.
	 *     new WP_HTML_Template(
	 *         '<button ...interactivity_args>Click me!</button>',
	 *         array(
	 *             'data-wp-text="context.buttonLabel"',
	 *             'data-wp-click="actions.clickButton",
	 *         )
	 *     );
	 *
	 * @param string     $template Static HTML template, possibly including placeholders.
	 * @param array|null $data     Optional. Data provided for placeholders, if any.
	 */
	public function __construct( string $template, array $data = null ) {
		$this->template = $template;
		$this->data     = $data;
	}
}
