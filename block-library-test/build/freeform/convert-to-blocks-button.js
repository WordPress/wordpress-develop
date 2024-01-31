"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const ConvertToBlocksButton = ({
  clientId
}) => {
  const {
    replaceBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const block = (0, _data.useSelect)(select => {
    return select(_blockEditor.store).getBlock(clientId);
  }, [clientId]);
  return (0, _react.createElement)(_components.ToolbarButton, {
    onClick: () => replaceBlocks(block.clientId, (0, _blocks.rawHandler)({
      HTML: (0, _blocks.serialize)(block)
    }))
  }, (0, _i18n.__)('Convert to blocks'));
};
var _default = exports.default = ConvertToBlocksButton;
//# sourceMappingURL=convert-to-blocks-button.js.map