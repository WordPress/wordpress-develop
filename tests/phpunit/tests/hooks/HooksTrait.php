<?php

namespace phpunit\tests\hooks;

use MockAction;
use WP_Hook;

trait HooksTrait {

	public function data_not_valid_callback() {
		$obj = new MockAction();

		return array(
			'function callback'                 => array(
				'callback'           => 'not_callable_callback',
				'callback_as_string' => 'not_callable_callback',
			),
			'static method callback as string'  => array(
				'callback'           => 'Foo::not_callable_callback',
				'callback_as_string' => 'Foo::not_callable_callback',
			),
			'static method callback as array'   => array(
				'callback'           => array( 'Foo', 'not_callable_callback' ),
				'callback_as_string' => 'Foo::not_callable_callback',
			),
			'object method callback'            => array(
				'callback'           => array( $obj, 'not_callable_callback' ),
				'callback_as_string' => 'MockAction::not_callable_callback',
			),
			'a boolean type is not a callback'  => array(
				'callback'           => false,
				'callback_as_string' => 'boolean',
			),
			'an integer type is not a callback' => array(
				'callback'           => 100,
				'callback_as_string' => 'integer',
			),
			'an empty array' => array(
				'callback'           => array(),
				'callback_as_string' => 'integer',
			),
		);
	}

	protected function setup_hook( $tag, $callback, $priority = 10, $num_args = 1 ) {
		$hook = new WP_Hook();
		$hook->add_filter( $tag, $callback, $priority, $num_args );

		return $hook;
	}
}
