/**
 * Tooltip functionality for WordPress.
 *
 * @version 6.7.0
 * @output wp-includes/js/wp-tooltip.js
 */

document.addEventListener( 'DOMContentLoaded', function () {
	var tooltipContainers = document.querySelectorAll(
		'.wp-tooltip-container'
	);

	tooltipContainers.forEach( function ( tooltipContainer ) {
		var tooltipButton =
			tooltipContainer.querySelector( '.wp-tooltip-button' );
		var tooltipContent = tooltipContainer.querySelector(
			'.wp-tooltip-content'
		);

		function showTooltip() {
			tooltipContent.style.display = 'block';
			adjustTooltipPosition( tooltipContainer, tooltipContent );
		}

		function hideTooltip() {
			tooltipContent.style.display = 'none';
		}

		// Event listeners for mouse and touch events
		tooltipContainer.addEventListener( 'mouseenter', showTooltip );
		tooltipContainer.addEventListener( 'mouseleave', hideTooltip );
		tooltipButton.addEventListener( 'touchstart', showTooltip );
		tooltipButton.addEventListener( 'touchend', hideTooltip );

		tooltipButton.style.cursor = 'help';

		tooltipButton.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Enter' ) {
				// Toggle the display of the tooltip content.
				tooltipContent.style.display =
					tooltipContent.style.display === 'block' ? 'none' : 'block';
				if ( tooltipContent.style.display === 'block' ) {
					adjustTooltipPosition( tooltipContainer, tooltipContent );
				}
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if (
				event.key === 'Escape' &&
				tooltipContent.style.display === 'block'
			) {
				// Hide the tooltip on Escape key press.
				tooltipContent.style.display = 'none';
				tooltipButton.focus();
			}
		} );

		document.body.addEventListener( 'click', function ( event ) {
			// Check if the clicked element is not within the tooltip container.
			if (
				! tooltipContent.contains( event.target ) &&
				! tooltipButton.contains( event.target )
			) {
				tooltipContent.style.display = 'none';
			}
		} );

		tooltipContainer.addEventListener( 'focusout', function ( event ) {
			if ( ! tooltipContainer.contains( event.relatedTarget ) ) {
				hideTooltip();
			}
		} );
	} );

	// Function to adjust tooltip position based on screen availability
	function adjustTooltipPosition( container, content ) {
		var containerRect = container.getBoundingClientRect();
		var contentRect = content.getBoundingClientRect();
		var viewportWidth = window.innerWidth;
		var viewportHeight = window.innerHeight;

		// Check if there's enough space in each direction
		var fitsAbove = containerRect.top >= contentRect.height;
		var fitsBelow =
			viewportHeight - containerRect.bottom >= contentRect.height;
		var fitsLeft = containerRect.left >= contentRect.width;
		var fitsRight =
			viewportWidth - containerRect.right >= contentRect.width;

		var defaultPosition = 'right';

		if ( container.classList.contains( 'position-top' ) ) {
			defaultPosition = 'top';
		} else if ( container.classList.contains( 'position-bottom' ) ) {
			defaultPosition = 'bottom';
		} else if ( container.classList.contains( 'position-left' ) ) {
			defaultPosition = 'left';
		}

		var newPosition = defaultPosition;

		if ( defaultPosition === 'top' && ! fitsAbove && fitsBelow ) {
			newPosition = 'bottom';
		} else if ( defaultPosition === 'bottom' && ! fitsBelow && fitsAbove ) {
			newPosition = 'top';
		} else if ( defaultPosition === 'left' && ! fitsLeft && fitsRight ) {
			newPosition = 'right';
		} else if ( defaultPosition === 'right' && ! fitsRight && fitsLeft ) {
			newPosition = 'left';
		}

		// Apply position adjustments
		container.classList.remove(
			'adjusted-position-top',
			'adjusted-position-bottom',
			'adjusted-position-left',
			'adjusted-position-right'
		);

		// Add the new position class
		container.classList.add( 'adjusted-position-' + newPosition );
	}
} );
