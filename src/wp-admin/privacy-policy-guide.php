<?php
/**
 * Privacy Policy Guide Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_privacy_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage privacy options on this site.' ) );
}

if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
}

add_filter(
	'admin_body_class',
	static function( $body_class ) {
		$body_class .= ' admin-page ';

		return $body_class;
	}
);

wp_enqueue_script( 'privacy-tools' );

require_once ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="admin-page-header">
	<div class="admin-page-title-section">
		<h1>
			<?php _e( 'Privacy' ); ?>
		</h1>
	</div>

	<nav class="admin-page-tabs-wrapper hide-if-no-js" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>" class="admin-page-tab">
			<?php
			/* translators: Tab heading for Site Health Status page. */
			_ex( 'Settings', 'Privacy Settings' );
			?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ); ?>" class="admin-page-tab active" aria-current="true">
			<?php
			/* translators: Tab heading for Site Health Status page. */
			_ex( 'Policy Guide', 'Privacy Settings' );
			?>
		</a>
	</nav>
</div>

<hr class="wp-header-end">

<div class="notice notice-error hide-if-js">
	<p><?php _e( 'The Privacy Settings require JavaScript.' ); ?></p>
</div>

<div class="admin-page-body hide-if-no-js">
	<h2><?php _e( 'Privacy Policy Guide' ); ?></h2>
	<h3 class="section-title"><?php _e( 'Introduction' ); ?></h3>
	<p><?php _e( 'This text template will help you to create your web site&#8217;s privacy policy.' ); ?></p>
	<p><?php _e( 'We have suggested the sections you will need. Under each section heading you will find a short summary of what information you should provide, which will help you to get started. Some sections include suggested policy content, others will have to be completed with information from your theme and plugins.' ); ?></p>
	<p><?php _e( 'Please edit your privacy policy content, making sure to delete the summaries, and adding any information from your theme and plugins. Once you publish your policy page, remember to add it to your navigation menu.' ); ?></p>
	<p><?php _e( 'It is your responsibility to write a comprehensive privacy policy, to make sure it reflects all national and international legal requirements on privacy, and to keep your policy current and accurate.' ); ?></p>
	<div class="admin-page-accordion">
		<h4 class="admin-page-accordion-heading">
			<button aria-expanded="false" class="admin-page-accordion-trigger" aria-controls="admin-page-accordion-block-privacy-policy-guide" type="button">
				<span class="title"><?php _e( 'Privacy Policy Guide' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="admin-page-accordion-block-privacy-policy-guide" class="admin-page-accordion-panel" hidden="hidden">
			<?php
			$content = WP_Privacy_Policy_Content::get_default_content( true, false );
			echo $content;
			?>
		</div>
	</div>
	<hr class="hr-separator">
	<h3 class="section-title"><?php _e( 'Policies' ); ?></h3>
	<div class="admin-page-accordion wp-privacy-policy-guide">
		<?php WP_Privacy_Policy_Content::privacy_policy_guide(); ?>
	</div>
</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
