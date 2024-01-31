"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.formatName = exports.format = void 0;
var _react = require("react");
var _uuid = require("uuid");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _richText = require("@wordpress/rich-text");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _blocks = require("@wordpress/blocks");
var _lockUnlock = require("../lock-unlock");
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
  usesContextKey
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
const formatName = exports.formatName = 'core/footnote';
const POST_CONTENT_BLOCK_NAME = 'core/post-content';
const SYNCED_PATTERN_BLOCK_NAME = 'core/block';
const format = exports.format = {
  title: (0, _i18n.__)('Footnote'),
  tagName: 'sup',
  className: 'fn',
  attributes: {
    'data-fn': 'data-fn'
  },
  interactive: true,
  contentEditable: false,
  [usesContextKey]: ['postType', 'postId'],
  edit: function Edit({
    value,
    onChange,
    isObjectActive,
    context: {
      postType,
      postId
    }
  }) {
    const registry = (0, _data.useRegistry)();
    const {
      getSelectedBlockClientId,
      getBlocks,
      getBlockRootClientId,
      getBlockName,
      getBlockParentsByBlockName
    } = registry.select(_blockEditor.store);
    const isFootnotesSupported = (0, _data.useSelect)(select => {
      if (!select(_blocks.store).getBlockType('core/footnotes')) {
        return false;
      }
      const entityRecord = select(_coreData.store).getEntityRecord('postType', postType, postId);
      if ('string' !== typeof entityRecord?.meta?.footnotes) {
        return false;
      }

      // Checks if the selected block lives within a pattern.
      const {
        getBlockParentsByBlockName: _getBlockParentsByBlockName,
        getSelectedBlockClientId: _getSelectedBlockClientId
      } = select(_blockEditor.store);
      const parentCoreBlocks = _getBlockParentsByBlockName(_getSelectedBlockClientId(), SYNCED_PATTERN_BLOCK_NAME);
      return !parentCoreBlocks || parentCoreBlocks.length === 0;
    }, [postType, postId]);
    const {
      selectionChange,
      insertBlock
    } = (0, _data.useDispatch)(_blockEditor.store);
    if (!isFootnotesSupported) {
      return null;
    }
    function onClick() {
      registry.batch(() => {
        let id;
        if (isObjectActive) {
          const object = value.replacements[value.start];
          id = object?.attributes?.['data-fn'];
        } else {
          id = (0, _uuid.v4)();
          const newValue = (0, _richText.insertObject)(value, {
            type: formatName,
            attributes: {
              'data-fn': id
            },
            innerHTML: `<a href="#${id}" id="${id}-link">*</a>`
          }, value.end, value.end);
          newValue.start = newValue.end - 1;
          onChange(newValue);
        }
        const selectedClientId = getSelectedBlockClientId();

        /*
         * Attempts to find a common parent post content block.
         * This allows for locating blocks within a page edited in the site editor.
         */
        const parentPostContent = getBlockParentsByBlockName(selectedClientId, POST_CONTENT_BLOCK_NAME);

        // When called with a post content block, getBlocks will return
        // the block with controlled inner blocks included.
        const blocks = parentPostContent.length ? getBlocks(parentPostContent[0]) : getBlocks();

        // BFS search to find the first footnote block.
        let fnBlock = null;
        {
          const queue = [...blocks];
          while (queue.length) {
            const block = queue.shift();
            if (block.name === 'core/footnotes') {
              fnBlock = block;
              break;
            }
            queue.push(...block.innerBlocks);
          }
        }

        // Maybe this should all also be moved to the entity provider.
        // When there is no footnotes block in the post, create one and
        // insert it at the bottom.
        if (!fnBlock) {
          let rootClientId = getBlockRootClientId(selectedClientId);
          while (rootClientId && getBlockName(rootClientId) !== POST_CONTENT_BLOCK_NAME) {
            rootClientId = getBlockRootClientId(rootClientId);
          }
          fnBlock = (0, _blocks.createBlock)('core/footnotes');
          insertBlock(fnBlock, undefined, rootClientId);
        }
        selectionChange(fnBlock.clientId, id, 0, 0);
      });
    }
    return (0, _react.createElement)(_blockEditor.RichTextToolbarButton, {
      icon: _icons.formatListNumbered,
      title: (0, _i18n.__)('Footnote'),
      onClick: onClick,
      isActive: isObjectActive
    });
  }
};
//# sourceMappingURL=format.js.map