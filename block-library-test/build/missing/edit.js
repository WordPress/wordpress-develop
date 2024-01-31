"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _blocks = require("@wordpress/blocks");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _dom = require("@wordpress/dom");
/**
 * WordPress dependencies
 */

function MissingBlockWarning({
  attributes,
  convertToHTML,
  clientId
}) {
  const {
    originalName,
    originalUndelimitedContent
  } = attributes;
  const hasContent = !!originalUndelimitedContent;
  const {
    hasFreeformBlock,
    hasHTMLBlock
  } = (0, _data.useSelect)(select => {
    const {
      canInsertBlockType,
      getBlockRootClientId
    } = select(_blockEditor.store);
    return {
      hasFreeformBlock: canInsertBlockType('core/freeform', getBlockRootClientId(clientId)),
      hasHTMLBlock: canInsertBlockType('core/html', getBlockRootClientId(clientId))
    };
  }, [clientId]);
  const actions = [];
  let messageHTML;
  const convertToHtmlButton = (0, _react.createElement)(_components.Button, {
    key: "convert",
    onClick: convertToHTML,
    variant: "primary"
  }, (0, _i18n.__)('Keep as HTML'));
  if (hasContent && !hasFreeformBlock && !originalName) {
    if (hasHTMLBlock) {
      messageHTML = (0, _i18n.__)('It appears you are trying to use the deprecated Classic block. You can leave this block intact, convert its content to a Custom HTML block, or remove it entirely. Alternatively, you can refresh the page to use the Classic block.');
      actions.push(convertToHtmlButton);
    } else {
      messageHTML = (0, _i18n.__)('It appears you are trying to use the deprecated Classic block. You can leave this block intact, or remove it entirely. Alternatively, you can refresh the page to use the Classic block.');
    }
  } else if (hasContent && hasHTMLBlock) {
    messageHTML = (0, _i18n.sprintf)( /* translators: %s: block name */
    (0, _i18n.__)('Your site doesn’t include support for the "%s" block. You can leave this block intact, convert its content to a Custom HTML block, or remove it entirely.'), originalName);
    actions.push(convertToHtmlButton);
  } else {
    messageHTML = (0, _i18n.sprintf)( /* translators: %s: block name */
    (0, _i18n.__)('Your site doesn’t include support for the "%s" block. You can leave this block intact or remove it entirely.'), originalName);
  }
  return (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)({
      className: 'has-warning'
    })
  }, (0, _react.createElement)(_blockEditor.Warning, {
    actions: actions
  }, messageHTML), (0, _react.createElement)(_element.RawHTML, null, (0, _dom.safeHTML)(originalUndelimitedContent)));
}
const MissingEdit = (0, _data.withDispatch)((dispatch, {
  clientId,
  attributes
}) => {
  const {
    replaceBlock
  } = dispatch(_blockEditor.store);
  return {
    convertToHTML() {
      replaceBlock(clientId, (0, _blocks.createBlock)('core/html', {
        content: attributes.originalUndelimitedContent
      }));
    }
  };
})(MissingBlockWarning);
var _default = exports.default = MissingEdit;
//# sourceMappingURL=edit.js.map