"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _reactNativeVideo = _interopRequireDefault(require("react-native-video"));
var _dedupe = _interopRequireDefault(require("classnames/dedupe"));
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _icons = require("@wordpress/icons");
var _url = require("@wordpress/url");
var _editPost = require("@wordpress/edit-post");
var _style = _interopRequireDefault(require("./style.scss"));
var _shared = require("./shared");
var _controls = _interopRequireDefault(require("./controls"));
var _useCoverIsDark = _interopRequireDefault(require("./use-cover-is-dark"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

// eslint-disable-next-line no-restricted-imports

/**
 * Internal dependencies
 */

/**
 * Constants
 */
const INNER_BLOCKS_TEMPLATE = [['core/paragraph', {
  align: 'center',
  placeholder: (0, _i18n.__)('Write title…')
}]];
function useIsScreenReaderEnabled() {
  const [isScreenReaderEnabled, setIsScreenReaderEnabled] = (0, _element.useState)(false);
  (0, _element.useEffect)(() => {
    let mounted = true;
    const changeListener = _reactNative.AccessibilityInfo.addEventListener('screenReaderChanged', enabled => setIsScreenReaderEnabled(enabled));
    _reactNative.AccessibilityInfo.isScreenReaderEnabled().then(screenReaderEnabled => {
      if (mounted && screenReaderEnabled) {
        setIsScreenReaderEnabled(screenReaderEnabled);
      }
    });
    return () => {
      mounted = false;
      changeListener.remove();
    };
  }, []);
  return isScreenReaderEnabled;
}
const Cover = ({
  attributes,
  getStylesFromColorScheme,
  isParentSelected,
  onFocus,
  setAttributes,
  openGeneralSidebar,
  closeSettingsBottomSheet,
  isSelected,
  selectBlock,
  blockWidth,
  hasInnerBlocks
}) => {
  const {
    backgroundType,
    dimRatio,
    focalPoint,
    minHeight,
    url,
    id,
    style,
    customOverlayColor,
    minHeightUnit = 'px',
    allowedBlocks,
    templateLock,
    customGradient,
    gradient,
    overlayColor,
    isDark
  } = attributes;
  const isScreenReaderEnabled = useIsScreenReaderEnabled();
  (0, _element.useEffect)(() => {
    // Sync with local media store.
    (0, _reactNativeBridge.mediaUploadSync)();
  }, []);
  const convertedMinHeight = (0, _components.useConvertUnitToMobile)(minHeight || _shared.COVER_DEFAULT_HEIGHT, minHeightUnit);
  const isImage = backgroundType === _blockEditor.MEDIA_TYPE_IMAGE;
  const THEME_COLORS_COUNT = 4;
  const colorsDefault = (0, _components.useMobileGlobalStylesColors)();
  const coverDefaultPalette = (0, _element.useMemo)(() => {
    return {
      colors: colorsDefault.slice(0, THEME_COLORS_COUNT)
    };
  }, [colorsDefault]);
  const gradients = (0, _components.useMobileGlobalStylesColors)('gradients');
  const gradientValue = customGradient || (0, _blockEditor.getGradientValueBySlug)(gradients, gradient);
  const overlayColorValue = (0, _blockEditor.getColorObjectByAttributeValues)(colorsDefault, overlayColor);
  const hasBackground = !!(url || style && style.color && style.color.background || attributes.overlayColor || overlayColorValue.color || customOverlayColor || gradientValue);
  const hasOnlyColorBackground = !url && (hasBackground || hasInnerBlocks);
  const [isCustomColorPickerShowing, setCustomColorPickerShowing] = (0, _element.useState)(false);
  const openMediaOptionsRef = (0, _element.useRef)();

  // Initialize uploading flag to false, awaiting sync.
  const [isUploadInProgress, setIsUploadInProgress] = (0, _element.useState)(false);

  // Initialize upload failure flag to true if url is local.
  const [didUploadFail, setDidUploadFail] = (0, _element.useState)(id && (0, _url.getProtocol)(url) === 'file:');

  // Don't show failure if upload is in progress.
  const shouldShowFailure = didUploadFail && !isUploadInProgress;
  const onSelectMedia = media => {
    setDidUploadFail(false);
    const mediaAttributes = (0, _shared.attributesFromMedia)(media);
    setAttributes({
      ...mediaAttributes,
      focalPoint: undefined,
      useFeaturedImage: undefined,
      dimRatio: dimRatio === 100 ? 50 : dimRatio,
      isDark: undefined
    });
  };
  const onMediaPressed = () => {
    if (isUploadInProgress) {
      (0, _reactNativeBridge.requestImageUploadCancelDialog)(id);
    } else if (shouldShowFailure) {
      (0, _reactNativeBridge.requestImageFailedRetryDialog)(id);
    } else if (isImage && url) {
      (0, _reactNativeBridge.requestImageFullscreenPreview)(url);
    }
  };
  const [isVideoLoading, setIsVideoLoading] = (0, _element.useState)(true);
  const onVideoLoadStart = () => {
    setIsVideoLoading(true);
  };
  const onVideoLoad = () => {
    setIsVideoLoading(false);
  };
  const onClearMedia = (0, _element.useCallback)(() => {
    setAttributes({
      focalPoint: undefined,
      hasParallax: undefined,
      id: undefined,
      url: undefined
    });
    closeSettingsBottomSheet();
  }, [closeSettingsBottomSheet]);
  function setColor(color) {
    var _colorValue$slug, _ref;
    const colorValue = (0, _blockEditor.getColorObjectByColorValue)(colorsDefault, color);
    setAttributes({
      // Clear all related attributes (only one should be set).
      overlayColor: (_colorValue$slug = colorValue?.slug) !== null && _colorValue$slug !== void 0 ? _colorValue$slug : undefined,
      customOverlayColor: (_ref = !colorValue?.slug && color) !== null && _ref !== void 0 ? _ref : undefined,
      gradient: undefined,
      customGradient: undefined
    });
  }
  function openColorPicker() {
    selectBlock();
    setCustomColorPickerShowing(true);
    openGeneralSidebar();
  }
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const isCoverDark = (0, _useCoverIsDark.default)(isDark, url, dimRatio, overlayColorValue?.color);
  (0, _element.useEffect)(() => {
    // This side-effect should not create an undo level.
    __unstableMarkNextChangeAsNotPersistent();
    // Used to set a default color for its InnerBlocks
    // since there's no system to inherit styles yet
    // the RichText component will check if there are
    // parent styles for the current block. If there are,
    // it will use that color instead.
    setAttributes({
      isDark: isCoverDark,
      childrenStyles: isCoverDark ? _style.default.defaultColor : _style.default.defaultColorLightMode
    });

    // Ensure that "is-light" is removed from "className" attribute if cover background is dark.
    if (isCoverDark && attributes.className?.includes('is-light')) {
      const className = (0, _dedupe.default)(attributes.className, {
        'is-light': false
      });
      setAttributes({
        className: className !== '' ? className : undefined
      });
    }
  }, [isCoverDark]);
  const backgroundColor = getStylesFromColorScheme(_style.default.backgroundSolid, _style.default.backgroundSolidDark);
  const overlayStyles = [_style.default.overlay, url && {
    opacity: dimRatio / 100
  }, !gradientValue && {
    backgroundColor: customOverlayColor || overlayColorValue?.color || style?.color?.background || _style.default.overlay?.color
  },
  // While we don't support theme colors we add a default bg color.
  !overlayColorValue.color && !url ? backgroundColor : {}, isImage && isParentSelected && !isUploadInProgress && !didUploadFail && _style.default.overlaySelected];
  const placeholderIconStyle = getStylesFromColorScheme(_style.default.icon, _style.default.iconDark);
  const placeholderIcon = (0, _react.createElement)(_components.Icon, {
    icon: _icons.cover,
    ...placeholderIconStyle
  });
  const toolbarControls = open => (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.__)('Edit cover media'),
    icon: _icons.replace,
    onClick: open
  }));
  const accessibilityHint = _reactNative.Platform.OS === 'ios' ? (0, _i18n.__)('Double tap to open Action Sheet to add image or video') : (0, _i18n.__)('Double tap to open Bottom Sheet to add image or video');
  const addMediaButton = () => (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    accessibilityHint: accessibilityHint,
    accessibilityLabel: (0, _i18n.__)('Add image or video'),
    accessibilityRole: "button",
    onPress: openMediaOptionsRef.current
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default.selectImageContainer
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default.selectImage
  }, (0, _react.createElement)(_components.Icon, {
    size: 16,
    icon: _icons.image,
    ..._style.default.selectImageIcon
  }))));
  const onBottomSheetClosed = (0, _element.useCallback)(() => {
    _reactNative.InteractionManager.runAfterInteractions(() => {
      setCustomColorPickerShowing(false);
    });
  }, []);
  const selectedColorText = getStylesFromColorScheme(_style.default.selectedColorText, _style.default.selectedColorTextDark);
  const bottomLabelText = customOverlayColor ? (0, _react.createElement)(_reactNative.Text, {
    style: selectedColorText
  }, customOverlayColor.toUpperCase()) : (0, _i18n.__)('Select a color');
  const colorPickerControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.BottomSheetConsumer, null, ({
    shouldEnableBottomSheetScroll,
    shouldEnableBottomSheetMaxHeight,
    onHandleClosingBottomSheet,
    onHandleHardwareButtonPress,
    isBottomSheetContentScrolling
  }) => (0, _react.createElement)(_components.ColorPicker, {
    shouldEnableBottomSheetScroll: shouldEnableBottomSheetScroll,
    shouldEnableBottomSheetMaxHeight: shouldEnableBottomSheetMaxHeight,
    setColor: setColor,
    onNavigationBack: closeSettingsBottomSheet,
    onHandleClosingBottomSheet: onHandleClosingBottomSheet,
    onHandleHardwareButtonPress: onHandleHardwareButtonPress,
    onBottomSheetClosed: onBottomSheetClosed,
    isBottomSheetContentScrolling: isBottomSheetContentScrolling,
    bottomLabelText: bottomLabelText
  })));
  const renderContent = getMediaOptions => (0, _react.createElement)(_react.Fragment, null, renderBackground(getMediaOptions), isParentSelected && hasOnlyColorBackground && addMediaButton());
  const renderBackground = getMediaOptions => (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    accessible: !isParentSelected,
    onPress: onMediaPressed,
    disabled: !isParentSelected
  }, (0, _react.createElement)(_reactNative.View, {
    style: [_style.default.background, backgroundColor]
  }, getMediaOptions(), isParentSelected && backgroundType === _shared.VIDEO_BACKGROUND_TYPE && toolbarControls(openMediaOptionsRef.current), (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
    mediaId: id,
    onUpdateMediaProgress: () => {
      setIsUploadInProgress(true);
    },
    onFinishMediaUploadWithSuccess: ({
      mediaServerId,
      mediaUrl
    }) => {
      setIsUploadInProgress(false);
      setDidUploadFail(false);
      setAttributes({
        id: mediaServerId,
        url: mediaUrl,
        backgroundType
      });
    },
    onFinishMediaUploadWithFailure: () => {
      setIsUploadInProgress(false);
      setDidUploadFail(true);
    },
    onMediaUploadStateReset: () => {
      setIsUploadInProgress(false);
      setDidUploadFail(false);
      setAttributes({
        id: undefined,
        url: undefined
      });
    }
  }), _shared.IMAGE_BACKGROUND_TYPE === backgroundType && (0, _react.createElement)(_reactNative.View, {
    style: _style.default.imageContainer
  }, (0, _react.createElement)(_components.Image, {
    editButton: false,
    focalPoint: focalPoint || _components.IMAGE_DEFAULT_FOCAL_POINT,
    isSelected: isParentSelected,
    isUploadFailed: didUploadFail,
    isUploadInProgress: isUploadInProgress,
    onSelectMediaUploadOption: onSelectMedia,
    openMediaOptions: openMediaOptionsRef.current,
    url: url,
    width: _style.default.image?.width
  })), _shared.VIDEO_BACKGROUND_TYPE === backgroundType && (0, _react.createElement)(_reactNativeVideo.default, {
    muted: true,
    disableFocus: true,
    repeat: true,
    resizeMode: 'cover',
    source: {
      uri: url
    },
    onLoad: onVideoLoad,
    onLoadStart: onVideoLoadStart,
    style: [_style.default.background,
    // Hide Video component since it has black background while loading the source.
    {
      opacity: isVideoLoading ? 0 : 1
    }]
  })));
  if (!hasBackground && !hasInnerBlocks || isCustomColorPickerShowing) {
    return (0, _react.createElement)(_reactNative.View, null, isCustomColorPickerShowing && colorPickerControls, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      height: _style.default.mediaPlaceholderEmptyStateContainer?.height,
      backgroundColor: customOverlayColor,
      hideContent: customOverlayColor !== '' && customOverlayColor !== undefined,
      icon: placeholderIcon,
      labels: {
        title: (0, _i18n.__)('Cover')
      },
      onSelect: onSelectMedia,
      allowedTypes: _shared.ALLOWED_MEDIA_TYPES,
      onFocus: onFocus
    }, (0, _react.createElement)(_reactNative.View, {
      style: _style.default.colorPaletteWrapper,
      pointerEvents: isScreenReaderEnabled ? 'none' : 'auto'
    }, (0, _react.createElement)(_components.BottomSheetConsumer, null, ({
      shouldEnableBottomSheetScroll
    }) => (0, _react.createElement)(_components.ColorPalette, {
      enableCustomColor: true,
      customColorIndicatorStyles: _style.default.paletteColorIndicator,
      customIndicatorWrapperStyles: _style.default.paletteCustomIndicatorWrapper,
      setColor: setColor,
      onCustomPress: openColorPicker,
      defaultSettings: coverDefaultPalette,
      shouldShowCustomLabel: false,
      shouldShowCustomVerticalSeparator: false,
      shouldEnableBottomSheetScroll: shouldEnableBottomSheetScroll
    })))));
  }
  return (0, _react.createElement)(_reactNative.View, {
    style: _style.default.backgroundContainer
  }, isSelected && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_controls.default, {
    attributes: attributes,
    didUploadFail: didUploadFail,
    hasOnlyColorBackground: hasOnlyColorBackground,
    isUploadInProgress: isUploadInProgress,
    onClearMedia: onClearMedia,
    onSelectMedia: onSelectMedia,
    setAttributes: setAttributes
  })), (0, _react.createElement)(_reactNative.View, {
    pointerEvents: "box-none",
    style: [_style.default.content, {
      minHeight: convertedMinHeight
    }]
  }, (0, _react.createElement)(_blockEditor.InnerBlocks, {
    allowedBlocks: allowedBlocks,
    template: INNER_BLOCKS_TEMPLATE,
    templateLock: templateLock,
    templateInsertUpdatesSelection: true,
    blockWidth: blockWidth
  })), (0, _react.createElement)(_reactNative.View, {
    pointerEvents: "none",
    style: _style.default.overlayContainer
  }, (0, _react.createElement)(_reactNative.View, {
    style: overlayStyles
  }, gradientValue && (0, _react.createElement)(_components.Gradient, {
    gradientValue: gradientValue,
    style: _style.default.background
  }))), (0, _react.createElement)(_blockEditor.MediaUpload, {
    allowedTypes: _shared.ALLOWED_MEDIA_TYPES,
    isReplacingMedia: !hasOnlyColorBackground,
    onSelect: onSelectMedia,
    render: ({
      open,
      getMediaOptions
    }) => {
      openMediaOptionsRef.current = open;
      return renderContent(getMediaOptions);
    }
  }), isImage && url && openMediaOptionsRef.current && isParentSelected && !isUploadInProgress && !didUploadFail && (0, _react.createElement)(_reactNative.View, {
    style: _style.default.imageEditButton
  }, (0, _react.createElement)(_components.ImageEditingButton, {
    onSelectMediaUploadOption: onSelectMedia,
    openMediaOptions: openMediaOptionsRef.current,
    pickerOptions: [{
      destructiveButton: true,
      id: 'clearMedia',
      label: (0, _i18n.__)('Clear Media'),
      onPress: onClearMedia,
      separated: true,
      value: 'clearMedia'
    }],
    url: url
  })), shouldShowFailure && (0, _react.createElement)(_reactNative.View, {
    pointerEvents: "none",
    style: _style.default.uploadFailedContainer
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default.uploadFailed
  }, (0, _react.createElement)(_components.Icon, {
    icon: _icons.warning,
    ..._style.default.uploadFailedIcon
  }))));
};
var _default = exports.default = (0, _compose.compose)([(0, _data.withSelect)((select, {
  clientId
}) => {
  const {
    getSelectedBlockClientId,
    getBlock
  } = select(_blockEditor.store);
  const selectedBlockClientId = getSelectedBlockClientId();
  const hasInnerBlocks = getBlock(clientId)?.innerBlocks.length > 0;
  return {
    isParentSelected: selectedBlockClientId === clientId,
    hasInnerBlocks
  };
}), (0, _data.withDispatch)((dispatch, {
  clientId
}) => {
  const {
    openGeneralSidebar
  } = dispatch(_editPost.store);
  const {
    selectBlock
  } = dispatch(_blockEditor.store);
  return {
    openGeneralSidebar: () => openGeneralSidebar('edit-post/block'),
    closeSettingsBottomSheet() {
      dispatch(_editPost.store).closeGeneralSidebar();
    },
    selectBlock: () => selectBlock(clientId)
  };
}), _compose.withPreferredColorScheme])(Cover);
//# sourceMappingURL=edit.native.js.map