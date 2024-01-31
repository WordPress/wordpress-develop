"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function OverlayColorSettings({
  overlayColor,
  customOverlayColor,
  gradient,
  customGradient,
  setAttributes
}) {
  const colors = (0, _components.useMobileGlobalStylesColors)();
  const gradients = (0, _components.useMobileGlobalStylesColors)('gradients');
  const gradientValue = customGradient || (0, _blockEditor.getGradientValueBySlug)(gradients, gradient);
  const colorValue = (0, _blockEditor.getColorObjectByAttributeValues)(colors, overlayColor, customOverlayColor).color;
  const settings = (0, _element.useMemo)(() => {
    const setOverlayAttribute = (attributeName, value) => {
      setAttributes({
        // Clear all related attributes (only one should be set)
        overlayColor: undefined,
        customOverlayColor: undefined,
        gradient: undefined,
        customGradient: undefined,
        [attributeName]: value
      });
    };
    const onColorChange = value => {
      // Do nothing for falsy values.
      if (!value) {
        return;
      }
      const colorObject = (0, _blockEditor.getColorObjectByColorValue)(colors, value);
      if (colorObject?.slug) {
        setOverlayAttribute('overlayColor', colorObject.slug);
      } else {
        setOverlayAttribute('customOverlayColor', value);
      }
    };
    const onGradientChange = value => {
      // Do nothing for falsy values.
      if (!value) {
        return;
      }
      const slug = (0, _blockEditor.getGradientSlugByValue)(gradients, value);
      if (slug) {
        setOverlayAttribute('gradient', slug);
      } else {
        setOverlayAttribute('customGradient', value);
      }
    };
    const onColorCleared = () => {
      setAttributes({
        overlayColor: undefined,
        customOverlayColor: undefined,
        gradient: undefined,
        customGradient: undefined
      });
    };
    return [{
      label: (0, _i18n.__)('Color'),
      onColorChange,
      colorValue,
      gradientValue,
      onGradientChange,
      onColorCleared
    }];
  }, [colorValue, gradientValue, colors, gradients]);
  return (0, _react.createElement)(_blockEditor.__experimentalPanelColorGradientSettings, {
    title: (0, _i18n.__)('Overlay'),
    initialOpen: false,
    settings: settings
  });
}
var _default = exports.default = OverlayColorSettings;
//# sourceMappingURL=overlay-color-settings.native.js.map