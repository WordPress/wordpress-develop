"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useDeprecatedOpacity;
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
/**
 * WordPress dependencies
 */

function useDeprecatedOpacity(opacity, currentColor, setAttributes) {
  const [deprecatedOpacityWithNoColor, setDeprecatedOpacityWithNoColor] = (0, _element.useState)(false);
  const previousColor = (0, _compose.usePrevious)(currentColor);

  // A separator with no color set will always have previousColor set to undefined,
  // and we need to differentiate these from those with color set that will return
  // previousColor as undefined on the first render.
  (0, _element.useEffect)(() => {
    if (opacity === 'css' && !currentColor && !previousColor) {
      setDeprecatedOpacityWithNoColor(true);
    }
  }, [currentColor, previousColor, opacity]);

  // For deprecated blocks, that have a default 0.4 css opacity set, we
  // need to remove this if the current color is changed, or a color is added.
  // In these instances the opacity attribute is set back to the default of
  // alpha-channel which allows a new custom opacity to be set via the color picker.
  (0, _element.useEffect)(() => {
    if (opacity === 'css' && (deprecatedOpacityWithNoColor && currentColor || previousColor && currentColor !== previousColor)) {
      setAttributes({
        opacity: 'alpha-channel'
      });
      setDeprecatedOpacityWithNoColor(false);
    }
  }, [deprecatedOpacityWithNoColor, currentColor, previousColor]);
}
//# sourceMappingURL=use-deprecated-opacity.js.map