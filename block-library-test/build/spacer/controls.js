"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SpacerControls;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _primitives = require("@wordpress/primitives");
var _constants = require("./constants");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function DimensionInput({
  label,
  onChange,
  isResizing,
  value = ''
}) {
  const inputId = (0, _compose.useInstanceId)(_components.__experimentalUnitControl, 'block-spacer-height-input');
  const [spacingSizes, spacingUnits] = (0, _blockEditor.useSettings)('spacing.spacingSizes', 'spacing.units');
  // In most contexts the spacer size cannot meaningfully be set to a
  // percentage, since this is relative to the parent container. This
  // unit is disabled from the UI.
  const availableUnits = spacingUnits ? spacingUnits.filter(unit => unit !== '%') : ['px', 'em', 'rem', 'vw', 'vh'];
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits,
    defaultValues: {
      px: 100,
      em: 10,
      rem: 10,
      vw: 10,
      vh: 25
    }
  });
  const handleOnChange = unprocessedValue => {
    onChange(unprocessedValue.all);
  };

  // Force the unit to update to `px` when the Spacer is being resized.
  const [parsedQuantity, parsedUnit] = (0, _components.__experimentalParseQuantityAndUnitFromRawValue)(value);
  const computedValue = (0, _blockEditor.isValueSpacingPreset)(value) ? value : [parsedQuantity, isResizing ? 'px' : parsedUnit].join('');
  return (0, _react.createElement)(_react.Fragment, null, (!spacingSizes || spacingSizes?.length === 0) && (0, _react.createElement)(_components.BaseControl, {
    label: label,
    id: inputId
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    id: inputId,
    isResetValueOnUnitChange: true,
    min: _constants.MIN_SPACER_SIZE,
    onChange: handleOnChange,
    style: {
      maxWidth: 80
    },
    value: computedValue,
    units: units
  })), spacingSizes?.length > 0 && (0, _react.createElement)(_primitives.View, {
    className: "tools-panel-item-spacing"
  }, (0, _react.createElement)(_blockEditor.__experimentalSpacingSizesControl, {
    values: {
      all: computedValue
    },
    onChange: handleOnChange,
    label: label,
    sides: ['all'],
    units: units,
    allowReset: false,
    splitOnAxis: false,
    showSideInLabel: false
  })));
}
function SpacerControls({
  setAttributes,
  orientation,
  height,
  width,
  isResizing
}) {
  return (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, orientation === 'horizontal' && (0, _react.createElement)(DimensionInput, {
    label: (0, _i18n.__)('Width'),
    value: width,
    onChange: nextWidth => setAttributes({
      width: nextWidth
    }),
    isResizing: isResizing
  }), orientation !== 'horizontal' && (0, _react.createElement)(DimensionInput, {
    label: (0, _i18n.__)('Height'),
    value: height,
    onChange: nextHeight => setAttributes({
      height: nextHeight
    }),
    isResizing: isResizing
  })));
}
//# sourceMappingURL=controls.js.map