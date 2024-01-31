"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _dedupe = _interopRequireDefault(require("classnames/dedupe"));
var _primitives = require("@wordpress/primitives");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _util = require("./util");
var _embedNoPreview = _interopRequireDefault(require("./embed-no-preview"));
var _wpEmbedPreview = _interopRequireDefault(require("./wp-embed-preview"));
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

const EmbedPreview = ({
  align,
  className,
  clientId,
  icon,
  insertBlocksAfter,
  isSelected,
  label,
  onFocus,
  preview,
  previewable,
  isProviderPreviewable,
  type,
  url,
  isDefaultEmbedInfo
}) => {
  const [isCaptionSelected, setIsCaptionSelected] = (0, _element.useState)(false);
  const {
    locale
  } = (0, _data.useSelect)(_blockEditor.store).getSettings();
  const wrapperStyle = _styles.default['embed-preview__wrapper'];
  const wrapperAlignStyle = _styles.default[`embed-preview__wrapper--align-${align}`];
  const sandboxAlignStyle = _styles.default[`embed-preview__sandbox--align-${align}`];
  function accessibilityLabelCreator(caption) {
    return _blockEditor.RichText.isEmpty(caption) ? /* translators: accessibility text. Empty Embed caption. */
    (0, _i18n.__)('Embed caption. Empty') : (0, _i18n.sprintf)( /* translators: accessibility text. %s: Embed caption. */
    (0, _i18n.__)('Embed caption. %s'), caption);
  }
  function onEmbedPreviewPress() {
    setIsCaptionSelected(false);
  }
  function onFocusCaption() {
    if (onFocus) {
      onFocus();
    }
    if (!isCaptionSelected) {
      setIsCaptionSelected(true);
    }
  }
  const {
    provider_url: providerUrl
  } = preview;
  const html = 'photo' === type ? (0, _util.getPhotoHtml)(preview) : preview.html;
  const parsedHost = new URL(url).host.split('.');
  const parsedHostBaseUrl = parsedHost.splice(parsedHost.length - 2, parsedHost.length - 1).join('.');
  const iframeTitle = (0, _i18n.sprintf)(
  // translators: %s: host providing embed content e.g: www.youtube.com
  (0, _i18n.__)('Embedded content from %s'), parsedHostBaseUrl);
  const sandboxClassnames = (0, _dedupe.default)(type, className, 'wp-block-embed__wrapper');
  const PreviewContent = 'wp-embed' === type ? _wpEmbedPreview.default : _components.SandBox;
  const embedWrapper = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    onPress: () => {
      if (onFocus) {
        onFocus();
      }
      if (isCaptionSelected) {
        setIsCaptionSelected(false);
      }
    }
  }, (0, _react.createElement)(_primitives.View, {
    pointerEvents: "box-only",
    style: [wrapperStyle, wrapperAlignStyle]
  }, (0, _react.createElement)(PreviewContent, {
    html: html,
    lang: locale,
    title: iframeTitle,
    type: sandboxClassnames,
    providerUrl: providerUrl,
    url: url,
    containerStyle: sandboxAlignStyle
  }))));
  return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    accessible: !isSelected,
    onPress: onEmbedPreviewPress,
    disabled: !isSelected
  }, (0, _react.createElement)(_primitives.View, null, isProviderPreviewable && previewable ? embedWrapper : (0, _react.createElement)(_embedNoPreview.default, {
    label: label,
    icon: icon,
    isSelected: isSelected,
    onPress: () => setIsCaptionSelected(false),
    previewable: previewable,
    isDefaultEmbedInfo: isDefaultEmbedInfo
  }), (0, _react.createElement)(_blockEditor.BlockCaption, {
    accessibilityLabelCreator: accessibilityLabelCreator,
    accessible: true,
    clientId: clientId,
    insertBlocksAfter: insertBlocksAfter,
    isSelected: isCaptionSelected,
    onFocus: onFocusCaption
  })));
};
var _default = exports.default = (0, _element.memo)(EmbedPreview);
//# sourceMappingURL=embed-preview.native.js.map