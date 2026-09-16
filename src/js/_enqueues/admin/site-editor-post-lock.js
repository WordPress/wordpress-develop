/**
 * @file Site Editor post lock bridge.
 *
 * Wires the Site Editor (wp_template, wp_template_part) into the existing
 * Heartbeat-based post lock workflow. The bulk of the implementation lives in
 * core: wp_refresh_post_lock() handles the server side, and the
 * `#post-lock-dialog` markup is rendered by _admin_notice_post_locked() via
 * site-editor.php. This file is the JS glue that:
 *
 *   - Tracks the currently edited template entity through the core/edit-site
 *     data store so client-side navigation between templates is reflected in
 *     the Heartbeat payload.
 *   - Emits the same `wp-refresh-post-lock` payload that
 *     wp-admin/js/post.js sends from the classic editor.
 *   - Reveals the server-rendered lock dialog on a `lock_error` tick and
 *     stores the refreshed lock token on a `new_lock` tick.
 *
 * @output wp-admin/js/site-editor-post-lock.js
 */

/* global wp */

( function ( $, settings ) {
	'use strict';

	if ( ! settings || ! settings.enabled ) {
		return;
	}

	var initialPostId = parseInt( settings.postId, 10 ) || 0;
	var activePostId  = initialPostId;
	var activeLock    = String( settings.lock || '' );

	/**
	 * Subscribe to the core/edit-site data store so the active post ID is
	 * always kept in sync when the user navigates between templates without
	 * a full page reload.
	 */
	if ( wp && wp.data && typeof wp.data.subscribe === 'function' ) {
		wp.data.subscribe( function () {
			var store = wp.data.select( 'core/edit-site' );
			if ( ! store ) {
				return;
			}

			var nextType = store.getEditedPostType();
			var nextId   = store.getEditedPostId();

			if (
				( 'wp_template' === nextType || 'wp_template_part' === nextType ) &&
				'number' === typeof nextId &&
				nextId !== activePostId
			) {
				activePostId = nextId;
				/*
				 * The new entity will receive its own lock token via the
				 * next Heartbeat tick.
				 */
				activeLock = '';
			}
		} );
	}

	$( function () {
		var $dialog = $( '#post-lock-dialog' );
		if ( $dialog.length && settings.takeOverUrl ) {
			$dialog.find( '.button-primary.wp-tab-last' ).attr( 'href', settings.takeOverUrl );
		}
	} );

	$( document )
		/*
		 * Send the lock payload on every Heartbeat tick when we have a
		 * resolved template post. Mirrors `heartbeat-send.refresh-lock`
		 * in wp-admin/js/post.js.
		 */
		.on( 'heartbeat-send.refresh-site-editor-post-lock', function ( e, data ) {
			if ( ! activePostId ) {
				return;
			}

			var send = { post_id: activePostId };

			if ( activeLock ) {
				send.lock = activeLock;
			}

			data['wp-refresh-post-lock'] = send;
		} )
		/*
		 * Handle the response: surface the server-rendered dialog on
		 * `lock_error`, or remember the refreshed lock token on
		 * `new_lock`.
		 */
		.on( 'heartbeat-tick.refresh-site-editor-post-lock', function ( e, data ) {
			if ( ! data['wp-refresh-post-lock'] ) {
				return;
			}

			var received = data['wp-refresh-post-lock'];

			if ( received.lock_error ) {
				var $wrap = $( '#post-lock-dialog' );

				if ( $wrap.length && ! $wrap.is( ':visible' ) ) {
					if ( received.lock_error.avatar_src ) {
						var $avatar = $( '<img />', {
							'class': 'avatar avatar-64 photo',
							width: 64,
							height: 64,
							alt: '',
							src: received.lock_error.avatar_src,
							srcset: received.lock_error.avatar_src_2x ?
								received.lock_error.avatar_src_2x + ' 2x' :
								undefined
						} );

						$wrap.find( 'div.post-locked-avatar' ).empty().append( $avatar );
					}

					$wrap.removeClass( 'hidden' )
						.show()
						.find( '.currently-editing' ).text( received.lock_error.text );

					$wrap.find( '.wp-tab-first' ).trigger( 'focus' );
				}
			} else if ( received.new_lock ) {
				activeLock = received.new_lock;
			}
		} );

}( jQuery, window.wpSiteEditorPostLockL10n ) );
