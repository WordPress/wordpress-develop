/**
 * This file contains the functions needed for the inline editing of posts.
 *
 * @since 2.7.0
 * @output wp-admin/js/inline-edit-post.js
 */

/* global ajaxurl, typenow, inlineEditPost */

window.wp = window.wp || {};

/**
 * Manages the quick edit and bulk edit windows for editing posts or pages.
 *
 * @namespace inlineEditPost
 *
 * @since 2.7.0
 *
 * @type {Object}
 *
 * @property {string} type The type of inline editor.
 * @property {string} what The prefix before the post ID.
 *
 */
( function( $, wp ) {

	window.inlineEditPost = {

	/**
	 * Initializes the inline and bulk post editor.
	 *
	 * Binds event handlers to the Escape key to close the inline editor
	 * and to the save and close buttons. Changes DOM to be ready for inline
	 * editing. Adds event handler to bulk edit.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 *
	 * @return {void}
	 */
	init : function(){
		var t = this, qeRow = $('#inline-edit'), bulkRow = $('#bulk-edit');

		t.type = $('table.widefat').hasClass('pages') ? 'page' : 'post';
		// Post ID prefix.
		t.what = '#post-';

		/**
		 * Binds the Escape key to revert the changes and close the quick editor.
		 *
		 * @return {boolean} The result of revert.
		 */
		qeRow.on( 'keyup', function(e){
			// Revert changes if Escape key is pressed.
			if ( e.which === 27 ) {
				return inlineEditPost.revert();
			}
		});

		/**
		 * Binds the Escape key to revert the changes and close the bulk editor.
		 *
		 * @return {boolean} The result of revert.
		 */
		bulkRow.on( 'keyup', function(e){
			// Revert changes if Escape key is pressed.
			if ( e.which === 27 ) {
				return inlineEditPost.revert();
			}
		});

		/**
		 * Reverts changes and close the quick editor if the cancel button is clicked.
		 *
		 * @return {boolean} The result of revert.
		 */
		$( '.cancel', qeRow ).on( 'click', function() {
			return inlineEditPost.revert();
		});

		/**
		 * Saves changes in the quick editor if the save(named: update) button is clicked.
		 *
		 * @return {boolean} The result of save.
		 */
		$( '.save', qeRow ).on( 'click', function() {
			return inlineEditPost.save(this);
		});

		/**
		 * If Enter is pressed, and the target is not the cancel button, save the post.
		 *
		 * @return {boolean} The result of save.
		 */
		$('td', qeRow).on( 'keydown', function(e){
			if ( e.which === 13 && ! $( e.target ).hasClass( 'cancel' ) ) {
				return inlineEditPost.save(this);
			}
		});

		/**
		 * Reverts changes and close the bulk editor if the cancel button is clicked.
		 *
		 * @return {boolean} The result of revert.
		 */
		$( '.cancel', bulkRow ).on( 'click', function() {
			return inlineEditPost.revert();
		});

		/**
		 * Disables the password input field when the private post checkbox is checked.
		 */
		$('#inline-edit .inline-edit-private input[value="private"]').on( 'click', function(){
			var pw = $('input.inline-edit-password-input');
			if ( $(this).prop('checked') ) {
				pw.val('').prop('disabled', true);
			} else {
				pw.prop('disabled', false);
			}
		});

		/**
		 * Binds click event to the .editinline button which opens the quick editor.
		 */
		$( '#the-list' ).on( 'click', '.editinline', function() {
			$( this ).attr( 'aria-expanded', 'true' );
			inlineEditPost.edit( this );
		});

		// Clone quick edit categories for the bulk editor.
		var beCategories = $( '#inline-edit fieldset.inline-edit-categories' ).clone();

		// Make "id" attributes globally unique.
		beCategories.find( '*[id]' ).each( function() {
			this.id = 'bulk-edit-' + this.id;
		});

		$('#bulk-edit').find('fieldset:first').after(
			beCategories
		).siblings( 'fieldset:last' ).prepend(
			$( '#inline-edit .inline-edit-tags-wrap' ).clone()
		);

		$('select[name="_status"] option[value="future"]', bulkRow).remove();

		/**
		 * Adds onclick events to the apply buttons.
		 */
		$('#doaction').on( 'click', function(e){
			var n,
				$itemsSelected = $( '#posts-filter .check-column input[type="checkbox"]:checked' );

			if ( $itemsSelected.length < 1 ) {
				return;
			}

			t.whichBulkButtonId = $( this ).attr( 'id' );
			n = t.whichBulkButtonId.substr( 2 );

			if ( 'edit' === $( 'select[name="' + n + '"]' ).val() ) {
				e.preventDefault();
				t.setBulk();
			} else if ( $('form#posts-filter tr.inline-editor').length > 0 ) {
				t.revert();
			}
		});
	},

	/**
	 * Toggles the quick edit window, hiding it when it's active and showing it when
	 * inactive.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 *
	 * @param {Object} el Element within a post table row.
	 */
	toggle : function(el){
		var t = this;
		$( t.what + t.getId( el ) ).css( 'display' ) === 'none' ? t.revert() : t.edit( el );
	},

	/**
	 * Creates the bulk editor row to edit multiple posts at once.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 */
	setBulk : function(){
		var te = '', type = this.type, c = true;
		var checkedPosts = $( 'tbody th.check-column input[type="checkbox"]:checked' );
		var categories = {};
		this.revert();

		$( '#bulk-edit td' ).attr( 'colspan', $( 'th:visible, td:visible', '.widefat:first thead' ).length );

		// Insert the editor at the top of the table with an empty row above to maintain zebra striping.
		$('table.widefat tbody').prepend( $('#bulk-edit') ).prepend('<tr class="hidden"></tr>');
		$('#bulk-edit').addClass('inline-editor').show();

		/**
		 * Create a HTML div with the title and a link(delete-icon) for each selected
		 * post.
		 *
		 * Get the selected posts based on the checked checkboxes in the post table.
		 */
		$( 'tbody th.check-column input[type="checkbox"]' ).each( function() {

			// If the checkbox for a post is selected, add the post to the edit list.
			if ( $(this).prop('checked') ) {
				c = false;
				var id = $( this ).val(),
					theTitle = $( '#inline_' + id + ' .post_title' ).html() || wp.i18n.__( '(no title)' ),
					buttonVisuallyHiddenText = wp.i18n.sprintf(
						/* translators: %s: Post title. */
						wp.i18n.__( 'Remove &#8220;%s&#8221; from Bulk Edit' ),
						theTitle
					);

				te += '<li class="ntdelitem"><button type="button" id="_' + id + '" class="button-link ntdelbutton"><span class="screen-reader-text">' + buttonVisuallyHiddenText + '</span></button><span class="ntdeltitle" aria-hidden="true">' + theTitle + '</span></li>';
			}
		});

		// If no checkboxes where checked, just hide the quick/bulk edit rows.
		if ( c ) {
			return this.revert();
		}

		// Populate the list of items to bulk edit.
		$( '#bulk-titles' ).html( '<ul id="bulk-titles-list" role="list">' + te + '</ul>' );

		// Gather up some statistics on which of these checked posts are in which categories.
		checkedPosts.each( function() {
			var id      = $( this ).val();
			var checked = $( '#category_' + id ).text().split( ',' );

			checked.map( function( cid ) {
				categories[ cid ] || ( categories[ cid ] = 0 );
				// Just record that this category is checked.
				categories[ cid ]++;
			} );
		} );

		// Compute initial states.
		$( '.inline-edit-categories input[name="post_category[]"]' ).each( function() {
			if ( categories[ $( this ).val() ] == checkedPosts.length ) {
				// If the number of checked categories matches the number of selected posts, then all posts are in this category.
				$( this ).prop( 'checked', true );
			} else if ( categories[ $( this ).val() ] > 0 ) {
				// If the number is less than the number of selected posts, then it's indeterminate.
				$( this ).prop( 'indeterminate', true );
				if ( ! $( this ).parent().find( 'input[name="indeterminate_post_category[]"]' ).length ) {
					// Get the term label text.
					var label = $( this ).parent().text();
					// Set indeterminate states for the backend. Add accessible text for indeterminate inputs.
					$( this ).after( '<input type="hidden" name="indeterminate_post_category[]" value="' + $( this ).val() + '">' ).attr( 'aria-label', label.trim() + ': ' + wp.i18n.__( 'Some selected posts have this category' ) );
				}
			}
		} );

		$( '.inline-edit-categories input[name="post_category[]"]:indeterminate' ).on( 'change', function() {
			// Remove accessible label text. Remove the indeterminate flags as there was a specific state change.
			$( this ).removeAttr( 'aria-label' ).parent().find( 'input[name="indeterminate_post_category[]"]' ).remove();
		} );

		$( '.inline-edit-save button' ).on( 'click', function() {
			$( '.inline-edit-categories input[name="post_category[]"]' ).prop( 'indeterminate', false );
		} );

		/**
		 * Binds on click events to handle the list of items to bulk edit.
		 *
		 * @listens click
		 */
		$( '#bulk-titles .ntdelbutton' ).click( function() {
			var $this = $( this ),
				id = $this.attr( 'id' ).substr( 1 ),
				$prev = $this.parent().prev().children( '.ntdelbutton' ),
				$next = $this.parent().next().children( '.ntdelbutton' );

			$( 'input#cb-select-all-1, input#cb-select-all-2' ).prop( 'checked', false );
			$( 'table.widefat input[value="' + id + '"]' ).prop( 'checked', false );
			$( '#_' + id ).parent().remove();
			wp.a11y.speak( wp.i18n.__( 'Item removed.' ), 'assertive' );

			// Move focus to a proper place when items are removed.
			if ( $next.length ) {
				$next.focus();
			} else if ( $prev.length ) {
				$prev.focus();
			} else {
				$( '#bulk-titles-list' ).remove();
				inlineEditPost.revert();
				wp.a11y.speak( wp.i18n.__( 'All selected items have been removed. Select new items to use Bulk Actions.' ) );
			}
		});

		// Enable auto-complete for tags when editing posts.
		if ( 'post' === type ) {
			$( 'tr.inline-editor textarea[data-wp-taxonomy]' ).each( function ( i, element ) {
				/*
				 * While Quick Edit clones the form each time, Bulk Edit always re-uses
				 * the same form. Let's check if an autocomplete instance already exists.
				 */
				if ( $( element ).autocomplete( 'instance' ) ) {
					// jQuery equivalent of `continue` within an `each()` loop.
					return;
				}

				$( element ).wpTagsSuggest();
			} );
		}

		// Set initial focus on the Bulk Edit region.
		$( '#bulk-edit .inline-edit-wrapper' ).attr( 'tabindex', '-1' ).focus();
		// Scrolls to the top of the table where the editor is rendered.
		$('html, body').animate( { scrollTop: 0 }, 'fast' );
	},

	/**
	 * Creates a quick edit window for the post that has been clicked.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 *
	 * @param {number|Object} id The ID of the clicked post or an element within a post
	 *                           table row.
	 * @return {boolean} Always returns false at the end of execution.
	 */
	edit : function(id) {
		var t = this, fields, editRow, rowData, status, pageOpt, pageLevel, nextPage, pageLoop = true, nextLevel, f, val, pw;
		t.revert();

		if ( typeof(id) === 'object' ) {
			id = t.getId(id);
		}

		fields = ['post_title', 'post_name', 'post_author', '_status', 'jj', 'mm', 'aa', 'hh', 'mn', 'ss', 'post_password', 'post_format', 'menu_order', 'page_template'];
		if ( t.type === 'page' ) {
			fields.push('post_parent');
		}

		// Add the new edit row with an extra blank row underneath to maintain zebra striping.
		editRow = $('#inline-edit').clone(true);
		if( $('.the-list').lenth > 0 ){
			$( 'td', editRow ).attr( 'colspan', $( 'th:visible, td:visible', '.widefat:first thead' ).length );
		}
	
		// 
		// Remove the ID from the copied row and let the `for` attribute reference the hidden ID.
		$( 'td', editRow ).find('#quick-edit-legend').removeAttr('id');
		$( 'td', editRow ).find('p[id^="quick-edit-"]').removeAttr('id');

		$(t.what+id).removeClass('is-expanded').hide().after(editRow).after('<tr class="hidden"></tr>');

		// Populate fields in the quick edit window.
		rowData = $('#inline_'+id);
		if ( !$(':input[name="post_author"] option[value="' + $('.post_author', rowData).text() + '"]', editRow).val() ) {

			// The post author no longer has edit capabilities, so we need to add them to the list of authors.
			$(':input[name="post_author"]', editRow).prepend('<option value="' + $('.post_author', rowData).text() + '">' + $('#post-' + id + ' .author').text() + '</option>');
		}
		if ( $( ':input[name="post_author"] option', editRow ).length === 1 ) {
			$('label.inline-edit-author', editRow).hide();
		}

		for ( f = 0; f < fields.length; f++ ) {
			val = $('.'+fields[f], rowData);

			/**
			 * Replaces the image for a Twemoji(Twitter emoji) with it's alternate text.
			 *
			 * @return {string} Alternate text from the image.
			 */
			val.find( 'img' ).replaceWith( function() { return this.alt; } );
			val = val.text();
			$(':input[name="' + fields[f] + '"]', editRow).val( val );
		}

		if ( $( '.comment_status', rowData ).text() === 'open' ) {
			$( 'input[name="comment_status"]', editRow ).prop( 'checked', true );
		}
		if ( $( '.ping_status', rowData ).text() === 'open' ) {
			$( 'input[name="ping_status"]', editRow ).prop( 'checked', true );
		}
		if ( $( '.sticky', rowData ).text() === 'sticky' ) {
			$( 'input[name="sticky"]', editRow ).prop( 'checked', true );
		}

		/**
		 * Creates the select boxes for the categories.
		 */
		$('.post_category', rowData).each(function(){
			var taxname,
				term_ids = $(this).text();

			if ( term_ids ) {
				taxname = $(this).attr('id').replace('_'+id, '');
				$('ul.'+taxname+'-checklist :checkbox', editRow).val(term_ids.split(','));
			}
		});

		/**
		 * Gets all the taxonomies for live auto-fill suggestions when typing the name
		 * of a tag.
		 */
		$('.tags_input', rowData).each(function(){
			var terms = $(this),
				taxname = $(this).attr('id').replace('_' + id, ''),
				textarea = $('textarea.tax_input_' + taxname, editRow),
				comma = wp.i18n._x( ',', 'tag delimiter' ).trim();

			// Ensure the textarea exists.
			if ( ! textarea.length ) {
				return;
			}

			terms.find( 'img' ).replaceWith( function() { return this.alt; } );
			terms = terms.text();

			if ( terms ) {
				if ( ',' !== comma ) {
					terms = terms.replace(/,/g, comma);
				}
				textarea.val(terms);
			}

			textarea.wpTagsSuggest();
		});

		// Handle the post status.
		var post_date_string = $(':input[name="aa"]').val() + '-' + $(':input[name="mm"]').val() + '-' + $(':input[name="jj"]').val();
		post_date_string += ' ' + $(':input[name="hh"]').val() + ':' + $(':input[name="mn"]').val() + ':' + $(':input[name="ss"]').val();
		var post_date = new Date( post_date_string );
		status = $('._status', rowData).text();
		if ( 'future' !== status && Date.now() > post_date ) {
			$('select[name="_status"] option[value="future"]', editRow).remove();
		} else {
			$('select[name="_status"] option[value="publish"]', editRow).remove();
		}

		pw = $( '.inline-edit-password-input' ).prop( 'disabled', false );
		if ( 'private' === status ) {
			$('input[name="keep_private"]', editRow).prop('checked', true);
			pw.val( '' ).prop( 'disabled', true );
		}

		// Remove the current page and children from the parent dropdown.
		pageOpt = $('select[name="post_parent"] option[value="' + id + '"]', editRow);
		if ( pageOpt.length > 0 ) {
			pageLevel = pageOpt[0].className.split('-')[1];
			nextPage = pageOpt;
			while ( pageLoop ) {
				nextPage = nextPage.next('option');
				if ( nextPage.length === 0 ) {
					break;
				}

				nextLevel = nextPage[0].className.split('-')[1];

				if ( nextLevel <= pageLevel ) {
					pageLoop = false;
				} else {
					nextPage.remove();
					nextPage = pageOpt;
				}
			}
			pageOpt.remove();
		}

		$(editRow).attr('id', 'edit-'+id).addClass('inline-editor').show();
		$('.ptitle', editRow).trigger( 'focus' );

		return false;
	},

	/**
	 * Saves the changes made in the quick edit window to the post.
	 * Ajax saving is only for Quick Edit and not for bulk edit.
	 *
	 * @since 2.7.0
	 *
	 * @param {number} id The ID for the post that has been changed.
	 * @return {boolean} False, so the form does not submit when pressing
	 *                   Enter on a focused field.
	 */
	save : function(id) {
		var params, fields, page = $('.post_status_page').val() || '';

		if ( typeof(id) === 'object' ) {
			id = this.getId(id);
		}

		$( 'table.widefat .spinner' ).addClass( 'is-active' );

		params = {
			action: 'inline-save',
			post_type: typenow,
			post_ID: id,
			edit_date: 'true',
			post_status: page
		};

		fields = $('#edit-'+id).find(':input').serialize();
		params = fields + '&' + $.param(params);

		// Make Ajax request.
		$.post( ajaxurl, params,
			function(r) {
				var $errorNotice = $( '#edit-' + id + ' .inline-edit-save .notice-error' ),
					$error = $errorNotice.find( '.error' );

				$( 'table.widefat .spinner' ).removeClass( 'is-active' );

				if (r) {
					if ( -1 !== r.indexOf( '<tr' ) ) {
						$(inlineEditPost.what+id).siblings('tr.hidden').addBack().remove();
						$('#edit-'+id).before(r).remove();
						$( inlineEditPost.what + id ).hide().fadeIn( 400, function() {
							// Move focus back to the Quick Edit button. $( this ) is the row being animated.
							$( this ).find( '.editinline' )
								.attr( 'aria-expanded', 'false' )
								.trigger( 'focus' );
							wp.a11y.speak( wp.i18n.__( 'Changes saved.' ) );
						});
					} else {
						r = r.replace( /<.[^<>]*?>/g, '' );
						$errorNotice.removeClass( 'hidden' );
						$error.html( r );
						wp.a11y.speak( $error.text() );
					}
				} else {
					$errorNotice.removeClass( 'hidden' );
					$error.text( wp.i18n.__( 'Error while saving the changes.' ) );
					wp.a11y.speak( wp.i18n.__( 'Error while saving the changes.' ) );
				}
			},
		'html');

		// Prevent submitting the form when pressing Enter on a focused field.
		return false;
	},

	/**
	 * Hides and empties the Quick Edit and/or Bulk Edit windows.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 *
	 * @return {boolean} Always returns false.
	 */
	revert : function(){
		var $tableWideFat = $( '.widefat' ),
			id = $( '.inline-editor', $tableWideFat ).attr( 'id' );

		if ( id ) {
			$( '.spinner', $tableWideFat ).removeClass( 'is-active' );

			if ( 'bulk-edit' === id ) {

				// Hide the bulk editor.
				$( '#bulk-edit', $tableWideFat ).removeClass( 'inline-editor' ).hide().siblings( '.hidden' ).remove();
				$('#bulk-titles').empty();

				// Store the empty bulk editor in a hidden element.
				$('#inlineedit').append( $('#bulk-edit') );

				// Move focus back to the Bulk Action button that was activated.
				$( '#' + inlineEditPost.whichBulkButtonId ).trigger( 'focus' );
			} else {

				// Remove both the inline-editor and its hidden tr siblings.
				$('#'+id).siblings('tr.hidden').addBack().remove();
				id = id.substr( id.lastIndexOf('-') + 1 );

				// Show the post row and move focus back to the Quick Edit button.
				$( this.what + id ).show().find( '.editinline' )
					.attr( 'aria-expanded', 'false' )
					.trigger( 'focus' );
			}
		}

		return false;
	},

	/**
	 * Gets the ID for a the post that you want to quick edit from the row in the quick
	 * edit table.
	 *
	 * @since 2.7.0
	 *
	 * @memberof inlineEditPost
	 *
	 * @param {Object} o DOM row object to get the ID for.
	 * @return {string} The post ID extracted from the table row in the object.
	 */
	getId : function(o) {
		var id = $(o).closest('tr').attr('id'),
			parts = id.split('-');
		return parts[parts.length - 1];
	}
};

$( function() { inlineEditPost.init(); } );

// Show/hide locks on posts.
$( function() {

	// Set the heartbeat interval to 10 seconds.
	if ( typeof wp !== 'undefined' && wp.heartbeat ) {
		wp.heartbeat.interval( 10 );
	}
}).on( 'heartbeat-tick.wp-check-locked-posts', function( e, data ) {
	var locked = data['wp-check-locked-posts'] || {};

	$('#the-list tr').each( function(i, el) {
		var key = el.id, row = $(el), lock_data, avatar;

		if ( locked.hasOwnProperty( key ) ) {
			if ( ! row.hasClass('wp-locked') ) {
				lock_data = locked[key];
				row.find('.column-title .locked-text').text( lock_data.text );
				row.find('.check-column checkbox').prop('checked', false);

				if ( lock_data.avatar_src ) {
					avatar = $( '<img />', {
						'class': 'avatar avatar-18 photo',
						width: 18,
						height: 18,
						alt: '',
						src: lock_data.avatar_src,
						srcset: lock_data.avatar_src_2x ? lock_data.avatar_src_2x + ' 2x' : undefined
					} );
					row.find('.column-title .locked-avatar').empty().append( avatar );
				}
				row.addClass('wp-locked');
			}
		} else if ( row.hasClass('wp-locked') ) {
			row.removeClass( 'wp-locked' ).find( '.locked-info span' ).empty();
		}
	});
}).on( 'heartbeat-send.wp-check-locked-posts', function( e, data ) {
	var check = [];

	$('#the-list tr').each( function(i, el) {
		if ( el.id ) {
			check.push( el.id );
		}
	});

	if ( check.length ) {
		data['wp-check-locked-posts'] = check;
	}
});

})( jQuery, window.wp );
