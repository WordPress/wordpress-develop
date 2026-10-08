/* global QUnit, postboxes */
( function( $ ) {
	QUnit.module( 'post', function( hooks ) {
		hooks.beforeEach( function() {
			var testContext = this;

			this.pagenow = window.pagenow;
			this.ClipboardJS = window.ClipboardJS;
			this.addPostboxToggles = postboxes.add_postbox_toggles;
			this.windowName = window.name;
			this.postGlobals = {};
			this.postGlobalNames = [
				'makeSlugeditClickable',
				'editPermalink',
				'commentsBox',
				'WPSetThumbnailHTML',
				'WPSetThumbnailID',
				'WPRemoveThumbnail',
				'wptitlehint'
			];
			$.each( this.postGlobalNames, function( index, name ) {
				testContext.postGlobals[ name ] = {
					exists: Object.prototype.hasOwnProperty.call( window, name ),
					value: window[ name ]
				};
			} );

			window.pagenow = 'post';
			window.ClipboardJS = function() {
				this.on = function() {};
			};
			postboxes.add_postbox_toggles = function() {};
		} );

		hooks.afterEach( function() {
			var testContext = this;

			$( document ).off( '.refresh-lock .update-post-slug .wp-refresh-nonces .edit-post' );
			$( window ).off( '.edit-post' );
			$( 'script[data-qunit-post-script]' ).remove();
			$.each( this.postGlobalNames, function( index, name ) {
				if ( testContext.postGlobals[ name ].exists ) {
					window[ name ] = testContext.postGlobals[ name ].value;
				} else {
					delete window[ name ];
				}
			} );
			window.name = this.windowName;
			window.pagenow = this.pagenow;
			window.ClipboardJS = this.ClipboardJS;
			postboxes.add_postbox_toggles = this.addPostboxToggles;
		} );

		QUnit.test( 'restores a custom status after changing visibility from private to public', function( assert ) {
			var done = assert.async(),
				scriptElement = document.createElement( 'script' );

			$( '#qunit-fixture' ).html(
				'<form id="post">' +
					'<input id="post_ID" value="1">' +
					'<input id="original_post_status" value="reviewed">' +
					'<div id="submitdiv">' +
						'<div id="misc-publishing-actions"><a class="edit-post-status"></a></div>' +
						'<div id="post-status-select">' +
							'<input id="hidden_post_status" value="reviewed">' +
							'<select id="post_status">' +
								'<option value="pending">Pending Review</option>' +
								'<option value="reviewed" data-save-text="Save as Reviewed" selected>Reviewed</option>' +
							'</select>' +
						'</div>' +
						'<span id="post-status-display">Reviewed</span>' +
						'<input id="save-post" value="Save as Reviewed">' +
						'<input id="publish" value="Publish">' +
						'<span id="timestamp">Publish immediately</span>' +
						'<div id="timestampdiv">' +
							'<select id="mm"><option value="8" data-text="August" selected>08-Aug</option></select>' +
							'<input id="aa" value="2026"><input id="jj" value="4"><input id="hh" value="8"><input id="mn" value="0">' +
							'<input id="hidden_aa" value="2026"><input id="hidden_mm" value="8"><input id="hidden_jj" value="4"><input id="hidden_hh" value="8"><input id="hidden_mn" value="0">' +
							'<input id="cur_aa" value="2026"><input id="cur_mm" value="8"><input id="cur_jj" value="4"><input id="cur_hh" value="8"><input id="cur_mn" value="0">' +
						'</div>' +
						'<div id="visibility">' +
							'<span id="post-visibility-display">Public</span>' +
							'<a class="edit-visibility"></a>' +
							'<div id="post-visibility-select">' +
								'<input type="radio" name="visibility" value="public" checked>' +
								'<input type="radio" name="visibility" value="private">' +
								'<a class="save-post-visibility"></a>' +
							'</div>' +
						'</div>' +
					'</div>' +
				'</form>'
			);

			scriptElement.onload = function() {
				$( function() {
					$( 'input[name="visibility"][value="private"]' ).prop( 'checked', true );
					$( '.save-post-visibility' ).trigger( 'click' );

					assert.strictEqual( $( '#post_status' ).val(), 'publish', 'Private visibility selects the publish proxy status.' );
					assert.strictEqual( $( '#post_status option[data-private-status-option]' ).length, 1, 'A temporary publish option is added.' );

					$( 'input[name="visibility"][value="public"]' ).prop( 'checked', true );
					$( '.save-post-visibility' ).trigger( 'click' );

					assert.strictEqual( $( '#post_status' ).val(), 'reviewed', 'The custom status is restored.' );
					assert.strictEqual( $( '#post-status-display' ).text(), 'Reviewed', 'The custom status label is restored.' );
					assert.strictEqual( $( '#save-post' ).val(), 'Save as Reviewed', 'The custom save button text is restored.' );
					assert.strictEqual( $( '#post_status option[data-private-status-option]' ).length, 0, 'The temporary publish option is removed.' );
					done();
				} );
			};
			scriptElement.onerror = function() {
				assert.true( false, 'The post script loaded.' );
				done();
			};
			scriptElement.src = '../../build/wp-admin/js/' + ( /compiled\.html$/.test( window.location.pathname ) ? 'post.min.js' : 'post.js' );
			scriptElement.setAttribute( 'data-qunit-post-script', '' );
			document.head.appendChild( scriptElement );
		} );
	} );
} )( jQuery );
