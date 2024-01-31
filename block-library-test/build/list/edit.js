"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _deprecated = _interopRequireDefault(require("@wordpress/deprecated"));
var _orderedListSettings = _interopRequireDefault(require("./ordered-list-settings"));
var _utils = require("./utils");
var _tagName = _interopRequireDefault(require("./tag-name"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TEMPLATE = [['core/list-item']];
const NATIVE_MARGIN_SPACING = 8;

/**
 * At the moment, deprecations don't handle create blocks from attributes
 * (like when using CPT templates). For this reason, this hook is necessary
 * to avoid breaking templates using the old list block format.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} clientId   Block client ID.
 */
function useMigrateOnLoad(attributes, clientId) {
  const registry = (0, _data.useRegistry)();
  const {
    updateBlockAttributes,
    replaceInnerBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    // As soon as the block is loaded, migrate it to the new version.

    if (!attributes.values) {
      return;
    }
    const [newAttributes, newInnerBlocks] = (0, _utils.migrateToListV2)(attributes);
    (0, _deprecated.default)('Value attribute on the list block', {
      since: '6.0',
      version: '6.5',
      alternative: 'inner blocks'
    });
    registry.batch(() => {
      updateBlockAttributes(clientId, newAttributes);
      replaceInnerBlocks(clientId, newInnerBlocks);
    });
  }, [attributes.values]);
}
function useOutdentList(clientId) {
  const {
    replaceBlocks,
    selectionChange
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlockRootClientId,
    getBlockAttributes,
    getBlock
  } = (0, _data.useSelect)(_blockEditor.store);
  return (0, _element.useCallback)(() => {
    const parentBlockId = getBlockRootClientId(clientId);
    const parentBlockAttributes = getBlockAttributes(parentBlockId);
    // Create a new parent block without the inner blocks.
    const newParentBlock = (0, _blocks.createBlock)('core/list-item', parentBlockAttributes);
    const {
      innerBlocks
    } = getBlock(clientId);
    // Replace the parent block with a new parent block without inner blocks,
    // and make the inner blocks siblings of the parent.
    replaceBlocks([parentBlockId], [newParentBlock, ...innerBlocks]);
    // Select the last child of the list being outdent.
    selectionChange(innerBlocks[innerBlocks.length - 1].clientId);
  }, [clientId]);
}
function IndentUI({
  clientId
}) {
  const outdentList = useOutdentList(clientId);
  const canOutdent = (0, _data.useSelect)(select => {
    const {
      getBlockRootClientId,
      getBlockName
    } = select(_blockEditor.store);
    return getBlockName(getBlockRootClientId(clientId)) === 'core/list-item';
  }, [clientId]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToolbarButton, {
    icon: (0, _i18n.isRTL)() ? _icons.formatOutdentRTL : _icons.formatOutdent,
    title: (0, _i18n.__)('Outdent'),
    describedBy: (0, _i18n.__)('Outdent list item'),
    disabled: !canOutdent,
    onClick: outdentList
  }));
}
function Edit({
  attributes,
  setAttributes,
  clientId,
  style
}) {
  const {
    ordered,
    type,
    reversed,
    start
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    style: {
      ...(_element.Platform.isNative && style),
      listStyleType: ordered && type !== 'decimal' ? type : undefined
    }
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    templateLock: false,
    templateInsertUpdatesSelection: true,
    ...(_element.Platform.isNative && {
      marginVertical: NATIVE_MARGIN_SPACING,
      marginHorizontal: NATIVE_MARGIN_SPACING,
      renderAppender: false
    }),
    __experimentalCaptureToolbars: true
  });
  useMigrateOnLoad(attributes, clientId);
  const controls = (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_components.ToolbarButton, {
    icon: (0, _i18n.isRTL)() ? _icons.formatListBulletsRTL : _icons.formatListBullets,
    title: (0, _i18n.__)('Unordered'),
    describedBy: (0, _i18n.__)('Convert to unordered list'),
    isActive: ordered === false,
    onClick: () => {
      setAttributes({
        ordered: false
      });
    }
  }), (0, _react.createElement)(_components.ToolbarButton, {
    icon: (0, _i18n.isRTL)() ? _icons.formatListNumberedRTL : _icons.formatListNumbered,
    title: (0, _i18n.__)('Ordered'),
    describedBy: (0, _i18n.__)('Convert to ordered list'),
    isActive: ordered === true,
    onClick: () => {
      setAttributes({
        ordered: true
      });
    }
  }), (0, _react.createElement)(IndentUI, {
    clientId: clientId
  }));
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_tagName.default, {
    ordered: ordered,
    reversed: reversed,
    start: start,
    ...innerBlocksProps
  }), controls, ordered && (0, _react.createElement)(_orderedListSettings.default, {
    setAttributes,
    reversed,
    start,
    type
  }));
}
//# sourceMappingURL=edit.js.map