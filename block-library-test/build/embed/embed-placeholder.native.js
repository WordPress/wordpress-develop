"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _styles = _interopRequireDefault(require("./styles.scss"));
var _gridicons = require("../../../components/src/mobile/gridicons");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const hitSlop = {
  top: 22,
  bottom: 22,
  left: 22,
  right: 22
};
const EmbedPlaceholder = ({
  icon,
  isSelected,
  label,
  onPress,
  cannotEmbed,
  fallback,
  tryAgain,
  openEmbedLinkSettings
}) => {
  const containerSelectedStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed__container-selected'], _styles.default['embed__container-selected--dark']);
  const containerStyle = [(0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__container, _styles.default['embed__container--dark']), isSelected && containerSelectedStyle];
  const labelStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__label, _styles.default['embed__label--dark']);
  const descriptionStyle = _styles.default.embed__description;
  const descriptionErrorStyle = _styles.default['embed__description--error'];
  const actionStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__action, _styles.default['embed__action--dark']);
  const embedIconErrorStyle = _styles.default['embed__icon--error'];
  const buttonStyles = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__button, _styles.default['embed__button--dark']);
  const iconStyles = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__icon, _styles.default['embed__icon--dark']);
  const cannotEmbedMenuPickerRef = (0, _element.useRef)();
  const errorPickerOptions = {
    retry: {
      id: 'retryOption',
      label: (0, _i18n.__)('Retry'),
      value: 'retryOption',
      onSelect: tryAgain
    },
    convertToLink: {
      id: 'convertToLinkOption',
      label: (0, _i18n.__)('Convert to link'),
      value: 'convertToLinkOption',
      onSelect: fallback
    },
    editLink: {
      id: 'editLinkOption',
      label: (0, _i18n.__)('Edit link'),
      value: 'editLinkOption',
      onSelect: openEmbedLinkSettings
    }
  };
  const options = [cannotEmbed && errorPickerOptions.retry, cannotEmbed && errorPickerOptions.convertToLink, cannotEmbed && errorPickerOptions.editLink].filter(Boolean);
  function onPickerSelect(value) {
    const selectedItem = options.find(item => item.value === value);
    selectedItem.onSelect();
  }

  // When the content cannot be embedded the onPress should trigger the Picker instead of the onPress prop.
  function resolveOnPressEvent() {
    if (cannotEmbed) {
      cannotEmbedMenuPickerRef.current?.presentPicker();
    } else {
      onPress();
    }
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_reactNative.View, {
    style: containerStyle
  }, cannotEmbed ? (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.Icon, {
    icon: _gridicons.noticeOutline,
    fill: embedIconErrorStyle.fill,
    style: embedIconErrorStyle
  }), (0, _react.createElement)(_reactNative.Text, {
    style: [descriptionStyle, descriptionErrorStyle]
  }, (0, _i18n.__)('Unable to embed media')), (0, _react.createElement)(_reactNative.TouchableOpacity, {
    activeOpacity: 0.5,
    accessibilityRole: 'button',
    accessibilityHint: (0, _i18n.__)('Double tap to view embed options.'),
    style: buttonStyles,
    hitSlop: hitSlop,
    onPress: resolveOnPressEvent,
    disabled: !isSelected
  }, (0, _react.createElement)(_reactNative.Text, {
    style: actionStyle
  }, (0, _i18n.__)('More options'))), (0, _react.createElement)(_components.Picker, {
    title: (0, _i18n.__)('Embed options'),
    ref: cannotEmbedMenuPickerRef,
    options: options,
    onChange: onPickerSelect,
    hideCancelButton: true,
    leftAlign: true
  })) : (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_reactNative.View, {
    style: _styles.default['embed__placeholder-header']
  }, (0, _react.createElement)(_blockEditor.BlockIcon, {
    icon: icon,
    fill: iconStyles.fill
  }), (0, _react.createElement)(_reactNative.Text, {
    style: labelStyle
  }, label)), (0, _react.createElement)(_reactNative.TouchableOpacity, {
    activeOpacity: 0.5,
    accessibilityRole: 'button',
    accessibilityHint: (0, _i18n.__)('Double tap to add a link.'),
    style: buttonStyles,
    hitSlop: hitSlop,
    onPress: resolveOnPressEvent,
    disabled: !isSelected
  }, (0, _react.createElement)(_reactNative.Text, {
    style: actionStyle
  }, (0, _i18n.__)('Add link'))))));
};
var _default = exports.default = EmbedPlaceholder;
//# sourceMappingURL=embed-placeholder.native.js.map