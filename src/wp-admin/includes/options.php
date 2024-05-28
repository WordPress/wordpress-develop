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
		function toggleEditableState(parentCheckboxId, childInputElement){
			var parentCheckbox = $(parentCheckboxId ),
				childrenInputs = $(childInputElement );
				ariaLiveRegion = $('#aria-live-region');


			function applyDisabledStyle(element, isDisabled) {
				element.prop('disabled', isDisabled);
				if (isDisabled) {
					element.css({
						'color': 'gray',
						'opacity': '0.85',
						'cursor': 'not-allowed'
					});
					element.closest('label').css({
						'color': 'gray',
						'opacity': '0.85',
						'cursor': 'not-allowed',
					});
				} else {
					element.css({
						'color': '',
						'opacity': '',
						'cursor': ''
					});
					element.closest('label').css({
						'color': '',
						'opacity': '',
						'cursor': '',
					});
				}
			}

			// Set the initial state based on the checkbox state
			childrenInputs.find('input, select, textarea').each(function() {
				applyDisabledStyle($(this), !parentCheckbox.prop('checked'));
			});
			parentCheckbox.attr('aria-expanded', parentCheckbox.prop('checked'));
			// Update the disabled state of children on parent checkbox change
			parentCheckbox.on('change', function() {
				var isChecked = this.checked;
				childrenInputs.find('input, select, textarea').each(function() {
					applyDisabledStyle($(this), !isChecked);
				});
				$(this).attr('aria-expanded', isChecked);

				// Announce the change to screen readers.
				var message = this.checked ? 'Checked Checkbox, Dependent fields are now editable.' : 'Unchecked Checkbox, Dependent fields are now disabled.';
				ariaLiveRegion.text( message );
			});
		}

		// Call function for each expandable section of discussion settings.
		toggleEditableState('#close_comments_for_old_posts', '.close-comments-setting' );
		toggleEditableState('#thread_comments', '.thread-comments-setting' );
		toggleEditableState('#page_comments', '.pagination-setting' );
		toggleEditableState( '#show_avatars', '.avatar-settings' );
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
<script type="text/javascript">
	jQuery( function($) {
		var $siteName = $( '#wp-admin-bar-site-name' ).children( 'a' ).first(),
			$siteIconPreview = $('#site-icon-preview-site-title'),
			homeURL = ( <?php echo wp_json_encode( get_home_url() ); ?> || '' ).replace( /^(https?:\/\/)?(www\.)?/, '' );

		$( '#blogname' ).on( 'input', function() {
			var title = $.trim( $( this ).val() ) || homeURL;

			// Truncate to 40 characters.
			if ( 40 < title.length ) {
				title = title.substring( 0, 40 ) + '\u2026';
			}

			$siteName.text( title );
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
<script type="text/javascript">
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
