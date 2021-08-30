var Details = wp.media.view.Attachment.Details,
	TwoColumn;

/**
 * wp.media.view.Attachment.Details.TwoColumn
 *
 * A similar view to media.view.Attachment.Details
 * for use in the Edit Attachment modal.
 *
 * @memberOf wp.media.view.Attachment.Details
 *
 * @class
 * @augments wp.media.view.Attachment.Details
 * @augments wp.media.view.Attachment
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
TwoColumn = Details.extend(/** @lends wp.media.view.Attachment.Details.TowColumn.prototype */{
	template: wp.template( 'attachment-details-two-column' ),

	initialize: function() {
		this.controller.on( 'content:activate:edit-details', _.bind( this.editAttachment, this ) );

		Details.prototype.initialize.apply( this, arguments );
	},

	editAttachment: function( event ) {
		if ( event ) {
			event.preventDefault();
		}
		this.controller.content.mode( 'edit-image' );
	},

	/**
	 * Noop this from parent class, doesn't apply here.
	 */
	toggleSelectionHandler: function() {}

});

module.exports = TwoColumn;
