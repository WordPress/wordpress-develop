"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _editor = _interopRequireDefault(require("./editor.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ColumnPreviewItem({
  index,
  selectedColumnIndex,
  width
}) {
  const columnIndicatorStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor.default.columnIndicator, _editor.default.columnIndicatorDark);
  const isSelectedColumn = index === selectedColumnIndex;
  const convertedWidth = (0, _components.useConvertUnitToMobile)(width);
  return (0, _react.createElement)(_reactNative.View, {
    style: [isSelectedColumn && columnIndicatorStyle, {
      flex: convertedWidth
    }],
    key: index
  });
}
function ColumnsPreview({
  columnWidths,
  selectedColumnIndex
}) {
  const columnsPreviewStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor.default.columnsPreview, _editor.default.columnsPreviewDark);
  return (0, _react.createElement)(_reactNative.View, {
    style: columnsPreviewStyle
  }, columnWidths.map((width, index) => {
    return (0, _react.createElement)(ColumnPreviewItem, {
      index: index,
      selectedColumnIndex: selectedColumnIndex,
      width: width,
      key: index
    });
  }));
}
var _default = exports.default = ColumnsPreview;
//# sourceMappingURL=column-preview.native.js.map