"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _url = require("@wordpress/url");
var _data = require("@wordpress/data");
var _notices = require("@wordpress/notices");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

const EmbedLinkSettings = ({
  autoFocus,
  value,
  label,
  isVisible,
  onClose,
  onSubmit,
  withBottomSheet
}) => {
  const url = (0, _element.useRef)(value);
  const [inputURL, setInputURL] = (0, _element.useState)(value);
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const linkSettingsOptions = {
    url: {
      label: (0, _i18n.sprintf)(
      // translators: %s: embed block variant's label e.g: "Twitter".
      (0, _i18n.__)('%s link'), label),
      placeholder: (0, _i18n.__)('Add link'),
      autoFocus,
      autoFill: true
    },
    footer: {
      label: (0, _react.createElement)(_components.FooterMessageLink, {
        href: (0, _i18n.__)('https://wordpress.org/documentation/article/embeds/'),
        value: (0, _i18n.__)('Learn more about embeds')
      }),
      separatorType: 'topFullWidth'
    }
  };
  const onDismiss = (0, _element.useCallback)(() => {
    if (!(0, _url.isURL)(url.current) && url.current !== '') {
      createErrorNotice((0, _i18n.__)('Invalid URL. Please enter a valid URL.'));
      // If the URL was already defined, we submit it to stop showing the embed placeholder.
      onSubmit(value);
      return;
    }
    onSubmit(url.current);
  }, [onSubmit, value]);
  (0, _element.useEffect)(() => {
    url.current = value;
    setInputURL(value);
  }, [value]);

  /**
   * If the Embed Bottom Sheet component does not utilize a bottom sheet then the onDismiss action is not
   * called. Here we are wiring the onDismiss to the onClose callback that gets triggered when input is submitted.
   */
  const performOnCloseOperations = (0, _element.useCallback)(() => {
    if (onClose) {
      onClose();
    }
    if (!withBottomSheet) {
      onDismiss();
    }
  }, [onClose]);
  const onSetAttributes = (0, _element.useCallback)(attributes => {
    url.current = attributes.url;
    setInputURL(attributes.url);
  }, []);
  return (0, _react.createElement)(_components.LinkSettingsNavigation, {
    isVisible: isVisible,
    url: inputURL,
    onClose: performOnCloseOperations,
    onDismiss: onDismiss,
    setAttributes: onSetAttributes,
    options: linkSettingsOptions,
    withBottomSheet: withBottomSheet,
    showIcon: true
  });
};
var _default = exports.default = EmbedLinkSettings;
//# sourceMappingURL=embed-link-settings.native.js.map