/* global QUnit, wp, sinon */

QUnit.module( 'wp.updates.ajaxAlways', {
	beforeEach: function() {
		sinon.stub( window.console, 'log' );
		sinon.stub( wp.updates, 'queueChecker' );
	},
	afterEach: function() {
		window.console.log.restore();
		wp.updates.queueChecker.restore();
		wp.updates.ajaxLocked = false;
	}
} );

QUnit.test( 'Debug messages are logged with character references decoded', function( assert ) {
	wp.updates.ajaxAlways( {
		debug: [ 'Downloading installation package from <span class="code pre">https://example.org/plugin.zip</span>&#8230;' ]
	} );

	assert.ok( window.console.log.calledOnce, 'One message should be logged.' );
	assert.strictEqual(
		window.console.log.getCall( 0 ).args[0],
		'Downloading installation package from https://example.org/plugin.zip…',
		'HTML tags should be stripped and the ellipsis decoded.'
	);
} );

QUnit.test( 'Debug messages decode named character references', function( assert ) {
	wp.updates.ajaxAlways( {
		debug: [ '<strong>Tom &amp; Jerry</strong> &lt;3' ]
	} );

	assert.strictEqual( window.console.log.getCall( 0 ).args[0], 'Tom & Jerry <3' );
} );

QUnit.test( 'Debug messages do not execute markup', function( assert ) {
	window.updatesAjaxAlwaysXss = false;

	wp.updates.ajaxAlways( {
		debug: [ '<img src="x" onerror="window.updatesAjaxAlwaysXss = true">Done' ]
	} );

	assert.strictEqual( window.console.log.getCall( 0 ).args[0], 'Done' );
	assert.strictEqual( window.updatesAjaxAlwaysXss, false, 'Event handlers in debug messages should not run.' );

	delete window.updatesAjaxAlwaysXss;
} );

QUnit.test( 'Non-string debug messages are skipped', function( assert ) {
	wp.updates.ajaxAlways( {
		debug: [ null, 123, { foo: 'bar' }, 'Done.' ]
	} );

	assert.ok( window.console.log.calledOnce, 'Only the string message should be logged.' );
	assert.strictEqual( window.console.log.getCall( 0 ).args[0], 'Done.' );
} );

QUnit.test( 'Nothing is logged without debug messages', function( assert ) {
	wp.updates.ajaxAlways( {} );

	assert.notOk( window.console.log.called );
} );
