<?php

/**
 * @since 6.3.0
 */
class WP_HTML {
	/**
	 * Serializes an HTML tag, escaping inner text if provided.
	 *
	 * Example:
	 *     WP_HTML::tag( 'p', array( 'class' => 'summary' ), '<p> makes a paragraph.' );
	 *     // <p class="summary">&lt;p> makes a paragraph.</p>
	 *
	 *     WP_HTML::tag( 'input', array( 'enabled' => true, 'inert' => false, 'type' => 'text', 'value' => '<3 HTML' ) );
	 *     // <input enabled type="text" value="&lt;3 HTML">
	 *
	 * @since 6.3.0
	 *
	 * @param string $tag_name   Name of HTML tag to create, e.g. "div".
	 * @param array  $attributes Name-value pairs of HTML attributes to create; `false` values are excluded from generated HTML.
	 * @param string $inner_text Raw inner text which browsers should render to display verbatim, not as HTML.
	 * @return string Generated HTML corresponding to tag described by inputs.
	 */
	public static function tag( $tag_name, $attributes = null, $inner_text = '' ) {
		return WP_HTML::tag_with_inner_html( $tag_name, $attributes, esc_html( $inner_text ) );
	}

	/**
	 * Serializes an HTML tag, inserting verbatim inner HTML if provided.
	 *
	 * Example:
	 *     WP_HTML::tag_with_inner_html( 'p', array( 'class' => 'summary' ), 'this <em>is</em> important' );
	 *     // <p class="summary">this <em>is</em> important</p>
	 *
	 *     WP_HTML::tag_with_inner_html( 'div', null, WP_HTML::tag( 'p', null, 'Fire & Ice' ) . ' & Bubblegum');
	 *     // <div><p>Fire &amp; Ice</p> & Bubblegum</div>
	 *                     └─┬─┘    └────┴── Not Escaped because it was passed into `WP_HTML::tag_with_inner_html`.
	 *                       └────────────── Escaped because it was created with `WP_HTML::tag`.
	 *
	 * @since 6.3.0
	 *
	 * @param string $tag_name   Name of HTML tag to create, e.g. "div".
	 * @param array  $attributes Name-value pairs of HTML attributes to create; `false` values are excluded from generated HTML.
	 * @param string $inner_html Already-escaped inner HTML which contains HTML syntax that browsers should interpret at HTML.
	 * @return string Generated HTML corresponding to tag described by inputs.
	 */
	public static function tag_with_inner_html( $tag_name, $attributes = null, $inner_html = '' ) {
		$is_void = WP_HTML_Spec::is_void_element( $tag_name );
		$html = $is_void ? "<{$tag_name}>" : "<{$tag_name}>{$inner_html}</{$tag_name}>";
		if ( $is_void && ! empty( $inner_html ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					// translator: 1: The name of a given HTML tag.
					__( 'HTML void element %1$s cannot contain child nodes.' ),
					"<{$tag_name}>"
				),
				'6.3.0'
			);
		}

		$p = new WP_HTML_Tag_Processor( $html );

		if ( is_array( $attributes ) ) {
			$p->next_tag();
			foreach ( $attributes as $name => $value ) {
				$p->set_attribute( $name, $value );
			}
		}

		return $p->get_updated_html();
	}
}
