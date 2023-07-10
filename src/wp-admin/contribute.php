<?php
/**
 * Contribute administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

// Used in the HTML title tag.
$title = __( 'Get Involved' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap about__container">

	<div class="about__header">
		<div class="about__header-title">
			<h1>
				<?php _e( 'Get Involved' ); ?>
			</h1>
		</div>

		<div class="about__header-text">
			<?php _e( 'Be the future of WordPress' ); ?>
		</div>
	</div>

	<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
		<a href="about.php" class="nav-tab"><?php _e( 'What&#8217;s New' ); ?></a>
		<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
		<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
		<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
		<a href="contribute.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'Get Involved' ); ?></a>
	</nav>

	<div class="about__section has-2-columns is-wider-right">
		<div class="column about__image">
			<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>
		<div class="column is-vertically-aligned-center">
			<p>
				<?php 
				printf(
					/* translators: %s: Number of Make teams */
					__( 'You can shape the long-term success of WordPress. With %s Make WordPress teams working on different parts of the open-source WordPress project, there&#8217;s a place for everyone, no matter what your skill set is. Get involved and connect with others passionate about maintaining a free and Open Web with WordPress.' ),
					22
				);
				?>
			</p>

			<ul>
				<li><?php _e( 'Connect with a global, open source community' ); ?></li>
				<li><?php _e( 'Deploy your skills or learn some new ones' ); ?></li>
				<li><?php _e( 'Grow your network… and even make new friends' ); ?></li>
			</ul>
		</div>
	</div>

	<div class="about__section">
		<div class="column">
			<h2 class="is-smaller-heading"><?php _e( 'Eager to find a team you will enjoy?' ); ?></h2>
			<p><?php _e( 'What you like most or have a particular knowledge in can be a starting point, but you can take time to look around and explore possibilities before stepping in to contribute. Either way you can learn a lot.' ); ?></p>
		</div>
	</div>

	<div class="about__section has-2-columns">
		<div class="column is-vertically-aligned-center">
			<ul>
				<li><?php _e( 'Choose a team' ); ?></li>
				<li><?php _e( 'Follow its news to see how you can participate' ); ?></li>
				<li><?php _e( 'Attend team&#8217;s meetings' ); ?></li>
				<li><?php _e( 'Ask for a Good First issue to solve' ); ?></li>
			</ul>
		</div>
		<div class="column">
			<img src="data:image/svg+xml,%3Csvg width='436' height='218' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>

		<div class="column">
			<img src="data:image/svg+xml,%3Csvg width='436' height='300' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>
		<div class="column is-vertically-aligned-center">
			<h3 class="is-smaller-heading"><?php _e( 'Non-technical areas' ); ?></h3>
			<ul>
				<li><?php _e( 'Translate strings for WordPress, plugins, themes and other WordPress.org related projects' ); ?></li>
				<li><?php _e( 'Submit photography for Photo Directory' ); ?></li>
				<li><?php _e( 'Help someone on the Support forum' ); ?></li>
				<li><?php _e( 'Translate WordPress News articles into your native language' ); ?></li>
				<li><?php _e( 'Test WordPress before releases, checking how new features and fixes works' ); ?></li>
			</ul>
		</div>

		<div class="column is-vertically-aligned-center">
			<h3 class="is-smaller-heading"><?php _e( 'Technical areas' ); ?></h3>
			<ul>
				<li><?php _e( 'Pick your favourite language or library:' ); ?>
					<ul>
						<li><?php _e( 'HTML, CSS, PHP, JavaScript and React for the Core of the CMS and the Block Editor (Gutenberg project' ); ?></li>
						<li><?php _e( 'Kotlin, Java, Swift, Objective-C, Vue, Python, TypeScript for mobile apps' ); ?></li>
					</ul>
				</li>
				<li><?php _e( 'Choose what you want to do: testing, creating patches, working on documentation' ); ?></li>
			</ul>
		</div>
		<div class="column">
			<img src="data:image/svg+xml,%3Csvg width='436' height='280' viewbox='0 0 100 50' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>

	</div>

	<div class="about__section">
		<div class="column aligncenter">
			<p><a href="https://make.wordpress.org/contribute/" target="_blank"><?php _e( 'Discover your Make WordPress team' ); ?></a></p>
		</div>
	</div>

</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
