<?php
/**
 * Template types.
 *
 * @package WordPress
 * @since 5.9.0
 */

/**
 * Returns a filtered list of default template types, containing their
 * localized titles and descriptions.
 *
 * @return array The default template types.
 */
function gutenberg_get_default_template_types() {
	$default_template_types = array(
		'index'          => array(
			'title'       => _x( 'Index', 'Template name' ),
			'description' => __( 'The default template used when no other template is available. This is a required template in WordPress.' ),
		),
		'home'           => array(
			'title'       => _x( 'Home', 'Template name' ),
			'description' => __( 'Template used for the main page that displays blog posts. This is the front page by default in WordPress. If a static front page is set, this is the template used for the page that contains the latest blog posts.' ),
		),
		'front-page'     => array(
			'title'       => _x( 'Front Page', 'Template name' ),
			'description' => __( 'Template used to render the front page of the site, whether it displays blog posts or a static page. The front page template takes precedence over the "Home" template.' ),
		),
		'singular'       => array(
			'title'       => _x( 'Singular', 'Template name' ),
			'description' => __( 'Template used for displaying single views of the content. This template is a fallback for the Single, Post, and Page templates, which take precedence when they exist.' ),
		),
		'single'         => array(
			'title'       => _x( 'Single Post', 'Template name' ),
			'description' => __( 'Template used to display a single blog post.' ),
		),
		'page'           => array(
			'title'       => _x( 'Page', 'Template name' ),
			'description' => __( 'Template used to display individual pages.' ),
		),
		'archive'        => array(
			'title'       => _x( 'Archive', 'Template name' ),
			'description' => __( 'The archive template displays multiple entries at once. It is used as a fallback for the Category, Author, and Date templates, which take precedence when they are available.' ),
		),
		'author'         => array(
			'title'       => _x( 'Author', 'Template name' ),
			'description' => __( 'Archive template used to display a list of posts from a single author.' ),
		),
		'category'       => array(
			'title'       => _x( 'Category', 'Template name' ),
			'description' => __( 'Archive template used to display a list of posts from the same category.' ),
		),
		'taxonomy'       => array(
			'title'       => _x( 'Taxonomy', 'Template name' ),
			'description' => __( 'Archive template used to display a list of posts from the same taxonomy.' ),
		),
		'date'           => array(
			'title'       => _x( 'Date', 'Template name' ),
			'description' => __( 'Archive template used to display a list of posts from a specific date.' ),
		),
		'tag'            => array(
			'title'       => _x( 'Tag', 'Template name' ),
			'description' => __( 'Archive template used to display a list of posts with a given tag.' ),
		),
		'attachment'     => array(
			'title'       => __( 'Media' ),
			'description' => __( 'Template used to display individual media items or attachments.' ),
		),
		'search'         => array(
			'title'       => _x( 'Search', 'Template name' ),
			'description' => __( 'Template used to display search results.' ),
		),
		'privacy-policy' => array(
			'title'       => __( 'Privacy Policy' ),
			'description' => '',
		),
		'404'            => array(
			'title'       => _x( '404', 'Template name' ),
			'description' => __( 'Template shown when no content is found.' ),
		),
	);

	/**
	 * Filters the list of template types.
	 *
	 * @param array $default_template_types An array of template types, formatted as [ slug => [ title, description ] ].
	 *
	 * @since 5.x.x
	 */
	return apply_filters( 'default_template_types', $default_template_types );
}

/**
 * Converts the default template types array from associative to indexed,
 * to be used in JS, where numeric keys (e.g. '404') always appear before alphabetical
 * ones, regardless of the actual order of the array.
 *
 * From slug-keyed associative array:
 * `[ 'index' => [ 'title' => 'Index', 'description' => 'Index template' ] ]`
 *
 * ...to indexed array with slug as property:
 * `[ [ 'slug' => 'index', 'title' => 'Index', 'description' => 'Index template' ] ]`
 *
 * @return array The default template types as an indexed array.
 */
function gutenberg_get_indexed_default_template_types() {
	$indexed_template_types = array();
	$default_template_types = gutenberg_get_default_template_types();
	foreach ( $default_template_types as $slug => $template_type ) {
		$template_type['slug']    = (string) $slug;
		$indexed_template_types[] = $template_type;
	}
	return $indexed_template_types;
}

/**
 * Return a list of all overrideable default template type slugs.
 *
 * @see get_query_template
 *
 * @return string[] List of all overrideable default template type slugs.
 */
function gutenberg_get_template_type_slugs() {
	return array_keys( gutenberg_get_default_template_types() );
}
