var Controller = wp.media.controller,
	CustomizeImageCropper;

/**
 * A state for cropping an image in the customizer.
 *
 * @since 4.3.0
 *
 * @constructs wp.media.controller.CustomizeImageCropper
 * @memberOf wp.media.controller
 * @augments wp.media.controller.CustomizeImageCropper.Cropper
 * @inheritDoc
 */
CustomizeImageCropper = Controller.Cropper.extend(/** @lends wp.media.controller.CustomizeImageCropper.prototype */{
	/**
	 * Posts the crop details to the admin.
	 *
	 * Uses crop measurements when flexible in both directions.
	 * Constrains flexible side based on image ratio and size of the fixed side.
	 *
	 * @since 4.3.0
	 *
	 * @param {Object} attachment The attachment to crop.
	 *
	 * @return {$.promise} A jQuery promise that represents the crop image request.
	 */
	doCrop: function( attachment ) {
		var cropDetails = attachment.get( 'cropDetails' ),
			control = this.get( 'control' ),
			ratio = cropDetails.width / cropDetails.height;

		// Use crop measurements when flexible in both directions.
		if ( control.params.flex_width && control.params.flex_height ) {
			cropDetails.dst_width  = cropDetails.width;
			cropDetails.dst_height = cropDetails.height;

		// Constrain flexible side based on image ratio and size of the fixed side.
		} else {
			cropDetails.dst_width  = control.params.flex_width  ? control.params.height * ratio : control.params.width;
			cropDetails.dst_height = control.params.flex_height ? control.params.width  / ratio : control.params.height;
		}

		return wp.ajax.post( 'crop-image', {
			wp_customize: 'on',
			nonce: attachment.get( 'nonces' ).edit,
			id: attachment.get( 'id' ),
			context: control.id,
			cropDetails: cropDetails
		} );
	}
});

module.exports = CustomizeImageCropper;
