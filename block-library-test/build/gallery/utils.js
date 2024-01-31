"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.getHrefAndDestination = getHrefAndDestination;
var _constants = require("./constants");
var _constants2 = require("../image/constants");
/**
 * Internal dependencies
 */

/**
 * Determines new href and linkDestination values for an Image block from the
 * supplied Gallery link destination, or falls back to the Image blocks link.
 *
 * @param {Object} image              Gallery image.
 * @param {string} galleryDestination Gallery's selected link destination.
 * @param {Object} imageDestination   Image blocks attributes.
 * @return {Object}            New attributes to assign to image block.
 */
function getHrefAndDestination(image, galleryDestination, imageDestination) {
  // Gutenberg and WordPress use different constants so if image_default_link_type
  // option is set we need to map from the WP Core values.
  switch (imageDestination ? imageDestination : galleryDestination) {
    case _constants.LINK_DESTINATION_MEDIA_WP_CORE:
    case _constants.LINK_DESTINATION_MEDIA:
      return {
        href: image?.source_url || image?.url,
        // eslint-disable-line camelcase
        linkDestination: _constants2.LINK_DESTINATION_MEDIA
      };
    case _constants.LINK_DESTINATION_ATTACHMENT_WP_CORE:
    case _constants.LINK_DESTINATION_ATTACHMENT:
      return {
        href: image?.link,
        linkDestination: _constants2.LINK_DESTINATION_ATTACHMENT
      };
    case _constants.LINK_DESTINATION_NONE:
      return {
        href: undefined,
        linkDestination: _constants2.LINK_DESTINATION_NONE
      };
  }
  return {};
}
//# sourceMappingURL=utils.js.map