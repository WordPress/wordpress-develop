"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.defaultColumnsNumber = defaultColumnsNumber;
exports.isGalleryV2Enabled = isGalleryV2Enabled;
exports.pickRelevantMediaFiles = void 0;
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

function defaultColumnsNumber(imageCount) {
  return imageCount ? Math.min(3, imageCount) : 3;
}
const pickRelevantMediaFiles = (image, sizeSlug = 'large') => {
  const imageProps = Object.fromEntries(Object.entries(image !== null && image !== void 0 ? image : {}).filter(([key]) => ['alt', 'id', 'link'].includes(key)));
  imageProps.url = image?.sizes?.[sizeSlug]?.url || image?.media_details?.sizes?.[sizeSlug]?.source_url || image?.url || image?.source_url;
  const fullUrl = image?.sizes?.full?.url || image?.media_details?.sizes?.full?.source_url;
  if (fullUrl) {
    imageProps.fullUrl = fullUrl;
  }
  return imageProps;
};
exports.pickRelevantMediaFiles = pickRelevantMediaFiles;
function getGalleryBlockV2Enabled() {
  // We want to fail early here, at least during beta testing phase, to ensure
  // there aren't instances where undefined values cause false negatives.
  if (!window.wp || typeof window.wp.galleryBlockV2Enabled !== 'boolean') {
    throw 'window.wp.galleryBlockV2Enabled is not defined';
  }
  return window.wp.galleryBlockV2Enabled;
}

/**
 * The new gallery block format is not compatible with the use_BalanceTags option
 * in WP versions <= 5.8 https://core.trac.wordpress.org/ticket/54130. The
 * window.wp.galleryBlockV2Enabled flag is set in lib/compat.php. This method
 * can be removed when minimum supported WP version >=5.9.
 */
function isGalleryV2Enabled() {
  if (_element.Platform.isNative) {
    return getGalleryBlockV2Enabled();
  }
  return true;
}
//# sourceMappingURL=shared.js.map