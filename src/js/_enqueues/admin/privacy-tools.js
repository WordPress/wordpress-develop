/**
 * Interactions used by the User Privacy tools in WordPress.
 *
 * @output wp-admin/js/privacy-tools.js
 */

// Privacy request action handling.
jQuery( function( $ ) {
	var __ = wp.i18n.__,
		copiedNoticeTimeout;

	function setActionState( $action, state ) {
		$action.children().addClass( 'hidden' );
		$action.children( '.' + state ).removeClass( 'hidden' );
	}

	function getUserEmail( $requestRow ) {
		var $emailCell = $requestRow.find( '.column-email a[href^="mailto:"]' );
		return $emailCell.length ? $emailCell.text() : '';
	}

	function showAdminNotice( message, type ) {
		var $headerEnd = $( '.wp-header-end' ),
			$notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p><strong>' + message + '</strong></p></div>' );

		$( '.wrap > .notice' ).remove();

		if ( $headerEnd.length ) {
			$headerEnd.after( $notice );
		} else {
			$( '.wrap' ).find( '> h1' ).after( $notice );
		}

		$( document ).trigger( 'wp-notice-added' );
	}

	$( '.export-personal-data-handle' ).on( 'click', function( event ) {
		var $this          = $( this ),
			$action        = $this.parents( '.export-personal-data' ),
			$requestRow    = $this.parents( 'tr' ),
			$progress      = $requestRow.find( '.export-progress' ),
			$rowActions    = $this.parents( '.row-actions' ),
			requestID      = $action.data( 'request-id' ),
			nonce          = $action.data( 'nonce' ),
			exportersCount = $action.data( 'exporters-count' ),
			sendAsEmail    = $action.data( 'send-as-email' ) ? true : false;

		event.preventDefault();
		event.stopPropagation();

		$rowActions.addClass( 'processing' );

		$action.trigger( 'blur' );
		setExportProgress( 0 );

		function onExportDoneSuccess( zipUrl ) {
			var userEmail = getUserEmail( $requestRow ),
				summaryMessage;

			if ( 'undefined' !== typeof zipUrl ) {
				summaryMessage = userEmail ? 
					/* translators: %s: User's email address. */
					wp.i18n.sprintf( __( 'Personal data export file for %s was downloaded.' ), userEmail ) :
					__( 'This user&#8217;s personal data export file was downloaded.' );
			} else {
				summaryMessage = userEmail ?
					/* translators: %s: User's email address. */
					wp.i18n.sprintf( __( 'Personal data export link for %s was sent.' ), userEmail ) :
					__( 'This user&#8217;s personal data export link was sent.' );
			}

			setActionState( $action, 'export-personal-data-success' );

			showAdminNotice( summaryMessage, 'success' );

			if ( 'undefined' !== typeof zipUrl ) {
				window.location = zipUrl;
			} else if ( ! sendAsEmail ) {
				onExportFailure( __( 'No personal data export file was generated.' ) );
			}

			setTimeout( function() { $rowActions.removeClass( 'processing' ); }, 500 );
		}

		function onExportFailure( errorMessage ) {
			var userEmail = getUserEmail( $requestRow ),
				summaryMessage = userEmail ?
					/* translators: %s: User's email address. */
					wp.i18n.sprintf( __( 'An error occurred while attempting to export personal data for %s.' ), userEmail ) :
					__( 'An error occurred while attempting to export personal data.' );

			setActionState( $action, 'export-personal-data-failed' );

			if ( errorMessage ) {
				summaryMessage += ' ' + errorMessage;
			}

			showAdminNotice( summaryMessage, 'error' );

			setTimeout( function() { $rowActions.removeClass( 'processing' ); }, 500 );
		}

		function setExportProgress( exporterIndex ) {
			var progress       = ( exportersCount > 0 ? exporterIndex / exportersCount : 0 ),
				progressString = Math.round( progress * 100 ).toString() + '%';

			$progress.html( progressString );
		}

		function doNextExport( exporterIndex, pageIndex ) {
			$.ajax(
				{
					url: window.ajaxurl,
					data: {
						action: 'wp-privacy-export-personal-data',
						exporter: exporterIndex,
						id: requestID,
						page: pageIndex,
						security: nonce,
						sendAsEmail: sendAsEmail
					},
					method: 'post'
				}
			).done( function( response ) {
				var responseData = response.data;

				if ( ! response.success ) {
					// e.g. invalid request ID.
					setTimeout( function() { onExportFailure( response.data ); }, 500 );
					return;
				}

				if ( ! responseData.done ) {
					setTimeout( doNextExport( exporterIndex, pageIndex + 1 ) );
				} else {
					setExportProgress( exporterIndex );
					if ( exporterIndex < exportersCount ) {
						setTimeout( doNextExport( exporterIndex + 1, 1 ) );
					} else {
						setTimeout( function() { onExportDoneSuccess( responseData.url ); }, 500 );
					}
				}
			}).fail( function( jqxhr, textStatus, error ) {
				// e.g. Nonce failure.
				setTimeout( function() { onExportFailure( error ); }, 500 );
			});
		}

		// And now, let's begin.
		setActionState( $action, 'export-personal-data-processing' );
		doNextExport( 1, 1 );
	});

	$( '.remove-personal-data-handle' ).on( 'click', function( event ) {
		var $this         = $( this ),
			$action       = $this.parents( '.remove-personal-data' ),
			$requestRow   = $this.parents( 'tr' ),
			$progress     = $requestRow.find( '.erasure-progress' ),
			$rowActions   = $this.parents( '.row-actions' ),
			requestID     = $action.data( 'request-id' ),
			nonce         = $action.data( 'nonce' ),
			erasersCount  = $action.data( 'erasers-count' ),
			hasRemoved    = false,
			hasRetained   = false,
			messages      = [];

		event.preventDefault();
		event.stopPropagation();

		$rowActions.addClass( 'processing' );

		$action.trigger( 'blur' );
		setErasureProgress( 0 );

		function onErasureDoneSuccess() {
			var userEmail = getUserEmail( $requestRow ),
				summaryMessage,
				noticeType = 'success';

			setActionState( $action, 'remove-personal-data-success' );

			if ( false === hasRemoved ) {
				if ( false === hasRetained ) {
					summaryMessage = userEmail ?
						/* translators: %s: User's email address. */
						wp.i18n.sprintf( __( 'No personal data was found for %s.' ), userEmail ) :
						__( 'No personal data was found for this user.' );
				} else {
					summaryMessage = userEmail ?
						/* translators: %s: User's email address. */
						wp.i18n.sprintf( __( 'Personal data was found for %s but was not erased.' ), userEmail ) :
						__( 'Personal data was found for this user but was not erased.' );
					noticeType = 'warning';
				}
			} else {
				if ( false === hasRetained ) {
					summaryMessage = userEmail ?
						/* translators: %s: User's email address. */
						wp.i18n.sprintf( __( 'Personal data erasure for %s completed.' ), userEmail ) :
						__( 'All of the personal data found for this user was erased.' );
				} else {
					summaryMessage = userEmail ?
						/* translators: %s: User's email address. */
						wp.i18n.sprintf( __( 'Personal data erasure for %s completed, but some data was retained.' ), userEmail ) :
						__( 'Personal data was found for this user but some of the personal data found was not erased.' );
					noticeType = 'warning';
				}
			}

			if ( messages.length ) {
				summaryMessage += ' ' + messages.join( ' ' );
			}

			showAdminNotice( summaryMessage, noticeType );

			setTimeout( function() { $rowActions.removeClass( 'processing' ); }, 500 );
		}

		function onErasureFailure() {
			var userEmail = getUserEmail( $requestRow ),
				summaryMessage = userEmail ?
					/* translators: %s: User's email address. */
					wp.i18n.sprintf( __( 'An error occurred while attempting to find and erase personal data for %s.' ), userEmail ) :
					__( 'An error occurred while attempting to find and erase personal data.' );

			setActionState( $action, 'remove-personal-data-failed' );
			
			showAdminNotice( summaryMessage, 'error' );

			setTimeout( function() { $rowActions.removeClass( 'processing' ); }, 500 );
		}

		function setErasureProgress( eraserIndex ) {
			var progress       = ( erasersCount > 0 ? eraserIndex / erasersCount : 0 ),
				progressString = Math.round( progress * 100 ).toString() + '%';

			$progress.html( progressString );
		}

		function doNextErasure( eraserIndex, pageIndex ) {
			$.ajax({
				url: window.ajaxurl,
				data: {
					action: 'wp-privacy-erase-personal-data',
					eraser: eraserIndex,
					id: requestID,
					page: pageIndex,
					security: nonce
				},
				method: 'post'
			}).done( function( response ) {
				var responseData = response.data;

				if ( ! response.success ) {
					setTimeout( function() { onErasureFailure(); }, 500 );
					return;
				}
				if ( responseData.items_removed ) {
					hasRemoved = hasRemoved || responseData.items_removed;
				}
				if ( responseData.items_retained ) {
					hasRetained = hasRetained || responseData.items_retained;
				}
				if ( responseData.messages ) {
					messages = messages.concat( responseData.messages );
				}
				if ( ! responseData.done ) {
					setTimeout( doNextErasure( eraserIndex, pageIndex + 1 ) );
				} else {
					setErasureProgress( eraserIndex );
					if ( eraserIndex < erasersCount ) {
						setTimeout( doNextErasure( eraserIndex + 1, 1 ) );
					} else {
						setTimeout( function() { onErasureDoneSuccess(); }, 500 );
					}
				}
			}).fail( function() {
				setTimeout( function() { onErasureFailure(); }, 500 );
			});
		}

		// And now, let's begin.
		setActionState( $action, 'remove-personal-data-processing' );

		doNextErasure( 1, 1 );
	});

	// Privacy Policy page, copy action.
	$( document ).on( 'click', function( event ) {
		var $parent,
			range,
			$target = $( event.target ),
			copiedNotice = $target.siblings( '.success' );

		clearTimeout( copiedNoticeTimeout );

		if ( $target.is( 'button.privacy-text-copy' ) ) {
			$parent = $target.closest( '.privacy-settings-accordion-panel' );

			if ( $parent.length ) {
				try {
					var documentPosition = document.documentElement.scrollTop,
						bodyPosition     = document.body.scrollTop;

					// Setup copy.
					window.getSelection().removeAllRanges();

					// Hide tutorial content to remove from copied content.
					range = document.createRange();
					$parent.addClass( 'hide-privacy-policy-tutorial' );

					// Copy action. Select only the dedicated copy-content wrapper
					// so the actions toolbar (which contains the "Copied!" notice
					// and button label) is never part of the selection - see #58969.
					range.selectNodeContents( $parent.find( '.privacy-text-copy-content' )[0] );
					window.getSelection().addRange( range );
					document.execCommand( 'copy' );

					// Reset section.
					$parent.removeClass( 'hide-privacy-policy-tutorial' );
					window.getSelection().removeAllRanges();

					// Return scroll position - see #49540.
					if ( documentPosition > 0 && documentPosition !== document.documentElement.scrollTop ) {
						document.documentElement.scrollTop = documentPosition;
					} else if ( bodyPosition > 0 && bodyPosition !== document.body.scrollTop ) {
						document.body.scrollTop = bodyPosition;
					}

					// Display and speak notice to indicate action complete.
					copiedNotice.addClass( 'visible' );
					wp.a11y.speak( __( 'The suggested policy text has been copied to your clipboard.' ) );

					// Delay notice dismissal.
					copiedNoticeTimeout = setTimeout( function() {
						copiedNotice.removeClass( 'visible' );
					}, 3000 );
				} catch ( er ) {}
			}
		}
	});

	// Label handling to focus the create page button on Privacy settings page.
	$( 'body.options-privacy-php label[for=create-page]' ).on( 'click', function( e ) {
		e.preventDefault();
		$( 'input#create-page' ).trigger( 'focus' );
	} );

	// Accordion handling in various new Privacy settings pages.
	$( '.privacy-settings-accordion' ).on( 'click', '.privacy-settings-accordion-trigger', function() {
		var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

		if ( isExpanded ) {
			$( this ).attr( 'aria-expanded', 'false' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', true );
		} else {
			$( this ).attr( 'aria-expanded', 'true' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', false );
		}
	} );
});
