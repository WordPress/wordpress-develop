<?php

class WP_Dynamic_Widget extends WP_Widget {
	public $widget_callback = null;

	public $update_callback = null;

	public $form_callback = null;

	public function __construct( $id_base ) {
		parent::__construct( $id_base, '' );
	}

	public function widget( $args, $instance ) {
		call_user_func( $this->widget_callback, $args, $instance );
	}

	public function update( $new_instance, $old_instance ) {
		return call_user_func( $this->update_callback, $new_instance, $old_instance );
	}

	public function form( $instance ) {
		return call_user_func( $this->form_callback, $instance );
	}
}
