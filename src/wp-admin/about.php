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
				<?php printf( 'Introducing the new default Twenty Twenty-One theme in WordPress 5.6.' ); ?>
			</div>

			<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
				<a href="about.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'What&#8217;s New' ); ?></a>
				<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
				<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
				<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
			</nav>
		</div>

		<hr />

		<div class="about__section has-1-column">
			<div class="column">
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
						( 'WordPress %s brings you countless ways to set your ideas free and bring them to life. With a brand-new default theme as your canvas, it supports an ever-growing collection of blocks as your brushes. Paint with words. Pictures. Sounds. Motion. Data. Or elegant apps.' ),
						$display_version
					);
					?>
				</p>
				<p>
					<?php printf( 'It’s up to you.' ); ?>
				</p>
			</div>
		</div>

		<hr class="is-large" />

		<div class="about__section is-feature">
			<h2><?php printf( 'Twenty Twenty-One is here!' ); ?></h2>
			<p>
				<?php
				printf( 'This is WordPress’ most accessible default theme ever: Twenty Twenty-One. Built for the block editor, this latest default theme brings you a fresh set of block patterns.' );
				?>
			</p>
		</div>

		<div class="about__section">
			<div class="column about__image is-edge-to-edge">
				<img src="https://via.placeholder.com/1000?text=Theme+Screenshots" alt="" />
			</div>
		</div>

		<hr />

		<div class="about__section has-overlap-style">
			<div class="column is-top-layer is-vertically-aligned-center">
				<h3><?php printf( 'Beautiful Color Palettes' ); ?></h3>
				<p>
					<?php
					printf( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in.' );
					?>
				</p>
			</div>
			<div class="column about__image">
				<img src="data:image/svg+xml,%3Csvg width='440' height='291' viewBox='0 0 440 291' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='294.5' cy='145.5' r='145.5' fill='%23E5D1D1'/%3E%3Ccircle cx='106.5' cy='106.5' r='106.5' fill='%23EEEADD'/%3E%3C/svg%3E" alt="" />
			</div>
		</div>

		<div class="about__section">
			<div class="column about__image is-edge-to-edge">
				<img src="https://via.placeholder.com/1000x366?text=All+Palettes+Screenshots" alt="" />
			</div>
		</div>

		<hr />
		<hr class="is-large" />

		<div class="about__section">
			<header class="column is-edge-to-edge">
				<h2><?php printf( 'Improvements for everyone' ); ?></h2>
			</header>
		</div>

		<div class="about__section has-3-columns" style="column-gap:32px;">
			<div class="column has-accent-background-color" style="background-color:var(--global--color-yellow)">
				<p><?php printf( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Nec ultrices dui sapien eget mi proin. Mi ipsum faucibus vitae aliquet. Congue mauris rhoncus aenean vel elit scelerisque mauris pellentesque.' ); ?></p>
			</div>
			<div class="column has-accent-background-color" style="background-color:var(--global--color-red)">
				<p><?php printf( 'Non pulvinar neque laoreet suspendisse interdum consectetur libero. Et netus et malesuada fames ac turpis egestas maecenas pharetra. Ac tortor dignissim convallis aenean et tortor at risus viverra.' ); ?></p>
			</div>
			<div class="column has-accent-background-color" style="background-color:var(--global--color-purple)">
				<p><?php printf( 'Morbi quis commodo odio aenean. Enim diam vulputate ut pharetra. Lorem dolor sed viverra ipsum nunc aliquet bibendum. Tortor pretium viverra suspendisse potenti nullam ac tortor vitae purus. Dui id ornare arcu odio ut sem nulla pharetra diam.' ); ?></p>
			</div>
		</div>

		<hr />

		<div class="about__section has-accent-background-color has-2-columns">
			<header class="is-section-header">
				<h2><?php _e( 'For developers' ); ?></h2>
				<p><?php _e( '5.5 also brings a big box of changes just for developers.' ); ?></p>
			</header>
			<div class="column">
				<h3><?php _e( 'Server-side registered blocks in the REST API' ); ?></h3>
				<p><?php _e( 'The addition of block types endpoints means that JavaScript apps (like the block editor) can retrieve definitions for any blocks registered on the server.' ); ?></p>
			</div>
			<div class="column">
				<h3><?php _e( 'Dashicons' ); ?></h3>
				<p><?php _e( 'The Dashicons library has received its final update in 5.5. It adds 39 block editor icons along with 26 others.' ); ?></p>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section">
			<div class="column">
				<h3><?php _e( 'Check the Field Guide for more!' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: WordPress 5.5 Field Guide link. */
						__( 'There’s a lot more for developers to love in WordPress 5.5. To discover more and learn how to make these changes shine on your sites, themes, plugins and more, check the <a href="%s">WordPress 5.5 Field Guide.</a>' ),
						'https://make.wordpress.org/core/wordpress-5-5-field-guide/'
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
