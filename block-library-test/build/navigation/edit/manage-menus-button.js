"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _url = require("@wordpress/url");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const ManageMenusButton = ({
  className = '',
  disabled,
  isMenuItem = false
}) => {
  let ComponentName = _components.Button;
  if (isMenuItem) {
    ComponentName = _components.MenuItem;
  }
  return (0, _react.createElement)(ComponentName, {
    variant: "link",
    disabled: disabled,
    className: className,
    href: (0, _url.addQueryArgs)('edit.php', {
      post_type: 'wp_navigation'
    })
  }, (0, _i18n.__)('Manage menus'));
};
var _default = exports.default = ManageMenusButton;
//# sourceMappingURL=manage-menus-button.js.map