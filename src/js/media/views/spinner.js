/**
 * wp.media.view.Spinner
 *
 * Represents a spinner in the Media Library.
 *
 * @since 3.9.0
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
var Spinner = wp.media.View.extend(/** @lends wp.media.view.Spinner.prototype */{
	tagName:   'span',
	className: 'spinner',
	spinnerTimeout: false,
	delay: 400,

	/**
	 * Shows the spinner. Delays the visibility by the configured amount.
	 *
	 * @since 3.9.0
	 *
	 * @return {wp.media.view.Spinner} The spinner.
	 */
	show: function() {
		if ( ! this.spinnerTimeout ) {
			this.spinnerTimeout = _.delay(function( $el ) {
				$el.addClass( 'is-active' );
			}, this.delay, this.$el );
		}

		return this;
	},

	/**
	 * Hides the spinner.
	 *
	 * @since 3.9.0
	 *
	 * @return {wp.media.view.Spinner} The spinner.
	 */
	hide: function() {
		this.$el.removeClass( 'is-active' );
		this.spinnerTimeout = clearTimeout( this.spinnerTimeout );

		return this;
	}
});

module.exports = Spinner;
