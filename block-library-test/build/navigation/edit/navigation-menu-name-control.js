"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationMenuNameControl;
var _react = require("react");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

function NavigationMenuNameControl() {
  const [title, updateTitle] = (0, _coreData.useEntityProp)('postType', 'wp_navigation', 'title');
  return (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Menu name'),
    value: title,
    onChange: updateTitle
  });
}
//# sourceMappingURL=navigation-menu-name-control.js.map