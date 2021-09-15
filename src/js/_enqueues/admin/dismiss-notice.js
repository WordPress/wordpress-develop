/**
 * @output wp-admin/js/dismiss-notice.js
 *
 * @since 5.9.0
 * @see https://github.com/w3guy/persist-admin-notices-dismissal
 */

(function ($) {
	//shorthand for ready event.
	$(
		function () {
			$('div[data-dismissible] button.notice-dismiss, div[data-dismissible] .dismiss-this').on('click',
				function (event) {
					event.preventDefault();
					var $this = $(this);

					var attr_value, option_name, dismissible_length, dismissible_notice, data;

					attr_value = $this.closest('div[data-dismissible]').attr('data-dismissible').split('-');

					// remove the dismissible length from the attribute value and rejoin the array.
					dismissible_length = attr_value.pop();

					option_name = attr_value.join('-');

					data = {
						'action': 'dismiss_admin_notice',
						'option_name': option_name,
						'dismissible_length': dismissible_length,
						'nonce': dismissible_notice.nonce
					};

					$this.closest('div[data-dismissible]').hide('slow');
				}
			);
		}
	);

}(jQuery));
