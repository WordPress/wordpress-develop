<?php
/**
 * Widget API: WP_Widget_Recent_Posts class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.4.0
 */

/**
 * Core class used to implement a Recent Posts widget.
 *
 * @since 2.8.0
 *
 * @see WP_Widget
 */
class WP_Widget_Recent_Posts extends WP_Widget {

	/**
	 * Sets up a new Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'widget_recent_entries',
			'description'                 => __( 'Your site&#8217;s most recent Posts.' ),
			'customize_selective_refresh' => true,
			'show_instance_in_rest'       => true,
		);
		parent::__construct( 'recent-posts', __( 'Recent Posts' ), $widget_ops );
		$this->alt_option_name = 'widget_recent_entries';
	}

	/**
	 * Outputs the content for the current Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Recent Posts widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$default_title = __( 'Recent Posts' );
		$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 5;
		if ( ! $number ) {
			$number = 5;
		}
		$show_date = $instance['show_date'] ?? false;
		$post_type = $this->sanitize_post_types( $instance['post_type'] ?? array( 'post' ) );

		$r = new WP_Query(
			/**
			 * Filters the arguments for the Recent Posts widget.
			 *
			 * @since 3.4.0
			 * @since 4.9.0 Added the `$instance` parameter.
			 *
			 * @see WP_Query::get_posts()
			 *
			 * @param array $args     An array of arguments used to retrieve the recent posts.
			 * @param array $instance Array of settings for the current widget.
			 */
			apply_filters(
				'widget_posts_args',
				array(
					'posts_per_page'      => $number,
					'no_found_rows'       => true,
					'post_status'         => 'publish',
					'post_type'           => $post_type,
					'ignore_sticky_posts' => true,
				),
				$instance
			)
		);

		if ( ! $r->have_posts() ) {
			return;
		}
		?>

		<?php echo $args['before_widget']; ?>

		<?php
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$format = current_theme_supports( 'html5', 'navigation-widgets' ) ? 'html5' : 'xhtml';

		/** This filter is documented in wp-includes/widgets/class-wp-nav-menu-widget.php */
		$format = apply_filters( 'navigation_widgets_format', $format );

		if ( 'html5' === $format ) {
			// The title may be filtered: Strip out HTML and make sure the aria-label is never empty.
			$title      = trim( strip_tags( $title ) );
			$aria_label = $title ? $title : $default_title;
			echo '<nav aria-label="' . esc_attr( $aria_label ) . '">';
		}
		?>

		<ul>
			<?php foreach ( $r->posts as $recent_post ) : ?>
				<?php
				$post_title   = get_the_title( $recent_post->ID );
				$title        = ( ! empty( $post_title ) ) ? $post_title : __( '(no title)' );
				$aria_current = '';

				if ( get_queried_object_id() === $recent_post->ID ) {
					$aria_current = ' aria-current="page"';
				}
				?>
				<li>
					<a href="<?php the_permalink( $recent_post->ID ); ?>"<?php echo $aria_current; ?>><?php echo $title; ?></a>
					<?php if ( $show_date ) : ?>
						<span class="post-date"><?php echo get_the_date( '', $recent_post->ID ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		if ( 'html5' === $format ) {
			echo '</nav>';
		}

		echo $args['after_widget'];
	}

	/**
	 * Handles updating the settings for the current Recent Posts widget instance.
	 *
	 * @since 2.8.0
	 * @since 7.2.0 Added the `post_type` setting.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = $old_instance;
		$instance['title']     = sanitize_text_field( $new_instance['title'] );
		$instance['number']    = (int) $new_instance['number'];
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? (bool) $new_instance['show_date'] : false;

		$post_type             = isset( $new_instance['post_type'] ) ? array_map( 'sanitize_key', (array) $new_instance['post_type'] ) : array();
		$instance['post_type'] = $this->sanitize_post_types( $post_type );

		return $instance;
	}

	/**
	 * Outputs the settings form for the Recent Posts widget.
	 *
	 * @since 2.8.0
	 * @since 7.2.0 Added the post types selector.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$post_type = $this->sanitize_post_types( $instance['post_type'] ?? array( 'post' ) );

		$custom_post_types = $this->get_custom_post_types();

		/**
		 * Filters whether to display the post types selector in the Recent Posts widget form.
		 *
		 * By default, the selector is only displayed when the site has registered public,
		 * non-built-in post types for the widget to choose from.
		 *
		 * @since 7.2.0
		 *
		 * @param bool              $show_selector     Whether to display the post types selector.
		 * @param string[]          $custom_post_types Public, non-built-in post type names registered for the site.
		 * @param WP_Widget_Recent_Posts $widget       The current widget instance.
		 */
		$show_post_type_selector = apply_filters( 'widget_posts_cpt_selector', ! empty( $custom_post_types ), $custom_post_types, $this );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" />
		</p>

		<?php if ( $show_post_type_selector ) : ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'post_type' ); ?>"><?php _e( 'Post types to show:' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'post_type' ); ?>" name="<?php echo $this->get_field_name( 'post_type' ); ?>[]" multiple="multiple">
				<option value="any"<?php echo in_array( 'any', $post_type, true ) ? ' selected="selected"' : ''; ?>><?php _e( 'All public post types' ); ?></option>
				<option value="post"<?php echo in_array( 'post', $post_type, true ) ? ' selected="selected"' : ''; ?>><?php _e( 'Posts' ); ?></option>
				<?php foreach ( $custom_post_types as $custom_post_type ) : ?>
					<?php $post_type_object = get_post_type_object( $custom_post_type ); ?>
					<option value="<?php echo esc_attr( $custom_post_type ); ?>"<?php echo in_array( $custom_post_type, $post_type, true ) ? ' selected="selected"' : ''; ?>><?php echo esc_html( $post_type_object->labels->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php endif; ?>

		<p>
			<input class="checkbox" type="checkbox"<?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display post date?' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Retrieves the public, non-built-in post types available for selection in the widget.
	 *
	 * @since 7.2.0
	 *
	 * @return string[] Post type names.
	 */
	protected function get_custom_post_types() {
		/**
		 * Filters the arguments used to retrieve the custom post types that can be
		 * selected in the Recent Posts widget.
		 *
		 * @since 7.2.0
		 *
		 * @see get_post_types()
		 *
		 * @param array $args Arguments used to retrieve registered post types.
		 */
		$args = apply_filters(
			'widget_posts_cpt_args',
			array(
				'public'   => true,
				'_builtin' => false,
			)
		);

		return array_values( get_post_types( $args ) );
	}

	/**
	 * Sanitizes a list of post types for use in the widget's query, keeping only
	 * post types that are currently valid choices for the widget.
	 *
	 * @since 7.2.0
	 *
	 * @param string|string[] $post_type One or more post type names, or 'any'.
	 * @return string|string[] 'any', or a non-empty list of valid post type names.
	 */
	protected function sanitize_post_types( $post_type ) {
		$post_type        = (array) $post_type;
		$valid_post_types = array_merge( array( 'any', 'post' ), $this->get_custom_post_types() );
		$post_type        = array_values( array_intersect( $post_type, $valid_post_types ) );

		if ( empty( $post_type ) ) {
			return array( 'post' );
		}

		if ( in_array( 'any', $post_type, true ) ) {
			return 'any';
		}

		return $post_type;
	}
}
