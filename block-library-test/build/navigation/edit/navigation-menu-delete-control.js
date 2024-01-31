"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationMenuDeleteControl;
var _react = require("react");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

function NavigationMenuDeleteControl({
  onDelete
}) {
  const [isConfirmModalVisible, setIsConfirmModalVisible] = (0, _element.useState)(false);
  const id = (0, _coreData.useEntityId)('postType', 'wp_navigation');
  const [title] = (0, _coreData.useEntityProp)('postType', 'wp_navigation', 'title');
  const {
    deleteEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.Button, {
    className: "wp-block-navigation-delete-menu-button",
    variant: "secondary",
    isDestructive: true,
    onClick: () => {
      setIsConfirmModalVisible(true);
    }
  }, (0, _i18n.__)('Delete menu')), isConfirmModalVisible && (0, _react.createElement)(_components.Modal, {
    title: (0, _i18n.sprintf)( /* translators: %s: the name of a menu to delete */
    (0, _i18n.__)('Delete %s'), title),
    onRequestClose: () => setIsConfirmModalVisible(false)
  }, (0, _react.createElement)("p", null, (0, _i18n.__)('Are you sure you want to delete this navigation menu?')), (0, _react.createElement)(_components.__experimentalHStack, {
    justify: "right"
  }, (0, _react.createElement)(_components.Button, {
    variant: "tertiary",
    onClick: () => {
      setIsConfirmModalVisible(false);
    }
  }, (0, _i18n.__)('Cancel')), (0, _react.createElement)(_components.Button, {
    variant: "primary",
    onClick: () => {
      deleteEntityRecord('postType', 'wp_navigation', id, {
        force: true
      });
      onDelete(title);
    }
  }, (0, _i18n.__)('Confirm')))));
}
//# sourceMappingURL=navigation-menu-delete-control.js.map