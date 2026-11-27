<?php
/**
 * WordPress Options Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.4.0
 */

/**
 * Output JavaScript to toggle display of additional settings if avatars are disabled.
 *
 * @since 4.2.0
 */
function options_discussion_add_js() {
	?>
	<script>
	(function($){
		var parent = $( '#show_avatars' ),
			children = $( '.avatar-settings' );
		parent.on( 'change', function(){
			children.toggleClass( 'hide-if-js', ! this.checked );
		});
	})(jQuery);
	</script>
	<?php
}

/**
 * Display JavaScript on the page.
 *
 * @since 3.5.0
 */
function options_general_add_js() {
	?>
<script>
	jQuery( function($) {
		var $siteName = $( '#wp-admin-bar-site-name' ).children( 'a' ).first(),
			$siteIconPreview = $('#site-icon-preview-site-title'),
			homeURL = ( <?php echo wp_json_encode( get_home_url(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ); ?> || '' ).replace( /^(https?:\/\/)?(www\.)?/, '' );

		$( '#blogname' ).on( 'input', function() {
			var title = $.trim( $( this ).val() ) || homeURL,
				$siteIcon = $siteName.children();

			// Truncate to 40 characters.
			if ( 40 < title.length ) {
				title = title.substring( 0, 40 ) + '\u2026';
			}

			$siteName.text( title ).prepend( $siteIcon );
			$siteIconPreview.text( title );
		});

		$( 'input[name="date_format"]' ).on( 'click', function() {
			if ( 'date_format_custom_radio' !== $(this).attr( 'id' ) )
				$( 'input[name="date_format_custom"]' ).val( $( this ).val() ).closest( 'fieldset' ).find( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
		});

		$( 'input[name="date_format_custom"]' ).on( 'click input', function() {
			$( '#date_format_custom_radio' ).prop( 'checked', true );
		});

		$( 'input[name="time_format"]' ).on( 'click', function() {
			if ( 'time_format_custom_radio' !== $(this).attr( 'id' ) )
				$( 'input[name="time_format_custom"]' ).val( $( this ).val() ).closest( 'fieldset' ).find( '.example' ).text( $( this ).parent( 'label' ).children( '.format-i18n' ).text() );
		});

		$( 'input[name="time_format_custom"]' ).on( 'click input', function() {
			$( '#time_format_custom_radio' ).prop( 'checked', true );
		});

		$( 'input[name="date_format_custom"], input[name="time_format_custom"]' ).on( 'input', function() {
			var format = $( this ),
				fieldset = format.closest( 'fieldset' ),
				example = fieldset.find( '.example' ),
				spinner = fieldset.find( '.spinner' );

			// Debounce the event callback while users are typing.
			clearTimeout( $.data( this, 'timer' ) );
			$( this ).data( 'timer', setTimeout( function() {
				// If custom date is not empty.
				if ( format.val() ) {
					spinner.addClass( 'is-active' );

					$.post( ajaxurl, {
						action: 'date_format_custom' === format.attr( 'name' ) ? 'date_format' : 'time_format',
						date 	: format.val()
					}, function( d ) { spinner.removeClass( 'is-active' ); example.text( d ); } );
				}
			}, 500 ) );
		} );

		var languageSelect = $( '#WPLANG' );
		$( 'form' ).on( 'submit', function() {
			/*
			 * Don't show a spinner for English and installed languages,
			 * as there is nothing to download.
			 */
			if ( ! languageSelect.find( 'option:selected' ).data( 'installed' ) ) {
				$( '#submit', this ).after( '<span class="spinner language-install-spinner is-active" />' );
			}
		});
	} );
</script>
	<?php
}

/**
 * Display JavaScript on the page.
 *
 * @since 3.5.0
 */
function options_reading_add_js() {
	?>
<script>
	jQuery( function($) {
		var section = $('#front-static-pages'),
			staticPage = section.find('input:radio[value="page"]'),
			selects = section.find('select'),
			check_disabled = function(){
				selects.prop( 'disabled', ! staticPage.prop('checked') );
			};
		check_disabled();
		section.find( 'input:radio' ).on( 'change', check_disabled );
	} );
</script>
	<?php
}

/**
 * Render the site charset setting.
 *
 * @since 3.5.0
 */
function options_reading_blog_charset() {
	echo '<input name="blog_charset" type="text" id="blog_charset" value="' . esc_attr( get_option( 'blog_charset' ) ) . '" class="regular-text" />';
	echo '<p class="description">' . __( 'The <a href="https://wordpress.org/documentation/article/wordpress-glossary/#character-set">character encoding</a> of your site (UTF-8 is recommended)' ) . '</p>';
}

/**
 * Updates the date and time formats after the site language changes.
 *
 * A format is only updated when it still matches the default format of the
 * previous locale, so formats chosen by the user are kept.
 *
 * @since 7.3.0
 *
 * @param string $old_locale The site locale before the change.
 * @param string $new_locale The site locale after the change.
 */
function wp_update_date_time_formats_on_locale_change( $old_locale, $new_locale ) {
	if ( $old_locale === $new_locale ) {
		return;
	}

	/*
	 * Load the translations directly, as switch_to_locale() does not know
	 * about language packs installed during the current request.
	 */
	load_default_textdomain( $old_locale );
	$old_formats = array(
		'date_format' => __( 'F j, Y' ),
		'time_format' => __( 'g:i a' ),
	);

	load_default_textdomain( $new_locale );
	$new_formats = array(
		'date_format' => __( 'F j, Y' ),
		'time_format' => __( 'g:i a' ),
	);

	// Restore the translations for the current user.
	load_default_textdomain();

	foreach ( $old_formats as $option => $old_format ) {
		if ( get_option( $option ) === $old_format ) {
			update_option( $option, $new_formats[ $option ] );
		}
	}
}
