"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _list = _interopRequireDefault(require("./list"));
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function save({
  attributes: {
    headings = []
  }
}) {
  if (headings.length === 0) {
    return null;
  }
  return (0, _react.createElement)("nav", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)("ol", null, (0, _react.createElement)(_list.default, {
    nestedHeadingList: (0, _utils.linearToNestedHeadingList)(headings)
  })));
}
//# sourceMappingURL=save.js.map