/**
 * @output wp-includes/js/media-grid.js
 */

var media = wp.media;

media.controller.EditAttachmentMetadata = require( '../../../media/controllers/edit-attachment-metadata.js' );
media.view.MediaFrame.Manage = require( '../../../media/views/frame/manage.js' );
media.view.Attachment.Details.TwoColumn = require( '../../../media/views/attachment/details-two-column.js' );
media.view.MediaFrame.Manage.Router = require( '../../../media/routers/manage.js' );
media.view.EditImage.Details = require( '../../../media/views/edit-image-details.js' );
media.view.MediaFrame.EditAttachments = require( '../../../media/views/frame/edit-attachments.js' );
media.view.SelectModeToggleButton = require( '../../../media/views/button/select-mode-toggle.js' );
media.view.DeleteSelectedButton = require( '../../../media/views/button/delete-selected.js' );
media.view.DeleteSelectedPermanentlyButton = require( '../../../media/views/button/delete-selected-permanently.js' );
