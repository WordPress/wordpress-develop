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

		// Default position is top
		var newPosition = 'top';

		// Choose position based on available space
		if ( ! fitsAbove && fitsBelow ) {
			newPosition = 'bottom';
		} else if ( ! fitsBelow && fitsAbove ) {
			newPosition = 'top';
		} else if ( ! fitsLeft && fitsRight ) {
			newPosition = 'right';
		} else if ( ! fitsRight && fitsLeft ) {
			newPosition = 'left';
		}

		// Apply position adjustments
		if ( newPosition === 'top' ) {
			content.style.top = 'auto';
			content.style.bottom = '100%';
			content.style.left = '50%';
			content.style.transform = 'translateX(-50%)';
		} else if ( newPosition === 'bottom' ) {
			content.style.top = '100%';
			content.style.bottom = 'auto';
			content.style.left = '50%';
			content.style.transform = 'translateX(-50%)';
		} else if ( newPosition === 'left' ) {
			content.style.top = '50%';
			content.style.bottom = 'auto';
			content.style.left = 'auto';
			content.style.right = '100%';
			content.style.transform = 'translateY(-50%)';
		} else if ( newPosition === 'right' ) {
			content.style.top = '50%';
			content.style.bottom = 'auto';
			content.style.left = '100%';
			content.style.right = 'auto';
			content.style.transform = 'translateY(-50%)';
		}
	}
} );
