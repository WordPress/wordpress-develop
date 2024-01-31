"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

function DeletedNavigationWarning({
  onCreateNew
}) {
  return (0, _react.createElement)(_blockEditor.Warning, null, (0, _element.createInterpolateElement)((0, _i18n.__)('Navigation menu has been deleted or is unavailable. <button>Create a new menu?</button>'), {
    button: (0, _react.createElement)(_components.Button, {
      onClick: onCreateNew,
      variant: "link"
    })
  }));
}
var _default = exports.default = DeletedNavigationWarning;
//# sourceMappingURL=deleted-navigation-warning.js.map