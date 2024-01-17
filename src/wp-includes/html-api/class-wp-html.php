<?php
/**
 * HTML API: WP_HTML class
 *
 * Provides a public interface for HTML-related functionality in WordPress.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.5.0
 */

/**
 * WP_HTML class.
 *
 * @since 6.5.0
 */
class WP_HTML {
	/**
	 * Generates HTML for a given tag and attribute set.
	 *
	 * Although this doesn't currently support nesting HTML tags inside
	 * the generated tag, it may do so in the future. When that happens
	 * the `$inner_text` parameter will transform into `$inner_content`
	 * and allow passing an array of strings and other tags to nest.
	 *
	 * Example:
	 *
	 *     echo WP_HTML::tag( 'div', array( 'class' => 'is-safe' ), 'Hello, world!' );
	 *     // <div class="is-safe">Hello, world!</div>
	 *
	 *     echo WP_HTML::tag( 'input', array( 'type' => '"></script>', 'disabled' => true ), 'Is this > that?' );
	 *     // <input type="&quot;&gt;&lt;/script&gt;" disabled>
	 *
	 *     echo WP_HTML::tag( 'p', null, 'Is this > that?' );
	 *     // <p>Is this &gt; that?</p>
	 *
	 *     echo WP_HTML::tag( 'wp-emoji', array( 'name' => ':smile:' ), null, 'self-closing' );
	 *     // <wp-emoji name=":smile:" />
	 *
	 * @since 6.5.0
	 *
	 * @param string  $tag_name     Name of tag to create.
	 * @param ?array  $attributes   Key/value pairs of attribute names and their values.
	 *                              Values may be boolean, null, or a string.
	 * @param ?string $inner_text   Will always be escaped to preserve the given string in the rendered page.
	 * @param ?string $element_type 'self-closing' to self-close the generated HTML for a custom-element.
	 *                              This only generates the self-closing flag for non-HTML tags, as HTML
	 *                              itself contains no self-closing tags.
	 * @return string|null          Generated HTML for the tag if provided valid inputs, otherwise null.
	 */
	public static function tag( $tag_name, $attributes = null, $inner_text = null, $element_type = 'html' ) {
		if (
			! is_string( $tag_name ) ||
			( null !== $attributes && ! is_array( $attributes ) ) ||
			( null !== $inner_text && ! is_string( $inner_text ) )
		) {
			return null;
		}

		// Validate tag name.
		if ( 0 === strlen( $tag_name ) ) {
			return null;
		}

		// Compare the first byte against [a-zA-Z].
		$tag_initial = ord( $tag_name[0] );
		if (
			// Before A or after Z.
			( $tag_initial < 65 || $tag_initial > 90 ) &&

			// Before a or after z.
			( $tag_initial < 97 || $tag_initial > 122 )
		) {
			return null;
		}
		if ( strlen( $tag_name ) !== strcspn( $tag_name, " \t\f\r\n/>" ) ) {
			return null;
		}

		$is_void     = WP_HTML_Processor::is_void( $tag_name );
		$self_closes = (
			! $is_void &&
			'self-closing' === $element_type &&
			! WP_HTML_Processor::is_html_tag( $tag_name )
		);

		/*
		 * This is unexpected with the closing tag, but it's required
		 * for special tags with modifiable text, such as TEXTAREA.
		 */
		$source_html = $self_closes ? "<{$tag_name}/></{$tag_name}>" : "<{$tag_name}></{$tag_name}>";

		$processor = new WP_HTML_Tag_Processor( $source_html );
		$processor->next_tag();

		if ( null !== $attributes ) {
			foreach ( $attributes as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
		}

		/*
		 * Strip off expected closing tag; it will be appropriately
		 * re-added if necessary after appending the inner text.
		 */
		$html = substr( $processor->get_updated_html(), 0, -strlen( "</{$tag_name}>" ) );

		if ( $is_void || $self_closes ) {
			return $html;
		}

		if ( $inner_text ) {
			$big_tag_name = strtoupper( $tag_name );

			/*
			 * Since HTML PRE and TEXTAREA elements strip a leading newline, if
			 * their inner content contains a leading newline, then they _need_
			 * to begin with a leading newline before the inner text so that it
			 * doesn't confuse the syntax for the content.
			 */
			if (
				( 'PRE' === $big_tag_name || 'TEXTAREA' === $big_tag_name ) &&
				"\n" === $inner_text[0]
			) {
				$html .= "\n";
			}

			switch ( $big_tag_name ) {
				case 'SCRIPT':
				case 'STYLE':
					/*
					 * Over-zealously prevent escaping from SCRIPT and STYLE tags.
					 * It would be more complete to run the Tag Processor and look
					 * for the appropriate closers, but that requires parsing the
					 * contents which could add unexpected cost. This simplification
					 * will reject some rare and valid SCRIPT and STYLE text contents,
					 * but will never allow invalid ones.
					 */
					if ( false !== stripos( $inner_text, "</{$big_tag_name}" ) ) {
						return null;
					}
					$html .= $inner_text;
					break;

				default:
					$html .= esc_html( $inner_text );
			}
		}

		$html .= "</{$tag_name}>";

		return $html;
	}
}
