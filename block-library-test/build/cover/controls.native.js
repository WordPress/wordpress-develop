"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _reactNativeVideo = _interopRequireDefault(require("react-native-video"));
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _style = _interopRequireDefault(require("./style.scss"));
var _overlayColorSettings = _interopRequireDefault(require("./overlay-color-settings"));
var _focalPointSettingsButton = _interopRequireDefault(require("./focal-point-settings-button"));
var _shared = require("./shared");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function Controls({
  attributes,
  didUploadFail,
  hasOnlyColorBackground,
  isUploadInProgress,
  onClearMedia,
  onSelectMedia,
  setAttributes
}) {
  const {
    backgroundType,
    dimRatio,
    hasParallax,
    focalPoint,
    minHeight,
    minHeightUnit = 'px',
    url
  } = attributes;
  const CONTAINER_HEIGHT = minHeight || _shared.COVER_DEFAULT_HEIGHT;
  const onHeightChange = (0, _element.useCallback)(value => {
    if (minHeight || value !== _shared.COVER_DEFAULT_HEIGHT) {
      setAttributes({
        minHeight: value
      });
    }
  }, [minHeight]);
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['px', 'em', 'rem', 'vw', 'vh'],
    defaultValues: {
      px: 430,
      em: 20,
      rem: 20,
      vw: 20,
      vh: 50
    }
  });
  const onOpacityChange = (0, _element.useCallback)(value => {
    setAttributes({
      dimRatio: value
    });
  }, []);
  const onChangeUnit = (0, _element.useCallback)(nextUnit => {
    setAttributes({
      minHeightUnit: nextUnit,
      minHeight: nextUnit === 'px' ? Math.max(CONTAINER_HEIGHT, _shared.COVER_MIN_HEIGHT) : CONTAINER_HEIGHT
    });
  }, []);
  const [displayPlaceholder, setDisplayPlaceholder] = (0, _element.useState)(true);
  function setFocalPoint(value) {
    setAttributes({
      focalPoint: value
    });
  }
  const toggleParallax = () => {
    setAttributes({
      hasParallax: !hasParallax,
      ...(!hasParallax ? {
        focalPoint: undefined
      } : {
        focalPoint: _components.IMAGE_DEFAULT_FOCAL_POINT
      })
    });
  };
  const addMediaButtonStyle = (0, _compose.usePreferredColorSchemeStyle)(_style.default.addMediaButton, _style.default.addMediaButtonDark);
  function focalPointPosition({
    x,
    y
  } = _components.IMAGE_DEFAULT_FOCAL_POINT) {
    return {
      left: `${(hasParallax ? 0.5 : x) * 100}%`,
      top: `${(hasParallax ? 0.5 : y) * 100}%`
    };
  }
  const [videoNaturalSize, setVideoNaturalSize] = (0, _element.useState)(null);
  const videoRef = (0, _element.useRef)(null);
  const mediaBackground = (0, _compose.usePreferredColorSchemeStyle)(_style.default.mediaBackground, _style.default.mediaBackgroundDark);
  const imagePreviewStyles = [displayPlaceholder && _style.default.imagePlaceholder];
  const videoPreviewStyles = [{
    aspectRatio: videoNaturalSize && videoNaturalSize.width / videoNaturalSize.height,
    // Hide Video component since it has black background while loading the source
    opacity: displayPlaceholder ? 0 : 1
  }, _style.default.video, displayPlaceholder && _style.default.imagePlaceholder];
  const focalPointHint = !hasParallax && !displayPlaceholder && (0, _react.createElement)(_components.Icon, {
    icon: _icons.plus,
    size: _style.default.focalPointHint?.width,
    style: [_style.default.focalPointHint, focalPointPosition(focalPoint)]
  });
  const renderMediaSection = ({
    open: openMediaOptions,
    getMediaOptions
  }) => (0, _react.createElement)(_react.Fragment, null, getMediaOptions(), url ? (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.BottomSheet.Cell, {
    accessible: false,
    cellContainerStyle: [_style.default.mediaPreview, mediaBackground]
  }, (0, _react.createElement)(_reactNative.View, {
    style: _style.default.mediaInner
  }, _shared.IMAGE_BACKGROUND_TYPE === backgroundType && (0, _react.createElement)(_components.Image, {
    editButton: !displayPlaceholder,
    highlightSelected: false,
    isSelected: !displayPlaceholder,
    isUploadFailed: didUploadFail,
    isUploadInProgress: isUploadInProgress,
    mediaPickerOptions: [{
      destructiveButton: true,
      id: 'clearMedia',
      label: (0, _i18n.__)('Clear Media'),
      onPress: onClearMedia,
      separated: true,
      value: 'clearMedia'
    }],
    onImageDataLoad: () => {
      setDisplayPlaceholder(false);
    },
    onSelectMediaUploadOption: onSelectMedia,
    openMediaOptions: openMediaOptions,
    url: url,
    height: "100%",
    style: imagePreviewStyles,
    width: _style.default.image?.width
  }), _shared.VIDEO_BACKGROUND_TYPE === backgroundType && (0, _react.createElement)(_reactNativeVideo.default, {
    muted: true,
    paused: true,
    disableFocus: true,
    onLoadStart: () => {
      setDisplayPlaceholder(true);
    },
    onLoad: event => {
      const {
        height,
        width
      } = event.naturalSize;
      setVideoNaturalSize({
        height,
        width
      });
      setDisplayPlaceholder(false);
      // Avoid invisible, paused video on Android, presumably
      // related to https://github.com/react-native-video/react-native-video/issues/1979
      videoRef?.current.seek(0);
    },
    ref: videoRef,
    resizeMode: 'cover',
    source: {
      uri: url
    },
    style: videoPreviewStyles
  }), displayPlaceholder ? null : focalPointHint)), (0, _react.createElement)(_focalPointSettingsButton.default, {
    disabled: hasParallax,
    focalPoint: focalPoint || _components.IMAGE_DEFAULT_FOCAL_POINT,
    onFocalPointChange: setFocalPoint,
    url: url
  }), _shared.IMAGE_BACKGROUND_TYPE === backgroundType && (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Fixed background'),
    checked: hasParallax,
    onChange: toggleParallax
  }), (0, _react.createElement)(_components.TextControl, {
    leftAlign: true,
    label: (0, _i18n.__)('Clear Media'),
    labelStyle: _style.default.clearMediaButton,
    onPress: onClearMedia
  })) : (0, _react.createElement)(_components.TextControl, {
    accessibilityLabel: (0, _i18n.__)('Add image or video'),
    label: (0, _i18n.__)('Add image or video'),
    labelStyle: addMediaButtonStyle,
    leftAlign: true,
    onPress: openMediaOptions
  }));
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Media')
  }, (0, _react.createElement)(_blockEditor.MediaUpload, {
    allowedTypes: _shared.ALLOWED_MEDIA_TYPES,
    isReplacingMedia: !hasOnlyColorBackground,
    onSelect: onSelectMedia,
    render: renderMediaSection
  })), (0, _react.createElement)(_overlayColorSettings.default, {
    overlayColor: attributes.overlayColor,
    customOverlayColor: attributes.customOverlayColor,
    gradient: attributes.gradient,
    customGradient: attributes.customGradient,
    setAttributes: setAttributes
  }), url ? (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.RangeControl, {
    label: (0, _i18n.__)('Opacity'),
    minimumValue: 0,
    maximumValue: 100,
    value: dimRatio,
    onChange: onOpacityChange,
    style: _style.default.rangeCellContainer,
    separatorType: 'topFullWidth'
  })) : null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Dimensions')
  }, (0, _react.createElement)(_components.UnitControl, {
    label: (0, _i18n.__)('Minimum height'),
    min: minHeightUnit === 'px' ? _shared.COVER_MIN_HEIGHT : 1,
    max: _shared.COVER_MAX_HEIGHT,
    unit: minHeightUnit,
    value: CONTAINER_HEIGHT,
    onChange: onHeightChange,
    onUnitChange: onChangeUnit,
    units: units,
    style: _style.default.rangeCellContainer,
    key: minHeightUnit
  })));
}
var _default = exports.default = Controls;
//# sourceMappingURL=controls.native.js.map