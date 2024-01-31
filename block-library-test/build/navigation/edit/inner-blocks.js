"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationInnerBlocks;
var _react = require("react");
var _coreData = require("@wordpress/core-data");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _placeholderPreview = _interopRequireDefault(require("./placeholder/placeholder-preview"));
var _constants = require("../constants");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function NavigationInnerBlocks({
  clientId,
  hasCustomPlaceholder,
  orientation,
  templateLock
}) {
  const {
    isImmediateParentOfSelectedBlock,
    selectedBlockHasChildren,
    isSelected
  } = (0, _data.useSelect)(select => {
    const {
      getBlockCount,
      hasSelectedInnerBlock,
      getSelectedBlockClientId
    } = select(_blockEditor.store);
    const selectedBlockId = getSelectedBlockClientId();
    return {
      isImmediateParentOfSelectedBlock: hasSelectedInnerBlock(clientId, false),
      selectedBlockHasChildren: !!getBlockCount(selectedBlockId),
      // This prop is already available but computing it here ensures it's
      // fresh compared to isImmediateParentOfSelectedBlock.
      isSelected: selectedBlockId === clientId
    };
  }, [clientId]);
  const [blocks, onInput, onChange] = (0, _coreData.useEntityBlockEditor)('postType', 'wp_navigation');
  const shouldDirectInsert = (0, _element.useMemo)(() => blocks.every(({
    name
  }) => name === 'core/navigation-link' || name === 'core/navigation-submenu' || name === 'core/page-list'), [blocks]);

  // When the block is selected itself or has a top level item selected that
  // doesn't itself have children, show the standard appender. Else show no
  // appender.
  const parentOrChildHasSelection = isSelected || isImmediateParentOfSelectedBlock && !selectedBlockHasChildren;
  const placeholder = (0, _element.useMemo)(() => (0, _react.createElement)(_placeholderPreview.default, null), []);
  const hasMenuItems = !!blocks?.length;

  // If there is a `ref` attribute pointing to a `wp_navigation` but
  // that menu has no **items** (i.e. empty) then show a placeholder.
  // The block must also be selected else the placeholder will display
  // alongside the appender.
  const showPlaceholder = !hasCustomPlaceholder && !hasMenuItems && !isSelected;
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    className: 'wp-block-navigation__container'
  }, {
    value: blocks,
    onInput,
    onChange,
    prioritizedInserterBlocks: _constants.PRIORITIZED_INSERTER_BLOCKS,
    defaultBlock: _constants.DEFAULT_BLOCK,
    directInsert: shouldDirectInsert,
    orientation,
    templateLock,
    // As an exception to other blocks which feature nesting, show
    // the block appender even when a child block is selected.
    // This should be a temporary fix, to be replaced by improvements to
    // the sibling inserter.
    // See https://github.com/WordPress/gutenberg/issues/37572.
    renderAppender: isSelected || isImmediateParentOfSelectedBlock && !selectedBlockHasChildren ||
    // Show the appender while dragging to allow inserting element between item and the appender.
    parentOrChildHasSelection ? _blockEditor.InnerBlocks.ButtonBlockAppender : false,
    placeholder: showPlaceholder ? placeholder : undefined,
    __experimentalCaptureToolbars: true,
    __unstableDisableLayoutClassNames: true
  });
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=inner-blocks.js.map