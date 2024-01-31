"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _utils = require("./utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const Overlay = ({
  clientId,
  attributes,
  setAttributes,
  overlayColor,
  setOverlayColor
}) => {
  const {
    dimRatio
  } = attributes;
  const {
    gradientClass,
    gradientValue,
    setGradient
  } = (0, _blockEditor.__experimentalUseGradient)();
  const colorGradientSettings = (0, _blockEditor.__experimentalUseMultipleOriginColorsAndGradients)();
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const overlayStyles = {
    backgroundColor: overlayColor.color,
    backgroundImage: gradientValue,
    ...borderProps.style
  };
  if (!colorGradientSettings.hasColorsOrGradients) {
    return null;
  }
  return (0, _react.createElement)(_react.Fragment, null, !!dimRatio && (0, _react.createElement)("span", {
    "aria-hidden": "true",
    className: (0, _classnames.default)('wp-block-post-featured-image__overlay', (0, _utils.dimRatioToClass)(dimRatio), {
      [overlayColor.class]: overlayColor.class,
      'has-background-dim': dimRatio !== undefined,
      'has-background-gradient': gradientValue,
      [gradientClass]: gradientClass
    }, borderProps.className),
    style: overlayStyles
  }), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "color"
  }, (0, _react.createElement)(_blockEditor.__experimentalColorGradientSettingsDropdown, {
    __experimentalIsRenderedInSidebar: true,
    settings: [{
      colorValue: overlayColor.color,
      gradientValue,
      label: (0, _i18n.__)('Overlay'),
      onColorChange: setOverlayColor,
      onGradientChange: setGradient,
      isShownByDefault: true,
      resetAllFilter: () => ({
        overlayColor: undefined,
        customOverlayColor: undefined,
        gradient: undefined,
        customGradient: undefined
      })
    }],
    panelId: clientId,
    ...colorGradientSettings
  }), (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => dimRatio !== undefined,
    label: (0, _i18n.__)('Overlay opacity'),
    onDeselect: () => setAttributes({
      dimRatio: 0
    }),
    resetAllFilter: () => ({
      dimRatio: 0
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Overlay opacity'),
    value: dimRatio,
    onChange: newDimRatio => setAttributes({
      dimRatio: newDimRatio
    }),
    min: 0,
    max: 100,
    step: 10,
    required: true,
    __next40pxDefaultSize: true
  }))));
};
var _default = exports.default = (0, _compose.compose)([(0, _blockEditor.withColors)({
  overlayColor: 'background-color'
})])(Overlay);
//# sourceMappingURL=overlay.js.map