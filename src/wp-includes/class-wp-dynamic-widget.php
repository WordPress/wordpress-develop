<?php

class WP_Dynamic_Widget extends WP_Widget {
	public $display_callback = null;

	public $update_callback = null;

	public $form_callback = null;

	public function __construct( $id_base ) {
		parent::__construct( $id_base, '' );
	}

	public function _get_display_callback() {
		return $this->display_callback ?? parent::_get_display_callback();
	}

	public function _get_update_callback() {
		return $this->update_callback ?? parent::_get_update_callback();
	}

	public function _get_form_callback() {
		return $this->form_callback ?? parent::_get_form_callback();
	}
}
