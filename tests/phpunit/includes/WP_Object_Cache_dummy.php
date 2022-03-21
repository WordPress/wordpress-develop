<?php

class WP_Object_Cache_dummy {
	public function dummy_function() {
		return true;
	}

	public function flush() {
		return;
	}

	public function add_global_groups() {
		return;
	}

	public function set() {
		return;
	}
}
