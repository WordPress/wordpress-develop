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
	 * @since 7.1.0 Added $format parameter.
	 *
	 * @param array  $sitemaps Array of sitemap URLs.
	 * @param string $format   The format for the sitemap index.  Accepts 'xml', 'html'.
	 */
	public function render_index( $sitemaps, $format ) {
		$this->check_for_simple_xml_availability();

		$index_xml = $this->get_sitemap_index_xml( $sitemaps );
		if ( ! $index_xml ) {
			return;
		}

		if ( ! in_array( $format, array( 'xml', 'html' ), true ) ) {
			$format = 'xml';
		}

		switch ( $format ) {
			case 'html':
				$this->check_for_xslt_availability();

				$xslt = ( new WP_Sitemaps_Stylesheet() )->get_sitemap_index_stylesheet();

				$html = $this->generate_html( $index_xml, $xslt );

				echo $html;

				break;
			case 'xml':
			default:
				header( 'Content-Type: application/xml; charset=UTF-8' );

				// All output is escaped within get_sitemap_xml().
				echo $index_xml;

				break;
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
	 * @since 7.1.0 Added $format parameter.
	 *
	 * @param array  $url_list Array of URLs for a sitemap.
	 * @param string $format   The format for the sitemap index.  Accepts 'xml', 'html'.
	 */
	public function render_sitemap( $url_list, $format ) {
		$this->check_for_simple_xml_availability();

		$sitemap_xml = $this->get_sitemap_xml( $url_list );
		if ( empty( $sitemap_xml ) ) {
			return;
		}

		if ( ! in_array( $format, array( 'xml', 'html' ), true ) ) {
			$format = 'xml';
		}

		switch ( $format ) {
			case 'html':
				$this->check_for_xslt_availability();

				$xslt = ( new WP_Sitemaps_Stylesheet() )->get_sitemap_stylesheet();

				$html = $this->generate_html( $sitemap_xml, $xslt );

				echo $html;

				break;
			case 'xml':
			default:
				header( 'Content-Type: application/xml; charset=UTF-8' );

				// All output is escaped within get_sitemap_xml().
				echo $sitemap_xml;

				break;
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

	/**
	 * Checks for the availability of the DOMDocument & XSLTProcessor extension and errors if missing.
	 *
	 * @since 7.1.0
	 */
	private function check_for_xslt_availability() {
		if ( ! class_exists( 'DOMDocument' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: DOMDocument */
					esc_html( __( 'Could not generate XML sitemap due to missing %s extension' ) ),
					'DOMDocument'
				),
				esc_html( __( 'WordPress &rsaquo; Error' ) ),
				array(
					'response' => 501, // "Not implemented".
				)
			);
		}

		if ( ! class_exists( 'XSLTProcessor' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: XSLTProcessor */
					esc_html( __( 'Could not generate XML sitemap due to missing %s extension' ) ),
					'XSLTProcessor'
				),
				esc_html( __( 'WordPress &rsaquo; Error' ) ),
				array(
					'response' => 501, // "Not implemented".
				)
			);
		}
	}

	/**
	 * Generate the HTML rendering of the sitemap or sitemap index using XSLT.
	 *
	 * @since 7.1.0
	 *
	 * @param SimpleXMLElement $xml        The XML of the sitemap or sitemap index.
	 * @param string           $stylesheet The XSLT to apply to $xml.
	 * @return string
	 */
	protected function generate_html( $xml, $stylesheet ) {
		// @todo add some DOM/XSLTProcessor related error checking.

		// Load the XML source document into a DOM.
		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		// Load the XSLT into a DOM.
		$xslt = new DOMDocument();
		$xslt->loadXML( $stylesheet );

		// Create the XSLT processor and load the XSLT.
		$proc = new XSLTProcessor();
		$proc->importStylesheet( $xslt );

		// Run the XSLT on the source XML.
		// Note: XSLTProcessor::transformToXML() is poorly named and need NOT produce XML;
		//       that is, it will produce whatever is specified by xsl:output/@method in the transform,
		//       which in our case is 'text/html'.
		$html = $proc->transformToXML( $dom );
		if ( ! $html ) {
			$html = '';
		}

		return $html;
	}
}
