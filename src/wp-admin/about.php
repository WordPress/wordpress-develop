<?php
/**
 * About This Version administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/* translators: Page title of the About WordPress page in the admin. */
$title = _x( 'About', 'page title' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
	<div class="wrap about__container">

		<div class="about__header">
			<div class="about__header-title">
				<p>
					<?php _e( 'WordPress' ); ?>
					<?php echo $display_version; ?>
				</p>
			</div>

			<div class="about__header-text">
				<?php _e( 'Sharing your stories has never been easier' ); ?>
			</div>

			<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
				<a href="about.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'What&#8217;s New' ); ?></a>
				<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
				<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
				<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
			</nav>
		</div>

		<div class="about__section is-feature">
			<h1 class="aligncenter">
				<?php
				printf(
					/* translators: %s: The current WordPress version number. */
					__( 'Welcome to WordPress %s.' ),
					$display_version
				);
				?>
			</h1>
			<p>
				<?php
				printf(
					/* translators: %s: The current WordPress version number. */
					__( 'WordPress %s brings you countless ways to set your ideas free and bring them to life. With a brand-new default theme as your canvas, it supports an ever-growing collection of blocks as your brushes. Paint with words. Pictures. Sound. Or rich embedded media.' ),
					$display_version
				);
				?>
			</p>
		</div>

		<div class="has-background-image" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 1035 884%27%3E%3Ccircle cx=%27503%27 cy=%27434%27 r=%27310%27 fill=%27%23E3DAD1%27/%3E%3Ccircle cx=%27831%27 cy=%27204%27 r=%27204%27 fill=%27%23D1CFE4%27/%3E%3Ccircle cx=%27113.5%27 cy=%27770.5%27 r=%27113.5%27 fill=%27%23D1DEE4%27/%3E%3C/svg%3E%0A');">
			<div class="about__section has-2-columns is-wider-left has-transparent-background-color">
				<div class="column">
					<h2><?php _e( 'Greater layout flexibility' ); ?></h2>
					<p><?php _e( 'Bring your stories to life with more tools that let you edit your layout with or without code. Single column blocks, designs using mixed widths and columns, full-width headers, and videos in your cover block—make small change or big statements with equal ease!' ); ?></p>
				</div>
			</div>
			<div class="about__section has-2-columns is-wider-right has-transparent-background-color">
				<div class="column"><!-- space for alignment. --></div>
				<div class="column">
					<h2><?php _e( 'More block patterns' ); ?></h2>
					<p><?php _e( 'In select themes, preconfigured block patterns make setting up standard pages on your site a breeze. Find the power of patterns to streamline your workflow, or share some of that power with your clients and save yourself a few clicks.' ); ?></p>
				</div>
			</div>
			<div class="about__section has-2-columns is-wider-left has-transparent-background-color">
				<div class="column">
					<h2><?php _e( 'Caption videos—right in the block editor' ); ?></h2>
					<p><?php _e( 'Adding captions to your videos has landed in the block editor, with special attention to accessibility. Whether you’re navigating with a keyboard or a mouse, whether or not you use a screen reader, captions are easier to include than ever.' ); ?></p>
				</div>
			</div>
		</div>

		<hr class="is-large" />

		<div class="about__section has-1-column">
			<h2><?php _e( 'Twenty Twenty-One is here!' ); ?></h2>
			<p>
				<?php
				_e( 'Twenty Twenty-One is a blank canvas for your ideas, and the block editor is the best brush. It is built for the block editor and packed with brand-new block patterns you can only get in the default themes. Try different layouts in a matter of seconds, and let the theme’s eye-catching, yet timeless design make your work shine.' );
				?>
			</p>
		</div>

		<div class="about__section">
			<div class="column about__image is-edge-to-edge">
				<img src="data:image/svg+xml,%3Csvg width='1000' height='1000' xmlns='http://www.w3.org/2000/svg'%3E%3Crect x='0' y='0' width='100%25' height='100%25' fill='%23ddd' /%3E%3Ctext text-anchor='middle' font-family='sans-serif' font-size='24' y='50%25' x='50%25'%3ETheme Screenshot (1000x1000)%3C/text%3E%3C/svg%3E" alt="">
			</div>
		</div>

		<hr />

		<div class="about__section has-overlap-style">
			<div class="column is-vertically-aligned-center is-top-layer">
				<p>
					<?php
					printf(
						/* translators: %s: WCAG information link. */
						__( 'What’s more, this default theme puts accessibility at the heart of your website. It conforms to <a href="%s">Web Content Accessibility Guidelines (WCAG) 2.1</a> at Level AAA right out of the box, so you can meet the highest level of international accessibility standards. Just add the necessary elements to your plugins, pictures, and other content, and you’re ready to go!' ),
						'https://www.w3.org/WAI/WCAG2AAA-Conformance'
					);
					?>
				</p>
			</div>
			<div class="column about__image aligncenter is-edge-to-edge">
				<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 440 291'%3E%3Ccircle cx='294.5' cy='145.5' r='145.5' fill='%23E5D1D1'/%3E%3Ccircle cx='106.5' cy='106.5' r='106.5' fill='%23EEEADD'/%3E%3C/svg%3E%0A" style="max-width:25em" alt="" />
			</div>
		</div>

		<hr />

		<div class="about__section has-2-columns">
			<div class="column is-vertically-aligned-center">
				<h3 class="is-larger-heading"><?php _e( 'A rainbow of soft pastels' ); ?></h3>
			</div>
			<div class="column">
				<p><?php _e( 'Perfect for a new year, Twenty Twenty-One gives you a range of pre-selected color palettes in pastel, all of which meet AAA standards for contrast. You can also choose your own background color for the theme, and the theme chooses accessibility-conscious text colors for you — automatically!' ); ?></p>
				<p><?php _e( 'Need more flexibility than that? You can also choose your own color palette from the color picker.' ); ?></p>
			</div>
		</div>

		<div class="about__section">
			<div class="column about__image is-edge-to-edge">
				<img src="data:image/svg+xml,%3Csvg width='1000' height='366' xmlns='http://www.w3.org/2000/svg'%3E%3Crect x='0' y='0' width='100%25' height='100%25' fill='%23ddd' /%3E%3Ctext text-anchor='middle' font-family='sans-serif' font-size='24' y='50%25' x='50%25'%3EAll Palettes Screenshots (1000 wide)%3C/text%3E%3C/svg%3E" alt="">
			</div>
		</div>

		<hr />
		<hr class="is-large" />

		<div class="about__section">
			<header class="column is-edge-to-edge">
				<h2><?php _e( 'Improvements for everyone' ); ?></h2>
			</header>
		</div>

		<div class="about__section has-3-columns">
			<div class="column has-border" style="background-color:var(--global--color-yellow)">
				<h3><?php _e( 'Expanding auto-updates' ); ?></h3>
				<p><?php _e( 'For years, only developers have been able to update WordPress automatically. But now, you have that option, right in your dashboard. If this is your first site, you have auto-updates ready to go, right now! Upgrading an existing site? No problem! Everything is the same as it was before.' ); ?></p>
			</div>
			<div class="column has-border" style="background-color:var(--global--color-red)">
				<h3><?php _e( 'Accessibility statement template' ); ?></h3>
				<p><?php _e( 'Even if you’re not an expert, you can start letting folks know about your site’s commitment to accessibility at the click of a button! The new <a href="%s">feature plugin</a> includes template copy for you to update and publish, and it’s written to support different contexts and jurisdictions.', '#' ); ?></p>
			</div>
			<div class="column has-border" style="background-color:var(--global--color-purple)">
				<h3><?php _e( 'Built-in Patterns' ); ?></h3>
				<p><?php _e( 'If you’ve not had the chance to play with block patterns yet, all default themes now feature a range of block patterns that let you master complex layouts with minimal effort. Customize the patterns to your liking with the copy, images and colors that fit your story or brand.' ); ?></p>
			</div>
		</div>

		<hr />

		<div class="about__section has-2-columns">
			<h2 class="is-section-header"><?php _e( 'For developers' ); ?></h2>
			<div class="column">
				<h3><?php _e( 'REST API authentication with Application Passwords' ); ?></h3>
				<p><?php _e( 'Thanks to the API’s new Application Passwords authorization feature, third-party apps can connect to your site seamlessly and securely. This new REST API feature lets you see what apps are connecting to your site and control what they do.' ); ?></p>
			</div>
			<div class="column">
				<h3><?php _e( 'More PHP 8 support' ); ?></h3>
				<p><?php _e( '5.6 marks the first steps toward WordPress Core support for PHP 8. Now is a great time to start planning how your WordPress products, services and sites can support the latest PHP version. For more information about what to expect next, [link text].' ); ?></p>
			</div>
		</div>
		<div class="about__section">
			<div class="column">
				<h3><?php _e( 'jQuery' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: jQuery update test plugin link. */
						__( 'Updates to jQuery in WordPress take place across three releases 5.5, 5.6 and 5.7. As we reach the mid-point of this process, run the <a href="%s">update test plugin</a> to check your sites for errors ahead of time.' ),
						'https://wordpress.org/plugins/wp-jquery-update-test/'
					);
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: jQuery migrate plugin link. */
						__( 'If you find issues with the way your site looks (e.g. a slider doesn’t work, a button is stuck — that sort of thing), install the <a href="%s">jQuery Migrate plugin.</a>' ),
						'https://wordpress.org/plugins/enable-jquery-migrate-helper/ '
					);
					?>
				</p>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section">
			<div class="column">
				<h3><?php _e( 'Check the Field Guide for more!' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: WordPress 5.6 Field Guide link. */
						__( 'Check out the latest version of the WordPress Field Guide. It highlights developer notes for each change you may want to be aware of. <a href="%s">WordPress 5.6 Field Guide.</a>' ),
						'#'
					);
					?>
				</p>
			</div>
		</div>

		<hr />

		<div class="return-to-dashboard">
			<?php if ( current_user_can( 'update_core' ) && isset( $_GET['updated'] ) ) : ?>
				<a href="<?php echo esc_url( self_admin_url( 'update-core.php' ) ); ?>">
					<?php is_multisite() ? _e( 'Go to Updates' ) : _e( 'Go to Dashboard &rarr; Updates' ); ?>
				</a> |
			<?php endif; ?>
			<a href="<?php echo esc_url( self_admin_url() ); ?>"><?php is_blog_admin() ? _e( 'Go to Dashboard &rarr; Home' ) : _e( 'Go to Dashboard' ); ?></a>
		</div>
	</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';

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
