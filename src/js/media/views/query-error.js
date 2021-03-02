/**
 * wp.media.view.AttachmentQueryError
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
var AttachmentQueryError = wp.media.View.extend(/** @lends wp.media.view.AttachmentQueryError.prototype */{
	className: 'upload-error',
	template: wp.template( 'attachment-query-error' )
});

module.exports = AttachmentQueryError;
