<?php
/**
 * About This Version administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

// Used in the HTML title tag.
/* translators: Page title of the About WordPress page in the admin. */
$title = _x( 'About', 'page title' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
	<div class="wrap about__container">

		<div class="about__header">
			<div class="about__header-title">
				<h1>
					<?php
					printf(
						/* translators: %s: Version number. */
						__( 'WordPress %s' ),
						$display_version
					);
					?>
				</h1>
			</div>

			<div class="about__header-text"><?php _e( 'Edit pages in the Site Editor, sync patterns, and more' ); ?></div>
		</div>

		<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
			<a href="about.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'What&#8217;s New' ); ?></a>
			<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
			<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
			<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
			<a href="contribute.php" class="nav-tab"><?php _e( 'Get Involved' ); ?></a>
		</nav>

		<div class="about__section">
			<div class="column">
				<h2>
					<?php
					printf(
						/* translators: %s: Version number. */
						__( 'Welcome to WordPress %s' ),
						$display_version
					);
					?>
				</h2>
				<p class="is-subheading">
					<?php _e( 'Create beautiful and compelling websites more efficiently than ever with WordPress 6.3. Whether you want to build an entire site without coding or are a developer who wants to customize every detail, this release has something for you.' ); ?>
				</p>
			</div>
		</div>

		<div class="about__section has-1-column" style="margin-top: var(--gap);margin-bottom: var(--gap);">
			<div class="column is-edge-to-edge">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Create your site in the Site Editor' ); ?></h3>
				<p><?php _e( 'Designed to save time by bringing together the most important site-building tools, the Site Editor gets four direct actions: Navigation, Pages, Styles, and Patterns. Navigation allows you to edit and manage all navigation menus on your site rather than needing to look at templates. Pages add the ability to create and edit pages and the template surrounding your content. Styles makes browsing style variations easy, using the Style Book, and accessing all Styling tools. Patterns offer a way to create and manage your patterns and template parts from one place.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column is-vertically-aligned-center">
				<h3><?php _e( 'Create and sync Patterns' ); ?></h3>
				<p><?php _e( 'Lay out Blocks and save them as Patterns for use throughout your site. You can even specify whether to sync your patterns so one change globally applies to all parts of your site. Or, utilize patterns as a starting point with the ability to customize each instance.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
			</div>
			<div class="column is-vertically-aligned-center">
				<h3><?php _e( 'Work faster with the Command Palette' ); ?></h3>
				<p><?php _e( 'Switch to a specific template or open your editor preferences with a new tool to help you quickly navigate expansive functionality. With simple keyboard shortcuts (⌘+k on Mac or ctrl+k on Windows), clicking the sidebar search icon in Site View, or clicking the Edit View Title Bar while in Edit View, get where you need to go and do what you need to do in seconds, either in the Site Editor or the Post Editor.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column is-vertically-aligned-center">
				<h3><?php _e( 'Track design changes with Style Revisions' ); ?></h3>
				<p><?php _e( 'With more tools and options for styling your site than ever, wouldn’t it be helpful to see how it looked at a specific time? Now you can visualize these revisions in a timeline and access a one-click option to restore prior styles.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
			</div>
		</div>

		<div class="about__section has-1-column">
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='200' height='100' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Sharpen your designs with updated tools' ); ?></h3>
				<p><?php _e( 'New design controls bring more versatility for fine-tuning designs, starting with the ability to customize your caption’s styles from the Styles Interface without coding. You can manage your duotone filters in Styles for supported blocks and pick from the options provided by your theme or disable them entirely. The Cover block gets added settings for text color, layout controls, and border options, making this powerful block even more handy.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='200' height='100' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Annotate easily with the Footnotes Block' ); ?></h3>
				<p><?php _e( 'Footnotes add convenient annotations throughout your content. Now you can add, link, and design footnotes for any paragraph.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='200' height='100' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Show or hide content with the Details Block' ); ?></h3>
				<p><?php _e( 'Use the block to avoid spoiling a surprise, create an interactive Q&A section, or hide a long paragraph under a heading.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-3-columns">
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3 class="is-smaller-heading" style="margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Preview block themes' ); ?></h3>
				<p><?php _e( 'Experience block themes before you switch and preview the Site Editor, with options to customize directly before committing to a new theme.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3 class="is-smaller-heading" style="margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Set aspect ratio on images' ); ?></h3>
				<p><?php _e( 'Specify your aspect ratios and ensure design integrity, especially when using images in patterns.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3 class="is-smaller-heading" style="margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Build your site distraction-free' ); ?></h3>
				<p><?php _e( 'Distraction-free designing is now available in the Site Editor.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-3-columns">
			<div class="column">
				<div class="about__image">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<rect width="48" height="48" rx="4" fill="#151515"/>
					</svg>
				</div>
				<h3 class="is-smaller-heading" style="margin-top:calc(var(--gap) / 2);margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Rediscover the Top Toolbar' ); ?></h3>
				<p><?php _e( 'A revamped top toolbar offers parent selectors for nested blocks, options when selecting multiple blocks, and a new interface embedded into the title bar with new functionality in mind.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<rect width="48" height="48" rx="4" fill="#151515"/>
					</svg>
				</div>
				<h3 class="is-smaller-heading" style="margin-top:calc(var(--gap) / 2);margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Leverage List View improvements' ); ?></h3>
				<p><?php _e( 'Drag and drop to every content layer and delete any block you would like in the updated List View.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<rect width="48" height="48" rx="4" fill="#151515"/>
					</svg>
				</div>
				<h3 class="is-smaller-heading" style="margin-top:calc(var(--gap) / 2);margin-bottom:calc(var(--gap) / 4);"><?php _e( 'Build new templates with Patterns' ); ?></h3>
				<p><?php _e( 'Create unique patterns to jumpstart template creation with a new modal enabling access to pattern selection.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='200' height='100' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Performance gets a boost' ); ?></h3>
				<p><?php _e( 'The WordPress community is committed to delivering all these product updates on top of overall improvements to performance. This release includes more than 170 performance-related updates, including defer and async support to the WP Scripts API and fetchpriority support for images. These changes can improve your website’s load time as perceived by visitors. Other load time improvements implemented are updates to block template resolution, image lazy-loading, and the emoji loader.' ); ?></p>
			</div>
			<div class="column">
				<div class="about__image">
					<img src="data:image/svg+xml,%3Csvg width='200' height='100' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%23dcdcde' /%3E%3C/svg%3E" alt="" />
				</div>
				<h3><?php _e( 'Accessibility remains a core focus' ); ?></h3>
				<p><?php _e( 'WordPress remains steadfast in making the site-building experience accessible to everyone. 6.3 incorporates more than 50 accessibility improvements across the platform. Improved labeling, optimized tab and arrow-key navigation, revised heading hierarchy, and new controls in the admin image editor allow those using screen readers, keyboard navigation, and other assistive technology to navigate more easily. The login form, installation steps, list tables, and more have all received updates.' ); ?></p>
			</div>
		</div>

		<hr />

		<div class="about__section has-3-columns">
			<div class="column about__image is-vertically-aligned-top">
				<img src="./images/about-release-badge.svg" alt="" />
			</div>
			<div class="column is-vertically-aligned-center" style="grid-column-end:span 2">
				<h3>
					<?php
					printf(
						/* translators: %s: Version number. */
						__( 'Learn more about WordPress %s' ),
						$display_version
					);
					?>
				</h3>
				<p>
					<?php
					printf(
						/* translators: 1: Learn WordPress link, 2: Workshops link. */
						__( '<a href="%1$s">Learn WordPress</a> is a free resource for new and experienced WordPress users. Learn is stocked with how-to videos on using various features in WordPress, <a href="%2$s">interactive events</a> for exploring topics in-depth, and lesson plans for diving deep into specific areas of WordPress.' ),
						'https://learn.wordpress.org/',
						'https://learn.wordpress.org/online-workshops/'
					);
					?>
				</p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column">
				<div class="about__image">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<rect width="48" height="48" rx="4" fill="#151515"/>
						<path d="M23 34v-4h-5l-2.293-2.293a1 1 0 0 1 0-1.414L18 24h5v-2h-7v-6h7v-2h2v2h5l2.293 2.293a1 1 0 0 1 0 1.414L30 22h-5v2h7v6h-7v4h-2Zm-5-14h11.175l.646-.646a.5.5 0 0 0 0-.708L29.175 18H18v2Zm.825 8H30v-2H18.825l-.646.646a.5.5 0 0 0 0 .708l.646.646Z" fill="#fff"/>
					</svg>
				</div>
				<p style="margin-top:calc(var(--gap) / 2);">
					<?php
					printf(
						/* translators: %s: WordPress Field Guide link. */
						__( 'Check out the latest version of the <a href="%s">WordPress Field Guide</a>. It is overflowing with detailed developer notes to help you build with WordPress.' ),
						__( 'https://make.wordpress.org/core/2023/03/09/wordpress-6-2-field-guide/' )
					);
					?>
				</p>
			</div>
			<div class="column">
				<div class="about__image">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<rect width="48" height="48" rx="4" fill="#151515"/>
						<path d="M28 19.75h-8v1.5h8v-1.5ZM20 23h8v1.5h-8V23ZM26 26.25h-6v1.5h6v-1.5Z" fill="#fff"/>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M29 16H19a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V18a2 2 0 0 0-2-2Zm-10 1.5h10a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5H19a.5.5 0 0 1-.5-.5V18a.5.5 0 0 1 .5-.5Z" fill="#fff"/>
					</svg>
				</div>
				<p style="margin-top:calc(var(--gap) / 2);">
					<?php
					printf(
						/* translators: 1: WordPress Release Notes link, 2: WordPress version number. */
						__( '<a href="%1$s">Read the WordPress %2$s Release Notes</a> for more information on the included enhancements and issues fixed, installation information, developer notes and resources, release contributors, and the list of file changes in this release.' ),
						sprintf(
							/* translators: %s: WordPress version number. */
							esc_url( __( 'https://wordpress.org/documentation/wordpress-version/version-%s/' ) ),
							'6-2'
						),
						'6.2'
					);
					?>
				</p>
			</div>
		</div>

		<hr class="is-large" />

		<div class="return-to-dashboard">
			<?php
			if ( isset( $_GET['updated'] ) && current_user_can( 'update_core' ) ) {
				printf(
					'<a href="%1$s">%2$s</a> | ',
					esc_url( self_admin_url( 'update-core.php' ) ),
					is_multisite() ? __( 'Go to Updates' ) : __( 'Go to Dashboard &rarr; Updates' )
				);
			}

			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self_admin_url() ),
				is_blog_admin() ? __( 'Go to Dashboard &rarr; Home' ) : __( 'Go to Dashboard' )
			);
			?>
		</div>
	</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>

<?php

// These are strings we may use to describe maintenance/security releases, where we aim for no new strings.
return;

__( 'Maintenance Release' );
__( 'Maintenance Releases' );

__( 'Security Release' );
__( 'Security Releases' );

__( 'Maintenance and Security Release' );
__( 'Maintenance and Security Releases' );

/* translators: %s: WordPress version number. */
__( '<strong>Version %s</strong> addressed one security issue.' );
/* translators: %s: WordPress version number. */
__( '<strong>Version %s</strong> addressed some security issues.' );

/* translators: 1: WordPress version number, 2: Plural number of bugs. */
_n_noop(
	'<strong>Version %1$s</strong> addressed %2$s bug.',
	'<strong>Version %1$s</strong> addressed %2$s bugs.'
);

/* translators: 1: WordPress version number, 2: Plural number of bugs. Singular security issue. */
_n_noop(
	'<strong>Version %1$s</strong> addressed a security issue and fixed %2$s bug.',
	'<strong>Version %1$s</strong> addressed a security issue and fixed %2$s bugs.'
);

/* translators: 1: WordPress version number, 2: Plural number of bugs. More than one security issue. */
_n_noop(
	'<strong>Version %1$s</strong> addressed some security issues and fixed %2$s bug.',
	'<strong>Version %1$s</strong> addressed some security issues and fixed %2$s bugs.'
);

/* translators: %s: Documentation URL. */
__( 'For more information, see <a href="%s">the release notes</a>.' );

/* translators: 1: WordPress version number, 2: Link to update WordPress */
__( 'Important! Your version of WordPress (%1$s) is no longer supported, you will not receive any security updates for your website. To keep your site secure, please <a href="%2$s">update to the latest version of WordPress</a>.' );

/* translators: 1: WordPress version number, 2: Link to update WordPress */
__( 'Important! Your version of WordPress (%1$s) will stop receiving security updates in the near future. To keep your site secure, please <a href="%2$s">update to the latest version of WordPress</a>.' );

/* translators: %s: The major version of WordPress for this branch. */
__( 'This is the final release of WordPress %s' );

/* translators: The localized WordPress download URL. */
__( 'https://wordpress.org/download/' );
