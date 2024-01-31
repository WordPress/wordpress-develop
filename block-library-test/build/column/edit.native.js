"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _data = require("@wordpress/data");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _editor = _interopRequireDefault(require("./editor.scss"));
var _columnPreview = _interopRequireDefault(require("./column-preview"));
var _utils = require("../columns/utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ColumnEdit({
  attributes,
  setAttributes,
  hasChildren,
  isSelected,
  getStylesFromColorScheme,
  contentStyle,
  columns,
  selectedColumnIndex,
  parentAlignment,
  clientId,
  blockWidth
}) {
  if (!contentStyle) {
    contentStyle = {
      [clientId]: {}
    };
  }
  const {
    verticalAlignment,
    width
  } = attributes;
  const {
    valueUnit = '%'
  } = (0, _components.getValueAndUnit)(width) || {};
  const screenWidth = Math.floor(_reactNative.Dimensions.get('window').width);
  const [widthUnit, setWidthUnit] = (0, _element.useState)(valueUnit || '%');
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['%', 'px', 'em', 'rem', 'vw']
  });
  const updateAlignment = alignment => {
    setAttributes({
      verticalAlignment: alignment
    });
  };
  (0, _element.useEffect)(() => {
    setWidthUnit(valueUnit);
  }, [valueUnit]);
  (0, _element.useEffect)(() => {
    if (!verticalAlignment && parentAlignment) {
      updateAlignment(parentAlignment);
    }
  }, []);
  const onChangeWidth = nextWidth => {
    const widthWithUnit = (0, _utils.getWidthWithUnit)(nextWidth, widthUnit);
    setAttributes({
      width: widthWithUnit
    });
  };
  const onChangeUnit = nextUnit => {
    setWidthUnit(nextUnit);
    const widthWithoutUnit = parseFloat(width || (0, _utils.getWidths)(columns)[selectedColumnIndex]);
    setAttributes({
      width: (0, _utils.getWidthWithUnit)(widthWithoutUnit, nextUnit)
    });
  };
  const onChange = nextWidth => {
    if ((0, _utils.isPercentageUnit)(widthUnit) || !widthUnit) {
      return;
    }
    onChangeWidth(nextWidth);
  };
  const renderAppender = (0, _element.useCallback)(() => {
    if (isSelected) {
      const {
        width: columnWidth
      } = contentStyle[clientId] || {};
      const isFullWidth = columnWidth === screenWidth;
      return (0, _react.createElement)(_reactNative.View, {
        style: [_editor.default.columnAppender, isFullWidth && _editor.default.fullwidthColumnAppender, isFullWidth && hasChildren && _editor.default.fullwidthHasInnerColumnAppender, !isFullWidth && hasChildren && _editor.default.hasInnerColumnAppender]
      }, (0, _react.createElement)(_blockEditor.InnerBlocks.ButtonBlockAppender, null));
    }
    return null;
  }, [contentStyle, clientId, screenWidth, isSelected, hasChildren]);
  if (!isSelected && !hasChildren) {
    return (0, _react.createElement)(_reactNative.View, {
      style: [getStylesFromColorScheme(_editor.default.columnPlaceholder, _editor.default.columnPlaceholderDark), contentStyle[clientId]]
    });
  }
  const parentWidth = contentStyle && contentStyle[clientId] && contentStyle[clientId].width;
  return (0, _react.createElement)(_react.Fragment, null, isSelected && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentToolbar, {
    onChange: updateAlignment,
    value: verticalAlignment
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.UnitControl, {
    label: (0, _i18n.__)('Width'),
    min: 1,
    max: (0, _utils.isPercentageUnit)(widthUnit) ? 100 : undefined,
    onChange: onChange,
    onComplete: onChangeWidth,
    onUnitChange: onChangeUnit,
    value: (0, _utils.getWidths)(columns)[selectedColumnIndex],
    unit: widthUnit,
    units: units,
    preview: (0, _react.createElement)(_columnPreview.default, {
      columnWidths: (0, _utils.getWidths)(columns, false),
      selectedColumnIndex: selectedColumnIndex
    })
  })), (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.FooterMessageControl, {
    label: (0, _i18n.__)('Note: Column layout may vary between themes and screen sizes')
  })))), (0, _react.createElement)(_reactNative.View, {
    style: [isSelected && hasChildren && _editor.default.innerBlocksBottomSpace, contentStyle[clientId]]
  }, (0, _react.createElement)(_blockEditor.InnerBlocks, {
    renderAppender: renderAppender,
    parentWidth: parentWidth,
    blockWidth: blockWidth
  })));
}
function ColumnEditWrapper(props) {
  const {
    verticalAlignment
  } = props.attributes;
  const getVerticalAlignmentRemap = alignment => {
    if (!alignment) return _editor.default.flexBase;
    return {
      ..._editor.default.flexBase,
      ..._editor.default[`is-vertically-aligned-${alignment}`]
    };
  };
  return (0, _react.createElement)(_reactNative.View, {
    style: getVerticalAlignmentRemap(verticalAlignment)
  }, (0, _react.createElement)(ColumnEdit, {
    ...props
  }));
}
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getBlockCount,
    getBlockRootClientId,
    getSelectedBlockClientId,
    getBlocks,
    getBlockOrder,
    getBlockAttributes
  } = select(_blockEditor.store);
  const selectedBlockClientId = getSelectedBlockClientId();
  const isSelected = selectedBlockClientId === clientId;
  const parentId = getBlockRootClientId(clientId);
  const hasChildren = !!getBlockCount(clientId);
  const blockOrder = getBlockOrder(parentId);
  const selectedColumnIndex = blockOrder.indexOf(clientId);
  const columns = getBlocks(parentId);
  const parentAlignment = getBlockAttributes(parentId)?.verticalAlignment;
  return {
    hasChildren,
    isSelected,
    selectedColumnIndex,
    columns,
    parentAlignment
  };
}), _compose.withPreferredColorScheme])(ColumnEditWrapper);
//# sourceMappingURL=edit.native.js.map