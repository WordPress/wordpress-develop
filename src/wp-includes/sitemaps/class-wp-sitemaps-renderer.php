<?php
/**
 * Sitemaps: WP_Sitemaps_Renderer class
 *
 * Responsible for rendering Sitemaps data to XML in accordance with sitemap protocol.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 */

/**
 * Class WP_Sitemaps_Renderer
 *
 * @since 5.5.0
 */
#[AllowDynamicProperties]
class WP_Sitemaps_Renderer {
	/**
	 * Renders a sitemap index.
	 *
	 * @since 5.5.0
	 *
	 * @param array $sitemaps Array of sitemap URLs.
	 */
	public function render_index( $sitemaps ) {
		header( 'Content-Type: application/xml; charset=UTF-8' );

		$this->check_for_simple_xml_availability();

		$index_xml = $this->get_sitemap_index_xml( $sitemaps );

		if ( ! empty( $index_xml ) ) {
			// All output is escaped within get_sitemap_index_xml().
			echo $index_xml;
		}
	}

	/**
	 * Gets XML for a sitemap index.
	 *
	 * @since 5.5.0
	 *
	 * @param array $sitemaps Array of sitemap URLs.
	 * @return string|false A well-formed XML string for a sitemap index. False on error.
	 */
	public function get_sitemap_index_xml( $sitemaps ) {
		$sitemap_index = new SimpleXMLElement(
			sprintf(
				'%1$s%2$s',
				'<?xml version="1.0" encoding="UTF-8" ?>',
				'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />'
			)
		);

		foreach ( $sitemaps as $entry ) {
			$sitemap = $sitemap_index->addChild( 'sitemap' );

			// Add each element as a child node to the <sitemap> entry.
			foreach ( $entry as $name => $value ) {
				if ( 'loc' === $name ) {
					$sitemap->addChild( $name, esc_url( $value ) );
				} elseif ( 'lastmod' === $name ) {
					$sitemap->addChild( $name, esc_xml( $value ) );
				} else {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: %s: List of element names. */
							__( 'Fields other than %s are not currently supported for the sitemap index.' ),
							implode( ',', array( 'loc', 'lastmod' ) )
						),
						'5.5.0'
					);
				}
			}
		}

		return $sitemap_index->asXML();
	}

	/**
	 * Renders a sitemap.
	 *
	 * @since 5.5.0
	 *
	 * @param array $url_list Array of URLs for a sitemap.
	 */
	public function render_sitemap( $url_list ) {
		header( 'Content-Type: application/xml; charset=UTF-8' );

		$this->check_for_simple_xml_availability();

		$sitemap_xml = $this->get_sitemap_xml( $url_list );

		if ( ! empty( $sitemap_xml ) ) {
			// All output is escaped within get_sitemap_xml().
			echo $sitemap_xml;
		}
	}

	/**
	 * Gets XML for a sitemap.
	 *
	 * @since 5.5.0
	 *
	 * @param array $url_list Array of URLs for a sitemap.
	 * @return string|false A well-formed XML string for a sitemap index. False on error.
	 */
	public function get_sitemap_xml( $url_list ) {
		$urlset = new SimpleXMLElement(
			sprintf(
				'%1$s%2$s',
				'<?xml version="1.0" encoding="UTF-8" ?>',
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />'
			)
		);

		foreach ( $url_list as $url_item ) {
			$url = $urlset->addChild( 'url' );

			// Add each element as a child node to the <url> entry.
			foreach ( $url_item as $name => $value ) {
				if ( 'loc' === $name ) {
					$url->addChild( $name, esc_url( $value ) );
				} elseif ( in_array( $name, array( 'lastmod', 'changefreq', 'priority' ), true ) ) {
					$url->addChild( $name, esc_xml( $value ) );
				} else {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: %s: List of element names. */
							__( 'Fields other than %s are not currently supported for sitemaps.' ),
							implode( ',', array( 'loc', 'lastmod', 'changefreq', 'priority' ) )
						),
						'5.5.0'
					);
				}
			}
		}

		return $urlset->asXML();
	}

	/**
	 * Checks for the availability of the SimpleXML extension and errors if missing.
	 *
	 * @since 5.5.0
	 */
	private function check_for_simple_xml_availability() {
		if ( ! class_exists( 'SimpleXMLElement' ) ) {
			add_filter(
				'wp_die_handler',
				static function () {
					return '_xml_wp_die_handler';
				}
			);

			wp_die(
				sprintf(
					/* translators: %s: SimpleXML */
					esc_xml( __( 'Could not generate XML sitemap due to missing %s extension' ) ),
					'SimpleXML'
				),
				esc_xml( __( 'WordPress &rsaquo; Error' ) ),
				array(
					'response' => 501, // "Not implemented".
				)
			);
		}
	}
}
