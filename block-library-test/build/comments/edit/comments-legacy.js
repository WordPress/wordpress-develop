"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsLegacy;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _placeholder = _interopRequireDefault(require("./placeholder"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function CommentsLegacy({
  attributes,
  setAttributes,
  context: {
    postType,
    postId
  }
}) {
  const {
    textAlign
  } = attributes;
  const actions = [(0, _react.createElement)(_components.Button, {
    key: "convert",
    onClick: () => void setAttributes({
      legacy: false
    }),
    variant: "primary"
  }, (0, _i18n.__)('Switch to editable mode'))];
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.Warning, {
    actions: actions
  }, (0, _i18n.__)('Comments block: You’re currently using the legacy version of the block. ' + 'The following is just a placeholder - the final styling will likely look different. ' + 'For a better representation and more customization options, ' + 'switch the block to its editable mode.')), (0, _react.createElement)(_placeholder.default, {
    postId: postId,
    postType: postType
  })));
}
//# sourceMappingURL=comments-legacy.js.map