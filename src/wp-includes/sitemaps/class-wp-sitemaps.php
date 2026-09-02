<?php
/**
 * Sitemaps: WP_Sitemaps class
 *
 * This is the main class integrating all other classes.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 */

/**
 * Class WP_Sitemaps.
 *
 * @since 5.5.0
 */
#[AllowDynamicProperties]
class WP_Sitemaps {
	/**
	 * The main index of supported sitemaps.
	 *
	 * @since 5.5.0
	 *
	 * @var WP_Sitemaps_Index
	 */
	public $index;

	/**
	 * The main registry of supported sitemaps.
	 *
	 * @since 5.5.0
	 *
	 * @var WP_Sitemaps_Registry
	 */
	public $registry;

	/**
	 * An instance of the renderer class.
	 *
	 * @since 5.5.0
	 *
	 * @var WP_Sitemaps_Renderer
	 */
	public $renderer;

	/**
	 * WP_Sitemaps constructor.
	 *
	 * @since 5.5.0
	 */
	public function __construct() {
		$this->registry = new WP_Sitemaps_Registry();
		$this->renderer = new WP_Sitemaps_Renderer();
		$this->index    = new WP_Sitemaps_Index( $this->registry );
	}

	/**
	 * Initiates all sitemap functionality.
	 *
	 * If sitemaps are disabled, only the rewrite rules will be registered
	 * by this method, in order to properly send 404s.
	 *
	 * @since 5.5.0
	 */
	public function init() {
		// These will all fire on the init hook.
		$this->register_rewrites();

		add_action( 'parse_request', array( $this, 'render_sitemaps' ) );

		if ( ! $this->sitemaps_enabled() ) {
			return;
		}

		$this->register_sitemaps();

		// Add additional action callbacks.
		add_filter( 'robots_txt', array( $this, 'add_robots' ), 0, 2 );
	}

	/**
	 * Determines whether sitemaps are enabled or not.
	 *
	 * @since 5.5.0
	 *
	 * @return bool Whether sitemaps are enabled.
	 */
	public function sitemaps_enabled() {
		$is_enabled = (bool) get_option( 'blog_public' );

		/**
		 * Filters whether XML Sitemaps are enabled or not.
		 *
		 * When XML Sitemaps are disabled via this filter, rewrite rules are still
		 * in place to ensure a 404 is returned.
		 *
		 * @see WP_Sitemaps::register_rewrites()
		 *
		 * @since 5.5.0
		 *
		 * @param bool $is_enabled Whether XML Sitemaps are enabled or not.
		 *                         Defaults to true for public sites.
		 */
		return (bool) apply_filters( 'wp_sitemaps_enabled', $is_enabled );
	}

	/**
	 * Registers and sets up the functionality for all supported sitemaps.
	 *
	 * @since 5.5.0
	 */
	public function register_sitemaps() {
		$providers = array(
			'posts'      => new WP_Sitemaps_Posts(),
			'taxonomies' => new WP_Sitemaps_Taxonomies(),
			'users'      => new WP_Sitemaps_Users(),
		);

		/* @var WP_Sitemaps_Provider $provider */
		foreach ( $providers as $name => $provider ) {
			$this->registry->add_provider( $name, $provider );
		}
	}

	/**
	 * Registers sitemap rewrite tags and routing rules.
	 *
	 * @since 5.5.0
	 */
	public function register_rewrites() {
		// Add rewrite tags.
		add_rewrite_tag( '%sitemap%', '([^?]+)' );
		add_rewrite_tag( '%sitemap-subtype%', '([^?]+)' );

		// Register index route.
		add_rewrite_rule( '^wp-sitemap\.xml$', 'index.php?sitemap=index', 'top' );

		// Register rewrites for the XSL stylesheet.
		add_rewrite_tag( '%sitemap-stylesheet%', '([^?]+)' );
		add_rewrite_rule( '^wp-sitemap\.xsl$', 'index.php?sitemap-stylesheet=sitemap', 'top' );
		add_rewrite_rule( '^wp-sitemap-index\.xsl$', 'index.php?sitemap-stylesheet=index', 'top' );

		// Register routes for providers.
		add_rewrite_rule(
			'^wp-sitemap-([a-z]+?)-([a-z\d_-]+?)-(\d+?)\.xml$',
			'index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			'^wp-sitemap-([a-z]+?)-(\d+?)\.xml$',
			'index.php?sitemap=$matches[1]&paged=$matches[2]',
			'top'
		);
	}

