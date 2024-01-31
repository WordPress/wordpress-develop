"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QuoteEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _deprecated = _interopRequireDefault(require("@wordpress/deprecated"));
var _deprecated2 = require("./deprecated");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const isWebPlatform = _element.Platform.OS === 'web';
const TEMPLATE = [['core/paragraph', {}]];

/**
 * At the moment, deprecations don't handle create blocks from attributes
 * (like when using CPT templates). For this reason, this hook is necessary
 * to avoid breaking templates using the old quote block format.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} clientId   Block client ID.
 */
const useMigrateOnLoad = (attributes, clientId) => {
  const registry = (0, _data.useRegistry)();
  const {
    updateBlockAttributes,
    replaceInnerBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    // As soon as the block is loaded, migrate it to the new version.

    if (!attributes.value) {
      // No need to migrate if it doesn't have the value attribute.
      return;
    }
    const [newAttributes, newInnerBlocks] = (0, _deprecated2.migrateToQuoteV2)(attributes);
    (0, _deprecated.default)('Value attribute on the quote block', {
      since: '6.0',
      version: '6.5',
      alternative: 'inner blocks'
    });
    registry.batch(() => {
      updateBlockAttributes(clientId, newAttributes);
      replaceInnerBlocks(clientId, newInnerBlocks);
    });
  }, [attributes.value]);
};
function QuoteEdit({
  attributes,
  setAttributes,
  insertBlocksAfter,
  clientId,
  className,
  style
}) {
  const {
    align,
    citation
  } = attributes;
  useMigrateOnLoad(attributes, clientId);
  const hasSelection = (0, _data.useSelect)(select => {
    const {
      isBlockSelected,
      hasSelectedInnerBlock
    } = select(_blockEditor.store);
    return hasSelectedInnerBlock(clientId) || isBlockSelected(clientId);
  }, []);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)(className, {
      [`has-text-align-${align}`]: align
    }),
    ...(!isWebPlatform && {
      style
    })
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    templateInsertUpdatesSelection: true,
    __experimentalCaptureToolbars: true
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: align,
    onChange: nextAlign => {
      setAttributes({
        align: nextAlign
      });
    }
  })), (0, _react.createElement)(_components.BlockQuotation, {
    ...innerBlocksProps
  }, innerBlocksProps.children, (!_blockEditor.RichText.isEmpty(citation) || hasSelection) && (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "citation",
    tagName: isWebPlatform ? 'cite' : undefined,
    style: {
      display: 'block'
    },
    value: citation,
    onChange: nextCitation => {
      setAttributes({
        citation: nextCitation
      });
    },
    __unstableMobileNoFocusOnMount: true,
    "aria-label": (0, _i18n.__)('Quote citation'),
    placeholder:
    // translators: placeholder text used for the
    // citation
    (0, _i18n.__)('Add citation'),
    className: "wp-block-quote__citation",
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)())),
    ...(!isWebPlatform ? {
      textAlign: align
    } : {})
  })));
}
//# sourceMappingURL=edit.js.map