"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _keycodes = require("@wordpress/keycodes");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _socialList = require("./social-list");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const SocialLinkURLPopover = ({
  url,
  setAttributes,
  setPopover,
  popoverAnchor,
  clientId
}) => {
  const {
    removeBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  return (0, _react.createElement)(_blockEditor.URLPopover, {
    anchor: popoverAnchor,
    onClose: () => setPopover(false)
  }, (0, _react.createElement)("form", {
    className: "block-editor-url-popover__link-editor",
    onSubmit: event => {
      event.preventDefault();
      setPopover(false);
    }
  }, (0, _react.createElement)("div", {
    className: "block-editor-url-input"
  }, (0, _react.createElement)(_blockEditor.URLInput, {
    __nextHasNoMarginBottom: true,
    value: url,
    onChange: nextURL => setAttributes({
      url: nextURL
    }),
    placeholder: (0, _i18n.__)('Enter address'),
    disableSuggestions: true,
    onKeyDown: event => {
      if (!!url || event.defaultPrevented || ![_keycodes.BACKSPACE, _keycodes.DELETE].includes(event.keyCode)) {
        return;
      }
      removeBlock(clientId);
    }
  })), (0, _react.createElement)(_components.Button, {
    icon: _icons.keyboardReturn,
    label: (0, _i18n.__)('Apply'),
    type: "submit"
  })));
};
const SocialLinkEdit = ({
  attributes,
  context,
  isSelected,
  setAttributes,
  clientId
}) => {
  const {
    url,
    service,
    label,
    rel
  } = attributes;
  const {
    showLabels,
    iconColor,
    iconColorValue,
    iconBackgroundColor,
    iconBackgroundColorValue
  } = context;
  const [showURLPopover, setPopover] = (0, _element.useState)(false);
  const classes = (0, _classnames.default)('wp-social-link', 'wp-social-link-' + service, {
    'wp-social-link__is-incomplete': !url,
    [`has-${iconColor}-color`]: iconColor,
    [`has-${iconBackgroundColor}-background-color`]: iconBackgroundColor
  });

  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = (0, _element.useState)(null);
  const IconComponent = (0, _socialList.getIconBySite)(service);
  const socialLinkName = (0, _socialList.getNameBySite)(service);
  const socialLinkLabel = label !== null && label !== void 0 ? label : socialLinkName;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes,
    style: {
      color: iconColorValue,
      backgroundColor: iconBackgroundColorValue
    }
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.sprintf)( /* translators: %s: name of the social service. */
    (0, _i18n.__)('%s label'), socialLinkName),
    initialOpen: false
  }, (0, _react.createElement)(_components.PanelRow, null, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link label'),
    help: (0, _i18n.__)('Briefly describe the link to help screen reader users.'),
    value: label || '',
    onChange: value => setAttributes({
      label: value
    })
  })))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link rel'),
    value: rel || '',
    onChange: value => setAttributes({
      rel: value
    })
  })), (0, _react.createElement)("li", {
    ...blockProps
  }, (0, _react.createElement)(_components.Button, {
    className: "wp-block-social-link-anchor",
    ref: setPopoverAnchor,
    onClick: () => setPopover(true)
  }, (0, _react.createElement)(IconComponent, null), (0, _react.createElement)("span", {
    className: (0, _classnames.default)('wp-block-social-link-label', {
      'screen-reader-text': !showLabels
    })
  }, socialLinkLabel), isSelected && showURLPopover && (0, _react.createElement)(SocialLinkURLPopover, {
    url: url,
    setAttributes: setAttributes,
    setPopover: setPopover,
    popoverAnchor: popoverAnchor,
    clientId: clientId
  }))));
};
var _default = exports.default = SocialLinkEdit;
//# sourceMappingURL=edit.js.map