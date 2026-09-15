/* global wp */

QUnit.module( 'wp.template' );

QUnit.test( 'uses the Underscore instance available at initialization', function( assert ) {
	var originalUnderscore = window._;

	document.getElementById( 'qunit-fixture' ).innerHTML =
		'<script type="text/html" id="tmpl-wp-util-test">Hello {{ data.name }}</script>';

	window._ = {
		template: function() {
			return 'not a function';
		}
	};

	try {
		assert.strictEqual(
			wp.template( 'wp-util-test' )( { name: 'World' } ),
			'Hello World'
		);
	} finally {
		window._ = originalUnderscore;
	}
} );
