"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _blob = require("@wordpress/blob");
var _notices = require("@wordpress/notices");
var _shared = require("../shared");
var _inspectorControls = _interopRequireDefault(require("./inspector-controls"));
var _blockControls = _interopRequireDefault(require("./block-controls"));
var _coverPlaceholder = _interopRequireDefault(require("./cover-placeholder"));
var _resizableCoverPopover = _interopRequireDefault(require("./resizable-cover-popover"));
var _colorUtils = require("./color-utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function getInnerBlocksTemplate(attributes) {
  return [['core/paragraph', {
    align: 'center',
    placeholder: (0, _i18n.__)('Write title…'),
    ...attributes
  }]];
}

/**
 * Is the URL a temporary blob URL? A blob URL is one that is used temporarily while
 * the media (image or video) is being uploaded and will not have an id allocated yet.
 *
 * @param {number} id  The id of the media.
 * @param {string} url The url of the media.
 *
 * @return {boolean} Is the URL a Blob URL.
 */
const isTemporaryMedia = (id, url) => !id && (0, _blob.isBlobURL)(url);
function CoverEdit({
  attributes,
  clientId,
  isSelected,
  overlayColor,
  setAttributes,
  setOverlayColor,
  toggleSelection,
  context: {
    postId,
    postType
  }
}) {
  const {
    contentPosition,
    id,
    url: originalUrl,
    backgroundType: originalBackgroundType,
    useFeaturedImage,
    dimRatio,
    focalPoint,
    hasParallax,
    isDark,
    isRepeated,
    minHeight,
    minHeightUnit,
    alt,
    allowedBlocks,
    templateLock,
    tagName: TagName = 'div',
    isUserOverlayColor
  } = attributes;
  const [featuredImage] = (0, _coreData.useEntityProp)('postType', postType, 'featured_media', postId);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const media = (0, _data.useSelect)(select => featuredImage && select(_coreData.store).getMedia(featuredImage, {
    context: 'view'
  }), [featuredImage]);
  const mediaUrl = media?.source_url;

  // User can change the featured image outside of the block, but we still
  // need to update the block when that happens. This effect should only
  // run when the featured image changes in that case. All other cases are
  // handled in their respective callbacks.
  (0, _element.useEffect)(() => {
    (async () => {
      if (!useFeaturedImage) {
        return;
      }
      const averageBackgroundColor = await (0, _colorUtils.getMediaColor)(mediaUrl);
      let newOverlayColor = overlayColor.color;
      if (!isUserOverlayColor) {
        newOverlayColor = averageBackgroundColor;
        __unstableMarkNextChangeAsNotPersistent();
        setOverlayColor(newOverlayColor);
      }
      const newIsDark = (0, _colorUtils.compositeIsDark)(dimRatio, newOverlayColor, averageBackgroundColor);
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        isDark: newIsDark
      });
    })();
    // Disable reason: Update the block only when the featured image changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mediaUrl]);

  // instead of destructuring the attributes
  // we define the url and background type
  // depending on the value of the useFeaturedImage flag
  // to preview in edit the dynamic featured image
  const url = useFeaturedImage ? mediaUrl :
  // Ensure the url is not malformed due to sanitization through `wp_kses`.
  originalUrl?.replaceAll('&amp;', '&');
  const backgroundType = useFeaturedImage ? _shared.IMAGE_BACKGROUND_TYPE : originalBackgroundType;
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const {
    gradientClass,
    gradientValue
  } = (0, _blockEditor.__experimentalUseGradient)();
  const onSelectMedia = async newMedia => {
    const mediaAttributes = (0, _shared.attributesFromMedia)(newMedia);
    const isImage = [newMedia?.type, newMedia?.media_type].includes(_shared.IMAGE_BACKGROUND_TYPE);
    const averageBackgroundColor = await (0, _colorUtils.getMediaColor)(isImage ? newMedia?.url : undefined);
    let newOverlayColor = overlayColor.color;
    if (!isUserOverlayColor) {
      newOverlayColor = averageBackgroundColor;
      setOverlayColor(newOverlayColor);

      // Make undo revert the next setAttributes and the previous setOverlayColor.
      __unstableMarkNextChangeAsNotPersistent();
    }

    // Only set a new dimRatio if there was no previous media selected
    // to avoid resetting to 50 if it has been explicitly set to 100.
    // See issue #52835 for context.
    const newDimRatio = originalUrl === undefined && dimRatio === 100 ? 50 : dimRatio;
    const newIsDark = (0, _colorUtils.compositeIsDark)(newDimRatio, newOverlayColor, averageBackgroundColor);
    setAttributes({
      ...mediaAttributes,
      focalPoint: undefined,
      useFeaturedImage: undefined,
      dimRatio: newDimRatio,
      isDark: newIsDark
    });
  };
  const onClearMedia = () => {
    let newOverlayColor = overlayColor.color;
    if (!isUserOverlayColor) {
      newOverlayColor = _colorUtils.DEFAULT_OVERLAY_COLOR;
      setOverlayColor(undefined);

      // Make undo revert the next setAttributes and the previous setOverlayColor.
      __unstableMarkNextChangeAsNotPersistent();
    }
    const newIsDark = (0, _colorUtils.compositeIsDark)(dimRatio, newOverlayColor, _colorUtils.DEFAULT_BACKGROUND_COLOR);
    setAttributes({
      url: undefined,
      id: undefined,
      backgroundType: undefined,
      focalPoint: undefined,
      hasParallax: undefined,
      isRepeated: undefined,
      useFeaturedImage: undefined,
      isDark: newIsDark
    });
  };
  const onSetOverlayColor = async newOverlayColor => {
    const averageBackgroundColor = await (0, _colorUtils.getMediaColor)(url);
    const newIsDark = (0, _colorUtils.compositeIsDark)(dimRatio, newOverlayColor, averageBackgroundColor);
    setOverlayColor(newOverlayColor);

    // Make undo revert the next setAttributes and the previous setOverlayColor.
    __unstableMarkNextChangeAsNotPersistent();
    setAttributes({
      isUserOverlayColor: true,
      isDark: newIsDark
    });
  };
  const onUpdateDimRatio = async newDimRatio => {
    const averageBackgroundColor = await (0, _colorUtils.getMediaColor)(url);
    const newIsDark = (0, _colorUtils.compositeIsDark)(newDimRatio, overlayColor.color, averageBackgroundColor);
    setAttributes({
      dimRatio: newDimRatio,
      isDark: newIsDark
    });
  };
  const onUploadError = message => {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  };
  const isUploadingMedia = isTemporaryMedia(id, url);
  const isImageBackground = _shared.IMAGE_BACKGROUND_TYPE === backgroundType;
  const isVideoBackground = _shared.VIDEO_BACKGROUND_TYPE === backgroundType;
  const [resizeListener, {
    height,
    width
  }] = (0, _compose.useResizeObserver)();
  const resizableBoxDimensions = (0, _element.useMemo)(() => {
    return {
      height: minHeightUnit === 'px' ? minHeight : 'auto',
      width: 'auto'
    };
  }, [minHeight, minHeightUnit]);
  const minHeightWithUnit = minHeight && minHeightUnit ? `${minHeight}${minHeightUnit}` : minHeight;
  const isImgElement = !(hasParallax || isRepeated);
  const style = {
    minHeight: minHeightWithUnit || undefined
  };
  const backgroundImage = url ? `url(${url})` : undefined;
  const backgroundPosition = (0, _shared.mediaPosition)(focalPoint);
  const bgStyle = {
    backgroundColor: overlayColor.color
  };
  const mediaStyle = {
    objectPosition: focalPoint && isImgElement ? (0, _shared.mediaPosition)(focalPoint) : undefined
  };
  const hasBackground = !!(url || overlayColor.color || gradientValue);
  const hasInnerBlocks = (0, _data.useSelect)(select => select(_blockEditor.store).getBlock(clientId).innerBlocks.length > 0, [clientId]);
  const ref = (0, _element.useRef)();
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref
  });

  // Check for fontSize support before we pass a fontSize attribute to the innerBlocks.
  const [fontSizes] = (0, _blockEditor.useSettings)('typography.fontSizes');
  const hasFontSizes = fontSizes?.length > 0;
  const innerBlocksTemplate = getInnerBlocksTemplate({
    fontSize: hasFontSizes ? 'large' : undefined
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    className: 'wp-block-cover__inner-container'
  }, {
    // Avoid template sync when the `templateLock` value is `all` or `contentOnly`.
    // See: https://github.com/WordPress/gutenberg/pull/45632
    template: !hasInnerBlocks ? innerBlocksTemplate : undefined,
    templateInsertUpdatesSelection: true,
    allowedBlocks,
    templateLock,
    dropZoneElement: ref.current
  });
  const mediaElement = (0, _element.useRef)();
  const currentSettings = {
    isVideoBackground,
    isImageBackground,
    mediaElement,
    hasInnerBlocks,
    url,
    isImgElement,
    overlayColor
  };
  const toggleUseFeaturedImage = async () => {
    const newUseFeaturedImage = !useFeaturedImage;
    const averageBackgroundColor = newUseFeaturedImage ? await (0, _colorUtils.getMediaColor)(mediaUrl) : _colorUtils.DEFAULT_BACKGROUND_COLOR;
    const newOverlayColor = !isUserOverlayColor ? averageBackgroundColor : overlayColor.color;
    if (!isUserOverlayColor) {
      if (newUseFeaturedImage) {
        setOverlayColor(newOverlayColor);
      } else {
        setOverlayColor(undefined);
      }

      // Make undo revert the next setAttributes and the previous setOverlayColor.
      __unstableMarkNextChangeAsNotPersistent();
    }
    const newDimRatio = dimRatio === 100 ? 50 : dimRatio;
    const newIsDark = (0, _colorUtils.compositeIsDark)(newDimRatio, newOverlayColor, averageBackgroundColor);
    setAttributes({
      id: undefined,
      url: undefined,
      useFeaturedImage: newUseFeaturedImage,
      dimRatio: newDimRatio,
      backgroundType: useFeaturedImage ? _shared.IMAGE_BACKGROUND_TYPE : undefined,
      isDark: newIsDark
    });
  };
  const blockControls = (0, _react.createElement)(_blockControls.default, {
    attributes: attributes,
    setAttributes: setAttributes,
    onSelectMedia: onSelectMedia,
    currentSettings: currentSettings,
    toggleUseFeaturedImage: toggleUseFeaturedImage
  });
  const inspectorControls = (0, _react.createElement)(_inspectorControls.default, {
    attributes: attributes,
    setAttributes: setAttributes,
    clientId: clientId,
    setOverlayColor: onSetOverlayColor,
    coverRef: ref,
    currentSettings: currentSettings,
    toggleUseFeaturedImage: toggleUseFeaturedImage,
    updateDimRatio: onUpdateDimRatio,
    onClearMedia: onClearMedia
  });
  const resizableCoverProps = {
    className: 'block-library-cover__resize-container',
    clientId,
    height,
    minHeight: minHeightWithUnit,
    onResizeStart: () => {
      setAttributes({
        minHeightUnit: 'px'
      });
      toggleSelection(false);
    },
    onResize: value => {
      setAttributes({
        minHeight: value
      });
    },
    onResizeStop: newMinHeight => {
      toggleSelection(true);
      setAttributes({
        minHeight: newMinHeight
      });
    },
    // Hide the resize handle if an aspect ratio is set, as the aspect ratio takes precedence.
    showHandle: !attributes.style?.dimensions?.aspectRatio ? true : false,
    size: resizableBoxDimensions,
    width
  };
  if (!useFeaturedImage && !hasInnerBlocks && !hasBackground) {
    return (0, _react.createElement)(_react.Fragment, null, blockControls, inspectorControls, isSelected && (0, _react.createElement)(_resizableCoverPopover.default, {
      ...resizableCoverProps
    }), (0, _react.createElement)(TagName, {
      ...blockProps,
      className: (0, _classnames.default)('is-placeholder', blockProps.className),
      style: {
        ...blockProps.style,
        minHeight: minHeightWithUnit || undefined
      }
    }, resizeListener, (0, _react.createElement)(_coverPlaceholder.default, {
      onSelectMedia: onSelectMedia,
      onError: onUploadError,
      toggleUseFeaturedImage: toggleUseFeaturedImage
    }, (0, _react.createElement)("div", {
      className: "wp-block-cover__placeholder-background-options"
    }, (0, _react.createElement)(_blockEditor.ColorPalette, {
      disableCustomColors: true,
      value: overlayColor.color,
      onChange: onSetOverlayColor,
      clearable: false
    })))));
  }
  const classes = (0, _classnames.default)({
    'is-dark-theme': isDark,
    'is-light': !isDark,
    'is-transient': isUploadingMedia,
    'has-parallax': hasParallax,
    'is-repeated': isRepeated,
    'has-custom-content-position': !(0, _shared.isContentPositionCenter)(contentPosition)
  }, (0, _shared.getPositionClassName)(contentPosition));
  return (0, _react.createElement)(_react.Fragment, null, blockControls, inspectorControls, (0, _react.createElement)(TagName, {
    ...blockProps,
    className: (0, _classnames.default)(classes, blockProps.className),
    style: {
      ...style,
      ...blockProps.style
    },
    "data-url": url
  }, resizeListener, (!useFeaturedImage || url) && (0, _react.createElement)("span", {
    "aria-hidden": "true",
    className: (0, _classnames.default)('wp-block-cover__background', (0, _shared.dimRatioToClass)(dimRatio), {
      [overlayColor.class]: overlayColor.class,
      'has-background-dim': dimRatio !== undefined,
      // For backwards compatibility. Former versions of the Cover Block applied
      // `.wp-block-cover__gradient-background` in the presence of
      // media, a gradient and a dim.
      'wp-block-cover__gradient-background': url && gradientValue && dimRatio !== 0,
      'has-background-gradient': gradientValue,
      [gradientClass]: gradientClass
    }),
    style: {
      backgroundImage: gradientValue,
      ...bgStyle
    }
  }), !url && useFeaturedImage && (0, _react.createElement)(_components.Placeholder, {
    className: "wp-block-cover__image--placeholder-image",
    withIllustration: true
  }), url && isImageBackground && (isImgElement ? (0, _react.createElement)("img", {
    ref: mediaElement,
    className: "wp-block-cover__image-background",
    alt: alt,
    src: url,
    style: mediaStyle
  }) : (0, _react.createElement)("div", {
    ref: mediaElement,
    role: alt ? 'img' : undefined,
    "aria-label": alt ? alt : undefined,
    className: (0, _classnames.default)(classes, 'wp-block-cover__image-background'),
    style: {
      backgroundImage,
      backgroundPosition
    }
  })), url && isVideoBackground && (0, _react.createElement)("video", {
    ref: mediaElement,
    className: "wp-block-cover__video-background",
    autoPlay: true,
    muted: true,
    loop: true,
    src: url,
    style: mediaStyle
  }), isUploadingMedia && (0, _react.createElement)(_components.Spinner, null), (0, _react.createElement)(_coverPlaceholder.default, {
    disableMediaButtons: true,
    onSelectMedia: onSelectMedia,
    onError: onUploadError,
    toggleUseFeaturedImage: toggleUseFeaturedImage
  }), (0, _react.createElement)("div", {
    ...innerBlocksProps
  })), isSelected && (0, _react.createElement)(_resizableCoverPopover.default, {
    ...resizableCoverProps
  }));
}
var _default = exports.default = (0, _compose.compose)([(0, _blockEditor.withColors)({
  overlayColor: 'background-color'
})])(CoverEdit);
//# sourceMappingURL=index.js.map