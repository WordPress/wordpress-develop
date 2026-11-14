<?php
/**
 * Plugin Name: Fixture: Exit on activation
 * Description: Exits from its activation hook, before core records it as active. Expected to be skipped rather than pass.
 * Version: 1.0.0
 */

register_activation_hook(
	__FILE__,
	static function () {
		exit;
	}
);