	/**
	 * Renders sitemap templates based on rewrite rules.
	 *
	 * @since 5.5.0
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 *
	 * @param WP|null $wp Optional. Current WordPress environment instance.
	 */
	public function render_sitemaps( $wp = null ) {
		global $wp_query;

		$query_vars = $wp instanceof WP ? $wp->query_vars : $wp_query->query_vars;

		$sitemap = '';
		if ( ! empty( $query_vars['sitemap'] ) && is_scalar( $query_vars['sitemap'] ) ) {
			$sitemap = sanitize_text_field( $query_vars['sitemap'] );
		}

		$object_subtype = '';
		if ( ! empty( $query_vars['sitemap-subtype'] ) && is_scalar( $query_vars['sitemap-subtype'] ) ) {
			$object_subtype = sanitize_text_field( $query_vars['sitemap-subtype'] );
		}

		$stylesheet_type = '';
		if ( ! empty( $query_vars['sitemap-stylesheet'] ) && is_scalar( $query_vars['sitemap-stylesheet'] ) ) {
			$stylesheet_type = sanitize_text_field( $query_vars['sitemap-stylesheet'] );
		}

		$paged = 0;
		if ( ! empty( $query_vars['paged'] ) && is_scalar( $query_vars['paged'] ) ) {
			$paged = absint( $query_vars['paged'] );
		}

		// Bail early if this isn't a sitemap or stylesheet route.
		if ( ! ( $sitemap || $stylesheet_type ) ) {
			return;
		}

		if ( ! $this->sitemaps_enabled() ) {
			$wp_query->set_404();
			status_header( 404 );

			if ( $wp instanceof WP ) {
				exit;
			}

			return;
		}

		if ( $wp instanceof WP ) {
			$this->redirect_sitemap_to_canonical_url( $sitemap, $object_subtype, $stylesheet_type, $paged );
		}

		// Render stylesheet if this is stylesheet route.
		if ( $stylesheet_type ) {
			$stylesheet = new WP_Sitemaps_Stylesheet();

			$stylesheet->render_stylesheet( $stylesheet_type );
			exit;
		}

		// Render the index.
		if ( 'index' === $sitemap ) {
			$sitemap_list = $this->index->get_sitemap_list();

			$this->renderer->render_index( $sitemap_list );
			exit;
		}

		$provider = $this->registry->get_provider( $sitemap );

		if ( ! $provider ) {
			$wp_query->set_404();
			status_header( 404 );

			if ( $wp instanceof WP ) {
				exit;
			}

			return;
		}

		if ( empty( $paged ) ) {
			$paged = 1;
		}

		$url_list = $provider->get_url_list( $paged, $object_subtype );

		// Force a 404 and bail early if no URLs are present.
		if ( empty( $url_list ) ) {
			$wp_query->set_404();
			status_header( 404 );

			if ( $wp instanceof WP ) {
				exit;
			}

			return;
		}

		$this->renderer->render_sitemap( $url_list );
		exit;
	}

	/**
	 * Redirects sitemap requests to their canonical URL.
	 *
	 * @since x.x.x
	 *
	 * @param string $sitemap         Sitemap name.
	 * @param string $object_subtype  Sitemap object subtype.
	 * @param string $stylesheet_type Sitemap stylesheet type.
	 * @param int    $paged           Sitemap page number.
	 */
	private function redirect_sitemap_to_canonical_url( $sitemap, $object_subtype, $stylesheet_type, $paged ) {
		global $wp_rewrite;

		if (
			! $wp_rewrite->using_permalinks()
			|| empty( $_SERVER['HTTP_HOST'] )
			|| ! is_scalar( $_SERVER['HTTP_HOST'] )
			|| empty( $_SERVER['REQUEST_URI'] )
			|| ! is_scalar( $_SERVER['REQUEST_URI'] )
		) {
			return;
		}

		$redirect_url = '';

		if ( $sitemap ) {
			$redirect_url = get_sitemap_url( $sitemap, $object_subtype, $paged );
		} elseif ( 'sitemap' === $stylesheet_type ) {
			$redirect_url = home_url( '/wp-sitemap.xsl' );
		} elseif ( 'index' === $stylesheet_type ) {
			$redirect_url = home_url( '/wp-sitemap-index.xsl' );
		}

		if ( ! $redirect_url ) {
			return;
		}

		$request_uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );

		if ( is_string( $query_string ) && '' !== $query_string ) {
			wp_parse_str( $query_string, $query_args );
			$redirect_url = add_query_arg(
				array_diff_key(
					$query_args,
					array_flip( array( 'sitemap', 'sitemap-subtype', 'sitemap-stylesheet', 'paged' ) )
				),
				$redirect_url
			);
		}

		$requested_url  = is_ssl() ? 'https://' : 'http://';
		$requested_url .= (string) wp_unslash( $_SERVER['HTTP_HOST'] );
		$requested_url .= $request_uri;

		if ( $requested_url === $redirect_url ) {
			return;
		}

		if ( wp_redirect( $redirect_url, 301 ) ) {
			exit;
		}
	}

	/**
	 * Redirects a URL to the wp-sitemap.xml
	 *
	 * @since 5.5.0
	 * @deprecated 6.7.0 Deprecated in favor of {@see WP_Rewrite::rewrite_rules()}
	 *
	 * @param bool     $bypass Pass-through of the pre_handle_404 filter value.
	 * @param WP_Query $query  The WP_Query object.
	 * @return bool Bypass value.
	 */
	public function redirect_sitemapxml( $bypass, $query ) {
		_deprecated_function( __FUNCTION__, '6.7.0' );

		// If a plugin has already utilized the pre_handle_404 function, return without action to avoid conflicts.
		if ( $bypass ) {
			return $bypass;
		}

		// 'pagename' is for most permalink types, name is for when the %postname% is used as a top-level field.
		if ( 'sitemap-xml' === $query->get( 'pagename' )
			|| 'sitemap-xml' === $query->get( 'name' )
		) {
			wp_safe_redirect( $this->index->get_index_url() );
			exit();
		}

		return $bypass;
	}

	/**
	 * Adds the sitemap index to robots.txt.
	 *
	 * @since 5.5.0
	 *
	 * @param string $output    robots.txt output.
	 * @param bool   $is_public Whether the site is public.
	 * @return string The robots.txt output.
	 */
	public function add_robots( $output, $is_public ) {
		if ( $is_public ) {
			$output .= "\nSitemap: " . esc_url( $this->index->get_index_url() ) . "\n";
		}

		return $output;
	}
}
