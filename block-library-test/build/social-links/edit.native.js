"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
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

// Template contains the links that show when start.
const TEMPLATE = [['core/social-link-wordpress', {
  service: 'wordpress',
  url: 'https://wordpress.org'
}], ['core/social-link-facebook', {
  service: 'facebook'
}], ['core/social-link-twitter', {
  service: 'twitter'
}], ['core/social-link-instagram', {
  service: 'instagram'
}]];
function SocialLinksEdit({
  shouldDelete,
  onDelete,
  isSelected,
  isInnerIconSelected,
  innerBlocks,
  attributes,
  activeInnerBlocks,
  getBlock,
  blockWidth
}) {
  const [initialCreation, setInitialCreation] = (0, _element.useState)(true);
  const shouldRenderFooterAppender = isSelected || isInnerIconSelected;
  const {
    align
  } = attributes;
  const {
    marginLeft: spacing
  } = _editor.default.spacing;
  (0, _element.useEffect)(() => {
    if (!shouldRenderFooterAppender) {
      setInitialCreation(false);
    }
  }, [shouldRenderFooterAppender]);
  const renderFooterAppender = (0, _element.useRef)(() => (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.footerAppenderContainer
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.ButtonBlockAppender, {
    isFloating: true
  })));
  const placeholderStyle = (0, _compose.usePreferredColorSchemeStyle)(_editor.default.placeholder, _editor.default.placeholderDark);
  function renderPlaceholder() {
    return [...new Array(innerBlocks.length || 1)].map((_, index) => (0, _react.createElement)(_reactNative.View, {
      testID: "social-links-placeholder",
      style: placeholderStyle,
      key: index
    }));
  }
  function filterInnerBlocks(blockIds) {
    return blockIds.filter(blockId => getBlock(blockId).attributes.url);
  }
  if (!shouldRenderFooterAppender && activeInnerBlocks.length === 0) {
    return (0, _react.createElement)(_reactNative.View, {
      style: _editor.default.placeholderWrapper
    }, renderPlaceholder());
  }
  return (0, _react.createElement)(_blockEditor.InnerBlocks, {
    templateLock: false,
    template: initialCreation && TEMPLATE,
    renderFooterAppender: shouldRenderFooterAppender && renderFooterAppender.current,
    orientation: 'horizontal',
    onDeleteBlock: shouldDelete ? onDelete : undefined,
    marginVertical: spacing,
    marginHorizontal: spacing,
    horizontalAlignment: align,
    filterInnerBlocks: !shouldRenderFooterAppender && filterInnerBlocks,
    blockWidth: blockWidth
  });
}
var _default = exports.default = (0, _compose.compose)((0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getBlockCount,
    getBlockParents,
    getSelectedBlockClientId,
    getBlocks,
    getBlock
  } = select(_blockEditor.store);
  const selectedBlockClientId = getSelectedBlockClientId();
  const selectedBlockParents = getBlockParents(selectedBlockClientId, true);
  const innerBlocks = getBlocks(clientId);
  const activeInnerBlocks = innerBlocks.filter(block => block.attributes?.url);
  return {
    shouldDelete: getBlockCount(clientId) === 1,
    isInnerIconSelected: selectedBlockParents[0] === clientId,
    innerBlocks,
    activeInnerBlocks,
    getBlock
  };
}), (0, _data.withDispatch)((dispatch, {
  clientId
}) => {
  const {
    removeBlock
  } = dispatch(_blockEditor.store);
  return {
    onDelete: () => {
      removeBlock(clientId, false);
    }
  };
}))(SocialLinksEdit);
//# sourceMappingURL=edit.native.js.map