/**
 * @output wp-admin/js/wp-tooltip.js
 */

/**
 * Add focus and hover support for the 'tooltip' type in `wp_tooltip()`.
 * This script can be made obsolete when support is available for Interest Invokers.
 */
(() => {

	const popovers = document.querySelectorAll( '.wp-is-tooltip' );
	let openTimeout;

	popovers.forEach( function( popover ) {
		let trigger = popover.querySelector( 'button.wp-tooltip__toggle' );
		let panel   = popover.querySelector( 'span.wp-tooltip__bubble' );

		// Show Tooltip Function (with delay to prevent flickering).
		const showTooltip = () => {
			clearTimeout( openTimeout );
			openTimeout = setTimeout( () => {
				// Only show if it's not already open.
				if ( ! panel.matches( ':popover-open' ) ) {
					// pass the triggering element so implicit position anchors work.
					panel.showPopover( { source: trigger } );
				}
			}, 300 );
		};
		// Hide Tooltip Function.
		const hideTooltip = () => {
			clearTimeout( openTimeout );
			if ( panel.matches( ':popover-open' ) ) {
				panel.hidePopover();
			}
		};

		// Bind Hover and Focus Events.
		trigger.addEventListener( 'mouseenter', showTooltip );
		trigger.addEventListener( 'focus', showTooltip );

		trigger.addEventListener( 'mouseleave', hideTooltip );
		trigger.addEventListener( 'blur', hideTooltip );
	});
})();

/**
 * Add focus and hover support for the toggle tip's hint in `wp_get_toggletip()`.
 *
 * A toggle tip has no visible text label, so a hover/focus hint exposes the
 * toggle button's accessible name to sighted users. The hint is suppressed while
 * the toggle tip dialog itself is open, so the two never overlap.
 * This script can be made obsolete when support is available for Interest Invokers.
 */
(() => {

	const toggletips = document.querySelectorAll( '.wp-is-toggletip' );
	let openTimeout;

	toggletips.forEach( function( toggletip ) {
		let trigger = toggletip.querySelector( 'button.wp-tooltip__toggle' );
		let hint    = toggletip.querySelector( '.wp-tooltip__hint' );
		let dialog  = toggletip.querySelector( 'dialog.wp-tooltip__bubble' );

		if ( ! trigger || ! hint ) {
			return;
		}

		// Show Hint Function (with delay to prevent flickering).
		const showHint = () => {
			clearTimeout( openTimeout );
			openTimeout = setTimeout( () => {
				// Don't show the hint over an open toggle tip dialog.
				if ( dialog && dialog.matches( ':popover-open' ) ) {
					return;
				}
				// Only show if it's not already open.
				if ( ! hint.matches( ':popover-open' ) ) {
					// pass the triggering element so implicit position anchors work.
					hint.showPopover( { source: trigger } );
				}
			}, 300 );
		};
		// Hide Hint Function.
		const hideHint = () => {
			clearTimeout( openTimeout );
			if ( hint.matches( ':popover-open' ) ) {
				hint.hidePopover();
			}
		};

		// Bind Hover and Focus Events.
		trigger.addEventListener( 'mouseenter', showHint );
		trigger.addEventListener( 'focus', showHint );

		trigger.addEventListener( 'mouseleave', hideHint );
		trigger.addEventListener( 'blur', hideHint );

		// Hide the hint as soon as the toggle tip is opened.
		trigger.addEventListener( 'click', hideHint );
	});
})();
