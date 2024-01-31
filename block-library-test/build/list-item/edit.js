"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.IndentUI = IndentUI;
exports.default = ListItemEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _hooks = require("./hooks");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function IndentUI({
  clientId
}) {
  const indentListItem = (0, _hooks.useIndentListItem)(clientId);
  const outdentListItem = (0, _hooks.useOutdentListItem)();
  const {
    canIndent,
    canOutdent
  } = (0, _data.useSelect)(select => {
    const {
      getBlockIndex,
      getBlockRootClientId,
      getBlockName
    } = select(_blockEditor.store);
    return {
      canIndent: getBlockIndex(clientId) > 0,
      canOutdent: getBlockName(getBlockRootClientId(getBlockRootClientId(clientId))) === 'core/list-item'
    };
  }, [clientId]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToolbarButton, {
    icon: (0, _i18n.isRTL)() ? _icons.formatOutdentRTL : _icons.formatOutdent,
    title: (0, _i18n.__)('Outdent'),
    describedBy: (0, _i18n.__)('Outdent list item'),
    disabled: !canOutdent,
    onClick: () => outdentListItem()
  }), (0, _react.createElement)(_components.ToolbarButton, {
    icon: (0, _i18n.isRTL)() ? _icons.formatIndentRTL : _icons.formatIndent,
    title: (0, _i18n.__)('Indent'),
    describedBy: (0, _i18n.__)('Indent list item'),
    isDisabled: !canIndent,
    onClick: () => indentListItem()
  }));
}
function ListItemEdit({
  attributes,
  setAttributes,
  onReplace,
  clientId,
  mergeBlocks
}) {
  const {
    placeholder,
    content
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref: (0, _hooks.useCopy)(clientId)
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    renderAppender: false,
    __unstableDisableDropZone: true
  });
  const useEnterRef = (0, _hooks.useEnter)({
    content,
    clientId
  });
  const useSpaceRef = (0, _hooks.useSpace)(clientId);
  const onSplit = (0, _hooks.useSplit)(clientId);
  const onMerge = (0, _hooks.useMerge)(clientId, mergeBlocks);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("li", {
    ...innerBlocksProps
  }, (0, _react.createElement)(_blockEditor.RichText, {
    ref: (0, _compose.useMergeRefs)([useEnterRef, useSpaceRef]),
    identifier: "content",
    tagName: "div",
    onChange: nextContent => setAttributes({
      content: nextContent
    }),
    value: content,
    "aria-label": (0, _i18n.__)('List text'),
    placeholder: placeholder || (0, _i18n.__)('List'),
    onSplit: onSplit,
    onMerge: onMerge,
    onReplace: onReplace ? (blocks, ...args) => {
      onReplace((0, _utils.convertToListItems)(blocks), ...args);
    } : undefined
  }), innerBlocksProps.children), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(IndentUI, {
    clientId: clientId
  })));
}
//# sourceMappingURL=edit.js.map