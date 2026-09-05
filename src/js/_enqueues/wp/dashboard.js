/**
 * @output wp-admin/js/dashboard.js
 */

/* global pagenow, ajaxurl, postboxes, wpActiveEditor:true, ajaxWidgets */
/* global ajaxPopulateWidgets, quickPressLoad */
window.wp = window.wp || {};
window.communityEventsData = window.communityEventsData || {};

/**
 * Initializes the dashboard widget functionality.
 *
 * @since 2.7.0
 */
jQuery( function($) {
	var welcomePanel = $( '#welcome-panel' ),
		welcomePanelHide = $('#wp_welcome_panel-hide'),
		updateWelcomePanel;

	/**
	 * Saves the visibility of the welcome panel.
	 *
	 * @since 3.3.0
	 *
	 * @param {boolean} visible Should it be visible or not.
	 *
	 * @return {void}
	 */
	updateWelcomePanel = function( visible ) {
		$.post(
			ajaxurl,
			{
				action: 'update-welcome-panel',
				visible: visible,
				welcomepanelnonce: $( '#welcomepanelnonce' ).val()
			},
			function() {
				wp.a11y.speak( wp.i18n.__( 'Screen Options updated.' ) );
			}
		);
	};

	// Unhide the welcome panel if the Welcome Option checkbox is checked.
	if ( welcomePanel.hasClass('hidden') && welcomePanelHide.prop('checked') ) {
		welcomePanel.removeClass('hidden');
	}

	// Hide the welcome panel when the dismiss button or close button is clicked.
	$('.welcome-panel-close, .welcome-panel-dismiss a', welcomePanel).on( 'click', function(e) {
		e.preventDefault();
		welcomePanel.addClass('hidden');
		updateWelcomePanel( 0 );
		$('#wp_welcome_panel-hide').prop('checked', false);
	});

	// Set welcome panel visibility based on Welcome Option checkbox value.
	welcomePanelHide.on( 'click', function() {
		welcomePanel.toggleClass('hidden', ! this.checked );
		updateWelcomePanel( this.checked ? 1 : 0 );
	});

	/**
	 * These widgets can be populated via ajax.
	 *
	 * @since 2.7.0
	 *
	 * @type {string[]}
	 *
	 * @global
 	 */
	window.ajaxWidgets = ['dashboard_primary'];

	/**
	 * Triggers widget updates via Ajax.
	 *
	 * @since 2.7.0
	 *
	 * @global
	 *
	 * @param {string} el Optional. Widget to fetch or none to update all.
	 *
	 * @return {void}
	 */
	window.ajaxPopulateWidgets = function(el) {
		/**
		 * Fetch the latest representation of the widget via Ajax and show it.
		 *
		 * @param {number} i Number of half-seconds to use as the timeout.
		 * @param {string} id ID of the element which is going to be checked for changes.
		 *
		 * @return {void}
		 */
		function show(i, id) {
			var p, e = $('#' + id + ' div.inside:visible').find('.widget-loading');
			// If the element is found in the dom, queue to load latest representation.
			if ( e.length ) {
				p = e.parent();
				setTimeout( function(){
					// Request the widget content.
					p.load( ajaxurl + '?action=dashboard-widgets&widget=' + id + '&pagenow=' + pagenow, '', function() {
						// Hide the parent and slide it out for visual fanciness.
						p.hide().slideDown('normal', function(){
							$(this).css('display', '');
						});
					});
				}, i * 500 );
			}
		}

		// If we have received a specific element to fetch, check if it is valid.
		if ( el ) {
			el = el.toString();
			// If the element is available as Ajax widget, show it.
			if ( $.inArray(el, ajaxWidgets) !== -1 ) {
				// Show element without any delay.
				show(0, el);
			}
		} else {
			// Walk through all ajaxWidgets, loading them after each other.
			$.each( ajaxWidgets, show );
		}
	};

	// Initially populate ajax widgets.
	ajaxPopulateWidgets();

	// Register ajax widgets as postbox toggles.
	postboxes.add_postbox_toggles(pagenow, { pbshow: ajaxPopulateWidgets } );

	/**
	 * Control the Quick Press (Quick Draft) widget.
	 *
	 * @since 2.7.0
	 *
	 * @global
	 *
	 * @return {void}
	 */
	window.quickPressLoad = function() {
		var act = $( '#quickpost-action' ), t;

		// Enable the submit button.
		$( '#quick-press .submit input[type="submit"]' ).prop( 'disabled', false );

		t = $( '#quick-press' ).on( 'submit', function( e ) {
			e.preventDefault();

			// Disable the submit button to prevent duplicate submissions.
			$( '#quick-press .submit input[type="submit"]' ).prop( 'disabled', true );

			// Post the entered data to save it.
			$.post( t.attr( 'action' ), t.serializeArray(), function( data ) {
				// Replace the form, and prepend the published post.
				$( '#dashboard_quick_press .inside' ).html( data );
				quickPressLoad();
				highlightLatestPost();

				// Focus the title to allow for quickly drafting another post.
				$( '#title' ).trigger( 'focus');
			} );

			/**
			 * Highlights the latest post for one second.
			 *
			 * @return {void}
 			 */
			function highlightLatestPost () {
				var latestPost = $( '.drafts ul li' ) .first(),
					errorNotice = $( '#quick-press .notice-error' );

				if ( errorNotice.length ) {
					return;
				}

				latestPost.css( 'background', '#fffbe5' );
				setTimeout( function () {
					latestPost.css( 'background', 'none' );
				}, 1000 );
			}
		} );

		// Change the QuickPost action to the publish value.
		$( '#publish' ).on( 'click', function() { act.val( 'post-quickpress-publish' ); } );

		$( '#quick-press' ).on( 'click focusin', function() {
			wpActiveEditor = 'content';
		} );

		autoResizeTextarea();
	};
	window.quickPressLoad();

	// Enable the dragging functionality of the widgets.
	$( '.meta-box-sortables' ).sortable( 'option', 'containment', '#wpwrap' );

	/**
	 * Adjust the height of the textarea based on the content.
	 *
	 * @since 3.6.0
	 *
	 * @return {void}
	 */
	function autoResizeTextarea() {
		// When IE8 or older is used to render this document, exit.
		if ( document.documentMode && document.documentMode < 9 ) {
			return;
		}

		// Add a hidden div. We'll copy over the text from the textarea to measure its height.
		$('body').append( '<div class="quick-draft-textarea-clone" style="display: none;"></div>' );

		var clone = $('.quick-draft-textarea-clone'),
			editor = $('#content'),
			editorHeight = editor.height(),
			/*
			 * 100px roughly accounts for browser chrome and allows the
			 * save draft button to show on-screen at the same time.
			 */
			editorMaxHeight = $(window).height() - 100;

		/*
		 * Match up textarea and clone div as much as possible.
		 * Padding cannot be reliably retrieved using shorthand in all browsers.
		 */
		clone.css({
			'font-family': editor.css('font-family'),
			'font-size':   editor.css('font-size'),
			'line-height': editor.css('line-height'),
			'padding-bottom': editor.css('paddingBottom'),
			'padding-left': editor.css('paddingLeft'),
			'padding-right': editor.css('paddingRight'),
			'padding-top': editor.css('paddingTop'),
			'white-space': 'pre-wrap',
			'word-wrap': 'break-word',
			'display': 'none'
		});

		// The 'propertychange' is used in IE < 9.
		editor.on('focus input propertychange', function() {
			var $this = $(this),
				// Add a non-breaking space to ensure that the height of a trailing newline is
				// included.
				textareaContent = $this.val() + '&nbsp;',
				// Add 2px to compensate for border-top & border-bottom.
				cloneHeight = clone.css('width', $this.css('width')).text(textareaContent).outerHeight() + 2;

			// Default to show a vertical scrollbar, if needed.
			editor.css('overflow-y', 'auto');

			// Only change the height if it has changed and both heights are below the max.
			if ( cloneHeight === editorHeight || ( cloneHeight >= editorMaxHeight && editorHeight >= editorMaxHeight ) ) {
				return;
			}

			/*
			 * Don't allow editor to exceed the height of the window.
			 * This is also bound in CSS to a max-height of 1300px to be extra safe.
			 */
			if ( cloneHeight > editorMaxHeight ) {
				editorHeight = editorMaxHeight;
			} else {
				editorHeight = cloneHeight;
			}

			// Disable scrollbars because we adjust the height to the content.
			editor.css('overflow', 'hidden');

			$this.css('height', editorHeight + 'px');
		});
	}

} );

