"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function save({
  attributes
}) {
  return (0, _react.createElement)("pre", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "code"
    // To do: `escape` encodes characters in shortcodes and URLs to
    // prevent embedding in PHP. Ideally checks for the code block,
    // or pre/code tags, should be made on the PHP side?
    ,
    value: (0, _utils.escape)(attributes.content.toString())
  }));
}
//# sourceMappingURL=save.js.map