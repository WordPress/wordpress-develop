"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = AccessibleMenuDescription;
var _react = require("react");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
var _accessibleDescription = _interopRequireDefault(require("./accessible-description"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function AccessibleMenuDescription({
  id
}) {
  const [menuTitle] = (0, _coreData.useEntityProp)('postType', 'wp_navigation', 'title');
  /* translators: %s: Title of a Navigation Menu post. */
  const description = (0, _i18n.sprintf)((0, _i18n.__)(`Navigation menu: "%s"`), menuTitle);
  return (0, _react.createElement)(_accessibleDescription.default, {
    id: id
  }, description);
}
//# sourceMappingURL=accessible-menu-description.js.map