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
					<?php _e( 'WordPress' ); ?>
					<span class="screen-reader-text"><?php echo $display_version; ?></span>
				</h1>
			</div>

			<div class="about__header-text">
				<?php _e( 'Introducing site editing with block-based themes' ); ?>
			</div>

			<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
				<a href="about.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'What&#8217;s New' ); ?></a>
				<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
				<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
				<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
			</nav>
		</div>

		<hr class="is-large" />

		<div class="about__section">
			<h2 class="aligncenter">
				<?php _e( 'Full Site Editing is here' ); ?>
			</h2>
			<p class="aligncenter is-subheading">
				<?php _e( 'And puts you in control of your whole site, right in the WordPress Admin.' ); ?>
			</p>
		</div>

		<hr />

		<div class="about__section has-2-columns is-wider-left">
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-tt2.png" alt="" />
			</div>
			<div class="column">
				<h3>
					<?php _e( 'Say hello to Twenty Twenty&#8209;Two' ); ?>
				</h3>
				<p>
					<?php _e( 'And say hello to the first default block theme in the history of WordPress. This is more than just a new default theme. It&#8217;s a brand-new way to work with WordPress themes.' ); ?>
				</p>
				<p>
					<?php _e( 'Block themes put a startling array of visual choices directly in your hands, from color schemes and type combinations to page templates and image filters—all together, in the site editing interface. With blocks, you can make Twenty Twenty-Two follow your branding&#8212;or take on just about any other graphic look you can think of.' ); ?>
				</p>
				<?php if ( current_user_can( 'switch_themes' ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: Link to Themes screen. */
						__( 'You already have Twenty Twenty-Two. It came installed with WordPress 5.9, and you will find it with <a href="%s">your other installed themes</a>.' ),
						admin_url( 'themes.php' )
					);
					?>
				</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="about__section has-2-columns is-wider-right">
			<div class="column">
				<h3>
					<?php _e( 'Your personal paintbox awaits' ); ?>
				</h3>
				<p>
					<?php _e( 'When you use a theme built for Full Site Editing (also known as a block theme), you no longer need the Customizer. Instead, feel the power of the Styles interface inside the Site Editor.' ); ?>
				</p>
				<p>
					<?php _e( 'Everything you need to build the site you’ve always wanted (but used to need a developer for) is in that editor, in a fluid interface that practically comes alive in your hands.' ); ?>
				</p>
			</div>
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-global-styles.png" alt="" />
			</div>
		</div>

		<div class="about__section has-2-columns is-wider-left">
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-navigation-block.png" alt="" />
			</div>
			<div class="column">
				<h3>
					<?php _e( 'The Navigation block' ); ?>
				</h3>
				<p>
					<?php _e( 'Finally. Blocks come to site navigation, the heart of user experience.' ); ?>
				</p>
				<p>
					<?php _e( 'The new Navigation block is the soul of flexibility. Choose an always-on responsive menu or one that adapts to your user’s screen size—they’re both built in.' ); ?>
				</p>
				<p>
					<?php _e( 'Plus: In 5.9, the block saves menus as custom post types, which get saved to the database. When you build a menu, it stays built. Even if you change themes.' ); ?>
				</p>
			</div>
		</div>

		<hr class="is-large" />

		<div class="about__section">
			<h2 class="aligncenter">
				<?php _e( 'More improvements and updates' ); ?>
			</h2>
			<p class="aligncenter is-subheading">
				<?php _e( 'Do you love to blog? New tweaks to the publishing flow help you say more, faster.' ); ?>
			</p>
		</div>

		<hr />

		<div class="about__section has-2-columns is-wider-left">
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-pattern-explorer.png" alt="" />
			</div>
			<div class="column">
				<h3>
					<?php _e( 'The power of patterns' ); ?>
				</h3>
				<p>
					<?php _e( 'The WordPress Pattern Directory is the home of a wide range of pre-built block patterns you can use as they are or change as you need to. Don&#8217;t like the header or footer that comes in your theme? Swap it out with a new one in a few clicks.' ); ?>
				</p>
				<p>
					<?php _e( 'With a nearly full-screen view that draws you in to see fine details, the Pattern Explorer makes it easy to compare patterns and choose the right one. Just open the inserter, switch to the patterns tab, and click &#8220;Explore&#8221; to see all your options.' ); ?>
				</p>
			</div>
		</div>

		<div class="about__section has-2-columns is-wider-right">
			<div class="column">
				<h3>
					<?php _e( 'Better block controls' ); ?>
				</h3>
				<p>
					<?php _e( 'WordPress 5.9 features new typography tools, flexible layout controls, and finer control over details like spacing, borders, and more&#8212;to help you get not just the look, but the polish that says you care about details.' ); ?>
				</p>
			</div>
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-block-controls.png" alt="" />
			</div>
		</div>

		<div class="about__section has-2-columns is-wider-left">
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-list-view.png" alt="" />
			</div>
			<div class="column">
				<h3>
					<?php _e( 'A revamped List View' ); ?>
				</h3>
				<p>
					<?php _e( 'In 5.9, the List View lets you drag and drop your content exactly where you want it. Managing complex documents is easier, too: simple controls let you expand and collapse sections as you build your site&#8212;and add HTML anchors to your blocks to help users get around the page.' ); ?>
				</p>
			</div>
		</div>

		<div class="about__section has-2-columns is-wider-right">
			<div class="column">
				<h3>
					<?php _e( 'A better Gallery block' ); ?>
				</h3>
				<p>
					<?php _e( 'Treat every image in a Gallery Block the same way you&#8217;d treat it in the Image Block.' ); ?>
				</p>
				<p>
					<?php _e( 'Make every image in your gallery different from the next, or make them all the same, except for one or two. Or change the layout with drag-and-drop.' ); ?>
				</p>
			</div>
			<div class="column about__image is-vertically-aligned-center is-edge-to-edge">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-gallery-block.png" alt="" />
			</div>
		</div>

		<hr class="is-large" />

		<div class="about__section">
			<h2 class="aligncenter" style="margin-bottom:0;">
				<?php _e( 'For Developers to Explore' ); ?>
			</h2>
		</div>

		<div class="about__section has-3-columns">
			<div class="column">
				<h3>
					<?php _e( 'Theme.json for child themes' ); ?>
				</h3>
				<p>
					<?php
					printf(
						/* translators: %s: dev note link TBD. */
						__( 'In 5.9, theme.json supports child themes. That means your users can build a child theme right in the WordPress Admin, without writing a single line of code. More information is available in the <a href="%s">theme.json dev note</a>.' ),
						'#'
					);
					?>
				</p>
			</div>
			<div class="column">
				<h3>
					<?php _e( 'Block-level locking' ); ?>
				</h3>
				<p>
					<?php _e( 'Now you can lock any block (or a few of them)  in a pattern, just by adding a lock attribute to its settings in block.json—leaving the rest of the pattern free for users to adapt to their content.' ); ?>
				</p>
			</div>
			<div class="column">
				<h3>
					<?php _e( 'A refactored Gallery block' ); ?>
				</h3>
				<p>
					<?php
					printf(
						/* translators: %s: Gallery Refactor dev note link. */
						__( 'The changes to the Gallery block are the result of near-complete refactor. Have you built a plugin or theme on the Gallery block functionality? Be sure to read the <a href="%s">Gallery block compatibility dev note</a>.' ),
						'https://make.wordpress.org/core/2021/08/20/gallery-block-refactor-dev-note/'
					);
					?>
				</p>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section has-subtle-background-color has-2-columns is-wider-right">
			<div class="column about__image is-vertically-aligned-center">
				<img src="https://make.wordpress.org/core/files/2021/12/about-59-learn-video.png" alt="" />
			</div>
			<div class="column">
				<h3><?php _e( 'Learn more about the new features in 5.9' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: Learn WordPress link. */
						__( 'Want to dive into 5.9 but don’t know where to start? Visit <a href="%s">learn.wordpress.org/workshops</a>, the official WordPress education site, for short how-to video tutorials on many of the new features in WordPress 5.9.' ),
						'https://learn.wordpress.org/workshops/'
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
						/* translators: %s: WordPress 5.9 Field Guide link. */
						__( 'Check out the latest version of the WordPress Field Guide. It highlights developer notes for each change you may want to be aware of. <a href="%s">WordPress 5.9 Field Guide.</a>' ),
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
