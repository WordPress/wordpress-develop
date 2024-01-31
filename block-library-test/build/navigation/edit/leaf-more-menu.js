"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = LeafMoreMenu;
var _react = require("react");
var _blocks = require("@wordpress/blocks");
var _icons = require("@wordpress/icons");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const POPOVER_PROPS = {
  className: 'block-editor-block-settings-menu__popover',
  placement: 'bottom-start'
};
const BLOCKS_THAT_CAN_BE_CONVERTED_TO_SUBMENU = ['core/navigation-link', 'core/navigation-submenu'];
function AddSubmenuItem({
  block,
  onClose,
  expandedState,
  expand,
  setInsertedBlock
}) {
  const {
    insertBlock,
    replaceBlock,
    replaceInnerBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const clientId = block.clientId;
  const isDisabled = !BLOCKS_THAT_CAN_BE_CONVERTED_TO_SUBMENU.includes(block.name);
  return (0, _react.createElement)(_components.MenuItem, {
    icon: _icons.addSubmenu,
    disabled: isDisabled,
    onClick: () => {
      const updateSelectionOnInsert = false;
      const newLink = (0, _blocks.createBlock)('core/navigation-link');
      if (block.name === 'core/navigation-submenu') {
        insertBlock(newLink, block.innerBlocks.length, clientId, updateSelectionOnInsert);
      } else {
        // Convert to a submenu if the block currently isn't one.
        const newSubmenu = (0, _blocks.createBlock)('core/navigation-submenu', block.attributes, block.innerBlocks);

        // The following must happen as two independent actions.
        // Why? Because the offcanvas editor relies on the getLastInsertedBlocksClientIds
        // selector to determine which block is "active". As the UX needs the newLink to be
        // the "active" block it must be the last block to be inserted.
        // Therefore the Submenu is first created and **then** the newLink is inserted
        // thus ensuring it is the last inserted block.
        replaceBlock(clientId, newSubmenu);
        replaceInnerBlocks(newSubmenu.clientId, [newLink], updateSelectionOnInsert);
      }

      // This call sets the local List View state for the "last inserted block".
      // This is required for the Nav Block to determine whether or not to display
      // the Link UI for this new block.
      setInsertedBlock(newLink);
      if (!expandedState[block.clientId]) {
        expand(block.clientId);
      }
      onClose();
    }
  }, (0, _i18n.__)('Add submenu link'));
}
function LeafMoreMenu(props) {
  const {
    block
  } = props;
  const {
    clientId
  } = block;
  const {
    moveBlocksDown,
    moveBlocksUp,
    removeBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const removeLabel = (0, _i18n.sprintf)( /* translators: %s: block name */
  (0, _i18n.__)('Remove %s'), (0, _blockEditor.BlockTitle)({
    clientId,
    maximumLength: 25
  }));
  const rootClientId = (0, _data.useSelect)(select => {
    const {
      getBlockRootClientId
    } = select(_blockEditor.store);
    return getBlockRootClientId(clientId);
  }, [clientId]);
  return (0, _react.createElement)(_components.DropdownMenu, {
    icon: _icons.moreVertical,
    label: (0, _i18n.__)('Options'),
    className: "block-editor-block-settings-menu",
    popoverProps: POPOVER_PROPS,
    noIcons: true,
    ...props
  }, ({
    onClose
  }) => (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.MenuGroup, null, (0, _react.createElement)(_components.MenuItem, {
    icon: _icons.chevronUp,
    onClick: () => {
      moveBlocksUp([clientId], rootClientId);
      onClose();
    }
  }, (0, _i18n.__)('Move up')), (0, _react.createElement)(_components.MenuItem, {
    icon: _icons.chevronDown,
    onClick: () => {
      moveBlocksDown([clientId], rootClientId);
      onClose();
    }
  }, (0, _i18n.__)('Move down')), (0, _react.createElement)(AddSubmenuItem, {
    block: block,
    onClose: onClose,
    expanded: true,
    expandedState: props.expandedState,
    expand: props.expand,
    setInsertedBlock: props.setInsertedBlock
  })), (0, _react.createElement)(_components.MenuGroup, null, (0, _react.createElement)(_components.MenuItem, {
    onClick: () => {
      removeBlocks([clientId], false);
      onClose();
    }
  }, removeLabel))));
}
//# sourceMappingURL=leaf-more-menu.js.map