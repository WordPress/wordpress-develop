const FULL_COMMIT = /^[0-9a-f]{40}$/;
const REPOSITORY = /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/;
const RUN_URL =
	/^https:\/\/github\.com\/[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\/actions\/runs\/\d+$/;

/**
 * @param {unknown} value
 */
function phpString( value ) {
	return JSON.stringify( value );
}

/**
 * @param {Record<string, any>} provenance
 */
export function validateProvenance( provenance ) {
	if ( ! REPOSITORY.test( provenance?.sourceRepository || '' ) ) {
		throw new Error( 'Preview provenance repository is invalid.' );
	}
	if ( ! FULL_COMMIT.test( provenance.sourceSha || '' ) ) {
		throw new Error( 'Preview provenance commit is invalid.' );
	}
	if (
		Number.isNaN( Date.parse( provenance.generationTimestamp ) ) ||
		! provenance.generationTimestamp.endsWith( 'Z' )
	) {
		throw new Error( 'Preview provenance timestamp must be UTC.' );
	}
	if (
		provenance.runUrl !== null &&
		! RUN_URL.test( provenance.runUrl || '' )
	) {
		throw new Error( 'Preview provenance run URL is invalid.' );
	}
	return provenance;
}

/**
 * @param {Record<string, any>} candidate
 */
export function renderRuntimePlugin( candidate ) {
	const provenance = validateProvenance( candidate );
	const runUrl =
		provenance.runUrl === null ? 'null' : phpString( provenance.runUrl );
	return `<?php
/**
 * Runtime policy and provenance for the Code Reference preview.
 */

$wporg_docs_preview_provenance = array(
	'sourceRepository'   => ${ phpString( provenance.sourceRepository ) },
	'sourceSha'          => ${ phpString( provenance.sourceSha ) },
	'generationTimestamp' => ${ phpString( provenance.generationTimestamp ) },
	'runUrl'             => ${ runUrl },
);

add_filter(
	'pre_http_request',
	static function() {
		return new WP_Error(
			'docs_preview_network_disabled',
			'Outbound networking is disabled in the Code Reference preview.'
		);
	},
	PHP_INT_MAX
);

$wporg_docs_preview_banner_rendered = false;
$wporg_docs_preview_render_banner   = static function() use ( &$wporg_docs_preview_banner_rendered, $wporg_docs_preview_provenance ) {
	if ( $wporg_docs_preview_banner_rendered || is_admin() ) {
		return;
	}
	$wporg_docs_preview_banner_rendered = true;
	$commit_url = sprintf(
		'https://github.com/%s/commit/%s',
		$wporg_docs_preview_provenance['sourceRepository'],
		$wporg_docs_preview_provenance['sourceSha']
	);
	?>
	<aside id="wporg-code-reference-preview-provenance" role="note" style="padding:12px 24px;background:#fff8c5;color:#1e1e1e;border-bottom:1px solid #dba617">
		<strong><?php echo esc_html__( 'Code Reference preview', 'wporg' ); ?></strong>
		<?php
		printf(
			/* translators: 1: source repository, 2: commit SHA, 3: UTC generation time. */
			esc_html__( 'Generated from %1$s at %2$s on %3$s UTC.', 'wporg' ),
			esc_html( $wporg_docs_preview_provenance['sourceRepository'] ),
			'<a href="' . esc_url( $commit_url ) . '"><code>' . esc_html( $wporg_docs_preview_provenance['sourceSha'] ) . '</code></a>',
			esc_html( gmdate( 'Y-m-d H:i:s', strtotime( $wporg_docs_preview_provenance['generationTimestamp'] ) ) )
		);
		if ( $wporg_docs_preview_provenance['runUrl'] ) {
			printf(
				' <a href="%s">%s</a>',
				esc_url( $wporg_docs_preview_provenance['runUrl'] ),
				esc_html__( 'Build run', 'wporg' )
			);
		}
		?>
	</aside>
	<?php
};
add_action( 'wp_body_open', $wporg_docs_preview_render_banner, 0 );
add_action( 'wp_footer', $wporg_docs_preview_render_banner, PHP_INT_MAX );

add_action(
	'rest_api_init',
	static function() use ( $wporg_docs_preview_provenance ) {
		register_rest_route(
			'docs-preview/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function() use ( $wporg_docs_preview_provenance ) {
					$marker_file = WP_CONTENT_DIR . '/docs-preview-import.json';
					$marker      = file_exists( $marker_file )
						? json_decode( file_get_contents( $marker_file ), true )
						: null;
					$network     = wp_remote_get( 'https://api.wordpress.org/' );
					return array(
						'provenance'              => $wporg_docs_preview_provenance,
						'import'                  => $marker,
						'outboundNetworkDisabled' => is_wp_error( $network ) && 'docs_preview_network_disabled' === $network->get_error_code(),
						'constants'                => array(
							'DISABLE_WP_CRON'             => defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON,
							'AUTOMATIC_UPDATER_DISABLED'  => defined( 'AUTOMATIC_UPDATER_DISABLED' ) && true === AUTOMATIC_UPDATER_DISABLED,
							'WP_AUTO_UPDATE_CORE'         => defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE,
							'DISALLOW_FILE_MODS'          => defined( 'DISALLOW_FILE_MODS' ) && true === DISALLOW_FILE_MODS,
						),
					);
				},
			)
		);
	}
);
`;
}
