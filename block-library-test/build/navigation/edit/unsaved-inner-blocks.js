"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = UnsavedInnerBlocks;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _areBlocksDirty = require("./are-blocks-dirty");
var _constants = require("../constants");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const EMPTY_OBJECT = {};
function UnsavedInnerBlocks({
  blocks,
  createNavigationMenu,
  hasSelection
}) {
  const originalBlocks = (0, _element.useRef)();
  (0, _element.useEffect)(() => {
    // Initially store the uncontrolled inner blocks for
    // dirty state comparison.
    if (!originalBlocks?.current) {
      originalBlocks.current = blocks;
    }
  }, [blocks]);

  // If the current inner blocks are different from the original inner blocks
  // from the post content then the user has made changes to the inner blocks.
  // At this point the inner blocks can be considered "dirty".
  // Note: referential equality is not sufficient for comparison as the inner blocks
  // of the page list are controlled and may be updated async due to syncing with
  // entity records. As a result we need to perform a deep equality check skipping
  // the page list's inner blocks.
  const innerBlocksAreDirty = (0, _areBlocksDirty.areBlocksDirty)(originalBlocks?.current, blocks);
  const shouldDirectInsert = (0, _element.useMemo)(() => blocks.every(({
    name
  }) => name === 'core/navigation-link' || name === 'core/navigation-submenu' || name === 'core/page-list'), [blocks]);

  // The block will be disabled in a block preview, use this as a way of
  // avoiding the side-effects of this component for block previews.
  const isDisabled = (0, _element.useContext)(_components.Disabled.Context);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    className: 'wp-block-navigation__container'
  }, {
    renderAppender: hasSelection ? undefined : false,
    defaultBlock: _constants.DEFAULT_BLOCK,
    directInsert: shouldDirectInsert
  });
  const {
    isSaving,
    hasResolvedAllNavigationMenus
  } = (0, _data.useSelect)(select => {
    if (isDisabled) {
      return EMPTY_OBJECT;
    }
    const {
      hasFinishedResolution,
      isSavingEntityRecord
    } = select(_coreData.store);
    return {
      isSaving: isSavingEntityRecord('postType', 'wp_navigation'),
      hasResolvedAllNavigationMenus: hasFinishedResolution('getEntityRecords', _constants.SELECT_NAVIGATION_MENUS_ARGS)
    };
  }, [isDisabled]);

  // Automatically save the uncontrolled blocks.
  (0, _element.useEffect)(() => {
    // The block will be disabled when used in a BlockPreview.
    // In this case avoid automatic creation of a wp_navigation post.
    // Otherwise the user will be spammed with lots of menus!
    //
    // Also ensure other navigation menus have loaded so an
    // accurate name can be created.
    //
    // Don't try saving when another save is already
    // in progress.
    //
    // And finally only create the menu when the block is selected,
    // which is an indication they want to start editing.
    if (isDisabled || isSaving || !hasResolvedAllNavigationMenus || !hasSelection || !innerBlocksAreDirty) {
      return;
    }
    createNavigationMenu(null, blocks);
  }, [blocks, createNavigationMenu, isDisabled, isSaving, hasResolvedAllNavigationMenus, innerBlocksAreDirty, hasSelection]);
  const Wrapper = isSaving ? _components.Disabled : 'div';
  return (0, _react.createElement)(Wrapper, {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=unsaved-inner-blocks.js.map