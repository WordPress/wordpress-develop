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
					<span><?php echo $display_version; ?></span>
				</p>
			</div>

			<div class="about__header-text">
			</div>

			<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
				<a href="about.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'What&#8217;s New' ); ?></a>
				<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
				<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
				<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
			</nav>
		</div>

		<div class="about__section is-feature has-accent-background-color">
			<h1><?php _e( 'In WordPress 5.5, your site gets new power in three major areas: Speed. Search. And Security.' ); ?></h1>

			<p><?php _e( 'And by installing this version of WordPress, you’ve already done the work.' ); ?></p>
		</div>

		<hr />

		<div class="about__section has-2-columns">
			<div class="column is-edge-to-edge">
				<div class="about__image aligncenter">
					<img src="data:image/svg+xml;charset=utf8,%3Csvg width='500' height='500' viewbox='0 0 500 500' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23F3F4F5' d='M0 0h500v500H0z'/%3E%3Cpath d='M346.7 37.645s100.5-2.8 102.6 0c2.1 2.8 0 124.999 0 124.999l-106.9 2.8 4.3-127.8z' fill='%232CA8EB'/%3E%3Cpath d='M343.5 185.844s100.5-1.9 102.6 0c2.1 1.9 1.1 125.9 4.3 127.8 3.2 1.9-100.5 1.9-104.8 2.8-4.3.9-2.1-130.6-2.1-130.6z' fill='%237CAED2'/%3E%3Cpath d='M195.6 186.744s102.7 2.8 106.9 2.8c4.2 0 7.4 120.4 4.2 122.2-3.2 1.9-106.9 2.8-106.9 2.8s2.1-126.9-4.2-127.8z' fill='%2381A4D4'/%3E%3Cpath d='M152.8 192.344s2.1 124.1 4.3 126.9c2.1 2.7-109.1 1.8-109.1 1.8v-128.7h104.8z' fill='%235DC3D8'/%3E%3Cpath d='M152 464.544H56v-119.8l101-1.7s-8.9 118-5 121.5z' fill='%230574E2'/%3E%3Cpath d='M195 35.844h101.6s-8.6 119.4 0 125c8.6 5.6-101.6 3.7-101.6 3.7v-128.7z' fill='%23216BCE'/%3E%3Cpath d='M302.3 463.844s-102.9 2.8-105.1 0c-2.2-2.8 0-125 0-125l109.5-2.8-4.4 127.8z' fill='%231C99D1'/%3E%3Cpath d='M346.2 464.544s-2.1-124.1-4.3-126.9c-2.1-2.8 109.1-1.9 109.1-1.9v128.7H346.2v.1z' fill='%231B44DD'/%3E%3Cpath d='M50.2 35.844s100.5-1.9 102.6 0c2.1 1.9 1.1 125.9 4.3 127.8 3.2 1.9-100.5 1.9-104.8 2.8-4.3.9-2.1-130.6-2.1-130.6z' fill='%231B36BC'/%3E%3C/svg%3E" alt="" />
				</div>
			</div>
			<div class="column is-vertically-aligned-center">
				<h2><?php _e( 'Speed' ); ?></h2>
				<p><?php _e( 'Posts and pages feel wildly faster, thanks to lazy-loaded images.' ); ?></p>
				<p><?php _e( 'We’ve all been there. That maddening, crazy-making time it takes for a page to load a piece of information we need RIGHT NOW … only, it wouldn’t load a single pixel until it’s loaded six images that go with sections farther down the screen …' ); ?></p>
				<p><?php _e( 'Well, never again.' ); ?></p>
				<p><?php _e( 'Because WordPress 5.5 makes those images wait until just before you scroll down to see them—and loads the text you want to read first.' ); ?></p>
				<p><?php _e( 'The technical term is <strong>*lazy-loading.*</strong> It makes every page feel faster for you and your users, getting them the information they came for and keeping more of them on your site longer. Which, in turn, builds their engagement with your content and community. Boosts the odds they’ll join your community, bump your authority scores and buy your products.' ); ?></p>
				<p><?php _e( 'And rank higher in search, because the engines love speed as much your users do.' ); ?></p>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column is-vertically-aligned-center">
				<h2><?php _e( 'XML sitemaps are here and on by default.' ); ?></h2>
				<p><?php _e( 'There’s more to search than speed.' ); ?></p>
				<p><?php _e( 'One of the best things you can do is install an XML sitemap. Because that’s the most efficient way for search engines to crawl your site, and the benefits to your rankings are real.' ); ?></p>
				<p><?php _e( 'So you decide one morning that it’s time to get serious about a sitemap. You look at plugins. You read articles. You ask for advice. And by lunchtime, you’re almost ready to make a decision … and life hauls you back to the real world. So long, sitemaps.' ); ?></p>
				<p><?php _e( 'Until today.' ); ?></p>
				<p><?php _e( 'Because when you upgraded to WordPress 5.5 a couple of minutes ago, you also installed an XML sitemap, and it’s turned on by default.' ); ?></p>
				<p><?php _e( 'Should you finish your research into plugins and best practices? Absolutely! Just know that until then, WordPress has your back, and your most important pages and posts are ready for the search engines to index them exactly as every SEO expert in the community would advise. (And you didn’t have to lift a finger.)' ); ?></p>
			</div>
			<div class="column is-edge-to-edge">
				<div class="about__image aligncenter">
					<img src="data:image/svg+xml;charset=utf8,%3Csvg width='500' height='500' viewbox='0 0 500 500' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23F3F4F5' d='M0 0h500v500H0z'/%3E%3Cg clip-path='url(%23clip0)'%3E%3Cpath d='M169.6 171.55l-.3 72.3 330.7-1v-72.6l-330.4 1.3z' fill='%230740B3'/%3E%3Cpath d='M291.2 97.85l-1.3-14.8-63.4-.7v76c0 3.6 176.7 4.1 273.5 4.1v-64.5H291.2v-.1z' fill='%230285D7'/%3E%3Cpath d='M500 27.75l-215.5-5.9 5.4 61.2 210.1 2.5v-57.8z' fill='%231730E5'/%3E%3Cpath d='M500 97.85v-12.3l-210.1-2.5 1.3 14.8H500z' fill='%230285D7'/%3E%3Cpath d='M500 97.85v-12.3l-210.1-2.5 1.3 14.8H500z' fill='%231730E5' style='mix-blend-mode:multiply'/%3E%3Cpath d='M255.2 379.75l-1-49.2-229.2.3-2 69.7 477-1.3v-24.3l-244.8 4.8z' fill='%230285D7'/%3E%3Cpath d='M500 424.35v-15l-430.8 1.2-4 51.5 134.6-.5v-34.4c.1-2.8 214.4-2.9 300.2-2.8z' fill='%230878FF'/%3E%3Cpath d='M500 290.05l-246.4 4.3.6 36.2 245.8-.3v-40.2z' fill='%23072CF0'/%3E%3Cpath d='M500 374.95v-44.7l-245.8.3 1 49.2 244.8-4.8z' fill='%230285D7'/%3E%3Cpath d='M500 374.95v-44.7l-245.8.3 1 49.2 244.8-4.8z' fill='%23072CF0' style='mix-blend-mode:multiply'/%3E%3Cpath d='M199.9 461.55v17.6l300.1-2.4v-16.3l-300.1 1.1z' fill='%230285D7'/%3E%3Cpath d='M500 424.35c-85.8-.1-300.1 0-300.1 2.8v34.4l300.1-1.1v-36.1z' fill='%230878FF'/%3E%3Cpath d='M500 424.35c-85.8-.1-300.1 0-300.1 2.8v34.4l300.1-1.1v-36.1z' fill='%230285D7' style='mix-blend-mode:multiply'/%3E%3C/g%3E%3Cdefs%3E%3CclipPath id='clip0'%3E%3Cpath transform='rotate(-90 23 479.15)' fill='%23fff' d='M23 479.15h457.3v477H23z'/%3E%3C/clipPath%3E%3C/defs%3E%3C/svg%3E" alt="">
				</div>
			</div>
		</div>

		<div class="about__section has-2-columns">
			<div class="column is-edge-to-edge">
				<div class="about__image aligncenter">
					<img src="data:image/svg+xml;charset=utf8,%3Csvg width='500' height='500' viewbox='0 0 500 500' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23F3F4F5' d='M0 0h500v500H0z'/%3E%3Cpath d='M31.3 284.4c-2-.1 12.2-250.6 12.2-250.6s94.8 4.4 99.7 5.2c.3 21.8 4.1 250.1 4.1 250.1l-116-4.7z' fill='%231730E5'/%3E%3Cpath d='M346.8 467.4l-11.7-305.9 138.2 2.4-3 304.1-123.5-.6z' fill='%230840B3'/%3E%3Cpath d='M287.7 34.9c2.3 0 5.9 398.5 5.9 398.5s-109.6-2.2-115 .6c-5.4 2.8 10.6-400.5 10.6-400.5l98.5 1.4z' fill='%23018BDE'/%3E%3Cpath d='M372.3 138c32.585 0 59-26.415 59-59s-26.415-59-59-59-59 26.415-59 59 26.415 59 59 59z' fill='%23062EF7'/%3E%3Cpath d='M35.8 315c-12.8 0-24.9 2.9-35.8 8.1v148.7c10.8 5.2 22.9 8.1 35.8 8.1 45.6 0 82.5-36.9 82.5-82.5S81.3 315 35.8 315z' fill='%231C87C0'/%3E%3C/svg%3E" alt="" />
				</div>
			</div>
			<div class="column is-vertically-aligned-center">
				<h2><?php _e( 'Security.' ); ?></h2>
				<p><?php _e( 'You hear it twice a day: The absolute best thing you can do to keep your site secure is to keep your themes and plugins (and WordPress itself!) up to date.' ); ?></p>
				<p><?php _e( 'Have you ever had a few days go by where you thought maybe that was your whole job?' ); ?></p>
				<p><?php _e( 'If so, you’re not alone. And you’re finally done.' ); ?></p>
				<p><?php _e( 'Because now you can update all your themes and plugins automatically in two clicks — one for themes and one for plugins. And if you choose, you never have to think about either one again.' ); ?></p>
				<p><?php _e( 'Of course, life is rarely that simple. So if you have some plugins or themes you need to keep an eye on, you can enable or disable automatic updating for each one. It’s a few more clicks, but you still do it all from just two pages — one for plugins and one for themes.' ); ?></p>
			</div>
		</div>

		<hr />

		<div class="about__section has-2-columns has-subtle-background-color">
			<h2 class="is-section-header"><?php _e( 'Speed. Search. Security. The power of three big improvements, all packed into WordPress 5.5.' ); ?></h2>
			<div class="column">
				<p><?php _e( 'They’ve been a long time coming, and they represent a huge step forward for WordPress.' ); ?></p>
				<p><?php _e( 'But that’s not the whole story of this release.' ); ?></p>
			</div>
			<div class="column">
				<p><?php _e( 'As we’ve all seen with every release in the last two years, WordPress 5.5 brings a basketful of improvements, refinements and a few things reimagined to the way more than 52 million people tell their stories every day.' ); ?></p>
			</div>
		</div>

		<hr />

		<div class="about__section ">
			<div class="column has-subtle-background-color">
				<h2 class="is-section-header"><?php _e( 'Highlights from the block editor' ); ?></h2>
			</div>
		</div>

		<div class="about__section">
			<div class="column">
				<p><?php _e( 'Check out these favorites of the Gutenberg squad, and see if you agree:' ); ?></p>
				<ul>
					<li><p><?php _e( '<strong>Inline image editing</strong> - Crop, rotate, and zoom your photos right from the image block. If you do a lot of work with images, this alone could save you hours every week!' ); ?></li>
					<li><?php _e( '<strong>Block patterns</strong> - Now building elaborate pages is a breeze, with new content templates that have the blocks you need, right where you need them.' ); ?>
					<ul>
						<li><?php _e( '<strong>Sets of columns.</strong></li><li>	Combinations of columns with images.' ); ?></li>
						<li><?php _e( 'Sets of two buttons.' ); ?></li>
						<li><?php _e( 'And many, many more – all together, all in a single dropdown. Even if they’re from a variety of different plugins!' ); ?></li>
					</ul>
				</li>
				<li><?php _e( '<strong>Device previews</strong> - Is there a bigger pain in the neck than having to move from device to device to check what your page design looks like before you can move on to the next section? What a way to break your train of thought! Well, forget that nonsense. Because now, in 5.5, you can preview screen sizes on the fly.' ); ?></li>
				<li><?php _e( '<strong>End block overwhelm forever</strong>. The new block inserter panel shows you streamlined categories and collections. Plus, it supports patterns and integrates with the block directory right out of the box.' ); ?></li>
				<li><?php _e( '<strong>Discover, install, and insert third-party blocks</strong> right from your editor, thanks to the new block directory. (It’s that list! No searching!)' ); ?></li>
				<li><?php _e( 'A better, <strong>smoother editing experience</strong>with (to name a few):' ); ?>
					<ul>
						<li><?php _e( 'refined drag-and-drop' ); ?></li>
						<li><?php _e( 'Block movers you can see and grab. For real!' ); ?></li>
						<li><?php _e( 'Finally. You can select a parent block, and keep it selected as long as you need it!' ); ?></li>
						<li><?php _e( 'Highlighting that follows what you’re doing and flows with the context.' ); ?></li>
						<li><?php _e( 'Select a bunch of blocks, change a bunch of blocks. All at once!' ); ?></li>
						<li><?php _e( 'With every iteration, it keeps getting easier to copy blocks and move them where you need them to go.' ); ?></li>
						<li><?php _e( 'And always, better performance. Because who wants to wait for a redraw?' ); ?></li>
					</ul>
				</li>
				<li><strong><?php _e( 'More design tools, for better control.</strong> For starters:' ); ?>
					<ul>
						<li><?php _e( 'Inline image editing' ); ?></li>
						<li><?php _e( 'Theme support for link color' ); ?></li>
						<li><?php _e( 'A full set of alignment options' ); ?></li>
						<li><?php _e( '(Finally!) padding in the cover block you can actually control' ); ?></li>
					</ul>
				</li>
				<li><?php _e( 'Now <strong>add backgrounds and gradients</strong> to more kinds of blocks, like groups, columns, media & text' ); ?></li>
				<li><?php _e( 'And <strong>use the units of measure you want</strong> -- not just pixels. Choose ems, rems, percentages, vh, vw, and more! Plus, adjust line heights as you type, turning writing and typesetting into the seamless act it always should have been.' ); ?></li>
			</ul>
			<p><?php _e( 'In all, WordPress 5.5 brings more than 1,500 useful improvements to the editor experience.' ); ?></p>
			<p><?php _e( 'To see all of the features for each release in detail check out these release posts: <a href="https://github.com/WordPress/gutenberg/releases/tag/v7.5.0">7.5</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v7.6.0">7.6</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v7.7.0">7.7</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v7.8.0">7.8</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v7.9.0">7.9</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v8.0.0">8.0</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v8.1.0">8.1</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v8.2.0">8.2</a> ,  <a href="https://github.com/WordPress/gutenberg/releases/tag/v8.3.0">8.3</a> , and  <a href="https://github.com/WordPress/gutenberg/releases/tag/v8.4.0">8.4</a>.' ); ?></p>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section ">
			<div class="column has-subtle-background-color">
				<h2 class="is-section-header"><?php _e( 'Wait! There’s more!' ); ?></h2>
			</div>
		</div>

		<div class="about__section">
			<div class="column">
				<h3><?php _e( 'Better accessibility' ); ?></h3>
				<p><?php _e( 'With every release, WordPress works hard to improve accessibility. Version 5.5 is no different and packs a parcel of accessibility fixes and enhancements.' ); ?></p>
				<p><?php _e( 'Take a look:' ); ?></p>
				<ul>
					<li><?php _e( 'Now WP List Tables come with alternate view modes, and they’re extensible.' ); ?></li>
					<li><?php _e( 'Ever wanted to switch link-list Widgets to HTML5 navigation blocks? Now you can.' ); ?></li>
					<li><?php _e( 'Copy links in media screens and modal dialogs by hitting a button – so much easier!' ); ?></li>
					<li><?php _e( 'Buttons that <i>are</i> disabled now <i>look</i> disabled.' ); ?></li>
					<li><?php _e( 'Move meta boxes with the keyboard, and never drop one again.' ); ?></li>
					<li><?php _e( 'A custom logo on the front page doesn’t need to link to the front page. And now it doesn’t.' ); ?></li>
					<li><?php _e( 'Assistive devices can see status messages in the Image Editor. So if you’re using such a device, now you can also use the Image Editor.' ); ?></li>
					<li><?php _e( 'That shake animation that indicates a login failure? Now it respects your choice if you enabled the <code>prefers-reduced-motion</code> media query.' ); ?></li>
					<li><?php _e( 'And that redundant <code>Error:</code> prefix in error notices? Gone.' ); ?></li>
				</ul>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section">
			<div class="column">
				<h3><?php _e( 'For developers' ); ?></h3>
				<p><?php _e( '5.5 also brings a big box of changes just for developers.' ); ?></p>
				<p><strong><?php _e( 'Lazy-loading images:' ); ?></strong></p>
				<p><?php _e( 'You saw the description. Now, here are the details: It works courtesy of a new loading attribute on image tags, which delays loading until the image scrolls into the user’s viewport.' ); ?></p>
				<p><?php _e( '<strong>PHPMailer:</strong>' ); ?></p>
				<p><?php _e( 'Now that the minimum PHP version is 5.6.20, PHPMailer is newly updated to the 6.x library,' ); ?></p>
				<p><?php _e( 'Note: that changes where the file lives and how you’ll call the class. The new setup maintains backward compatibility, but loading the files from the old location will generate a notice. <a href="https://make.wordpress.org/core/2020/07/01/external-library-updates-in-wordpress-5-5-call-for-testing/">Please read the full notice for more details</a>.' ); ?></p>
				<p><?php _e( '<strong>Other changes for developers</strong>' ); ?></p>
				<ul>
					<li><?php _e( '<a href="https://make.wordpress.org/core/2020/06/26/wordpress-5-5-better-fine-grained-control-of-redirect_guess_404_permalink/">Fine-grained control of redirect_guess_404_permalink()</a>.' ); ?></li>
					<li><?php _e( 'Plus, now that the WordPress project supports only PHP 5.6.2 and later, you’ll find updates to these bundled libraries:' ); ?>
					<li><?php _e( '<a href="https://make.wordpress.org/core/2020/07/01/external-library-updates-in-wordpress-5-5-call-for-testing/">SimplePie is now at 1.5.5</a>.' ); ?></li>
					<li><?php _e( '<a href="https://core.trac.wordpress.org/ticket/50148">Twemoji is now at v13.0</a>.' ); ?></li>
					<li><?php _e( '<a href="https://make.wordpress.org/core/tag/dev-notes/">The Masonry library, formerly at version v3.3.2 to 4.2.2, and the related imagesLoaded library has been updated from v3.2.0 to v4.1.4</a>.' ); ?></li>
					<li><?php _e( 'The getID3 library has been updated from version <a href="https://core.trac.wordpress.org/ticket/49945">v1.9.18 to v1.9.20</a>.' ); ?></li>
					<li><?php _e( 'The Moment.js library has been updated from version <a href="https://core.trac.wordpress.org/ticket/50408">2.22.2 to 2.27.0</a>.' ); ?></li>
					<li><?php _e( 'The clipboard.js library has been updated from version  <a href="https://core.trac.wordpress.org/ticket/50306">v2.0.4 to v2.0.6</a>.' ); ?></li>
				</ul>
				<p><?php _e( 'For all the details, check out the WordPress 5.5 Field Guide, a compendium of all the dev notes you’ll need (and then some!) to keep your sites running smoothly and your clients and colleagues happy with all the great things packed into WordPress 5.5!' ); ?></p>
			</div>
		</div>

		<hr class="is-small" />

		<div class="about__section">
			<div class="column">
				<p>
					<?php
					printf(
						/* translators: %s: WordPress 5.4 Field Guide link. */
						__( 'There&#8217;s lots more for developers to love in WordPress 5.5. To discover more and learn how to make these changes shine on your sites, themes, plugins and more, check the <a href="%s">WordPress 5.5 Field Guide</a>.' ),
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
					<?php is_multisite() ? _e( 'Return to Updates' ) : _e( 'Return to Dashboard &rarr; Updates' ); ?>
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
