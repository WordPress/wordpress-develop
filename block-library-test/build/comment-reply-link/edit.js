"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Renders the `core/comment-reply-link` block on the editor.
 *
 * @param {Object} props                      React props.
 * @param {Object} props.setAttributes        Callback for updating block attributes.
 * @param {Object} props.attributes           Block attributes.
 * @param {string} props.attributes.textAlign The `textAlign` attribute.
 *
 * @return {JSX.Element} React element.
 */
function Edit({
  setAttributes,
  attributes: {
    textAlign
  }
}) {
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const blockControls = (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  }));
  return (0, _react.createElement)(_react.Fragment, null, blockControls, (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)("a", {
    href: "#comment-reply-pseudo-link",
    onClick: event => event.preventDefault()
  }, (0, _i18n.__)('Reply'))));
}
var _default = exports.default = Edit;
//# sourceMappingURL=edit.js.map