jQuery( function( $ ) {
	'use strict';

	var communityEventsData = window.communityEventsData,
		dateI18n = wp.date.dateI18n,
		format = wp.date.format,
		sprintf = wp.i18n.sprintf,
		__ = wp.i18n.__,
		_x = wp.i18n._x,
		app;

	/**
	 * Global Community Events namespace.
	 *
	 * @since 4.8.0
	 *
	 * @memberOf wp
	 * @namespace wp.communityEvents
	 */
	app = window.wp.communityEvents = /** @lends wp.communityEvents */{
		initialized: false,
		model: null,

		/**
		 * Initializes the wp.communityEvents object.
		 *
		 * @since 4.8.0
		 *
		 * @return {void}
		 */
		init: function() {
			if ( app.initialized ) {
				return;
			}

			var $container = $( '#community-events' );

			/*
			 * When JavaScript is disabled, the errors container is shown, so
			 * that "This widget requires JavaScript" message can be seen.
			 *
			 * When JS is enabled, the container is hidden at first, and then
			 * revealed during the template rendering, if there actually are
			 * errors to show.
			 *
			 * The display indicator switches from `hide-if-js` to `aria-hidden`
			 * here in order to maintain consistency with all the other fields
			 * that key off of `aria-hidden` to determine their visibility.
			 * `aria-hidden` can't be used initially, because there would be no
			 * way to set it to false when JavaScript is disabled, which would
			 * prevent people from seeing the "This widget requires JavaScript"
			 * message.
			 */
			$( '.community-events-errors' )
				.attr( 'aria-hidden', 'true' )
				.removeClass( 'hide-if-js' );

			$container.on( 'click', '.community-events-toggle-location, .community-events-cancel', app.toggleLocationForm );

			/**
			 * Filters events based on entered location.
			 *
			 * @return {void}
			 */
			$container.on( 'submit', '.community-events-form', function( event ) {
				var location = $( '#community-events-location' ).val().trim();

				event.preventDefault();

				/*
				 * Don't trigger a search if the search field is empty or the
				 * search term was made of only spaces before being trimmed.
				 */
				if ( ! location ) {
					return;
				}

				app.getEvents({
					location: location
				});
			});
			
			/**
			 * Clears the location input field and resets the events.
			 */
			$container.on( 'click', '.community-events-clear', function( event ) {
				event.preventDefault();

				// Clear the input field and focus back on the location input.
				$( '#community-events-location' ).val( '' ).trigger( 'focus' );
				
				// Reset the cache.
				if (communityEventsData.cache) {
					communityEventsData.cache.location = false;
					communityEventsData.cache.events = [];
				}
				
				// Render the empty state.
				app.renderEventsTemplate( {
					'location' : false,
					'events'   : [],
					'error'    : false
				}, 'user' );

			});

			if ( communityEventsData && communityEventsData.cache && communityEventsData.cache.location && communityEventsData.cache.events ) {
				app.renderEventsTemplate( communityEventsData.cache, 'app' );
			} else {
				app.getEvents();
			}

			app.initialized = true;
		},

		/**
		 * Toggles the visibility of the Edit Location form.
		 *
		 * @since 4.8.0
		 *
		 * @param {event|string} action 'show' or 'hide' to specify a state;
		 *                              or an event object to flip between states.
		 *
		 * @return {void}
		 */
		toggleLocationForm: function( action ) {
			var $toggleButton = $( '.community-events-toggle-location' ),
				$form         = $( '.community-events-form' ),
				$target       = $();

			if ( 'object' === typeof action ) {
				// The action is the event object: get the clicked element.
				$target = $( action.target );
				/*
				 * Strict comparison doesn't work in this case because sometimes
				 * we explicitly pass a string as value of aria-expanded and
				 * sometimes a boolean as the result of an evaluation.
				 */
				action = 'true' == $toggleButton.attr( 'aria-expanded' ) ? 'hide' : 'show';
			}

			if ( 'hide' === action ) {
				$toggleButton.attr( 'aria-expanded', 'false' );
				$form.attr( 'aria-hidden', 'true' );
				/*
				 * If the Cancel button has been clicked, bring the focus back
				 * to the toggle button so users relying on screen readers don't
				 * lose their place.
				 */
				if ( $target.hasClass( 'community-events-cancel' ) ) {
					$toggleButton.trigger( 'focus' );
				}
			} else {
				$toggleButton.attr( 'aria-expanded', 'true' );
				$form.attr( 'aria-hidden', 'false' );
			}
		},

		/**
		 * Sends REST API requests to fetch events for the widget.
		 *
		 * @since 4.8.0
		 *
		 * @param {Object} requestParams REST API Request parameters object.
		 *
		 * @return {void}
		 */
		getEvents: function( requestParams ) {
			var initiatedBy,
				app = this,
				$spinner = $( '.community-events-form' ).children( '.spinner' );

			requestParams          = requestParams || {};
			requestParams._wpnonce = communityEventsData.nonce;
			requestParams.timezone = window.Intl ? window.Intl.DateTimeFormat().resolvedOptions().timeZone : '';

			initiatedBy = requestParams.location ? 'user' : 'app';

			$spinner.addClass( 'is-active' );

			wp.ajax.post( 'get-community-events', requestParams )
				.always( function() {
					$spinner.removeClass( 'is-active' );
				})

				.done( function( response ) {
					if ( 'no_location_available' === response.error ) {
						if ( requestParams.location ) {
							response.unknownCity = requestParams.location;
						} else {
							/*
							 * No location was passed, which means that this was an automatic query
							 * based on IP, locale, and timezone. Since the user didn't initiate it,
							 * it should fail silently. Otherwise, the error could confuse and/or
							 * annoy them.
							 */
							delete response.error;
						}
					}
					app.renderEventsTemplate( response, initiatedBy );
				})

				.fail( function() {
					app.renderEventsTemplate({
						'location' : false,
						'events'   : [],
						'error'    : true
					}, initiatedBy );
				});
		},

		/**
		 * Renders the template for the Events section of the Events & News widget.
		 *
		 * @since 4.8.0
		 *
		 * @param {Object} templateParams The various parameters that will get passed to wp.template.
		 * @param {string} initiatedBy    'user' to indicate that this was triggered manually by the user;
		 *                                'app' to indicate it was triggered automatically by the app itself.
		 *
		 * @return {void}
		 */
		renderEventsTemplate: function( templateParams, initiatedBy ) {
			var template,
				elementVisibility,
				$toggleButton    = $( '.community-events-toggle-location' ),
				$locationMessage = $( '#community-events-location-message' ),
				$results         = $( '.community-events-results' );

			templateParams.events = app.populateDynamicEventFields(
				templateParams.events,
				communityEventsData.time_format
			);

			/*
			 * Hide all toggleable elements by default, to keep the logic simple.
			 * Otherwise, each block below would have to turn hide everything that
			 * could have been shown at an earlier point.
			 *
			 * The exception to that is that the .community-events container is hidden
			 * when the page is first loaded, because the content isn't ready yet,
			 * but once we've reached this point, it should always be shown.
			 */
			elementVisibility = {
				'.community-events'                  : true,
				'.community-events-loading'          : false,
				'.community-events-errors'           : false,
				'.community-events-error-occurred'   : false,
				'.community-events-could-not-locate' : false,
				'#community-events-location-message' : false,
				'.community-events-toggle-location'  : false,
				'.community-events-results'          : false
			};

			/*
			 * Determine which templates should be rendered and which elements
			 * should be displayed.
			 */
			if ( templateParams.location.ip ) {
				/*
				 * If the API determined the location by geolocating an IP, it will
				 * provide events, but not a specific location.
				 */
				$locationMessage.text( __( 'Attend an upcoming event near you.' ) );

				if ( templateParams.events.length ) {
					template = wp.template( 'community-events-event-list' );
					$results.html( template( templateParams ) );
				} else {
					template = wp.template( 'community-events-no-upcoming-events' );
					$results.html( template( templateParams ) );
				}

				elementVisibility['#community-events-location-message'] = true;
				elementVisibility['.community-events-toggle-location']  = true;
				elementVisibility['.community-events-results']          = true;

			} else if ( templateParams.location.description ) {
				template = wp.template( 'community-events-attend-event-near' );
				$locationMessage.html( template( templateParams ) );

				if ( templateParams.events.length ) {
					template = wp.template( 'community-events-event-list' );
					$results.html( template( templateParams ) );
				} else {
					template = wp.template( 'community-events-no-upcoming-events' );
					$results.html( template( templateParams ) );
				}

				if ( 'user' === initiatedBy ) {
					wp.a11y.speak(
						sprintf(
							/* translators: %s: The name of a city. */
							__( 'City updated. Listing events near %s.' ),
							templateParams.location.description
						),
						'assertive'
					);
				}

				elementVisibility['#community-events-location-message'] = true;
				elementVisibility['.community-events-toggle-location']  = true;
				elementVisibility['.community-events-results']          = true;

			} else if ( templateParams.unknownCity ) {
				template = wp.template( 'community-events-could-not-locate' );
				$( '.community-events-could-not-locate' ).html( template( templateParams ) );
				wp.a11y.speak(
					sprintf(
						/*
						 * These specific examples were chosen to highlight the fact that a
						 * state is not needed, even for cities whose name is not unique.
						 * It would be too cumbersome to include that in the instructions
						 * to the user, so it's left as an implication.
						 */
						/*
						 * translators: %s is the name of the city we couldn't locate.
						 * Replace the examples with cities related to your locale. Test that
						 * they match the expected location and have upcoming events before
						 * including them. If no cities related to your locale have events,
						 * then use cities related to your locale that would be recognizable
						 * to most users. Use only the city name itself, without any region
						 * or country. Use the endonym (native locale name) instead of the
						 * English name if possible.
						 */
						__( 'We couldn’t locate %s. Please try another nearby city. For example: Kansas City; Springfield; Portland.' ),
						templateParams.unknownCity
					)
				);

				elementVisibility['.community-events-errors']           = true;
				elementVisibility['.community-events-could-not-locate'] = true;

			} else if ( templateParams.error && 'user' === initiatedBy ) {
				/*
				 * Errors messages are only shown for requests that were initiated
				 * by the user, not for ones that were initiated by the app itself.
				 * Showing error messages for an event that user isn't aware of
				 * could be confusing or unnecessarily distracting.
				 */
				wp.a11y.speak( __( 'An error occurred. Please try again.' ) );

				elementVisibility['.community-events-errors']         = true;
				elementVisibility['.community-events-error-occurred'] = true;
			} else {
				$locationMessage.text( __( 'Enter your closest city to find nearby events.' ) );

				elementVisibility['#community-events-location-message'] = true;
				elementVisibility['.community-events-toggle-location']  = true;
			}

			// Set the visibility of toggleable elements.
			_.each( elementVisibility, function( isVisible, element ) {
				$( element ).attr( 'aria-hidden', ! isVisible );
			});

			$toggleButton.attr( 'aria-expanded', elementVisibility['.community-events-toggle-location'] );

			if ( templateParams.location && ( templateParams.location.ip || templateParams.location.latitude ) ) {
				// Hide the form when there's a valid location.
				app.toggleLocationForm( 'hide' );

				if ( 'user' === initiatedBy ) {
					/*
					 * When the form is programmatically hidden after a user search,
					 * bring the focus back to the toggle button so users relying
					 * on screen readers don't lose their place.
					 */
					$toggleButton.trigger( 'focus' );
				}
			} else {
				app.toggleLocationForm( 'show' );
			}
		},

		/**
		 * Populate event fields that have to be calculated on the fly.
		 *
		 * These can't be stored in the database, because they're dependent on
		 * the user's current time zone, locale, etc.
		 *
		 * @since 5.5.2
		 *
		 * @param {Object[]} rawEvents  The events that should have dynamic fields added to them.
		 * @param {string}   timeFormat A time format acceptable by `wp.date.dateI18n()`.
		 *
		 * @return {Object[]} The events with dynamic fields added to them.
		 */
		populateDynamicEventFields: function( rawEvents, timeFormat ) {
			// Clone the parameter to avoid mutating it, so that this can remain a pure function.
			var populatedEvents = JSON.parse( JSON.stringify( rawEvents ) );

			$.each( populatedEvents, function( index, event ) {
				var timeZone = app.getTimeZone( event.start_unix_timestamp * 1000 );

				event.user_formatted_date = app.getFormattedDate(
					event.start_unix_timestamp * 1000,
					event.end_unix_timestamp * 1000,
					timeZone
				);

				event.user_formatted_time = dateI18n(
					timeFormat,
					event.start_unix_timestamp * 1000,
					timeZone
				);

				event.timeZoneAbbreviation = app.getTimeZoneAbbreviation( event.start_unix_timestamp * 1000 );
			} );

			return populatedEvents;
		},

		/**
		 * Returns the user's local/browser time zone, in a form suitable for `wp.date.i18n()`.
		 *
		 * @since 5.5.2
		 *
		 * @param {number} startTimestamp The start timestamp of the event.
		 *
		 * @return {string|number} A time zone string like `America/Chicago`, or a number representing the offset from UTC in minutes.
		 */
		getTimeZone: function( startTimestamp ) {
			/*
			 * Prefer a name like `Europe/Helsinki`, since that automatically tracks daylight savings. This
			 * doesn't need to take `startTimestamp` into account for that reason.
			 */
			var timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

			/*
			 * Fall back to an offset for IE11, which declares the property but doesn't assign a value.
			 */
			if ( 'undefined' === typeof timeZone ) {
				/*
				 * It's important to use the _event_ time, not the _current_
				 * time, so that daylight savings time is accounted for.
				 */
				timeZone = app.getFlippedTimeZoneOffset( startTimestamp );
			}

			return timeZone;
		},

		/**
		 * Get intuitive time zone offset.
		 *
		 * `Data.prototype.getTimezoneOffset()` returns a positive value for time zones
		 * that are _behind_ UTC, and a _negative_ value for ones that are ahead.
		 *
		 * See https://stackoverflow.com/questions/21102435/why-does-javascript-date-gettimezoneoffset-consider-0500-as-a-positive-off.
		 *
		 * @since 5.5.2
		 *
		 * @param {number} startTimestamp
		 *
		 * @return {number} The offset from UTC in minutes, with the sign flipped to be more intuitive.
		 */
		getFlippedTimeZoneOffset: function( startTimestamp ) {
			return new Date( startTimestamp ).getTimezoneOffset() * -1;
		},

		/**
		 * Get a short time zone name, like `PST`.
		 *
		 * @since 5.5.2
		 *
		 * @param {number} startTimestamp
		 *
		 * @return {string} A short time zone name, like `PST`, or a string like `GMT+5` if the abbreviation can't be determined.
		 */
		getTimeZoneAbbreviation: function( startTimestamp ) {
			var timeZoneAbbreviation,
				eventDateTime = new Date( startTimestamp );

			/*
			 * Leaving the `locales` argument undefined is important, so that the browser
			 * displays the abbreviation that's most appropriate for the current locale. For
			 * some that will be `UTC{+|-}{n}`, and for others it will be a code like `PST`.
			 *
			 * This doesn't need to take `startTimestamp` into account, because a name like
			 * `America/Chicago` automatically tracks daylight savings.
			 */
			var shortTimeStringParts = eventDateTime.toLocaleTimeString( undefined, { timeZoneName : 'short' } ).split( ' ' );

			if ( 3 === shortTimeStringParts.length ) {
				timeZoneAbbreviation = shortTimeStringParts[2];
			}

			if ( 'undefined' === typeof timeZoneAbbreviation ) {
				/*
				 * It's important to use the _event_ time, not the _current_
				 * time, so that daylight savings time is accounted for.
				 */
				var timeZoneOffset = app.getFlippedTimeZoneOffset( startTimestamp ),
					sign = -1 === Math.sign( timeZoneOffset ) ? '' : '+';

				// translators: Used as part of a string like `GMT+5` in the Events Widget.
				timeZoneAbbreviation = _x( 'GMT', 'Events widget offset prefix' ) + sign + ( timeZoneOffset / 60 );
			}

			return timeZoneAbbreviation;
		},

		/**
		 * Format a start/end date in the user's local time zone and locale.
		 *
		 * @since 5.5.2
		 *
		 * @param {number} startDate The Unix timestamp in milliseconds when the event starts.
		 * @param {number} endDate   The Unix timestamp in milliseconds when the event ends.
		 * @param {string} timeZone  A time zone string or offset which is parsable by `wp.date.i18n()`.
		 *
		 * @return {string} A formatted date string, like `Mon, Jan 1, 2024` or `Jan 1–3, 2024`.
		 */
		getFormattedDate: function( startDate, endDate, timeZone ) {
			var formattedDate;

			/*
			 * The `date_format` option is not used because it's important
			 * in this context to keep the day of the week in the displayed date,
			 * so that users can tell at a glance if the event is on a day they
			 * are available, without having to open the link.
			 *
			 * The case of crossing a year boundary is intentionally not handled.
			 * It's so rare in practice that it's not worth the complexity
			 * tradeoff. The _ending_ year should be passed to
			 * `multiple_month_event`, though, just in case.
			 */
			/* translators: Date format for upcoming events on the dashboard. Include the day of the week. See https://www.php.net/manual/datetime.format.php */
			var singleDayEvent = __( 'l, M j, Y' ),
				/* translators: Date string for upcoming events. 1: Month, 2: Starting day, 3: Ending day, 4: Year. */
				multipleDayEvent = __( '%1$s %2$d–%3$d, %4$d' ),
				/* translators: Date string for upcoming events. 1: Starting month, 2: Starting day, 3: Ending month, 4: Ending day, 5: Ending year. */
				multipleMonthEvent = __( '%1$s %2$d – %3$s %4$d, %5$d' );

			// Detect single-day events.
			if ( ! endDate || format( 'Y-m-d', startDate ) === format( 'Y-m-d', endDate ) ) {
				formattedDate = dateI18n( singleDayEvent, startDate, timeZone );

			// Multiple day events.
			} else if ( format( 'Y-m', startDate ) === format( 'Y-m', endDate ) ) {
				formattedDate = sprintf(
					multipleDayEvent,
					dateI18n( _x( 'F', 'upcoming events month format' ), startDate, timeZone ),
					dateI18n( _x( 'j', 'upcoming events day format' ), startDate, timeZone ),
					dateI18n( _x( 'j', 'upcoming events day format' ), endDate, timeZone ),
					dateI18n( _x( 'Y', 'upcoming events year format' ), endDate, timeZone )
				);

			// Multi-day events that cross a month boundary.
			} else {
				formattedDate = sprintf(
					multipleMonthEvent,
					dateI18n( _x( 'F', 'upcoming events month format' ), startDate, timeZone ),
					dateI18n( _x( 'j', 'upcoming events day format' ), startDate, timeZone ),
					dateI18n( _x( 'F', 'upcoming events month format' ), endDate, timeZone ),
					dateI18n( _x( 'j', 'upcoming events day format' ), endDate, timeZone ),
					dateI18n( _x( 'Y', 'upcoming events year format' ), endDate, timeZone )
				);
			}

			return formattedDate;
		}
	};

	if ( $( '#dashboard_primary' ).is( ':visible' ) ) {
		app.init();
	} else {
		$( document ).on( 'postbox-toggled', function( event, postbox ) {
			var $postbox = $( postbox );

			if ( 'dashboard_primary' === $postbox.attr( 'id' ) && $postbox.is( ':visible' ) ) {
				app.init();
			}
		});
	}
});

