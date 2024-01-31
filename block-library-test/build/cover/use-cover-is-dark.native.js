"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useCoverIsDark;
var _colord = require("colord");
var _element = require("@wordpress/element");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * useCoverIsDark is a hook that returns a boolean variable specifying if the cover
 * background is dark or not.
 *
 * @param {?boolean} initialValue Initial value.
 * @param {?string}  url          Url of the media background.
 * @param {?number}  dimRatio     Transparency of the overlay color. If an image and
 *                                color are set, dimRatio is used to decide what is used
 *                                for background darkness checking purposes.
 * @param {?string}  overlayColor String containing the overlay color value if one exists.
 *
 * @return {boolean} True if the cover background is considered "dark" and false otherwise.
 */
function useCoverIsDark(initialValue = false, url, dimRatio = 50, overlayColor) {
  const [isDark, setIsDark] = (0, _element.useState)(initialValue);
  (0, _element.useEffect)(() => {
    // If opacity is greater than 50 the dominant color is the overlay color,
    // so use that color for the dark mode computation.
    if (dimRatio > 50 || !url) {
      if (!overlayColor) {
        // If no overlay color exists the overlay color is black (isDark )
        setIsDark(true);
        return;
      }
      setIsDark((0, _colord.colord)(overlayColor).isDark());
    }
  }, [overlayColor, dimRatio > 50 || !url, setIsDark]);
  (0, _element.useEffect)(() => {
    if (!url && !overlayColor) {
      // Reset isDark.
      setIsDark(false);
    }
  }, [!url && !overlayColor, setIsDark]);
  return isDark;
}
//# sourceMappingURL=use-cover-is-dark.native.js.map