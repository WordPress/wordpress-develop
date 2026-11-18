/* global wp, jQuery */

QUnit.module( 'wp.ajax.send', {
	beforeEach: function() {
		this.originalAjax = jQuery.ajax;
	},
	afterEach: function() {
		jQuery.ajax = this.originalAjax;
	}
} );

QUnit.test( 'query-attachments request that completes synchronously should set totalAttachments', function( assert ) {
	const jqXHR = { getResponseHeader: () => '42' };
	const context = {};

	jQuery.ajax = function( options ) {
		const deferred = jQuery.Deferred();
		deferred.done( options.success );
		deferred.resolveWith( options.context, [ { success: true, data: [] }, 'success', jqXHR ] );
		return deferred.promise();
	};

	wp.ajax.send( { data: { action: 'query-attachments' }, context: context } );

	assert.strictEqual( context.totalAttachments, 42, 'totalAttachments is read from the X-WP-Total header' );
} );
