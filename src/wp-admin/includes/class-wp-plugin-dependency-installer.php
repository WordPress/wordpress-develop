<?php
/**
 * Dependencies manager for plugins.
 *
 * @package dependencies-manager.
 * @since 1.0
 *
 * @see https://github.com/afragen/wp-dependency-installer
 */

/**
 * Class WP_Plugin_Dependency_Installer
 *
 * Configuration
 * Use either a JSON file named `wp-dependencies.json` that is located in your plugin
 * or theme root, or an associative array.
 *
 * Example: JSON file
 * [
 *  {
 *    "name": "Query Monitor",
 *    "slug": "query-monitor/query-monitor.php",
 *    "uri": "https://wordpress.org/plugins/query-monitor/",
 *    "required": false
 *  },
 *  {
 *    "name": "WooCommerce",
 *    "slug": "woocommerce/woocommerce.php",
 *    "uri": "https://wordpress.org/plugins/woocommerce/",
 *    "required": true
 *  }
 * ]
 *
 * Example associative array
 * $config = array(
 *  array(
 *      'name'     => 'Hello Dolly',
 *      'slug'     => 'hello-dolly/hello.php',
 *      'uri'      => 'https://wordpress.org/plugins/hello-dolly',
 *      'required' => true,
 *  ),
 * );
 *
 * Initialize: The command to initialize is as follows.
 *
 * Load the class.
 * require_once ABSPATH . 'wp-admin/includes/class-wp-plugin-dependency-installer.php';
 *
 * Load the configuration and run.
 * If only using JSON config.
 * \WP_Plugin_Dependency_Installer::instance(__DIR__)->run();
 *
 * If using JSON config and/or configuration array.
 * \WP_Plugin_Dependency_Installer::instance( __DIR__ )->register( $config )->run();
 *
 * Admin notice format.
 * You must add `dependency-installer` as well as `data-dismissible='dependency-installer-<plugin basename>-<timeout>'`
 * to the admin notice div class. <timeout> values are from one day '1' to 'forever'. Default timeout is 14 days.
 *
 * Example using Query Monitor with a 14 day dismissible notice.
 * <div class="notice-warning notice is-dismissible dependency-installer" data-dismissible="dependency-installer-query-monitor-14">...</div>
 *
 * Example filter to adjust timeout.
 * Use this filter to adjust the timeout for the dismissal. Default is 14 days.
 * This example filter can be used to modify the default timeout.
 * The example filter will change the default timout for all plugin dependencies.
 * You can specify the exact plugin timeout by modifying the following line in the filter.
 *
 * $timeout = 'query-monitor' !== $source ? $timeout : 30;
 *
 * add_filter(
 *  'wp_plugin_dependency_timeout',
 *  function( $timeout, $source ) {
 *      $timeout = basename( __DIR__ ) !== $source ? $timeout : 30;
 *      return $timeout;
 *  },
 *  10,
 *  2
 * );
 */
class WP_Plugin_Dependency_Installer {
	/**
	 * Holds the JSON file contents.
	 *
	 * @var array $config
	 */
	private $config;

	/**
	 * Holds the calling plugin/theme file path.
	 *
	 * @var string $source
	 */
	private static $caller;

	/**
	 * Holds the calling plugin/theme slug.
	 *
	 * @var string $source
	 */
	private static $source;

