<?php
// An example test namespaced widget
namespace TestSpace\Sub\Sub {
	class Testwidget extends \WP_Widget {
		function __construct() {
			// An empty first argument is passed to use the default id_base calculation
			parent::__construct( '', 'unit-test-widget' );
		}
	}
}

namespace {
	// A non-namespaced widget to ensure previous behavior still works
	class Testwidget2 extends \WP_Widget {
		function __construct() {
			parent::__construct( '', 'unit-test-widget' );
		}
	}

	/**
	 * Test functions and classes for namespaced widgets.
	 *
	 * @group widgets
	 */
	class Tests_Widgets_Namespaced extends WP_UnitTestCase {

		function clean_up_global_scope() {
			global $wp_widget_factory;
			$wp_widget_factory->widgets = array();
			parent::clean_up_global_scope();
		}

		/**
		 * @ticket 44098
		 */
		public function test_widget_with_namespace_without_id_base() {
			global $wp_widget_factory;
			register_widget( 'TestSpace\Sub\Sub\Testwidget' );
			$this->assertEquals( 'testwidget', $wp_widget_factory->widgets['TestSpace\Sub\Sub\Testwidget']->id_base );
		}

		/**
		 * @ticket 44098
		 */
		public function test_widget_without_namespace_without_id_base() {
			global $wp_widget_factory;
			register_widget( 'Testwidget2' );
			$this->assertEquals( 'testwidget2', $wp_widget_factory->widgets['Testwidget2']->id_base );
		}
	}
}
