/*global QUnit, wp, sinon */
jQuery( function( $ ) {

	QUnit.module( 'wp.updates' );

	QUnit.test( 'Initially, the update lock should be false', function( assert ) {
		assert.strictEqual( wp.updates.ajaxLocked, false );
	});

	QUnit.test( 'The nonce should be set correctly', function( assert ) {
		assert.equal( wp.updates.ajaxNonce, window._wpUpdatesSettings.ajax_nonce );
	});

	QUnit.test( 'decrementCount correctly decreases the update number', function( assert ) {
		var menuItemCount  = $( '#menu-plugins' ).find( '.plugin-count' ).eq( 0 ).text();
		var screenReaderItemCount = $( '#wp-admin-bar-updates' ).find( '.screen-reader-text' ).text();
		var adminItemCount = $( '#wp-admin-bar-updates' ).find( '.ab-label' ).text();
		assert.equal( menuItemCount, 2, 'Initial value is correct' );
		assert.equal( screenReaderItemCount, '2 Plugin Updates', 'Initial value is correct' );
		assert.equal( adminItemCount, 2, 'Initial value is correct' );

		wp.updates.decrementCount( 'plugin' );

		// Re-read these values.
		menuItemCount  = $( '#menu-plugins' ).find( '.plugin-count' ).eq( 0 ).text();
		screenReaderItemCount = $( '#wp-admin-bar-updates' ).find( '.screen-reader-text' ).text();
		adminItemCount = $( '#wp-admin-bar-updates' ).find( '.ab-label' ).text();
		assert.equal( menuItemCount, 1 );

		// @todo: Update screen reader count.
		// Should the screenReader count change? Is that announced to the user?
		// assert.equal( screenReaderItemCount, '1 Plugin Update' );
		assert.equal( adminItemCount, 1 );
	});

	QUnit.test( '`beforeunload` should only fire when locked', function( assert ) {
		wp.updates.ajaxLocked = false;
		assert.notOk( wp.updates.beforeunload(), '`beforeunload` should not fire.' );
		wp.updates.ajaxLocked = true;
		assert.equal( wp.updates.beforeunload(), window._wpUpdatesSettings.l10n.beforeunload, '`beforeunload` should equal the localized `beforeunload` string.' );
		wp.updates.ajaxLocked = false;
	});

	// FTP creds... exist?
	// Admin notice?

	QUnit.module( 'wp.updates.plugins', {
		beforeEach: function() {
			this.oldPagenow = window.pagenow;
			window.pagenow = 'plugins';
			sinon.spy( jQuery, 'ajax' );
		},
		afterEach: function() {
			window.pagenow = this.oldPagenow;
			wp.updates.ajaxLocked = false;
			wp.updates.queue = [];
			jQuery.ajax.restore();
		}
	} );

	QUnit.test( 'Update lock is set when plugins are updating', function( assert ) {
		wp.updates.updatePlugin( {
			plugin: 'test/test.php',
			slug: 'test'
		} );
		assert.strictEqual( wp.updates.ajaxLocked, true );
	});

	QUnit.test( 'Plugins are queued when the lock is set', function( assert ) {
		var value = [
			{
				action: 'update-plugin',
				data: {
					plugin: 'test/test.php',
					slug: 'test',
					success: null,
					error: null
				}
			}
		];

		wp.updates.ajaxLocked = true;
		wp.updates.updatePlugin( {
			plugin: 'test/test.php',
			slug: 'test',
			success: null,
			error: null
		} );

		assert.deepEqual( wp.updates.queue, value );
	});

	QUnit.test( 'If plugins are installing (lock is set), the beforeUnload function should fire', function( assert ) {
		wp.updates.updatePlugin( {
			plugin: 'test/test.php',
			slug: 'test'
		} );
		assert.equal( wp.updates.beforeunload(), window._wpUpdatesSettings.l10n.beforeunload );
	} );

	QUnit.test( 'Starting a plugin update should call the update API', function( assert ) {
		wp.updates.updatePlugin( {
			plugin: 'test/test.php',
			slug: 'test'
		} );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'update-plugin' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'test' );
	} );

	QUnit.test( 'Installing a plugin should call the API', function( assert ) {
		wp.updates.installPlugin( { slug: 'jetpack' } );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'install-plugin' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'jetpack' );
	} );

	QUnit.test( 'Deleting a plugin should call the API', function( assert ) {
		wp.updates.deletePlugin( { slug: 'jetpack', plugin: 'jetpack/jetpack.php' } );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'delete-plugin' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'jetpack' );
	} );

	QUnit.test( 'Deleting a plugin should update the counts of custom plugin groups', function( assert ) {
		var plugins = window._wpUpdatesSettings.plugins;

		$( '#qunit-fixture' ).append(
			'<script type="text/html" id="tmpl-item-deleted-row"><tr class="plugin-deleted-tr"><td colspan="{{ data.colspan }}"></td></tr></script>' +
			'<ul class="subsubsub">' +
				'<li class="all"><a href="#">All <span class="count">(3)</span></a> |</li>' +
				'<li class="inactive"><a href="#">Inactive <span class="count">(2)</span></a> |</li>' +
				'<li class="demo_group"><a href="#">Demo Group <span class="count">(2)</span></a> |</li>' +
				'<li class="single_group"><a href="#">Single Group <span class="count">(1)</span></a></li>' +
			'</ul>' +
			'<form id="bulk-action-form"><table><tbody id="the-list">' +
				'<tr data-plugin="custom-a/custom-a.php"><td></td></tr>' +
			'</tbody></table></form>'
		);

		window._wpUpdatesSettings.plugins = {
			all: [ 'custom-a/custom-a.php', 'custom-b/custom-b.php', 'other/other.php' ],
			inactive: [ 'custom-a/custom-a.php', 'other/other.php' ],
			demo_group: [ 'custom-a/custom-a.php', 'custom-b/custom-b.php' ],
			single_group: [ 'custom-a/custom-a.php' ]
		};

		// Run the fade out callback right away.
		sinon.stub( $.fn, 'fadeOut' ).callsFake( function( speed, callback ) {
			return this.each( callback );
		} );

		wp.updates.deletePluginSuccess( { slug: 'custom-a', plugin: 'custom-a/custom-a.php', pluginName: 'Custom A' } );

		$.fn.fadeOut.restore();

		assert.equal( $( '.subsubsub .all .count' ).text(), '(2)', 'The All count should go down.' );
		assert.equal( $( '.subsubsub .inactive .count' ).text(), '(1)', 'The Inactive count should go down only once.' );
		assert.equal( $( '.subsubsub .demo_group .count' ).text(), '(1)', 'The custom group count should go down.' );
		assert.strictEqual( $( '.subsubsub .single_group' ).length, 0, 'An empty custom group should be removed.' );
		assert.strictEqual( $( '.subsubsub .all' ).text(), 'All (2) |', 'Separators between views should be kept.' );
		assert.strictEqual( $( '.subsubsub .demo_group' ).text(), 'Demo Group (1)', 'The new last view should not end with a separator.' );
		assert.deepEqual( window._wpUpdatesSettings.plugins.demo_group, [ 'custom-b/custom-b.php' ], 'The deleted plugin should be removed from the custom group.' );

		window._wpUpdatesSettings.plugins = plugins;
	} );

	QUnit.test( 'Deleting a plugin should only remove the view of an emptied custom group', function( assert ) {
		var plugins = window._wpUpdatesSettings.plugins;

		$( '#qunit-fixture' ).append(
			'<script type="text/html" id="tmpl-item-deleted-row"><tr class="plugin-deleted-tr"><td colspan="{{ data.colspan }}"></td></tr></script>' +
			'<ul class="subsubsub">' +
				'<li class="all"><a href="#">All <span class="count">(2)</span></a> |</li>' +
				'<li class="count"><a href="#">Count <span class="count">(1)</span></a></li>' +
			'</ul>' +
			'<form id="bulk-action-form"><table><tbody id="the-list">' +
				'<tr data-plugin="custom-a/custom-a.php"><td></td></tr>' +
			'</tbody></table></form>'
		);

		// A group key that matches the class of the count elements.
		window._wpUpdatesSettings.plugins = {
			all: [ 'custom-a/custom-a.php', 'other/other.php' ],
			count: [ 'custom-a/custom-a.php' ]
		};

		sinon.stub( $.fn, 'fadeOut' ).callsFake( function( speed, callback ) {
			return this.each( callback );
		} );

		wp.updates.deletePluginSuccess( { slug: 'custom-a', plugin: 'custom-a/custom-a.php', pluginName: 'Custom A' } );

		$.fn.fadeOut.restore();

		assert.strictEqual( $( '.subsubsub > li.count' ).length, 0, 'The emptied group view should be removed.' );
		assert.strictEqual( $( '.subsubsub .all' ).text(), 'All (1)', 'The All view should keep its count.' );

		window._wpUpdatesSettings.plugins = plugins;
	} );

	// QUnit.test( 'A successful update changes the message?', function( assert ) {} );
	// QUnit.test( 'A failed update changes the message?', function( assert ) {} );

	QUnit.module( 'wp.updates.themes', {
		beforeEach: function() {
			this.oldPagenow = window.pagenow;
			window.pagenow = 'themes';
			sinon.spy( jQuery, 'ajax' );
		},
		afterEach: function() {
			window.pagenow = this.oldPagenow;
			wp.updates.ajaxLocked = false;
			wp.updates.queue = [];
			wp.updates.shouldRequestFilesystemCredentials = false;
			wp.updates.filesystemCredentials.available = false;
			jQuery.ajax.restore();
		}
	} );

	QUnit.test( 'Update lock is set when themes are updating', function( assert ) {
		wp.updates.updateTheme( 'twentyeleven' );
		assert.strictEqual( wp.updates.ajaxLocked, true );
	});

	QUnit.test( 'If themes are installing (lock is set), the beforeUnload function should fire', function( assert ) {
		wp.updates.updateTheme( { slug: 'twentyeleven' } );
		assert.equal( wp.updates.beforeunload(), window._wpUpdatesSettings.l10n.beforeunload );
	} );

	QUnit.test( 'Starting a theme update should call the update API', function( assert ) {
		wp.updates.updateTheme( { slug: 'twentyeleven' } );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'update-theme' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'twentyeleven' );
	} );

	QUnit.test( 'Installing a theme should call the API', function( assert ) {
		wp.updates.installTheme( { slug: 'twentyeleven' } );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'install-theme' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'twentyeleven' );
	} );

	QUnit.test( 'Deleting a theme should call the API', function( assert ) {
		wp.updates.deleteTheme( { slug: 'twentyeleven' } );
		assert.ok( jQuery.ajax.calledOnce );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].url, '/wp-admin/admin-ajax.php' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.action, 'delete-theme' );
		assert.equal( jQuery.ajax.getCall( 0 ).args[0].data.slug, 'twentyeleven' );
	} );

	QUnit.test( 'Canceling the credentials modal restores both the theme details overlay and the grid card, each with its own original content', function( assert ) {
		var $overlayNotice = $(
				'<div class="theme-overlay" data-slug="twentyeleven">' +
					'<div class="theme-info">' +
						'<div class="notice notice-warning notice-alt notice-large update-message">' +
							'<h3 class="notice-title">Update Available</h3>' +
							'<p><strong>There is a new version of Twenty Eleven available. <a id="update-theme" data-slug="twentyeleven" href="#">update now</a>.</strong></p>' +
						'</div>' +
					'</div>' +
				'</div>'
			).appendTo( '#qunit-fixture' ),
			$rowNotice = $(
				'<div class="theme" data-slug="twentyeleven">' +
					'<div class="update-message notice inline notice-warning notice-alt">' +
						'<p>New version available. <button class="button-link" type="button">Update now</button></p>' +
					'</div>' +
				'</div>'
			).appendTo( '#qunit-fixture' ),
			eventTarget = $overlayNotice.find( '#update-theme' );

		$( '<div id="request-filesystem-credentials-dialog"><form id="request-filesystem-credentials-form"></form></div>' )
			.appendTo( '#qunit-fixture' );

		wp.updates.shouldRequestFilesystemCredentials = true;
		wp.updates.filesystemCredentials.available = false;

		wp.updates.maybeRequestFilesystemCredentials( $.Event( 'click', {
			target: eventTarget[0]
		} ) );

		wp.updates.updateTheme( { slug: 'twentyeleven' } );

		assert.strictEqual( wp.updates.queue.length, 1, 'Theme update waits for credentials.' );
		assert.true( $overlayNotice.find( '.notice' ).hasClass( 'updating-message' ), 'Overlay notice is marked as updating.' );
		assert.true( $rowNotice.find( '.update-message' ).hasClass( 'updating-message' ), 'Theme row notice is marked as updating.' );

		wp.updates.requestForCredentialsModalCancel();

		assert.false( $overlayNotice.find( '.notice' ).hasClass( 'updating-message' ), 'Overlay notice resets after cancel.' );
		assert.false( $rowNotice.find( '.update-message' ).hasClass( 'updating-message' ), 'Theme row notice resets after cancel.' );
		assert.strictEqual( $overlayNotice.find( '#update-theme' ).length, 1, 'Overlay notice keeps its own "update now" link, so a retry click still works.' );
		assert.strictEqual( $rowNotice.find( '#update-theme' ).length, 0, 'Row notice does not receive the overlay\'s link markup.' );
		assert.strictEqual( $rowNotice.find( '.button-link' ).length, 1, 'Row notice keeps its own "Update now" button after cancel.' );
	} );

	// QUnit.test( 'A successful update changes the message?', function( assert ) {} );
	// QUnit.test( 'A failed update changes the message?', function( assert ) {} );
});
