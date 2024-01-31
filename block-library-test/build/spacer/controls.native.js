"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.DEFAULT_VALUES = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _constants = require("./constants");
var _style = _interopRequireDefault(require("./style.scss"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DEFAULT_VALUES = exports.DEFAULT_VALUES = {
  px: 100,
  em: 10,
  rem: 10,
  vw: 10,
  vh: 25
};
function Controls({
  attributes,
  context,
  setAttributes,
  presetWidth,
  presetHeight
}) {
  const {
    orientation
  } = context;
  const label = orientation !== 'horizontal' ? (0, _i18n.__)('Height') : (0, _i18n.__)('Width');
  const width = presetWidth || attributes.width;
  const height = presetHeight || attributes.height;
  const {
    valueToConvert,
    valueUnit: unit
  } = (0, _components.getValueAndUnit)(orientation !== 'horizontal' ? height : width) || {};
  const value = Number(valueToConvert);
  const currentUnit = unit || 'px';
  const setNewDimensions = (nextValue, nextUnit) => {
    const valueWithUnit = `${nextValue}${nextUnit}`;
    if (orientation === 'horizontal') {
      setAttributes({
        width: valueWithUnit
      });
    } else {
      setAttributes({
        height: valueWithUnit
      });
    }
  };
  const handleChange = (0, _element.useCallback)(nextValue => {
    setNewDimensions(nextValue, currentUnit);
  }, [height, width]);
  const handleUnitChange = (0, _element.useCallback)(nextUnit => {
    setNewDimensions(value, nextUnit);
  }, [height, width]);
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['px', 'em', 'rem', 'vw', 'vh'],
    defaultValues: DEFAULT_VALUES
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Dimensions')
  }, (0, _react.createElement)(_components.UnitControl, {
    label: label,
    min: _constants.MIN_SPACER_SIZE,
    value: value,
    onChange: handleChange,
    onUnitChange: handleUnitChange,
    units: units,
    unit: currentUnit,
    style: _style.default.rangeCellContainer
  })));
}
var _default = exports.default = Controls;
//# sourceMappingURL=controls.native.js.map