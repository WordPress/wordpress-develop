<?php
/**
 * AI Services settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.0.0
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

// Used in the HTML title tag.
$title       = __( 'AI Services Settings' );
$parent_file = 'options-general.php';

$credentials_manager = $GLOBALS['wp_ai_client_credentials_manager'];

$cloud_providers = $credentials_manager->get_all_cloud_providers_metadata();

$settings_section = 'wp-ai-client-provider-credentials';

add_settings_section(
	$settings_section,
	'',
	static function () {
		?>
		<p class="description">
			<?php _e( 'Paste your API credentials for one or more AI providers you would like to use throughout your site.' ); ?>
		</p>
		<?php
	},
	'ai'
);

foreach ( $cloud_providers as $provider_metadata ) {
	$provider_id              = $provider_metadata->getId();
	$provider_name            = $provider_metadata->getName();
	$provider_credentials_url = $provider_metadata->getCredentialsUrl();

	$field_id   = 'wp-ai-client-provider-api-key-' . $provider_id;
	$field_args = array(
		'type'      => 'password',
		'label_for' => $field_id,
		'id'        => $field_id,
		'name'      => WP_AI_Client_Credentials_Manager::OPTION_PROVIDER_CREDENTIALS . '[' . $provider_id . ']',
	);
	if ( $provider_credentials_url ) {
		$field_args['description'] = sprintf(
			/* translators: 1: AI provider name, 2: URL to the provider's API credentials page. */
			__( 'Create and manage your %1$s API keys in the <a href="%2$s" target="_blank" rel="noopener noreferrer">%1$s account settings<span class="screen-reader-text"> (opens in a new tab)</span></a>.' ),
			$provider_name,
			esc_url( $provider_credentials_url )
		);
	}

	add_settings_field(
		$field_id,
		$provider_name,
		'wp_ai_client_render_credential_field',
		'ai',
		$settings_section,
		$field_args
	);
}

$ai_help = '<p>' . __( 'This screen allows you to configure API credentials for AI service providers. These credentials are used by AI-powered features throughout your site.' ) . '</p>';
$ai_help .= '<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $ai_help,
	)
);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/forums/">Support forums</a>' ) . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form action="options.php" method="post">
<?php settings_fields( 'ai' ); ?>
<?php do_settings_sections( 'ai' ); ?>
<?php submit_button(); ?>
</form>

</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
