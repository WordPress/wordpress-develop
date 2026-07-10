/**
 * Scripts for the options/settings administration screens.
 *
 * @output wp-admin/js/options.js
 */

/* global ajaxurl */

jQuery( function( $ ) {
	var $showAvatars     = $( '#show_avatars' ),
		$avatarSettings  = $( '.avatar-settings' ),
		$siteName        = $( '#wp-admin-bar-site-name' ).children( 'a' ).first(),
		$siteIconPreview = $( '#site-icon-preview-site-title' ),
		$languageSelect  = $( '#WPLANG' ),
		$frontStaticPages = $( '#front-static-pages' ),
		homeURL          = ( ( window.optionsL10n && window.optionsL10n.homeURL ) || '' ).replace( /^(https?:\/\/)?(www\.)?/, '' );

	// options-discussion.php: Toggle avatar settings when 'Show Avatars' is changed.
	$showAvatars.on( 'change', function() {
		$avatarSettings.toggleClass( 'hide-if-js', ! this.checked );
	} );

	// options-general.php: Update admin bar site name and site icon preview on title input.
	$( '#blogname' ).on( 'input', function() {
		var title = $.trim( $( this ).val() ) || homeURL;

		// Truncate to 40 characters.
		if ( 40 < title.length ) {
			title = title.substring( 0, 40 ) + '\u2026';
		}

		$siteName.text( title );
		$siteIconPreview.text( title );
	} );

	// options-general.php: Date and time format pickers.
	$( 'input[name="date_format"]' ).on( 'click', function() {
		if ( 'date_format_custom_radio' !== $( this ).attr( 'id' ) ) {
			$( 'input[name="date_format_custom"]' ).val( $( this ).val() ).closest( 'fieldset' ).find( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
		}
	} );

	$( 'input[name="date_format_custom"]' ).on( 'click input', function() {
		$( '#date_format_custom_radio' ).prop( 'checked', true );
	} );

	$( 'input[name="time_format"]' ).on( 'click', function() {
		if ( 'time_format_custom_radio' !== $( this ).attr( 'id' ) ) {
			$( 'input[name="time_format_custom"]' ).val( $( this ).val() ).closest( 'fieldset' ).find( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
		}
	} );

	$( 'input[name="time_format_custom"]' ).on( 'click input', function() {
		$( '#time_format_custom_radio' ).prop( 'checked', true );
	} );

	$( 'input[name="date_format_custom"], input[name="time_format_custom"]' ).on( 'input', function() {
		var format   = $( this ),
			fieldset = format.closest( 'fieldset' ),
			example  = fieldset.find( '.example' ),
			spinner  = fieldset.find( '.spinner' );

		// Debounce the event callback while users are typing.
		clearTimeout( $.data( this, 'timer' ) );
		$( this ).data( 'timer', setTimeout( function() {
			// If custom date is not empty.
			if ( format.val() ) {
				spinner.addClass( 'is-active' );

				$.post( ajaxurl, {
					action: 'date_format_custom' === format.attr( 'name' ) ? 'date_format' : 'time_format',
					date:   format.val()
				}, function( d ) { spinner.removeClass( 'is-active' ); example.text( d ); } );
			}
		}, 500 ) );
	} );

	// options-general.php: Language install spinner.
	$( 'form' ).on( 'submit', function() {
		/*
		 * Don't show a spinner for English and installed languages,
		 * as there is nothing to download.
		 */
		if ( $languageSelect.length && ! $languageSelect.find( 'option:selected' ).data( 'installed' ) ) {
			$( '#submit', this ).after( '<span class="spinner language-install-spinner is-active" />' );
		}
	} );

	// options-reading.php: Enable/disable page selects based on 'Your homepage displays' radio.
	if ( $frontStaticPages.length ) {
		var $staticPage   = $frontStaticPages.find( 'input:radio[value="page"]' ),
			$selects      = $frontStaticPages.find( 'select' ),
			checkDisabled = function() {
				$selects.prop( 'disabled', ! $staticPage.prop( 'checked' ) );
			};
		checkDisabled();
		$frontStaticPages.find( 'input:radio' ).on( 'change', checkDisabled );
	}
} );
