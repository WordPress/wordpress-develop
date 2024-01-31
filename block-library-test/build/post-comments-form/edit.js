"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostCommentsFormEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _form = _interopRequireDefault(require("./form"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function PostCommentsFormEdit({
  attributes,
  context,
  setAttributes
}) {
  const {
    textAlign
  } = attributes;
  const {
    postId,
    postType
  } = context;
  const instanceId = (0, _compose.useInstanceId)(PostCommentsFormEdit);
  const instanceIdDesc = (0, _i18n.sprintf)('comments-form-edit-%d-desc', instanceId);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    'aria-describedby': instanceIdDesc
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
  }, (0, _react.createElement)(_form.default, {
    postId: postId,
    postType: postType
  }), (0, _react.createElement)(_components.VisuallyHidden, {
    id: instanceIdDesc
  }, (0, _i18n.__)('Comments form disabled in editor.'))));
}
//# sourceMappingURL=edit.js.map