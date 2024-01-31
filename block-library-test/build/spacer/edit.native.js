"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _controls = _interopRequireWildcard(require("./controls"));
var _editorNative = _interopRequireDefault(require("./editor.native.scss"));
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DEFAULT_FONT_SIZE = 16;
const Spacer = ({
  attributes,
  context,
  setAttributes,
  isSelected,
  getStylesFromColorScheme
}) => {
  const {
    height: screenHeight,
    width: screenWidth
  } = (0, _reactNative.useWindowDimensions)();
  const cssUnitOptions = {
    height: screenHeight,
    width: screenWidth,
    fontSize: DEFAULT_FONT_SIZE
  };
  const {
    height,
    width
  } = attributes;
  const spacingSizes = [{
    name: 0,
    slug: '0',
    size: 0
  }];
  const [settingsSizes] = (0, _blockEditor.useSettings)('spacing.spacingSizes');
  if (settingsSizes) {
    spacingSizes.push(...settingsSizes);
  }
  const {
    orientation
  } = context;
  const defaultStyle = getStylesFromColorScheme(_editorNative.default.staticSpacer, _editorNative.default.staticDarkSpacer);
  (0, _element.useEffect)(() => {
    if (orientation === 'horizontal' && !width) {
      setAttributes({
        height: '0px',
        width: '72px'
      });
    }
  }, []);
  let convertedHeight = (0, _components.useConvertUnitToMobile)(height);
  let convertedWidth = (0, _components.useConvertUnitToMobile)(width);
  const presetValues = {};
  if ((0, _blockEditor.isValueSpacingPreset)(height)) {
    const heightValue = (0, _blockEditor.getCustomValueFromPreset)(height, spacingSizes);
    const parsedPresetHeightValue = parseFloat((0, _components.getPxFromCssUnit)(heightValue, cssUnitOptions));
    convertedHeight = parsedPresetHeightValue || _controls.DEFAULT_VALUES.px;
    presetValues.presetHeight = convertedHeight;
  }
  if ((0, _blockEditor.isValueSpacingPreset)(width)) {
    const widthValue = (0, _blockEditor.getCustomValueFromPreset)(width, spacingSizes);
    const parsedPresetWidthValue = parseFloat((0, _components.getPxFromCssUnit)(widthValue, cssUnitOptions));
    convertedWidth = parsedPresetWidthValue || _controls.DEFAULT_VALUES.px;
    presetValues.presetWidth = convertedWidth;
  }
  return (0, _react.createElement)(_reactNative.View, {
    style: [defaultStyle, isSelected && _editorNative.default.selectedSpacer, {
      height: convertedHeight,
      width: convertedWidth
    }]
  }, isSelected && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_controls.default, {
    attributes: attributes,
    context: context,
    setAttributes: setAttributes,
    ...presetValues
  })));
};
var _default = exports.default = (0, _compose.withPreferredColorScheme)(Spacer);
//# sourceMappingURL=edit.native.js.map