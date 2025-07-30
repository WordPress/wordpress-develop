<?php
/**
 * Server-side rendering of the `core/term-template` block.
 *
 * @package WordPress
 */

/**
 * Renders the `core/term-template` block on the server.
 *
 * @since 6.9.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the output of the term template.
 */
function render_block_core_term_template( $attributes, $content, $block ) {
	if ( ! isset( $block->context ) || ! isset( $attributes ) ) {
		return '';
	}

	$query_block_context = $block->context;

	if ( empty( $query_block_context['termQuery'] ) ) {
		return '';
	}

	$query = $query_block_context['termQuery'];

	$query_args = array(
		'per_page'   => $query['perPage'] ?? 100,
		'page'       => $query['pages'] ?? 1,
		'taxonomy'   => $query['taxonomy'] ?? 'category',
		'order'      => $query['order'] ?? 'asc',
		'orderby'    => $query['orderBy'] ?? 'name',
		'hide_empty' => $query['hideEmpty'] ?? true,
		'include'    => $query['include'] ?? array(),
		'exclude'    => $query['exclude'] ?? array(),
	);

	$terms_query = new WP_Term_Query( $query_args );
	$terms       = $terms_query->get_terms();

	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	// Handle parent.
	if ( isset( $query['parent'] ) ) {
		$terms = array_filter(
			$terms,
			function ( $term ) use ( $query ) {
				return $term->parent === $query['parent'];
			}
		);
	}

	// Handle hierarchical list.
	$is_hierarchical = ! empty( $query['hierarchical'] );

	if ( ! $is_hierarchical && isset( $query['parent'] ) ) {
		$query_args['parent'] = $query['parent'];
	}

	if ( $is_hierarchical ) {
		$content = render_block_core_term_template_hierarchical( $terms, $block );
	} else {
		$content = render_block_core_term_template_flat( $terms, $block );
	}

	return sprintf(
		'<ul>%s</ul>',
		$content
	);
}

/**
 * Renders terms in a flat list structure.
 *
 * @since 6.9.0
 *
 * @param array    $terms Array of WP_Term objects.
 * @param WP_Block $block Block instance.
 *
 * @return string HTML content for flat terms list.
 */
function render_block_core_term_template_flat( $terms, $block ) {
	$content = '';
	foreach ( $terms as $term ) {
		$content .= render_block_core_term_template_single( $term, $block );
	}
	return $content;
}

/**
 * Renders terms in a hierarchical structure.
 *
 * @since 6.9.0
 *
 * @param array    $terms Array of WP_Term objects.
 * @param WP_Block $block Block instance.
 * @param int      $parent_id Parent term ID (0 for top-level).
 *
 * @return string HTML content for hierarchical terms list.
 */
function render_block_core_term_template_hierarchical( $terms, $block, $parent_id = 0 ) {
	$content = '';

	// Filter terms for current parent.
	$child_terms = array_filter(
		$terms,
		function ( $term ) use ( $parent_id ) {
			return $term->parent === $parent_id;
		}
	);

	foreach ( $child_terms as $term ) {
		$term_content = render_block_core_term_template_single( $term, $block );

		$children_content = render_block_core_term_template_hierarchical( $terms, $block, $term->term_id );

		if ( ! empty( $children_content ) ) {
			$term_content = str_replace( '</li>', '<ul>' . $children_content . '</ul></li>', $term_content );
		}

		$content .= $term_content;
	}

	return $content;
}

/**
 * Renders a single term with its inner blocks.
 *
 * @since 6.9.0
 *
 * @param WP_Term  $term  Term object.
 * @param WP_Block $block Block instance.
 *
 * @return string HTML content for a single term.
 */
function render_block_core_term_template_single( $term, $block ) {
	$inner_blocks  = $block->inner_blocks;
	$block_content = '';

	if ( ! empty( $inner_blocks ) ) {
		$term_id  = $term->term_id;
		$taxonomy = $term->taxonomy;

		foreach ( $inner_blocks as $inner_block ) {
			$inner_block->context['termId']   = $term_id;
			$inner_block->context['taxonomy'] = $taxonomy;

			$block_content .= $inner_block->render( array( 'dynamic' => true ) );
		}
	}

	$term_classes = implode( ' ', array( 'wp-block-term', 'term-' . $term->term_id ) );

	$term_name = esc_html( $term->name );

	return '<li class="' . esc_attr( $term_classes ) . '">' . $term_name . $block_content . '</li>';
}

/**
 * Registers the `core/term-template` block on the server.
 *
 * @since 6.9.0
 */
function register_block_core_term_template() {
	register_block_type_from_metadata(
		__DIR__ . '/term-template',
		array(
			'render_callback' => 'render_block_core_term_template',
		)
	);
}
add_action( 'init', 'register_block_core_term_template' );
