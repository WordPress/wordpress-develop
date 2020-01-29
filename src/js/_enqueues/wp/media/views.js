/**
 * @output wp-includes/js/media-views.js
 */

var media = wp.media,
	$ = jQuery,
	l10n;

media.isTouchDevice = ( 'ontouchend' in document );

// Link any localized strings.
l10n = media.view.l10n = window._wpMediaViewsL10n || {};

// Link any settings.
media.view.settings = l10n.settings || {};
delete l10n.settings;

// Copy the `post` setting over to the model settings.
media.model.settings.post = media.view.settings.post;

// Check if the browser supports CSS 3.0 transitions.
$.support.transition = (function(){
	var style = document.documentElement.style,
		transitions = {
			WebkitTransition: 'webkitTransitionEnd',
			MozTransition:    'transitionend',
			OTransition:      'oTransitionEnd otransitionend',
			transition:       'transitionend'
		}, transition;

	transition = _.find( _.keys( transitions ), function( transition ) {
		return ! _.isUndefined( style[ transition ] );
	});

	return transition && {
		end: transitions[ transition ]
	};
}());

/**
 * A shared event bus used to provide events into
 * the media workflows that 3rd-party devs can use to hook
 * in.
 */
media.events = _.extend( {}, Backbone.Events );

/**
 * Makes it easier to bind events using transitions.
 *
 * @param {string} selector
 * @param {Number} sensitivity
 * @return {Promise}
 */
media.transition = function( selector, sensitivity ) {
	var deferred = $.Deferred();

	sensitivity = sensitivity || 2000;

	if ( $.support.transition ) {
		if ( ! (selector instanceof $) ) {
			selector = $( selector );
		}

		// Resolve the deferred when the first element finishes animating.
		selector.first().one( $.support.transition.end, deferred.resolve );

		// Just in case the event doesn't trigger, fire a callback.
		_.delay( deferred.resolve, sensitivity );

	// Otherwise, execute on the spot.
	} else {
		deferred.resolve();
	}

	return deferred.promise();
};

media.controller.Region = require( '../../../media/controllers/region.js' );
media.controller.StateMachine = require( '../../../media/controllers/state-machine.js' );
media.controller.State = require( '../../../media/controllers/state.js' );

media.selectionSync = require( '../../../media/utils/selection-sync.js' );
media.controller.Library = require( '../../../media/controllers/library.js' );
media.controller.ImageDetails = require( '../../../media/controllers/image-details.js' );
media.controller.GalleryEdit = require( '../../../media/controllers/gallery-edit.js' );
media.controller.GalleryAdd = require( '../../../media/controllers/gallery-add.js' );
media.controller.CollectionEdit = require( '../../../media/controllers/collection-edit.js' );
media.controller.CollectionAdd = require( '../../../media/controllers/collection-add.js' );
media.controller.FeaturedImage = require( '../../../media/controllers/featured-image.js' );
media.controller.ReplaceImage = require( '../../../media/controllers/replace-image.js' );
media.controller.EditImage = require( '../../../media/controllers/edit-image.js' );
media.controller.MediaLibrary = require( '../../../media/controllers/media-library.js' );
media.controller.Embed = require( '../../../media/controllers/embed.js' );
media.controller.Cropper = require( '../../../media/controllers/cropper.js' );
media.controller.CustomizeImageCropper = require( '../../../media/controllers/customize-image-cropper.js' );
media.controller.SiteIconCropper = require( '../../../media/controllers/site-icon-cropper.js' );

