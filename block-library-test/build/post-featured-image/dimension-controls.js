"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const SCALE_OPTIONS = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
  value: "cover",
  label: (0, _i18n._x)('Cover', 'Scale option for Image dimension control')
}), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
  value: "contain",
  label: (0, _i18n._x)('Contain', 'Scale option for Image dimension control')
}), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
  value: "fill",
  label: (0, _i18n._x)('Fill', 'Scale option for Image dimension control')
}));
const DEFAULT_SCALE = 'cover';
const DEFAULT_SIZE = 'full';
const scaleHelp = {
  cover: (0, _i18n.__)('Image is scaled and cropped to fill the entire space without being distorted.'),
  contain: (0, _i18n.__)('Image is scaled to fill the space without clipping nor distorting.'),
  fill: (0, _i18n.__)('Image will be stretched and distorted to completely fill the space.')
};
const DimensionControls = ({
  clientId,
  attributes: {
    aspectRatio,
    width,
    height,
    scale,
    sizeSlug
  },
  setAttributes,
  imageSizeOptions = []
}) => {
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['px', '%', 'vw', 'em', 'rem']
  });
  const onDimensionChange = (dimension, nextValue) => {
    const parsedValue = parseFloat(nextValue);
    /**
     * If we have no value set and we change the unit,
     * we don't want to set the attribute, as it would
     * end up having the unit as value without any number.
     */
    if (isNaN(parsedValue) && nextValue) return;
    setAttributes({
      [dimension]: parsedValue < 0 ? '0' : nextValue
    });
  };
  const scaleLabel = (0, _i18n._x)('Scale', 'Image scaling options');
  const showScaleControl = height || aspectRatio && aspectRatio !== 'auto';
  return (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "dimensions"
  }, (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!aspectRatio,
    label: (0, _i18n.__)('Aspect ratio'),
    onDeselect: () => setAttributes({
      aspectRatio: undefined
    }),
    resetAllFilter: () => ({
      aspectRatio: undefined
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Aspect ratio'),
    value: aspectRatio,
    options: [
    // These should use the same values as AspectRatioDropdown in @wordpress/block-editor
    {
      label: (0, _i18n.__)('Original'),
      value: 'auto'
    }, {
      label: (0, _i18n.__)('Square'),
      value: '1'
    }, {
      label: (0, _i18n.__)('16:9'),
      value: '16/9'
    }, {
      label: (0, _i18n.__)('4:3'),
      value: '4/3'
    }, {
      label: (0, _i18n.__)('3:2'),
      value: '3/2'
    }, {
      label: (0, _i18n.__)('9:16'),
      value: '9/16'
    }, {
      label: (0, _i18n.__)('3:4'),
      value: '3/4'
    }, {
      label: (0, _i18n.__)('2:3'),
      value: '2/3'
    }],
    onChange: nextAspectRatio => setAttributes({
      aspectRatio: nextAspectRatio
    })
  })), (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    className: "single-column",
    hasValue: () => !!height,
    label: (0, _i18n.__)('Height'),
    onDeselect: () => setAttributes({
      height: undefined
    }),
    resetAllFilter: () => ({
      height: undefined
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Height'),
    labelPosition: "top",
    value: height || '',
    min: 0,
    onChange: nextHeight => onDimensionChange('height', nextHeight),
    units: units
  })), (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    className: "single-column",
    hasValue: () => !!width,
    label: (0, _i18n.__)('Width'),
    onDeselect: () => setAttributes({
      width: undefined
    }),
    resetAllFilter: () => ({
      width: undefined
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Width'),
    labelPosition: "top",
    value: width || '',
    min: 0,
    onChange: nextWidth => onDimensionChange('width', nextWidth),
    units: units
  })), showScaleControl && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!scale && scale !== DEFAULT_SCALE,
    label: scaleLabel,
    onDeselect: () => setAttributes({
      scale: DEFAULT_SCALE
    }),
    resetAllFilter: () => ({
      scale: DEFAULT_SCALE
    }),
    isShownByDefault: true,
    panelId: clientId
  }, (0, _react.createElement)(_components.__experimentalToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: scaleLabel,
    value: scale,
    help: scaleHelp[scale],
    onChange: value => setAttributes({
      scale: value
    }),
    isBlock: true
  }, SCALE_OPTIONS)), !!imageSizeOptions.length && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!sizeSlug,
    label: (0, _i18n.__)('Resolution'),
    onDeselect: () => setAttributes({
      sizeSlug: undefined
    }),
    resetAllFilter: () => ({
      sizeSlug: undefined
    }),
    isShownByDefault: false,
    panelId: clientId
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Resolution'),
    value: sizeSlug || DEFAULT_SIZE,
    options: imageSizeOptions,
    onChange: nextSizeSlug => setAttributes({
      sizeSlug: nextSizeSlug
    }),
    help: (0, _i18n.__)('Select the size of the source image.')
  })));
};
var _default = exports.default = DimensionControls;
//# sourceMappingURL=dimension-controls.js.map