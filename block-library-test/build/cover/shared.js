"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.VIDEO_BACKGROUND_TYPE = exports.IMAGE_BACKGROUND_TYPE = exports.DEFAULT_FOCAL_POINT = exports.COVER_MIN_HEIGHT = exports.COVER_MAX_HEIGHT = exports.COVER_DEFAULT_HEIGHT = exports.ALLOWED_MEDIA_TYPES = void 0;
exports.attributesFromMedia = attributesFromMedia;
exports.dimRatioToClass = dimRatioToClass;
exports.getPositionClassName = getPositionClassName;
exports.isContentPositionCenter = isContentPositionCenter;
exports.mediaPosition = mediaPosition;
var _blob = require("@wordpress/blob");
/**
 * WordPress dependencies
 */

const POSITION_CLASSNAMES = {
  'top left': 'is-position-top-left',
  'top center': 'is-position-top-center',
  'top right': 'is-position-top-right',
  'center left': 'is-position-center-left',
  'center center': 'is-position-center-center',
  center: 'is-position-center-center',
  'center right': 'is-position-center-right',
  'bottom left': 'is-position-bottom-left',
  'bottom center': 'is-position-bottom-center',
  'bottom right': 'is-position-bottom-right'
};
const IMAGE_BACKGROUND_TYPE = exports.IMAGE_BACKGROUND_TYPE = 'image';
const VIDEO_BACKGROUND_TYPE = exports.VIDEO_BACKGROUND_TYPE = 'video';
const COVER_MIN_HEIGHT = exports.COVER_MIN_HEIGHT = 50;
const COVER_MAX_HEIGHT = exports.COVER_MAX_HEIGHT = 1000;
const COVER_DEFAULT_HEIGHT = exports.COVER_DEFAULT_HEIGHT = 300;
const DEFAULT_FOCAL_POINT = exports.DEFAULT_FOCAL_POINT = {
  x: 0.5,
  y: 0.5
};
const ALLOWED_MEDIA_TYPES = exports.ALLOWED_MEDIA_TYPES = ['image', 'video'];
function mediaPosition({
  x,
  y
} = DEFAULT_FOCAL_POINT) {
  return `${Math.round(x * 100)}% ${Math.round(y * 100)}%`;
}
function dimRatioToClass(ratio) {
  return ratio === 50 || ratio === undefined ? null : 'has-background-dim-' + 10 * Math.round(ratio / 10);
}
function attributesFromMedia(media) {
  if (!media || !media.url) {
    return {
      url: undefined,
      id: undefined
    };
  }
  if ((0, _blob.isBlobURL)(media.url)) {
    media.type = (0, _blob.getBlobTypeByURL)(media.url);
  }
  let mediaType;
  // For media selections originated from a file upload.
  if (media.media_type) {
    if (media.media_type === IMAGE_BACKGROUND_TYPE) {
      mediaType = IMAGE_BACKGROUND_TYPE;
    } else {
      // only images and videos are accepted so if the media_type is not an image we can assume it is a video.
      // Videos contain the media type of 'file' in the object returned from the rest api.
      mediaType = VIDEO_BACKGROUND_TYPE;
    }
  } else {
    // For media selections originated from existing files in the media library.
    if (media.type !== IMAGE_BACKGROUND_TYPE && media.type !== VIDEO_BACKGROUND_TYPE) {
      return;
    }
    mediaType = media.type;
  }
  return {
    url: media.url,
    id: media.id,
    alt: media?.alt,
    backgroundType: mediaType,
    ...(mediaType === VIDEO_BACKGROUND_TYPE ? {
      hasParallax: undefined
    } : {})
  };
}

/**
 * Checks of the contentPosition is the center (default) position.
 *
 * @param {string} contentPosition The current content position.
 * @return {boolean} Whether the contentPosition is center.
 */
function isContentPositionCenter(contentPosition) {
  return !contentPosition || contentPosition === 'center center' || contentPosition === 'center';
}

/**
 * Retrieves the className for the current contentPosition.
 * The default position (center) will not have a className.
 *
 * @param {string} contentPosition The current content position.
 * @return {string} The className assigned to the contentPosition.
 */
function getPositionClassName(contentPosition) {
  /*
   * Only render a className if the contentPosition is not center (the default).
   */
  if (isContentPositionCenter(contentPosition)) return '';
  return POSITION_CLASSNAMES[contentPosition];
}
//# sourceMappingURL=shared.js.map