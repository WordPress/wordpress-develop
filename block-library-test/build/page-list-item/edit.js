"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PageListItemEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _htmlEntities = require("@wordpress/html-entities");
var _icons = require("../navigation-link/icons");
var _utils = require("../navigation/edit/utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function useFrontPageId() {
  return (0, _data.useSelect)(select => {
    const canReadSettings = select(_coreData.store).canUser('read', 'settings');
    if (!canReadSettings) {
      return undefined;
    }
    const site = select(_coreData.store).getEntityRecord('root', 'site');
    return site?.show_on_front === 'page' && site?.page_on_front;
  }, []);
}
function PageListItemEdit({
  context,
  attributes
}) {
  const {
    id,
    label,
    link,
    hasChildren,
    title
  } = attributes;
  const isNavigationChild = ('showSubmenuIcon' in context);
  const frontPageId = useFrontPageId();
  const innerBlocksColors = (0, _utils.getColors)(context, true);
  const navigationChildBlockProps = (0, _utils.getNavigationChildBlockProps)(innerBlocksColors);
  const blockProps = (0, _blockEditor.useBlockProps)(navigationChildBlockProps, {
    className: 'wp-block-pages-list__item'
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps);
  return (0, _react.createElement)("li", {
    key: id,
    className: (0, _classnames.default)('wp-block-pages-list__item', {
      'has-child': hasChildren,
      'wp-block-navigation-item': isNavigationChild,
      'open-on-click': context.openSubmenusOnClick,
      'open-on-hover-click': !context.openSubmenusOnClick && context.showSubmenuIcon,
      'menu-item-home': id === frontPageId
    })
  }, hasChildren && context.openSubmenusOnClick ? (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("button", {
    type: "button",
    className: "wp-block-navigation-item__content wp-block-navigation-submenu__toggle",
    "aria-expanded": "false"
  }, (0, _htmlEntities.decodeEntities)(label)), (0, _react.createElement)("span", {
    className: "wp-block-page-list__submenu-icon wp-block-navigation__submenu-icon"
  }, (0, _react.createElement)(_icons.ItemSubmenuIcon, null))) : (0, _react.createElement)("a", {
    className: (0, _classnames.default)('wp-block-pages-list__item__link', {
      'wp-block-navigation-item__content': isNavigationChild
    }),
    href: link
  }, (0, _htmlEntities.decodeEntities)(title)), hasChildren && (0, _react.createElement)(_react.Fragment, null, !context.openSubmenusOnClick && context.showSubmenuIcon && (0, _react.createElement)("button", {
    className: "wp-block-navigation-item__content wp-block-navigation-submenu__toggle wp-block-page-list__submenu-icon wp-block-navigation__submenu-icon",
    "aria-expanded": "false",
    type: "button"
  }, (0, _react.createElement)(_icons.ItemSubmenuIcon, null)), (0, _react.createElement)("ul", {
    ...innerBlocksProps
  })));
}
//# sourceMappingURL=edit.js.map