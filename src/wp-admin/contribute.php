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
$title = __( 'Contribute' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap about__container">

	<div class="about__header">
		<div class="about__header-title">
			<h1>
				<?php _e( 'Contribute' ); ?>
			</h1>
		</div>

		<div class="about__header-text">
			<?php _e( 'Take part in WordPress development' ); ?>
		</div>
	</div>

	<nav class="about__header-navigation nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu' ); ?>">
		<a href="about.php" class="nav-tab"><?php _e( 'What&#8217;s New' ); ?></a>
		<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
		<a href="freedoms.php" class="nav-tab"><?php _e( 'Freedoms' ); ?></a>
		<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
		<a href="contribute.php" class="nav-tab nav-tab-active" aria-current="page"><?php _e( 'Contribute' ); ?></a>
	</nav>

	<div class="about__section has-2-columns is-wider-right">
		<div class="column about__image">
			<img src="data:image/svg+xml,%3Csvg width='436' height='436' viewbox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>
		<div class="column is-vertically-aligned-center">
			<p><?php _e( 'You can contribute to WordPress development bringing your ideas, collaborating on subjects close to your heart and trying many different roles and possibilities, learning continuously and meeting people who share passion to WordPress' ); ?></p>

			<p>
				<?php
				printf(
					/* translators: %s: Number of Make teams */
					__( 'The Make WordPress has more than %s teams you can join, which have working groups and separate projects.' ),
					20
				);
				?>
			</p>
		</div>
	</div>

	<div class="about__section">
		<div class="column">
			<h2 class="is-smaller-heading"><?php _e( 'Hot to find the team you will enjoy?' ); ?></h2>
			<p><?php _e( 'What you like most or have a particular knowledge in can be a starting point, but you can take time to look around and explore possibilities before stepping in to contribute. Either way you can learn a lot.' ); ?></p>
		</div>
	</div>

	<div class="about__section has-2-columns">
		<div class="column is-vertically-aligned-center">
			<ul>
				<li><?php _e( 'Pick a team' ); ?></li>
				<li><?php _e( 'Follow its news to see how you can participate' ); ?></li>
				<li><?php _e( "Attend team's meetings" ); ?></li>
				<li><?php _e( 'Ask for Good First issue to solve' ); ?></li>
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
				<li><a href="<?php _e( '#' ); ?>"><?php _e( 'Translate strings for WordPress and related sites' ); ?></a></li>
				<li><a href="<?php _e( '#' ); ?>"><?php _e( 'Submit photography for Photo Directory' ); ?></a></li>
				<li><a href="<?php _e( '#' ); ?>"><?php _e( 'Help someone on the Support forum' ); ?></a></li>
				<li><a href="<?php _e( '#' ); ?>"><?php _e( 'Translate WordPress News articles into your native language' ); ?></a></li>
				<li><?php _e( 'Test WordPress before releases, checking how new features and fixes works' ); ?></li>
			</ul>
		</div>

		<div class="column is-vertically-aligned-center">
			<h3 class="is-smaller-heading"><?php _e( 'Technical areas' ); ?></h3>
			<ul>
				<li><?php _e( 'Pick your favourite language or library: PHP, JavaScript, Kotlin, Java, Swift, Objective-C, Vue, Python, TypeScript' ); ?></li>
				<li><?php _e( 'Choose what you want to do: testing, creating patches, working on documentation' ); ?></li>
			</ul>
		</div>
		<div class="column">
			<img src="data:image/svg+xml,%3Csvg width='436' height='218' viewbox='0 0 100 50' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='100%25' height='100%25' fill='%231d35b4' /%3E%3C/svg%3E" alt="" />
		</div>

	</div>

	<div class="about__section">
		<div class="column aligncenter">
			<p><a href="<?php _e( '#' ); ?>"><?php _e( 'Orientation tool' ); ?></a></p>
		</div>
	</div>

</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
