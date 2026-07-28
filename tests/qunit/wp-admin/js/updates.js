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

	QUnit.test( 'Starting a theme update should not change unrelated theme details modal notices', function( assert ) {
		var $fixture = $( '#qunit-fixture' );

		$fixture.append(
			'<div class="theme-info">' +
				'<div class="notice notice-warning" data-slug="twentytwelve"><p>Different theme update</p></div>' +
			'</div>' +
			'<div class="theme" data-slug="twentyeleven">' +
				'<div class="update-message notice"><p>Update now</p></div>' +
			'</div>'
		);

		wp.updates.updateTheme( { slug: 'twentyeleven' } );

		assert.equal( $fixture.find( '.theme-info .notice[data-slug="twentytwelve"] p' ).text(), 'Different theme update' );
		assert.equal( $fixture.find( '.theme[data-slug="twentyeleven"] .update-message p' ).text(), 'Updating...' );
	} );

	QUnit.test( 'A successful theme update should not add notices to an unrelated theme details modal', function( assert ) {
		var $fixture = $( '#qunit-fixture' ),
			originalAdminNotice = wp.updates.adminNotice,
			addAdminNotice = sinon.stub( wp.updates, 'addAdminNotice' ),
			decrementCount = sinon.stub( wp.updates, 'decrementCount' );

		wp.updates.adminNotice = sinon.stub().returns( '<div class="updated-message notice-success notice-alt"><p>Updated!</p></div>' );
		$( 'body' ).addClass( 'modal-open' );

		$fixture.append(
			'<div class="theme-info">' +
				'<p class="theme-author">By the WordPress team</p>' +
				'<div class="notice notice-warning" data-slug="twentytwelve"><p>Different theme update</p></div>' +
			'</div>' +
			'<div class="theme" data-slug="twentyeleven">' +
				'<div class="update-message notice"><p>Update now</p></div>' +
				'<a class="load-customize" href="#">Live Preview</a>' +
			'</div>'
		);

		try {
			wp.updates.updateThemeSuccess( {
				slug: 'twentyeleven',
				oldVersion: '1.0',
				newVersion: '1.1'
			} );

			assert.strictEqual( addAdminNotice.firstCall.args[0].selector.length, 1 );
			assert.strictEqual( addAdminNotice.firstCall.args[0].selector[0], $fixture.find( '.theme[data-slug="twentyeleven"] .update-message' )[0] );
			assert.equal( $fixture.find( '.theme-info .updated-message' ).length, 0 );
		} finally {
			addAdminNotice.restore();
			decrementCount.restore();
			wp.updates.adminNotice = originalAdminNotice;
			$( 'body' ).removeClass( 'modal-open' );
		}
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

	// QUnit.test( 'A successful update changes the message?', function( assert ) {} );
	// QUnit.test( 'A failed update changes the message?', function( assert ) {} );
});