	/**
	 * Holds names of installed dependencies for admin notices.
	 *
	 * @var array $notices
	 */
	private $notices;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->config  = array();
		$this->notices = array();
		require_once 'class-wp-dismiss-notice.php';
	}

	/**
	 * Factory.
	 *
	 * @param string $caller File path to calling plugin/theme.
	 */
	public static function instance( $caller = false ) {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}
		self::$caller = $caller;
		self::$source = ! $caller ? false : basename( $caller );

		return $instance;
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_dependency_installer', array( $this, 'ajax_router' ) );

		// Initialize WP_Dismiss_Notice dependency.
		add_action( 'admin_init', array( 'WP_Dismiss_Notice', 'init' ) );
	}

	/**
	 * Let's get going.
	 * First load data from wp-dependencies.json if present.
	 * Then load hooks needed to run.
	 *
	 * @param string $caller Path to plugin or theme calling the framework.
	 *
	 * @return self
	 */
	public function run( $caller = false ) {
		$caller = ! $caller ? self::$caller : $caller;
		$config = $this->json_file_decode( $caller . '/wp-dependencies.json' );
		if ( ! empty( $config ) ) {
			$this->register( $config, $caller );
		}
		if ( ! empty( $this->config ) ) {
			$this->load_hooks();
		}

		return $this;
	}

	/**
	 * Decode JSON config data from a file.
	 *
	 * @param string $json_path File path to JSON config file.
	 *
	 * @return bool|array $config
	 */
	public function json_file_decode( $json_path ) {
		$config = array();
		if ( file_exists( $json_path ) ) {
			$config = file_get_contents( $json_path );
			$config = json_decode( $config, true );
		}

		return $config;
	}

	/**
	 * Register dependencies (supports multiple instances).
	 *
	 * @param array  $config JSON config as array.
	 * @param string $caller Path to plugin or theme calling the framework.
	 *
	 * @return self
	 */
	public function register( $config, $caller = false ) {
		$caller = ! $caller ? self::$caller : $caller;
		$source = ! self::$source ? basename( $caller ) : self::$source;
		foreach ( $config as $dependency ) {
			// Save a reference of current dependent plugin.
			$dependency['source']    = $source;
			$dependency['sources'][] = $source;
			$slug                    = $dependency['slug'];
			// Keep a reference of all dependent plugins.
			if ( isset( $this->config[ $slug ] ) ) {
				$dependency['sources'] = array_merge( $this->config[ $slug ]['sources'], $dependency['sources'] );
			}
			// Update config.
			if ( ! isset( $this->config[ $slug ] ) || $this->is_required( $dependency ) ) {
				$this->config[ $slug ] = $dependency;
			}
		}

		return $this;
	}

	/**
	 * Process the registered dependencies.
	 */
	private function apply_config() {
		foreach ( $this->config as $dependency ) {
			$download_link               = null;
			$uri                         = $dependency['uri'];
			$slug                        = $dependency['slug'];
			$uri_args                    = parse_url( $uri );
			$port                        = isset( $uri_args['port'] ) ? $uri_args['port'] : null;
			$api                         = isset( $uri_args['host'] ) ? $uri_args['host'] : null;
			$api                         = ! $port ? $api : "{$api}:{$port}";
			$scheme                      = isset( $uri_args['scheme'] ) ? $uri_args['scheme'] : null;
			$scheme                      = null !== $scheme ? $scheme . '://' : 'https://';
			$path                        = isset( $uri_args['path'] ) ? $uri_args['path'] : null;
			$owner_repo                  = str_replace( '.git', '', trim( $path, '/' ) );
			$download_link               = $this->get_dot_org_latest_download( basename( $owner_repo ) );
			$dependency['download_link'] = $download_link;
			$this->config[ $slug ]       = $dependency;
		}
	}

	/**
	 * Get lastest download link from WordPress API.
	 *
	 * @param  string $slug Plugin slug.
	 * @return string $download_link
	 */
	private function get_dot_org_latest_download( $slug ) {
		$download_link = get_site_transient( 'wpdi-' . md5( $slug ) );

		if ( ! $download_link ) {
			$url           = 'https://api.wordpress.org/plugins/info/1.1/';
			$url           = add_query_arg(
				array(
					'action'                        => 'plugin_information',
					rawurlencode( 'request[slug]' ) => $slug,
				),
				$url
			);
			$response      = wp_remote_get( $url );
			$response      = json_decode( wp_remote_retrieve_body( $response ) );
			$download_link = empty( $response )
				? "https://downloads.wordpress.org/plugin/{$slug}.zip"
				: $response->download_link;

			set_site_transient( 'wpdi-' . md5( $slug ), $download_link, DAY_IN_SECONDS );
		}

		return $download_link;
	}

	/**
	 * Determine if dependency is active or installed.
	 */
	public function admin_init() {
		// Get the gears turning.
		$this->apply_config();

		// Generate admin notices.
		foreach ( $this->config as $slug => $dependency ) {
			$is_required = $this->is_required( $dependency );

			if ( $is_required ) {
				$this->modify_plugin_row( $slug );
			}

			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( $this->is_active( $slug ) ) {
				// Do nothing.
			} elseif ( $this->is_installed( $slug ) ) {
				$this->notices[] = $this->activate_notice( $slug );
			} else {
				$this->notices[] = $this->install_notice( $slug );
			}
		}
	}

	/**
	 * Register jQuery AJAX.
	 */
	public function admin_footer() {
		?>
			<script>
				(function ($) {
					$(function () {
						$(document).on('click', '.wpdi-button', function () {
							var $this = $(this);
							var $parent = $(this).closest('p');
							$parent.html('Running...');
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: $this.attr('data-action'),
								slug  : $this.attr('data-slug')
							}, function (response) {
								$parent.html(response);
							});
						});
						$(document).on('click', '.dependency-installer .notice-dismiss', function () {
							var $this = $(this);
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: 'dismiss',
								slug  : $this.attr('data-slug')
							});
						});
					});
				})(jQuery);
			</script>
		<?php
	}

	/**
	 * AJAX router.
	 */
	public function ajax_router() {
		$method    = isset( $_POST['method'] ) ? sanitize_file_name( wp_unslash( $_POST['method'] ) ) : '';
		$slug      = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$whitelist = array( 'install', 'activate', 'dismiss' );

		if ( in_array( $method, $whitelist, true ) ) {
			$response = $this->$method( $slug );
			echo esc_attr( $response['message'] );
		}
		wp_die();
	}

	/**
	 * Check if a dependency is currently required.
	 *
	 * @param string|array $plugin Plugin dependency slug or config.
	 *
	 * @return boolean True if required. Default: False
	 */
	public function is_required( &$plugin ) {
		if ( empty( $this->config ) ) {
			return false;
		}
		if ( is_string( $plugin ) && isset( $this->config[ $plugin ] ) ) {
			$dependency = &$this->config[ $plugin ];
		} else {
			$dependency = &$plugin;
		}
		if ( isset( $dependency['required'] ) ) {
			return true === $dependency['required'] || 'true' === $dependency['required'];
		}
		if ( isset( $dependency['optional'] ) ) {
			return false === $dependency['optional'] || 'false' === $dependency['optional'];
		}

		return false;
	}

	/**
	 * Is dependency installed?
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return boolean
	 */
	public function is_installed( $slug ) {
		$plugins = get_plugins();

		return isset( $plugins[ $slug ] );
	}

	/**
	 * Is dependency active?
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return boolean
	 */
	public function is_active( $slug ) {
		return is_plugin_active( $slug );
	}

	/**
	 * Install and activate dependency.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool|array false or Message.
	 */
	public function install( $slug ) {
		if ( $this->is_installed( $slug ) || ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		require_once 'class-plugin-dependency-installer-skin.php';

		$skin     = new WP_Plugin_Dependency_Installer_Skin(
			array(
				'type'  => 'plugin',
				'nonce' => wp_nonce_url( $this->config[ $slug ]['download_link'] ),
			)
		);
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $this->config[ $slug ]['download_link'] );

		if ( is_wp_error( $result ) ) {
			return array(
				'status'  => 'notice-error',
				'message' => $result->get_error_message(),
			);
		}

		if ( null === $result ) {
			return array(
				'status'  => 'notice-error',
				'message' => esc_html__( 'Plugin download failed' ),
			);
		}

		wp_cache_flush();

		if ( true !== $result && 'error' === $result['status'] ) {
			return $result;
		}

		return array(
			'status'  => 'notice-success',
			/* translators: %s: Plugin name */
			'message' => sprintf( esc_html__( '%s has been installed.' ), $this->config[ $slug ]['name'] ),
			'source'  => $this->config[ $slug ]['source'],
		);
	}

	/**
	 * Get install plugin notice.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array Admin notice.
	 */
	public function install_notice( $slug ) {
		$dependency  = $this->config[ $slug ];
		$is_required = $this->is_required( $dependency );
		if ( $is_required ) {
			/* translators: %s: Plugin name */
			$message = sprintf( __( 'The %1$s plugin is required.' ), $dependency['name'] );
		} else {
			/* translators: %s: Plugin name */
			$message = sprintf( __( 'The %1$s plugin is recommended.' ), $dependency['name'] );
		}

		return array(
			'action'  => 'install',
			'status'  => $is_required ? 'notice-warning' : 'notice-info',
			'slug'    => $slug,
			'message' => esc_attr( $message ),
			'source'  => $dependency['source'],
		);
	}

	/**
	 * Activate dependency.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array Message.
	 */
	public function activate( $slug ) {
		// network activate only if on network admin pages.
		$result = is_network_admin() ? activate_plugin( $slug, null, true ) : activate_plugin( $slug );

		if ( is_wp_error( $result ) ) {
			return array(
				'status'  => 'notice-error',
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'status'  => 'notice-success',
			/* translators: %s: Plugin name */
			'message' => sprintf( esc_html__( '%s has been activated.' ), $this->config[ $slug ]['name'] ),
			'source'  => $this->config[ $slug ]['source'],
		);
	}

	/**
	 * Get activate plugin notice.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array Admin notice.
	 */
	public function activate_notice( $slug ) {
		$dependency = $this->config[ $slug ];

		return array(
			'action'  => 'activate',
			'slug'    => $slug,
			/* translators: %s: Plugin name */
			'message' => sprintf( esc_html__( 'Please activate the %s plugin.' ), $dependency['name'] ),
			'source'  => $dependency['source'],
		);
	}

	/**
	 * Dismiss admin notice for a week.
	 *
	 * @return array Empty Message.
	 */
	public function dismiss() {
		return array(
			'status'  => 'notice-info',
			'message' => '',
		);
	}

	/**
	 * Display admin notices / action links.
	 *
	 * @return bool/string false or Admin notice.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}
		foreach ( $this->notices as $notice ) {
			$status      = isset( $notice['status'] ) ? $notice['status'] : 'notice-info';
			$source      = isset( $notice['source'] ) ? $notice['source'] : __( 'Dependency' );
			$class       = esc_attr( $status ) . ' notice is-dismissible dependency-installer';
			$label       = esc_html( $this->get_dismiss_label( $source ) );
			$message     = '';
			$action      = '';
			$dismissible = '';

			if ( isset( $notice['message'] ) ) {
				$message = esc_html( $notice['message'] );
			}

			if ( isset( $notice['action'] ) ) {
				$action = sprintf(
					' <a href="javascript:;" class="wpdi-button" data-action="%1$s" data-slug="%2$s">%3$s Now &raquo;</a> ',
					esc_attr( $notice['action'] ),
					esc_attr( $notice['slug'] ),
					esc_html( ucfirst( $notice['action'] ) )
				);
			}
			if ( isset( $notice['slug'] ) ) {
				/**
				 * Filters the dismissal timeout.
				 *
				 * @since 1.4.1
				 *
				 * @param string|int '14'               Default dismissal in days.
				 * @param  string     $notice['source'] Plugin slug of calling plugin.
				 * @return string|int Dismissal timeout in days.
				 */
				$timeout     = apply_filters( 'wp_plugin_dependency_timeout', '14', $source );
				$dependency  = dirname( $notice['slug'] );
				$dismissible = empty( $timeout ) ? '' : sprintf( 'dependency-installer-%1$s-%2$s', esc_attr( $dependency ), esc_attr( $timeout ) );
			}
			if ( WP_Dismiss_Notice::is_admin_notice_active( $dismissible ) ) {
				printf(
					'<div class="%1$s" data-dismissible="%2$s"><p><strong>[%3$s]</strong> %4$s%5$s</p></div>',
					esc_attr( $class ),
					esc_attr( $dismissible ),
					esc_html( $label ),
					esc_html( $message ),
					$action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}
	}

	/**
	 * Make modifications to plugin row.
	 *
	 * @param string $plugin_file Plugin file.
	 */
	private function modify_plugin_row( $plugin_file ) {
		add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ), 10, 2 );
		add_action( 'after_plugin_row_' . $plugin_file, array( $this, 'modify_plugin_row_elements' ) );
	}

	/**
	 * Unset plugin action links so required plugins can't be removed or deactivated.
	 *
	 * @param array  $actions     Action links.
	 * @param string $plugin_file Plugin file.
	 *
	 * @return mixed
	 */
	public function unset_action_links( $actions, $plugin_file ) {
		if ( isset( $actions['delete'] ) ) {
			unset( $actions['delete'] );
		}
		if ( isset( $actions['deactivate'] ) ) {
			unset( $actions['deactivate'] );
		}

		/* translators: %s: opening and closing span tags */
		$actions = array_merge( array( 'required-plugin' => sprintf( esc_html__( '%1$sRequired Plugin%2$s' ), '<span class="network_active" style="font-variant-caps: small-caps;">', '<span>' ) ), $actions );

		return $actions;
	}

	/**
	 * Modify the plugin row elements.
	 *
	 * @param string $plugin_file Plugin file.
	 *
	 * @return void
	 */
	public function modify_plugin_row_elements( $plugin_file ) {
		print '<script>';
		print 'jQuery("tr[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .plugin-version-author-uri").append("<br><br><strong>' . esc_html__( 'Required by:' ) . '</strong> ' . esc_html( $this->get_dependency_sources( $plugin_file ) ) . '");';
		print 'jQuery(".inactive[data-plugin=\'' . esc_attr( $plugin_file ) . '\']").attr("class", "active");';
		print 'jQuery(".active[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .check-column input").remove();';
		print '</script>';
	}

	/**
	 * Get formatted string of dependent plugins.
	 *
	 * @param string $plugin_file Plugin file.
	 *
	 * @return string $dependents
	 */
	private function get_dependency_sources( $plugin_file ) {
		// Remove empty values from $sources.
		$sources = array_filter( $this->config[ $plugin_file ]['sources'] );
		$sources = array_unique( $sources );
		$sources = array_map( array( $this, 'get_dismiss_label' ), $sources );
		$sources = implode( ', ', $sources );

		return $sources;
	}

	/**
	 * Get formatted source string for text usage.
	 *
	 * @param string $source plugin source.
	 *
	 * @return string friendly plugin name.
	 */
	private function get_dismiss_label( $source ) {
		$label = str_replace( '-', ' ', $source );
		$label = ucwords( $label );
		$label = str_ireplace( 'wp ', 'WP ', $label );

		return $label;
	}

	/**
	 * Get the configuration.
	 *
	 * @since 1.4.11
	 *
	 * @param string $slug Plugin slug.
	 * @param string $key Dependency key.
	 *
	 * @return mixed|array The configuration.
	 */
	public function get_config( $slug = '', $key = '' ) {
		if ( empty( $slug ) && empty( $key ) ) {
			return $this->config;
		} elseif ( empty( $key ) ) {
			return isset( $this->config[ $slug ] ) ? $this->config[ $slug ] : null;
		} else {
			return isset( $this->config[ $slug ][ $key ] ) ? $this->config[ $slug ][ $key ] : null;
		}
	}
}
