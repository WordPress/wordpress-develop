<?php
/**
 * Class for rendering the On This Day dashboard widget.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.1.0
 */

/**
 * Core class used by the "On This Day" dashboard widget.
 *
 * Renders the current user's posts that were published on this same
 * month and day in previous years, grouped by year.
 *
 * @since 7.1.0
 */
#[AllowDynamicProperties]
class WP_On_This_Day {
	/**
	 * Number of posts to fetch for the widget.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const POSTS_PER_PAGE = 50;

	/**
	 * Object cache group used for storing widget results.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const CACHE_GROUP = 'on_this_day';

	/**
	 * Cache version. Bump when the rendered markup changes so stale
	 * entries from older releases are naturally ignored.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const CACHE_VERSION = 2;

	/**
	 * Renders the dashboard widget output.
	 *
	 * The rendered HTML is cached per user, locale, and site date. The
	 * cache key also incorporates the posts group's `last_changed` token,
	 * so any post mutation (publish, edit, delete, trash) automatically
	 * invalidates the entry on the next read, and entries roll over
	 * naturally at midnight.
	 *
	 * Note: I made the trade-off to ignore `time_format` option changes.
	 * They do not bust the cache; stale time strings clear on the next
	 * post mutation or at midnight.
	 *
	 * @since 7.1.0
	 */
	public static function render_dashboard_widget() {
		$user_id = get_current_user_id();

		$cache_key = sprintf(
			'render:v%d:%d:%s:%s:%s',
			self::CACHE_VERSION,
			$user_id,
			determine_locale(),
			current_time( 'Y-m-d' ),
			wp_cache_get_last_changed( 'posts' )
		);

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_string( $cached ) ) {
			// Already escaped at write time by the render_* methods below.
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$posts = self::get_posts( $user_id );

		ob_start();
		echo '<div class="on-this-day-widget">';
		if ( empty( $posts ) ) {
			self::render_empty_state();
		} else {
			self::render_posts( $posts );
		}
		echo '</div>';
		$html = ob_get_clean();

		wp_cache_set( $cache_key, $html, self::CACHE_GROUP, DAY_IN_SECONDS );

		// Already escaped at write time by the render_* methods below.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Retrieves posts by a given author that were published on this
	 * same month and day in previous years.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id Author ID to query posts for.
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	public static function get_posts( $user_id ) {
		$args = array(
			'author'              => (int) $user_id,
			'post_type'           => 'post',
			'post_status'         => array( 'publish', 'private' ),
			'posts_per_page'      => self::POSTS_PER_PAGE,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
		);

		/**
		 * Filters the arguments used to query posts for the On This Day dashboard widget.
		 *
		 * @since 7.1.0
		 *
		 * @param array $args    WP_Query arguments.
		 * @param int   $user_id The author ID the query is scoped to.
		 */
		$args = apply_filters( 'dashboard_on_this_day_query_args', $args, $user_id );

		add_filter( 'posts_where', array( __CLASS__, 'filter_posts_where' ) );
		$query = new WP_Query( $args );
		remove_filter( 'posts_where', array( __CLASS__, 'filter_posts_where' ) );

		return $query->posts;
	}

	/**
	 * Restricts the widget's query to the current month and day in prior years.
	 *
	 * @since 7.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $where SQL WHERE clause.
	 * @return string Filtered WHERE clause.
	 */
	public static function filter_posts_where( $where ) {
		global $wpdb;

		$month = (int) current_time( 'n' );
		$day   = (int) current_time( 'j' );
		$year  = (int) current_time( 'Y' );

		$where .= $wpdb->prepare(
			" AND MONTH({$wpdb->posts}.post_date) = %d AND DAY({$wpdb->posts}.post_date) = %d AND YEAR({$wpdb->posts}.post_date) < %d",
			$month,
			$day,
			$year
		);

		return $where;
	}

	/**
	 * Renders the empty state shown when no matching posts exist.
	 *
	 * @since 7.1.0
	 */
	protected static function render_empty_state() {
		?>
		<div class="on-this-day-empty">
			<div class="on-this-day-empty-icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="4" width="18" height="18" rx="3"></rect>
					<path d="M16 2v4M8 2v4M3 10h18"></path>
					<circle cx="12" cy="15" r="1.5" fill="currentColor" stroke="none"></circle>
				</svg>
			</div>
			<h3 class="on-this-day-empty-title"><?php _e( 'Your story starts here.' ); ?></h3>
			<p class="on-this-day-empty-text">
				<?php
				printf(
					/* translators: %s: Current date, e.g. "April 22". */
					__( 'You haven&#8217;t published anything on %s in previous years. Write something today and check back next year!' ),
					'<strong>' . esc_html( date_i18n( 'F j' ) ) . '</strong>'
				);
				?>
			</p>
			<p class="on-this-day-empty-cta">
				<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button button-primary">
					<?php _e( 'Write a new post' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the grouped post list for the widget.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post[] $posts Posts to render, most recent first.
	 */
	protected static function render_posts( $posts ) {
		$current_year = (int) current_time( 'Y' );

		$by_year = array();
		foreach ( $posts as $post ) {
			$year               = (int) get_the_date( 'Y', $post );
			$by_year[ $year ][] = $post;
		}
		krsort( $by_year );
		?>
		<ul class="on-this-day-timeline">
			<?php
			$is_latest = true;
			foreach ( $by_year as $year => $year_posts ) :
				$years_ago     = $current_year - (int) $year;
				$group_classes = 'on-this-day-year-group';
				if ( $is_latest ) {
					$group_classes .= ' is-latest';
					$is_latest      = false;
				}
				?>
				<li class="<?php echo esc_attr( $group_classes ); ?>">
					<h3 class="on-this-day-year-header">
						<span class="on-this-day-year-number"><?php echo esc_html( $year ); ?></span>
						<span class="on-this-day-year-ago">
							<?php
							printf(
								/* translators: %s: Number of years, e.g. "1 year ago" or "5 years ago". */
								esc_html( _n( '%s year ago', '%s years ago', $years_ago ) ),
								esc_html( number_format_i18n( $years_ago ) )
							);
							?>
						</span>
					</h3>
					<ul class="on-this-day-post-list">
						<?php foreach ( $year_posts as $post ) : ?>
							<?php self::render_post( $post ); ?>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders a single post row.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post Post object to render.
	 */
	protected static function render_post( $post ) {
		$edit_link  = get_edit_post_link( $post->ID );
		$view_link  = get_permalink( $post->ID );
		$status     = get_post_status( $post );
		$is_private = ( 'private' === $status );

		$title = get_the_title( $post );
		if ( '' === trim( $title ) ) {
			$title = __( '(no title)' );
		}

		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$excerpt = wp_strip_all_tags( strip_shortcodes( $excerpt ) );
		$excerpt = preg_replace( '/\s+/', ' ', $excerpt );
		$excerpt = wp_trim_words( trim( $excerpt ), 24, '&hellip;' );

		$time_str   = get_the_time( get_option( 'time_format' ), $post );
		$time_iso   = get_the_time( 'Y-m-d H:i', $post );
		$categories = get_the_category( $post->ID );

		$row_classes = 'on-this-day-post';
		if ( $is_private ) {
			$row_classes .= ' is-private';
		}
		?>
		<li class="<?php echo esc_attr( $row_classes ); ?>">
			<?php if ( $is_private ) : ?>
				<span class="on-this-day-post-icon dashicons-before dashicons-lock" aria-hidden="true">
			<?php else : ?>
				<span class="on-this-day-post-icon dashicons-before dashicons-edit" aria-hidden="true">
			<?php endif; ?>
			</span>
			<div class="on-this-day-post-body">
				<span class="screen-reader-text">
					<?php echo $is_private ? esc_html__( 'Private post' ) : esc_html__( 'Published post' ); ?>
				</span>

				<h4 class="on-this-day-post-title">
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h4>

				<?php if ( $excerpt ) : ?>
					<p class="on-this-day-post-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<div class="on-this-day-post-meta">
					<time class="on-this-day-post-time" datetime="<?php echo esc_attr( $time_iso ); ?>">
						<?php echo esc_html( $time_str ); ?>
					</time>

					<?php if ( ! empty( $categories ) ) : ?>
						<span class="on-this-day-post-sep" aria-hidden="true">&middot;</span>
						<span class="on-this-day-post-categories">
							<?php
							$names = wp_list_pluck( array_slice( $categories, 0, 3 ), 'name' );
							echo esc_html( implode( ', ', $names ) );
							?>
						</span>
					<?php endif; ?>

					<?php if ( $is_private ) : ?>
						<span class="on-this-day-post-sep" aria-hidden="true">&middot;</span>
						<span class="on-this-day-post-private"><?php _e( 'Private' ); ?></span>
					<?php endif; ?>

					<?php if ( $edit_link || ( 'publish' === $status && $view_link ) ) : ?>
						<span class="on-this-day-post-actions">
							<?php if ( $edit_link ) : ?>
								<a class="on-this-day-post-action" href="<?php echo esc_url( $edit_link ); ?>"><?php _e( 'Edit' ); ?></a>
							<?php endif; ?>
							<?php if ( 'publish' === $status && $view_link ) : ?>
								<a class="on-this-day-post-action" href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php _e( 'View' ); ?></a>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		</li>
		<?php
	}
}