media.View = require( '../../../media/views/view.js' );
media.view.Frame = require( '../../../media/views/frame.js' );
media.view.MediaFrame = require( '../../../media/views/media-frame.js' );
media.view.MediaFrame.Select = require( '../../../media/views/frame/select.js' );
media.view.MediaFrame.Post = require( '../../../media/views/frame/post.js' );
media.view.MediaFrame.ImageDetails = require( '../../../media/views/frame/image-details.js' );
media.view.Modal = require( '../../../media/views/modal.js' );
media.view.FocusManager = require( '../../../media/views/focus-manager.js' );
media.view.UploaderWindow = require( '../../../media/views/uploader/window.js' );
media.view.EditorUploader = require( '../../../media/views/uploader/editor.js' );
media.view.UploaderInline = require( '../../../media/views/uploader/inline.js' );
media.view.UploaderStatus = require( '../../../media/views/uploader/status.js' );
media.view.UploaderStatusError = require( '../../../media/views/uploader/status-error.js' );
media.view.Toolbar = require( '../../../media/views/toolbar.js' );
media.view.Toolbar.Select = require( '../../../media/views/toolbar/select.js' );
media.view.Toolbar.Embed = require( '../../../media/views/toolbar/embed.js' );
media.view.Button = require( '../../../media/views/button.js' );
media.view.ButtonGroup = require( '../../../media/views/button-group.js' );
media.view.PriorityList = require( '../../../media/views/priority-list.js' );
media.view.MenuItem = require( '../../../media/views/menu-item.js' );
media.view.Menu = require( '../../../media/views/menu.js' );
media.view.RouterItem = require( '../../../media/views/router-item.js' );
media.view.Router = require( '../../../media/views/router.js' );
media.view.Sidebar = require( '../../../media/views/sidebar.js' );
media.view.Attachment = require( '../../../media/views/attachment.js' );
media.view.Attachment.Library = require( '../../../media/views/attachment/library.js' );
media.view.Attachment.EditLibrary = require( '../../../media/views/attachment/edit-library.js' );
media.view.Attachments = require( '../../../media/views/attachments.js' );
media.view.Search = require( '../../../media/views/search.js' );
media.view.AttachmentFilters = require( '../../../media/views/attachment-filters.js' );
media.view.DateFilter = require( '../../../media/views/attachment-filters/date.js' );
media.view.AttachmentFilters.Uploaded = require( '../../../media/views/attachment-filters/uploaded.js' );
media.view.AttachmentFilters.All = require( '../../../media/views/attachment-filters/all.js' );
media.view.AttachmentsBrowser = require( '../../../media/views/attachments/browser.js' );
media.view.Selection = require( '../../../media/views/selection.js' );
media.view.Attachment.Selection = require( '../../../media/views/attachment/selection.js' );
media.view.Attachments.Selection = require( '../../../media/views/attachments/selection.js' );
media.view.Attachment.EditSelection = require( '../../../media/views/attachment/edit-selection.js' );
media.view.Settings = require( '../../../media/views/settings.js' );
media.view.Settings.AttachmentDisplay = require( '../../../media/views/settings/attachment-display.js' );
media.view.Settings.Gallery = require( '../../../media/views/settings/gallery.js' );
media.view.Settings.Playlist = require( '../../../media/views/settings/playlist.js' );
media.view.Attachment.Details = require( '../../../media/views/attachment/details.js' );
media.view.AttachmentCompat = require( '../../../media/views/attachment-compat.js' );
media.view.Iframe = require( '../../../media/views/iframe.js' );
media.view.Embed = require( '../../../media/views/embed.js' );
media.view.Label = require( '../../../media/views/label.js' );
media.view.EmbedUrl = require( '../../../media/views/embed/url.js' );
media.view.EmbedLink = require( '../../../media/views/embed/link.js' );
media.view.EmbedImage = require( '../../../media/views/embed/image.js' );
media.view.ImageDetails = require( '../../../media/views/image-details.js' );
media.view.Cropper = require( '../../../media/views/cropper.js' );
media.view.SiteIconCropper = require( '../../../media/views/site-icon-cropper.js' );
media.view.SiteIconPreview = require( '../../../media/views/site-icon-preview.js' );
media.view.EditImage = require( '../../../media/views/edit-image.js' );
media.view.Spinner = require( '../../../media/views/spinner.js' );
media.view.Heading = require( '../../../media/views/heading.js' );
