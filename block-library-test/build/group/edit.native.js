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
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
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

const {
  isFullWidth
} = _components.alignmentHelpers;
function GroupEdit({
  attributes,
  hasInnerBlocks,
  isSelected,
  isLastInnerBlockSelected,
  getStylesFromColorScheme,
  style,
  blockWidth
}) {
  const {
    align
  } = attributes;
  const [resizeObserver, sizes] = (0, _compose.useResizeObserver)();
  const {
    width
  } = sizes || {
    width: 0
  };
  const renderAppender = (0, _element.useCallback)(() => (0, _react.createElement)(_reactNative.View, {
    style: [!hasInnerBlocks && _editor.default.groupAppender, isFullWidth(align) && !hasInnerBlocks && _editor.default.fullwidthGroupAppender, isFullWidth(align) && hasInnerBlocks && _editor.default.fullwidthHasInnerGroupAppender]
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.ButtonBlockAppender, null)), [align, hasInnerBlocks]);
  if (!isSelected && !hasInnerBlocks) {
    return (0, _react.createElement)(_reactNative.View, {
      style: [getStylesFromColorScheme(_editor.default.groupPlaceholder, _editor.default.groupPlaceholderDark), !hasInnerBlocks && {
        ..._editor.default.marginVerticalDense,
        ..._editor.default.marginHorizontalNone
      }]
    });
  }
  return (0, _react.createElement)(_reactNative.View, {
    style: [isSelected && hasInnerBlocks && _editor.default.innerBlocks, style, isSelected && hasInnerBlocks && style?.backgroundColor && _editor.default.hasBackgroundAppender, isLastInnerBlockSelected && style?.backgroundColor && _editor.default.isLastInnerBlockSelected]
  }, resizeObserver, (0, _react.createElement)(_blockEditor.InnerBlocks, {
    renderAppender: isSelected && renderAppender,
    parentWidth: width,
    blockWidth: blockWidth
  }));
}
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getBlock,
    getBlockIndex,
    hasSelectedInnerBlock,
    getBlockRootClientId,
    getSelectedBlockClientId,
    getBlockAttributes
  } = select(_blockEditor.store);
  const block = getBlock(clientId);
  const hasInnerBlocks = !!(block && block.innerBlocks.length);
  const isInnerBlockSelected = hasInnerBlocks && hasSelectedInnerBlock(clientId, true);
  let isLastInnerBlockSelected = false;
  if (isInnerBlockSelected) {
    const {
      innerBlocks
    } = block;
    const selectedBlockClientId = getSelectedBlockClientId();
    const totalInnerBlocks = innerBlocks.length - 1;
    const blockIndex = getBlockIndex(selectedBlockClientId);
    isLastInnerBlockSelected = totalInnerBlocks === blockIndex;
  }
  const parentId = getBlockRootClientId(clientId);
  const parentBlockAlignment = getBlockAttributes(parentId)?.align;
  return {
    hasInnerBlocks,
    isLastInnerBlockSelected,
    parentBlockAlignment
  };
}), _compose.withPreferredColorScheme])(GroupEdit);
//# sourceMappingURL=edit.native.js.map