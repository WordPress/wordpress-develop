"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.DEFAULT_OVERLAY_COLOR = exports.DEFAULT_BACKGROUND_COLOR = void 0;
exports.compositeIsDark = compositeIsDark;
exports.compositeSourceOver = compositeSourceOver;
exports.getMediaColor = void 0;
exports.retrieveFastAverageColor = retrieveFastAverageColor;
var _colord = require("colord");
var _names = _interopRequireDefault(require("colord/plugins/names"));
var _fastAverageColor = require("fast-average-color");
var _memize = _interopRequireDefault(require("memize"));
var _hooks = require("@wordpress/hooks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * @typedef {import('colord').RgbaColor} RgbaColor
 */

(0, _colord.extend)([_names.default]);

/**
 * Fallback color when the average color can't be computed. The image may be
 * rendering as transparent, and most sites have a light color background.
 */
const DEFAULT_BACKGROUND_COLOR = exports.DEFAULT_BACKGROUND_COLOR = '#FFF';

/**
 * Default dim color specified in style.css.
 */
const DEFAULT_OVERLAY_COLOR = exports.DEFAULT_OVERLAY_COLOR = '#000';

/**
 * Performs a Porter Duff composite source over operation on two rgba colors.
 *
 * @see {@link https://www.w3.org/TR/compositing-1/#porterduffcompositingoperators_srcover}
 *
 * @param {RgbaColor} source Source color.
 * @param {RgbaColor} dest   Destination color.
 *
 * @return {RgbaColor} Composite color.
 */
function compositeSourceOver(source, dest) {
  return {
    r: source.r * source.a + dest.r * dest.a * (1 - source.a),
    g: source.g * source.a + dest.g * dest.a * (1 - source.a),
    b: source.b * source.a + dest.b * dest.a * (1 - source.a),
    a: source.a + dest.a * (1 - source.a)
  };
}

/**
 * Retrieves the FastAverageColor singleton.
 *
 * @return {FastAverageColor} The FastAverageColor singleton.
 */
function retrieveFastAverageColor() {
  if (!retrieveFastAverageColor.fastAverageColor) {
    retrieveFastAverageColor.fastAverageColor = new _fastAverageColor.FastAverageColor();
  }
  return retrieveFastAverageColor.fastAverageColor;
}

/**
 * Computes the average color of an image.
 *
 * @param {string} url The url of the image.
 *
 * @return {Promise<string>} Promise of an average color as a hex string.
 */
const getMediaColor = exports.getMediaColor = (0, _memize.default)(async url => {
  if (!url) {
    return DEFAULT_BACKGROUND_COLOR;
  }

  // making the default color rgb for compat with FAC
  const {
    r,
    g,
    b,
    a
  } = (0, _colord.colord)(DEFAULT_BACKGROUND_COLOR).toRgb();
  try {
    const imgCrossOrigin = (0, _hooks.applyFilters)('media.crossOrigin', undefined, url);
    const color = await retrieveFastAverageColor().getColorAsync(url, {
      // The default color is white, which is the color
      // that is returned if there's an error.
      // colord returns alpga 0-1, FAC needs 0-255
      defaultColor: [r, g, b, a * 255],
      // Errors that come up don't reject the promise,
      // so error logging has to be silenced
      // with this option.
      silent: process.env.NODE_ENV === 'production',
      crossOrigin: imgCrossOrigin
    });
    return color.hex;
  } catch (error) {
    // If there's an error return the fallback color.
    return DEFAULT_BACKGROUND_COLOR;
  }
});

/**
 * Computes if the color combination of the overlay and background color is dark.
 *
 * @param {number} dimRatio        Opacity of the overlay between 0 and 100.
 * @param {string} overlayColor    CSS color string for the overlay.
 * @param {string} backgroundColor CSS color string for the background.
 *
 * @return {boolean} true if the color combination composite result is dark.
 */
function compositeIsDark(dimRatio, overlayColor, backgroundColor) {
  // Opacity doesn't matter if you're overlaying the same color on top of itself.
  // And background doesn't matter when overlay is fully opaque.
  if (overlayColor === backgroundColor || dimRatio === 100) {
    return (0, _colord.colord)(overlayColor).isDark();
  }
  const overlay = (0, _colord.colord)(overlayColor).alpha(dimRatio / 100).toRgb();
  const background = (0, _colord.colord)(backgroundColor).toRgb();
  const composite = compositeSourceOver(overlay, background);
  return (0, _colord.colord)(composite).isDark();
}
//# sourceMappingURL=color-utils.js.map