/**
 * WordPress Global Events.
 * 
 * This follows the same pattern as wp.communityEvents but focuses on global events
 * rather than location-specific events.
 *
 * @since 6.0.0
 *
 * @memberOf wp
 * @namespace wp.globalEvents
 */
window.wp = window.wp || {};
window.wp.globalEvents = /** @lends wp.globalEvents */ {
    initialized: false,
    allEvents: [],
    
    // Constants for configuration
    API_ENDPOINT: 'https://api.wordpress.org/events/1.0/?location=San%20Francisco',
    MAX_EVENTS_PER_FILTER: 3,
    ACCESSIBILITY_MEETUP_NAME: 'WordPress Accessibility Meetup',
    LEARN_MEETUP_NAME: 'Learn WordPress Online Workshops',
    DEFAULT_LOCATION: 'San Francisco, CA',
    
    /**
     * Initializes the wp.globalEvents object.
     *
     * @since 6.0.0
     *
     * @return {void}
     */
    init: function() {
        if (wp.globalEvents.initialized) {
            return;
        }
        
        const $ = jQuery;
        
        // Fetch and render global events when the page loads
        wp.globalEvents.fetchEvents().then(globalEventsData => {
            wp.globalEvents.allEvents = globalEventsData.events || [];
            const latestEvents = wp.globalEvents.filterEvents(wp.globalEvents.allEvents, 'All');
            wp.globalEvents.renderEventsTemplate(latestEvents, 'All');
        });

        // Set up filter buttons
        $('#global-events-filters button').on('click', function() {
            const filter = $(this).data('filter');
            $('#global-events-filters button').removeClass('button-primary');
            $(this).addClass('button-primary');
            
            const filteredEvents = wp.globalEvents.filterEvents(wp.globalEvents.allEvents, filter);
            wp.globalEvents.renderEventsTemplate(filteredEvents, filter);
        });
        
        wp.globalEvents.initialized = true;
    },
    
    /**
     * Fetches events from the WordPress.org API.
     *
     * @since 6.0.0
     *
     * @return {Promise} Promise resolving to events data.
     */
    fetchEvents: async function() {
        try {
            const response = await fetch(wp.globalEvents.API_ENDPOINT);
            return await response.json();
        } catch (error) {
            console.error('Error fetching global events:', error);
            return { events: [] };
        }
    },
    
    /**
     * Filters events by category.
     *
     * @since 6.0.0
     *
     * @param {Array}  events Array of event objects.
     * @param {string} filter Filter category ('All', 'Accessibility', 'Learn').
     * @return {Array} Filtered events array.
     */
    filterEvents: function(events, filter) {
        let filteredEvents = [];

        if (filter === 'All') {
            filteredEvents = events.filter(event => {
                const meetupName = (event.meetup || '');
                return meetupName === wp.globalEvents.ACCESSIBILITY_MEETUP_NAME || 
                       meetupName === wp.globalEvents.LEARN_MEETUP_NAME;
            });
        } else {
            filteredEvents = events.filter(event => {
                const meetupName = (event.meetup || '');
                
                if (filter === 'Accessibility') {
                    return meetupName === wp.globalEvents.ACCESSIBILITY_MEETUP_NAME;
                } else if (filter === 'Learn') {
                    return meetupName === wp.globalEvents.LEARN_MEETUP_NAME;
                }
                return false;
            });
        }

        // Process events for display (add formatted time/date)
        filteredEvents = filteredEvents.map(event => {
            const eventCopy = {...event};
            
            // Add formatted date/time using existing WordPress functions
            if (wp.communityEvents && typeof wp.communityEvents.populateDynamicEventFields === 'function') {
                const processed = wp.communityEvents.populateDynamicEventFields([eventCopy], window.communityEventsData.time_format)[0];
                return processed;
            }
            
            return eventCopy;
        });

        // Limit the filtered events to a maximum configured number
        return filteredEvents.slice(0, wp.globalEvents.MAX_EVENTS_PER_FILTER);
    },
    
    /**
     * Renders the events template with the filtered events.
     *
     * @since 6.0.0
     *
     * @param {Array}  events Filtered events array.
     * @param {string} filter Current active filter.
     * @return {void}
     */
    renderEventsTemplate: function(events, filter) {
        const $ = jQuery;
        const $eventList = $('#global-events-list');
        const template = wp.template('global-events-event-list');
        
        // Format events to match community events structure
        const formattedEvents = events.map(event => {
            // Determine the correct type based on meetup name
            let eventType = 'global';
            const meetupName = event.meetup || '';
            
            if (meetupName === wp.globalEvents.ACCESSIBILITY_MEETUP_NAME) {
                eventType = 'Accessibility Meetup';
            } else if (meetupName === wp.globalEvents.LEARN_MEETUP_NAME) {
                eventType = 'Learn WordPress';
            }
            
            // Create a clone with all the expected properties
            return {
                ...event,
                // Use the determined type
                type: eventType,
                title: event.title || '',
                url: event.meetup_url || '#',
                // Format location properly
                location: {
                    location: typeof event.location === 'object' ? 
                        (event.location.location || event.location.description || wp.globalEvents.DEFAULT_LOCATION) :
                        (event.location || wp.globalEvents.DEFAULT_LOCATION)
                },
                // Make sure these exist for the template
                user_formatted_date: event.user_formatted_date || '',
                user_formatted_time: event.user_formatted_time || '',
                timeZoneAbbreviation: event.timeZoneAbbreviation || ''
            };
        });

        // Use the same template that community events uses
        if (formattedEvents.length > 0) {
            $eventList.html(template({ events: formattedEvents }));
            
            // Apply specific icon classes based on event type or filter
            $('.event-global .event-icon').each(function() {
                const $this = $(this);
                const $eventItem = $this.closest('.event');
                const eventUrl = $eventItem.find('.event-title').attr('href').toLowerCase();
                
                // Add dashicons-calendar class as the default icon
                $this.addClass('dashicons-nametag');
                
                // Apply specific icon based on event type or URL
                if (eventUrl.includes('accessibility')) {
                    $this.removeClass('dashicons-nametag').addClass('dashicons-universal-access');
                } else if (eventUrl.includes('learn')) {
                    $this.removeClass('dashicons-nametag').addClass('dashicons-welcome-learn-more');
                }
            });
        } else {
            // Use identical empty state markup
            $eventList.html('<li class="event-none"><p>' + wp.i18n.__('No global events found at the moment.') + '</p></li>');
        }
    }
};

jQuery(function($) {
    // Initialize both event modules if their containers exist
    if ($('#dashboard_primary').is(':visible')) {
        if ($('#community-events').length) {
            wp.communityEvents.init();
        }
        if ($('#global-events-section').length) {
            wp.globalEvents.init();
        }
    } else {
        $(document).on('postbox-toggled', function(event, postbox) {
            var $postbox = $(postbox);
            if ('dashboard_primary' === $postbox.attr('id') && $postbox.is(':visible')) {
                if ($('#community-events').length) {
                    wp.communityEvents.init();
                }
                if ($('#global-events-section').length) {
                    wp.globalEvents.init();
                }
            }
        });
    }
});

/**
 * Removed in 5.6.0, needed for back-compatibility.
 *
 * @since 4.8.0
 * @deprecated 5.6.0
 *
 * @type {Object}
*/
window.communityEventsData.l10n = window.communityEventsData.l10n || {
	enter_closest_city: '',
	error_occurred_please_try_again: '',
	attend_event_near_generic: '',
	could_not_locate_city: '',
	city_updated: ''
};

window.communityEventsData.l10n = window.wp.deprecateL10nObject( 'communityEventsData.l10n', window.communityEventsData.l10n, '5.6.0' );
