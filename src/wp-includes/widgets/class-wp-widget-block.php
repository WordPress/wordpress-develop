<?php
/**
 * Widget API: WP_Widget_Block class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 5.6.0
 */

/**
 * Core class used to implement a Block widget.
 *
 * @since 5.6.0
 *
 * @see WP_Widget
 */
class WP_Widget_Block extends WP_Widget {

	/**
	 * Default instance.
	 *
	 * @since 5.6.0
	 * @var array
	 */
	protected $default_instance = array(
		'content' => '',
	);

	/**
	 * Sets up a new Block widget instance.
	 *
	 * @since 5.6.0
	 */
	public function __construct() {
		$widget_ops  = array(
			'classname'                   => 'widget_block',
			'description'                 => __( 'A block.' ),
			'customize_selective_refresh' => true,
		);
		$control_ops = array(
			'width'  => 400,
			'height' => 350,
		);
		parent::__construct( 'block', __( 'Block' ), $widget_ops, $control_ops );
		add_action( 'is_wide_widget_in_customizer', array( $this, 'set_is_wide_widget_in_customizer' ), 10, 2 );
	}

	/**
	 * Outputs the content for the current Block widget instance.
	 *
	 * @since 5.6.0
	 *
	 * @param array $args Display arguments including 'before_title', 'after_title', 'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Block widget instance.
	 *
	 * @global WP_Post $post Global post object.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		echo do_blocks( $instance['content'] );
		echo $args['after_widget'];
	}

	/**
	 * Handles updating settings for the current Block widget instance.

	 * @since 5.6.0
	 *
	 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 *
	 * @return array Settings to save or bool false to cancel saving.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance            = array_merge( $this->default_instance, $old_instance );
		$instance['content'] = $new_instance['content'];

		return $instance;
	}

	/**
	 * Outputs the Block widget settings form.
	 *
	 * @since 5.6.0
	 *
	 * @param array $instance Current instance.
	 * @return string Default return is 'noform'.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->default_instance );
		echo do_blocks( $instance['content'] );
		$textarea_id = $this->get_field_id( 'content' );
		?>
		<br/>
		<textarea id="<?php echo $textarea_id; ?>" name="<?php echo $this->get_field_name( 'content' ); ?>"
				class="content sync-input" hidden><?php echo esc_textarea( $instance['content'] ); ?></textarea>
		<?php
		return 'noform';
	}

	/**
	 * Make sure no block widget is considered to be wide.
	 *
	 * @since 5.6.0
	 *
	 * @param boolean $is_wide Is regarded wide.
	 * @param string  $widget_id Widget ID.
	 *
	 * @return bool Updated is_wide value.
	 */
	public function set_is_wide_widget_in_customizer( $is_wide, $widget_id ) {
		if ( strpos( $widget_id, 'block-' ) === 0 ) {
			return false;
		}

		return $is_wide;
	}
}
