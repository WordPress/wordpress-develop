"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _data = require("@wordpress/data");
var _socialList = require("./social-list");
var _editor = _interopRequireDefault(require("./editor.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DEFAULT_ACTIVE_ICON_STYLES = {
  backgroundColor: '#f0f0f0',
  color: '#444'
};
const ANIMATION_DELAY = 300;
const ANIMATION_DURATION = 400;
const linkSettingsOptions = {
  url: {
    label: (0, _i18n.__)('URL'),
    placeholder: (0, _i18n.__)('Add URL'),
    autoFocus: true
  },
  linkLabel: {
    label: (0, _i18n.__)('Link label'),
    placeholder: (0, _i18n.__)('None')
  },
  footer: {
    label: (0, _i18n.__)('Briefly describe the link to help screen reader user')
  }
};
const SocialLinkEdit = ({
  attributes,
  setAttributes,
  isSelected,
  onFocus,
  name
}) => {
  const {
    url,
    service = name
  } = attributes;
  const [isLinkSheetVisible, setIsLinkSheetVisible] = (0, _element.useState)(false);
  const [hasUrl, setHasUrl] = (0, _element.useState)(!!url);
  const activeIcon = _editor.default[`wp-social-link-${service}`] || _editor.default[`wp-social-link`] || DEFAULT_ACTIVE_ICON_STYLES;
  const animatedValue = (0, _element.useRef)(new _reactNative.Animated.Value(0)).current;
  const IconComponent = (0, _socialList.getIconBySite)(service)();
  const socialLinkName = (0, _socialList.getNameBySite)(service);

  // When new social icon is added link sheet is opened automatically.
  (0, _element.useEffect)(() => {
    if (isSelected && !url) {
      setIsLinkSheetVisible(true);
    }
  }, []);
  (0, _element.useEffect)(() => {
    if (!url) {
      setHasUrl(false);
      animatedValue.setValue(0);
    } else if (url) {
      animateColors();
    }
  }, [url]);
  const interpolationColors = {
    opacity: animatedValue.interpolate({
      inputRange: [0, 1],
      outputRange: [0.3, 1]
    })
  };
  const {
    opacity
  } = hasUrl ? activeIcon : interpolationColors;
  function animateColors() {
    _reactNative.Animated.sequence([_reactNative.Animated.delay(ANIMATION_DELAY), _reactNative.Animated.timing(animatedValue, {
      toValue: 1,
      duration: ANIMATION_DURATION,
      easing: _reactNative.Easing.circle,
      useNativeDriver: false
    })]).start(() => setHasUrl(true));
  }
  const onCloseSettingsSheet = (0, _element.useCallback)(() => {
    setIsLinkSheetVisible(false);
  }, []);
  const onOpenSettingsSheet = (0, _element.useCallback)(() => {
    setIsLinkSheetVisible(true);
  }, []);
  const onEmptyURL = (0, _element.useCallback)(() => {
    animatedValue.setValue(0);
    setHasUrl(false);
  }, [animatedValue]);
  function onIconPress() {
    if (isSelected) {
      setIsLinkSheetVisible(true);
    } else {
      onFocus();
    }
  }
  const accessibilityHint = url ? (0, _i18n.sprintf)(
  // translators: %s: social link name e.g: "Instagram".
  (0, _i18n.__)('%s has URL set'), socialLinkName) : (0, _i18n.sprintf)(
  // translators: %s: social link name e.g: "Instagram".
  (0, _i18n.__)('%s has no URL set'), socialLinkName);
  return (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.container
  }, isSelected && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.sprintf)(
    // translators: %s: social link name e.g: "Instagram".
    (0, _i18n.__)('Add link to %s'), socialLinkName),
    icon: _icons.link,
    onClick: onOpenSettingsSheet,
    isActive: url
  }))), (0, _react.createElement)(_components.LinkSettingsNavigation, {
    isVisible: isLinkSheetVisible,
    url: attributes.url,
    label: attributes.label,
    rel: attributes.rel,
    onEmptyURL: onEmptyURL,
    onClose: onCloseSettingsSheet,
    setAttributes: setAttributes,
    options: linkSettingsOptions,
    withBottomSheet: true
  })), (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    onPress: onIconPress,
    accessibilityRole: 'button',
    accessibilityLabel: (0, _i18n.sprintf)(
    // translators: %s: social link name e.g: "Instagram".
    (0, _i18n.__)('%s social icon'), socialLinkName),
    accessibilityHint: accessibilityHint
  }, (0, _react.createElement)(_reactNative.Animated.View, {
    style: [_editor.default.iconContainer, {
      backgroundColor: activeIcon.backgroundColor,
      opacity
    }]
  }, (0, _react.createElement)(_icons.Icon, {
    animated: true,
    icon: IconComponent,
    style: {
      color: activeIcon.color
    }
  }))));
};
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getBlock
  } = select(_blockEditor.store);
  const block = getBlock(clientId);
  const name = block?.name.substring(17);
  return {
    name
  };
})])(SocialLinkEdit);
//# sourceMappingURL=edit.native.js.map