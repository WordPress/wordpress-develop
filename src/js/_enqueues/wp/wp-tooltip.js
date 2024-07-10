/**
 * Tooltip functionality for WordPress.
 *
 * @version 6.5.0
 * @output wp-includes/js/wp-tooltip.js
 */

document.addEventListener('DOMContentLoaded', function () {
    // Select all tooltip containers on the page
    var tooltipContainers = document.querySelectorAll('.wp-tooltip-container');

    tooltipContainers.forEach(function (tooltipContainer) {
        var tooltipButton = tooltipContainer.querySelector('.wp-tooltip-button');
        var tooltipContent = tooltipContainer.querySelector('.wp-tooltip-content');

        tooltipButton.addEventListener('click', function () {
            // Toggle the display of the tooltip content
            tooltipContent.style.display = tooltipContent.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && tooltipContent.style.display === 'block') {
                // Hide the tooltip on Escape key press
                tooltipContent.style.display = 'none';
                tooltipButton.focus();
            }
        });

        document.body.addEventListener('click', function (event) {
            // Check if the clicked element is not within the tooltip container
            if (!tooltipContent.contains(event.target) && !tooltipButton.contains(event.target)) {
                tooltipContent.style.display = 'none';
            }
        });
    });
});
