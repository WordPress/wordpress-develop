"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ListItemEdit;
var _react = require("react");
var _reactNative = require("react-native");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _hooks = require("./hooks");
var _utils = require("./utils");
var _edit = require("./edit.js");
var _style = _interopRequireDefault(require("./style.scss"));
var _listStyleType = _interopRequireDefault(require("./list-style-type"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const OPACITY = '9e';
function ListItemEdit({
  attributes,
  setAttributes,
  onReplace,
  clientId,
  style,
  mergeBlocks
}) {
  const [contentWidth, setContentWidth] = (0, _element.useState)();
  const {
    placeholder,
    content
  } = attributes;
  const {
    blockIndex,
    hasInnerBlocks,
    indentationLevel,
    numberOfListItems,
    ordered,
    reversed,
    start
  } = (0, _data.useSelect)(select => {
    const {
      getBlockAttributes,
      getBlockCount,
      getBlockIndex,
      getBlockParentsByBlockName,
      getBlockRootClientId
    } = select(_blockEditor.store);
    const currentIdentationLevel = getBlockParentsByBlockName(clientId, 'core/list-item', true).length;
    const currentBlockIndex = getBlockIndex(clientId);
    const blockWithInnerBlocks = getBlockCount(clientId) > 0;
    const rootClientId = getBlockRootClientId(clientId);
    const blockAttributes = getBlockAttributes(rootClientId);
    const totalListItems = getBlockCount(rootClientId);
    const {
      ordered: isOrdered,
      reversed: isReversed,
      start: startValue
    } = blockAttributes || {};
    return {
      blockIndex: currentBlockIndex,
      hasInnerBlocks: blockWithInnerBlocks,
      indentationLevel: currentIdentationLevel,
      numberOfListItems: totalListItems,
      ordered: isOrdered,
      reversed: isReversed,
      start: startValue
    };
  }, [clientId]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    ...(hasInnerBlocks && _style.default['wp-block-list-item__nested-blocks'])
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    renderAppender: false
  });

  // Set default placeholder text color from light/dark scheme or base colors
  const defaultPlaceholderFromScheme = (0, _compose.usePreferredColorSchemeStyle)(_style.default['wp-block-list-item__list-item-placeholder'], _style.default['wp-block-list-item__list-item-placeholder--dark']);
  const currentTextColor = style?.color || style?.baseColors?.color?.text;
  const defaultPlaceholderTextColor = currentTextColor ? currentTextColor : defaultPlaceholderFromScheme?.color;

  // Add hex opacity to default placeholder text color and style object
  const defaultPlaceholderTextColorWithOpacity = defaultPlaceholderTextColor + OPACITY;
  const styleWithPlaceholderOpacity = {
    ...style,
    ...(style?.color && {
      placeholderColor: style.color + OPACITY
    })
  };
  const preventDefault = (0, _element.useRef)(false);
  const {
    onEnter
  } = (0, _hooks.useEnter)({
    content,
    clientId
  }, preventDefault);
  const onSplit = (0, _hooks.useSplit)(clientId);
  const onMerge = (0, _hooks.useMerge)(clientId, mergeBlocks);
  const onSplitList = (0, _element.useCallback)(value => {
    if (!preventDefault.current) {
      return onSplit(value);
    }
  }, [clientId, onSplit]);
  const onReplaceList = (0, _element.useCallback)((blocks, ...args) => {
    if (!preventDefault.current) {
      onReplace((0, _utils.convertToListItems)(blocks), ...args);
    }
  }, [clientId, onReplace, _utils.convertToListItems]);
  const onLayout = (0, _element.useCallback)(({
    nativeEvent
  }) => {
    setContentWidth(prevState => {
      const {
        width
      } = nativeEvent.layout;
      if (!prevState || prevState.width !== width) {
        return Math.floor(width);
      }
      return prevState;
    });
  }, []);
  return (0, _react.createElement)(_reactNative.View, {
    style: _style.default['wp-block-list-item__list-item-parent']
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default['wp-block-list-item__list-item']
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default['wp-block-list-item__list-item-icon']
  }, (0, _react.createElement)(_listStyleType.default, {
    blockIndex: blockIndex,
    indentationLevel: indentationLevel,
    numberOfListItems: numberOfListItems,
    ordered: ordered,
    reversed: reversed,
    start: start,
    style: style
  })), (0, _react.createElement)(_reactNative.View, {
    style: _style.default['wp-block-list-item__list-item-content'],
    onLayout: onLayout
  }, (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "content",
    tagName: "p",
    onChange: nextContent => setAttributes({
      content: nextContent
    }),
    value: content,
    placeholder: placeholder || (0, _i18n.__)('List'),
    placeholderTextColor: defaultPlaceholderTextColorWithOpacity,
    onSplit: onSplitList,
    onMerge: onMerge,
    onReplace: onReplaceList,
    onEnter: onEnter,
    style: styleWithPlaceholderOpacity,
    deleteEnter: true,
    containerWidth: contentWidth
  }))), (0, _react.createElement)(_reactNative.View, {
    ...innerBlocksProps
  }), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_edit.IndentUI, {
    clientId: clientId
  })));
}
//# sourceMappingURL=edit.native.js.map