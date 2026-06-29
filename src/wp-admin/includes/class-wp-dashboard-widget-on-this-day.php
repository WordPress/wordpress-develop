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
 * Renders published posts that were published on this calendar day in
 * previous years.
 *
 * @since 7.1.0
 */
class WP_Dashboard_Widget_On_This_Day {
	/**
	 * Number of posts to fetch for the widget. -1 fetches all matches for the day.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const POSTS_PER_PAGE = -1;

	/**
	 * Object cache group used for storing widget results.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const CACHE_GROUP = 'on_this_day';

	/**
	 * Dashboard widget ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const WIDGET_ID = 'wp_dashboard_on_this_day';

	/**
	 * Cache version. Bump when the rendered markup changes so stale
	 * entries from older releases are naturally ignored.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const CACHE_VERSION = 6;

	/**
	 * Registers the dashboard widget and its supporting assets.
	 *
	 * Designed to be the single entry point called from the dashboard
	 * setup routine. It enqueues assets and registers the widget.
	 *
	 * @since 7.1.0
	 */
	public static function register_widget() {
		if ( ! self::has_posts() ) {
			return;
		}

		wp_enqueue_style( 'on-this-day' );

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			sprintf(
				'<span class="wp-on-this-day-title" data-wp-otd-window-label="%s">%s</span>',
				esc_attr( self::get_window_label() ),
				esc_html__( 'On This Day' )
			),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget output.
	 *
	 * The rendered HTML is cached per user and locale. The cache salt
	 * incorporates the site date and the posts group's
	 * `last_changed` token, so any post mutation (publish, edit, delete,
	 * trash) automatically invalidates the entry on the next read, and
	 * entries roll over naturally at midnight.
	 *
	 * Note: I made the trade-off to ignore `date_format` and `time_format`
	 * option changes. They do not bust the cache; stale date strings clear
	 * on the next post mutation or at midnight.
	 *
	 * @since 7.1.0
	 */
	public static function render_dashboard_widget() {
		$user_id = get_current_user_id();

		$cache_key  = sprintf(
			'render_otd_widget:v%d:%d:%s',
			self::CACHE_VERSION,
			$user_id,
			determine_locale()
		);
		$cache_salt = array(
			current_time( 'Y-m-d' ),
			wp_cache_get_last_changed( 'posts' ),
		);

		$cached = wp_cache_get_salted( $cache_key, self::CACHE_GROUP, $cache_salt );
		if ( ! is_string( $cached ) ) {
			$posts = self::get_cached_posts( $user_id );

			if ( empty( $posts ) ) {
				return;
			}

			ob_start();
			self::render_posts( $posts );
			$cached = ob_get_clean();

			wp_cache_set_salted( $cache_key, $cached, self::CACHE_GROUP, $cache_salt, DAY_IN_SECONDS );
		}

		echo '<div class="wp-on-this-day-widget">';
		// Already escaped at write time by the render_* methods below.
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Retrieves published posts from all authors that were published on this
	 * calendar day in previous years.
	 *
	 * The date constraint matches today's month and day, combined with a
	 * `before` clause anchored to January 1 of the current year.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id Current user ID for filter context.
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	public static function get_posts( $user_id ) {
		$today      = current_datetime();
		$year       = (int) $today->format( 'Y' );
		$date_query = array(
			'relation' => 'AND',
			array(
				'before' => array( 'year' => $year ),
			),
			self::get_date_query_clause( $today ),
		);

		$args = array(
			'post_type'              => 'post',
			'post_status'            => array( 'publish' ),
			'posts_per_page'         => self::POSTS_PER_PAGE,
			'ignore_sticky_posts'    => true,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'date_query'             => $date_query,
		);

		/**
		 * Filters the arguments used to query posts for the On This Day dashboard widget.
		 *
		 * @since 7.1.0
		 *
		 * @param array $args    WP_Query arguments.
		 * @param int   $user_id Current user ID.
		 */
		$args = apply_filters( 'wp_dashboard_on_this_day_query_args', $args, $user_id );

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Builds the date query clause for today's anniversary date.
	 *
	 * On February 28 in a non-leap year, February 29 posts are included so
	 * leap-day anniversaries still appear.
	 *
	 * @since 7.1.0
	 *
	 * @param DateTimeInterface $date Date to build the clause for.
	 * @return array Date query clause.
	 */
	protected static function get_date_query_clause( $date ) {
		$month  = (int) $date->format( 'm' );
		$day    = (int) $date->format( 'd' );
		$clause = array(
			'month' => $month,
			'day'   => $day,
		);

		if ( 2 !== $month || 28 !== $day || $date->format( 'L' ) ) {
			return $clause;
		}

		return array(
			'relation' => 'OR',
			$clause,
			array(
				'month' => 2,
				'day'   => 29,
			),
		);
	}

	/**
	 * Determines whether posts are available for the widget.
	 *
	 * @since 7.1.0
	 *
	 * @return bool True when matching posts exist, false otherwise.
	 */
	public static function has_posts() {
		return ! empty( self::get_cached_posts( get_current_user_id() ) );
	}

	/**
	 * Retrieves cached posts for the widget.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id Current user ID for cache and filter context.
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	protected static function get_cached_posts( $user_id ) {
		$cache_salt = array(
			current_time( 'Y-m-d' ),
			wp_cache_get_last_changed( 'posts' ),
		);
		$cache_key  = sprintf(
			'query_posts:v%d:%d',
			self::CACHE_VERSION,
			(int) $user_id
		);

		$cached = wp_cache_get_salted( $cache_key, self::CACHE_GROUP, $cache_salt );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = self::get_posts( $user_id );

		wp_cache_set_salted( $cache_key, $posts, self::CACHE_GROUP, $cache_salt, DAY_IN_SECONDS );

		return $posts;
	}

	/**
	 * Renders the post list grouped by publication year.
	 *
	 * Outputs rendered HTML that has already been escaped at write time.
	 * Callers must echo the captured buffer as-is to avoid double-escaping.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post[] $posts Posts to render, most recent first.
	 */
	protected static function render_posts( $posts ) {
		global $post;

		$posts_by_year = array();
		$post_count    = count( $posts );

		foreach ( $posts as $current_post ) {
			$year = get_the_date( 'Y', $current_post );

			if ( ! isset( $posts_by_year[ $year ] ) ) {
				$posts_by_year[ $year ] = array();
			}

			$posts_by_year[ $year ][] = $current_post;
		}
		?>
		<p class="wp-on-this-day-summary">
			<?php
			printf(
				esc_html(
					/* translators: %s: Number of posts. */
					_n(
						'%s post has been published in a previous year:',
						'%s posts have been published in previous years:',
						$post_count
					)
				),
				esc_html( number_format_i18n( $post_count ) )
			);
			?>
		</p>
		<ul class="wp-on-this-day-years">
			<?php foreach ( $posts_by_year as $year => $year_posts ) : ?>
				<li class="wp-on-this-day-year">
					<h3 class="wp-on-this-day-year-heading"><?php echo esc_html( $year ); ?></h3>
					<ul class="wp-on-this-day-posts">
						<?php foreach ( $year_posts as $post ) : ?>
							<?php
							setup_postdata( $post );
							self::render_post();
							?>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Returns the current month and day for display in the widget title.
	 *
	 * @since 7.1.0
	 *
	 * @return string Current month and day.
	 */
	public static function get_window_label() {
		/* translators: Date format for a specific On This Day date, without year. See https://www.php.net/manual/datetime.format.php */
		return wp_date( _x( 'F jS', 'on this day date format' ) );
	}

	/**
	 * Renders a single linked post title.
	 *
	 * @since 7.1.0
	 */
	protected static function render_post() {
		$title = get_the_title();

		if ( '' === trim( $title ) ) {
			$title = __( '(no title)' );
		}

		$author_id = (int) get_post_field( 'post_author', get_the_ID() );
		?>
		<li class="wp-on-this-day-post">
			<a href="<?php the_permalink(); ?>"><?php echo esc_html( $title ); ?></a>
			<?php if ( get_current_user_id() !== $author_id ) : ?>
				<span class="wp-on-this-day-post-author">
					<?php
					printf(
						/* translators: %s: Post author's display name. */
						esc_html__( 'by %s' ),
						esc_html( get_the_author() )
					);
					?>
				</span>
			<?php endif; ?>
		</li>
		<?php
	}
}
