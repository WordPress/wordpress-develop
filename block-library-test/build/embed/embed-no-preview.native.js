"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _primitives = require("@wordpress/primitives");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _data = require("@wordpress/data");
var _editor = require("@wordpress/editor");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _blockEditor = require("@wordpress/block-editor");
var _styles = _interopRequireDefault(require("./styles.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const EmbedNoPreview = ({
  label,
  icon,
  isSelected,
  onPress,
  previewable,
  isDefaultEmbedInfo
}) => {
  const shouldRequestReview = (0, _element.useRef)(false);
  const [isSheetVisible, setIsSheetVisible] = (0, _element.useState)(false);
  const {
    postType
  } = (0, _data.useSelect)(select => ({
    postType: select(_editor.store).getEditedPostAttribute('type')
  }));
  const containerStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__container, _styles.default['embed__container--dark']);
  const labelStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__label, _styles.default['embed__label--dark']);
  const descriptionStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.embed__description, _styles.default['embed__description--dark']);
  const helpIconStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-no-preview__help-icon'], _styles.default['embed-no-preview__help-icon--dark']);
  const sheetIconStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-no-preview__sheet-icon'], _styles.default['embed-no-preview__sheet-icon--dark']);
  const sheetTitleStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-no-preview__sheet-title'], _styles.default['embed-no-preview__sheet-title--dark']);
  const sheetDescriptionStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-no-preview__sheet-description'], _styles.default['embed-no-preview__sheet-description--dark']);
  const sheetButtonStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-no-preview__sheet-button'], _styles.default['embed-no-preview__sheet-button--dark']);
  const previewButtonA11yHint = postType === 'page' ? (0, _i18n.__)('Double tap to preview page.') : (0, _i18n.__)('Double tap to preview post.');
  const previewButtonText = postType === 'page' ? (0, _i18n.__)('Preview page') : (0, _i18n.__)('Preview post');
  const comingSoonDescription = postType === 'page' ? (0, _i18n.sprintf)(
  // translators: %s: embed block variant's label e.g: "Twitter".
  (0, _i18n.__)('We’re working hard on adding support for %s previews. In the meantime, you can preview the embedded content on the page.'), label) : (0, _i18n.sprintf)(
  // translators: %s: embed block variant's label e.g: "Twitter".
  (0, _i18n.__)('We’re working hard on adding support for %s previews. In the meantime, you can preview the embedded content on the post.'), label);
  function onOpenSheet() {
    setIsSheetVisible(true);
  }
  function onCloseSheet() {
    setIsSheetVisible(false);
  }
  function onDismissSheet() {
    // The preview request has to be done after the bottom sheet modal is dismissed,
    // otherwise the preview native modal is not displayed.
    if (shouldRequestReview.current) {
      (0, _reactNativeBridge.requestPreview)();
    }
    shouldRequestReview.current = false;
  }
  function onPressContainer() {
    onPress();
    onOpenSheet();
  }
  function onPressHelp() {
    onPressContainer();
  }
  const embedNoProviderPreview = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    accessibilityRole: 'button',
    accessibilityHint: previewButtonA11yHint,
    disabled: !isSelected,
    onPress: onPressContainer
  }, (0, _react.createElement)(_primitives.View, {
    style: containerStyle
  }, (0, _react.createElement)(_blockEditor.BlockIcon, {
    icon: icon
  }), (0, _react.createElement)(_reactNative.Text, {
    style: labelStyle
  }, label), (0, _react.createElement)(_reactNative.Text, {
    style: descriptionStyle
  }, (0, _i18n.sprintf)(
  // translators: %s: embed block variant's label e.g: "Twitter".
  (0, _i18n.__)('%s previews not yet available'), label)), (0, _react.createElement)(_reactNative.Text, {
    style: _styles.default.embed__action
  }, previewButtonText.toUpperCase()), (0, _react.createElement)(_reactNative.TouchableOpacity, {
    accessibilityHint: (0, _i18n.__)('Tap here to show help'),
    accessibilityLabel: (0, _i18n.__)('Help button'),
    accessibilityRole: 'button',
    disabled: !isSelected,
    onPress: onPressHelp,
    style: helpIconStyle
  }, (0, _react.createElement)(_components.Icon, {
    icon: _icons.help,
    fill: helpIconStyle.fill,
    size: helpIconStyle.width
  })))), (0, _react.createElement)(_components.BottomSheet, {
    isVisible: isSheetVisible,
    hideHeader: true,
    onDismiss: onDismissSheet,
    onClose: onCloseSheet,
    testID: "embed-no-preview-modal"
  }, (0, _react.createElement)(_primitives.View, {
    style: _styles.default['embed-no-preview__container']
  }, (0, _react.createElement)(_primitives.View, {
    style: sheetIconStyle
  }, (0, _react.createElement)(_components.Icon, {
    icon: _icons.help,
    fill: sheetIconStyle.fill,
    size: sheetIconStyle.width
  })), (0, _react.createElement)(_reactNative.Text, {
    style: sheetTitleStyle
  }, isDefaultEmbedInfo ? (0, _i18n.__)('Embed block previews are coming soon') : (0, _i18n.sprintf)(
  // translators: %s: embed block variant's label e.g: "Twitter".
  (0, _i18n.__)('%s embed block previews are coming soon'), label)), (0, _react.createElement)(_reactNative.Text, {
    style: sheetDescriptionStyle
  }, comingSoonDescription)), (0, _react.createElement)(_components.TextControl, {
    label: previewButtonText,
    separatorType: "topFullWidth",
    onPress: () => {
      shouldRequestReview.current = true;
      onCloseSheet();
    },
    labelStyle: sheetButtonStyle
  }), (0, _react.createElement)(_components.TextControl, {
    label: (0, _i18n.__)('Dismiss'),
    separatorType: "topFullWidth",
    onPress: onCloseSheet,
    labelStyle: sheetButtonStyle
  })));
  return (0, _react.createElement)(_react.Fragment, null, previewable ? embedNoProviderPreview : (0, _react.createElement)(_primitives.View, {
    style: containerStyle
  }, (0, _react.createElement)(_blockEditor.BlockIcon, {
    icon: icon
  }), (0, _react.createElement)(_reactNative.Text, {
    style: labelStyle
  }, (0, _i18n.__)('No preview available'))));
};
var _default = exports.default = EmbedNoPreview;
//# sourceMappingURL=embed-no-preview.native.js.map