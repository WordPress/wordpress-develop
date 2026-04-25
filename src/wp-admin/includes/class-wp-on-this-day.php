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
 * Renders the current user's posts that were published in the selected
 * date window in previous years, grouped by year.
 *
 * @since 7.1.0
 */
class WP_On_This_Day {
	/**
	 * Number of posts to fetch for the widget.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const POSTS_PER_PAGE = 50;

	/**
	 * Minimum number of days included in the widget's date window.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const MIN_WINDOW_DAYS = 1;

	/**
	 * Maximum number of days included in the widget's date window.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const MAX_WINDOW_DAYS = 7;

	/**
	 * Default number of days included in the widget's date window.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const DEFAULT_WINDOW_DAYS = 1;

	/**
	 * User meta key used to persist the date window preference.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const WINDOW_DAYS_META_KEY = 'dashboard_on_this_day_window_days';

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
	const CACHE_VERSION = 7;

	/**
	 * Registers the dashboard widget and its supporting hooks and assets.
	 *
	 * Designed to be the single entry point called from the dashboard
	 * setup routine. It processes any pending preference submission,
	 * registers the success notice, enqueues styles with the dynamic
	 * date label, and adds the widget itself.
	 *
	 * @since 7.1.0
	 */
	public static function register_widget() {
		self::handle_window_days_submission();

		add_action( 'admin_notices', array( __CLASS__, 'render_window_updated_notice' ) );

		wp_enqueue_style( 'on-this-day' );
		wp_add_inline_style(
			'on-this-day',
			sprintf(
				'#dashboard_on_this_day{--otd-today:%s;}',
				wp_json_encode( self::get_window_label( self::get_window_days() ) )
			)
		);

		wp_add_dashboard_widget(
			'dashboard_on_this_day',
			__( 'On This Day' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

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
		$user_id     = get_current_user_id();
		$window_days = self::get_window_days( $user_id );

		$cache_key = sprintf(
			'render:v%d:%d:%d:%s:%s:%s',
			self::CACHE_VERSION,
			$user_id,
			$window_days,
			determine_locale(),
			current_time( 'Y-m-d' ),
			wp_cache_get_last_changed( 'posts' )
		);

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( ! is_string( $cached ) ) {
			$posts = self::get_posts( $user_id, $window_days );

			ob_start();
			if ( empty( $posts ) ) {
				self::render_empty_state( $window_days );
			} else {
				self::render_posts( $posts, $window_days );
			}
			$cached = ob_get_clean();

			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, DAY_IN_SECONDS );
		}

		echo '<div class="on-this-day-widget">';
		echo '<div class="on-this-day-scroll">';
		// Already escaped at write time by the render_* methods below.
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		self::render_window_control( $window_days );
		echo '</div>';
	}

	/**
	 * Retrieves posts by a given author that were published in the
	 * selected date window in previous years.
	 *
	 * The "selected date window, prior year" constraint is expressed as a
	 * `date_query`: clauses pinning each `month`/`day`, combined with a
	 * `before` clause anchored to January 1 of the current year.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id     Author ID to query posts for.
	 * @param int $window_days Number of days to include, starting with today.
	 * @return WP_Post[] Array of posts ordered by newest first.
	 */
	public static function get_posts( $user_id, $window_days = self::DEFAULT_WINDOW_DAYS ) {
		$window_days = self::sanitize_window_days( $window_days );
		$year        = (int) current_time( 'Y' );
		$date_query  = array(
			'relation' => 'AND',
			array(
				'before' => array( 'year' => $year ),
			),
			array_merge(
				array( 'relation' => 'OR' ),
				self::get_window_date_query_clauses( $window_days )
			),
		);

		$args = array(
			'author'              => (int) $user_id,
			'post_type'           => 'post',
			'post_status'         => array( 'publish', 'private' ),
			'posts_per_page'      => self::POSTS_PER_PAGE,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'date_query'          => $date_query,
		);

		/**
		 * Filters the arguments used to query posts for the On This Day dashboard widget.
		 *
		 * @since 7.1.0
		 *
		 * @param array $args        WP_Query arguments.
		 * @param int   $user_id     The author ID the query is scoped to.
		 * @param int   $window_days Number of days included in the date window.
		 */
		$args = apply_filters( 'dashboard_on_this_day_query_args', $args, $user_id, $window_days );

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Handles date window preference form submissions.
	 *
	 * @since 7.1.0
	 */
	public static function handle_window_days_submission() {
		if (
			'POST' !== $_SERVER['REQUEST_METHOD'] ||
			! isset( $_POST['action'] ) ||
			'set_on_this_day_window' !== sanitize_text_field( wp_unslash( $_POST['action'] ) )
		) {
			return;
		}

		check_admin_referer( 'set-on-this-day-window' );

		$window_days = isset( $_POST['on_this_day_window_days'] ) ? wp_unslash( $_POST['on_this_day_window_days'] ) : self::DEFAULT_WINDOW_DAYS;
		$window_days = self::sanitize_window_days( $window_days );

		update_user_meta( get_current_user_id(), self::WINDOW_DAYS_META_KEY, $window_days );

		wp_safe_redirect(
			add_query_arg(
				'on-this-day-window-updated',
				'1',
				admin_url( 'index.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the success admin notice after the date window preference is saved.
	 *
	 * Hooked to the `admin_notices` action and only outputs when the
	 * `on-this-day-window-updated` query argument is present.
	 *
	 * @since 7.1.0
	 */
	public static function render_window_updated_notice() {
		if ( ! isset( $_GET['on-this-day-window-updated'] ) ) {
			return;
		}

		$window_days = self::get_window_days();

		wp_admin_notice(
			sprintf(
				/* translators: %s: Number of days. */
				__( 'On This Day duration updated to %s.' ),
				sprintf(
					/* translators: %s: Number of days. */
					_n( '%s day', '%s days', $window_days ),
					number_format_i18n( $window_days )
				)
			),
			array(
				'id'          => 'message',
				'type'        => 'success',
				'dismissible' => true,
			)
		);
	}

	/**
	 * Retrieves the current user's date window preference.
	 *
	 * @since 7.1.0
	 *
	 * @param int $user_id User ID.
	 * @return int Number of days to include, between 1 and 7.
	 */
	public static function get_window_days( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$window_days = get_user_meta( $user_id, self::WINDOW_DAYS_META_KEY, true );

		return self::sanitize_window_days( $window_days );
	}

	/**
	 * Returns a human-readable label for the active date window.
	 *
	 * @since 7.1.0
	 *
	 * @param int $window_days Number of days included in the date window.
	 * @return string Date or date range label.
	 */
	public static function get_window_label( $window_days ) {
		$window_days = self::sanitize_window_days( $window_days );
		$start       = current_datetime();
		$start_label = wp_date( 'F j', $start->getTimestamp(), $start->getTimezone() );

		if ( self::MIN_WINDOW_DAYS === $window_days ) {
			return $start_label;
		}

		$end       = $start->modify( '+' . ( $window_days - 1 ) . ' days' );
		$end_label = wp_date( 'F j', $end->getTimestamp(), $end->getTimezone() );

		return sprintf(
			/* translators: 1: Start date, 2: End date. */
			__( '%1$s - %2$s' ),
			$start_label,
			$end_label
		);
	}

	/**
	 * Sanitizes the date window size.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $window_days Raw window size.
	 * @return int Number of days to include, between 1 and 7.
	 */
	protected static function sanitize_window_days( $window_days ) {
		$window_days = absint( $window_days );

		if ( $window_days < self::MIN_WINDOW_DAYS || $window_days > self::MAX_WINDOW_DAYS ) {
			return self::DEFAULT_WINDOW_DAYS;
		}

		return $window_days;
	}

	/**
	 * Builds date query clauses for each day in the active window.
	 *
	 * @since 7.1.0
	 *
	 * @param int $window_days Number of days included in the date window.
	 * @return array[] Date query clauses.
	 */
	protected static function get_window_date_query_clauses( $window_days ) {
		$date    = current_datetime();
		$clauses = array();

		for ( $offset = 0; $offset < $window_days; $offset++ ) {
			$day_date  = $date->modify( '+' . $offset . ' days' );
			$clauses[] = array(
				'month' => (int) $day_date->format( 'n' ),
				'day'   => (int) $day_date->format( 'j' ),
			);
		}

		return $clauses;
	}

	/**
	 * Renders the empty state shown when no matching posts exist.
	 *
	 * @since 7.1.0
	 *
	 * @param int $window_days Number of days included in the date window.
	 */
	protected static function render_empty_state( $window_days ) {
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
					/* translators: %s: Current date or date range, e.g. "April 22" or "April 22 - April 28". */
					__( 'You haven&#8217;t published anything on %s in previous years. Write something today and check back next year!' ),
					'<strong>' . esc_html( self::get_window_label( $window_days ) ) . '</strong>'
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
	 * @param WP_Post[] $posts       Posts to render, most recent first.
	 * @param int       $window_days Number of days included in the date window.
	 */
	protected static function render_posts( $posts, $window_days ) {
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
					<p class="on-this-day-year-header">
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
					</p>
					<ul class="on-this-day-post-list">
						<?php foreach ( $year_posts as $post ) : ?>
							<?php self::render_post( $post, $window_days ); ?>
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
	 * @param WP_Post $post        Post object to render.
	 * @param int     $window_days Number of days included in the date window.
	 */
	protected static function render_post( $post, $window_days ) {
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

		$date_str   = get_the_date( 'F j', $post );
		$time_str   = get_the_time( get_option( 'time_format' ), $post );
		$time_iso   = get_the_time( 'c', $post );
		$categories = get_the_category( $post->ID );

		$row_classes = 'on-this-day-post';
		if ( $is_private ) {
			$row_classes .= ' is-private';
		}
		?>
		<li class="<?php echo esc_attr( $row_classes ); ?>">
			<?php if ( $is_private ) : ?>
				<span class="on-this-day-post-icon dashicons-before dashicons-lock" aria-hidden="true"></span>
			<?php elseif ( has_post_thumbnail( $post ) ) : ?>
				<span class="on-this-day-post-icon has-thumbnail" aria-hidden="true">
					<?php
					echo get_the_post_thumbnail(
						$post,
						array( 56, 56 ),
						array(
							'alt'     => '',
							'loading' => 'lazy',
						)
					);
					?>
				</span>
			<?php else : ?>
				<span class="on-this-day-post-icon dashicons-before dashicons-edit" aria-hidden="true"></span>
			<?php endif; ?>
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
						<?php
						if ( self::MIN_WINDOW_DAYS === $window_days ) {
							echo esc_html( $time_str );
						} else {
							echo esc_html(
								sprintf(
									/* translators: 1: Post date, 2: Post time. */
									__( '%1$s at %2$s' ),
									$date_str,
									$time_str
								)
							);
						}
						?>
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
				</div>

				<?php if ( $edit_link || ( 'publish' === $status && $view_link ) ) : ?>
					<div class="on-this-day-post-actions">
						<?php if ( $edit_link ) : ?>
							<a class="on-this-day-post-action button button-secondary button-compact" href="<?php echo esc_url( $edit_link ); ?>"><?php _e( 'Edit' ); ?></a>
						<?php endif; ?>
						<?php if ( 'publish' === $status && $view_link ) : ?>
							<a class="on-this-day-post-action button-link is-compact" href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php _e( 'View' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * Renders the date window slider.
	 *
	 * @since 7.1.0
	 *
	 * @param int $window_days Number of days included in the date window.
	 */
	protected static function render_window_control( $window_days ) {
		?>
		<form class="on-this-day-window-form" method="post" action="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">
			<?php wp_nonce_field( 'set-on-this-day-window', '_wpnonce', false ); ?>
			<input type="hidden" name="action" value="set_on_this_day_window" />
			<div class="on-this-day-window-control">
				<span class="on-this-day-window-scale" aria-hidden="true"><?php _e( '1 day' ); ?></span>
				<input
					type="range"
					id="on-this-day-window-days"
					name="on_this_day_window_days"
					min="<?php echo esc_attr( self::MIN_WINDOW_DAYS ); ?>"
					max="<?php echo esc_attr( self::MAX_WINDOW_DAYS ); ?>"
					step="1"
					value="<?php echo esc_attr( $window_days ); ?>"
					title="<?php esc_attr_e( 'Duration' ); ?>"
					aria-label="<?php esc_attr_e( 'Duration' ); ?>"
					onchange="this.form.submit();"
				/>
				<span class="on-this-day-window-scale" aria-hidden="true"><?php _e( '7 days' ); ?></span>
			</div>
		</form>
		<?php
	}
}
