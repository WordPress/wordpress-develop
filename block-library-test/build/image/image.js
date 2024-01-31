"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Image;
var _react = require("react");
var _blob = require("@wordpress/blob");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _blocks = require("@wordpress/blocks");
var _icons = require("@wordpress/icons");
var _notices = require("@wordpress/notices");
var _coreData = require("@wordpress/core-data");
var _lockUnlock = require("../lock-unlock");
var _util = require("../embed/util");
var _useClientWidth = _interopRequireDefault(require("./use-client-width"));
var _edit = require("./edit");
var _caption = require("../utils/caption");
var _constants = require("../utils/constants");
var _constants2 = require("./constants");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Module constants
 */

const {
  DimensionsTool,
  ResolutionTool
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
const scaleOptions = [{
  value: 'cover',
  label: (0, _i18n._x)('Cover', 'Scale option for dimensions control'),
  help: (0, _i18n.__)('Image covers the space evenly.')
}, {
  value: 'contain',
  label: (0, _i18n._x)('Contain', 'Scale option for dimensions control'),
  help: (0, _i18n.__)('Image is contained without distortion.')
}];

// If the image has a href, wrap in an <a /> tag to trigger any inherited link element styles.
const ImageWrapper = ({
  href,
  children
}) => {
  if (!href) {
    return children;
  }
  return (0, _react.createElement)("a", {
    href: href,
    onClick: event => event.preventDefault(),
    "aria-disabled": true,
    style: {
      // When the Image block is linked,
      // it's wrapped with a disabled <a /> tag.
      // Restore cursor style so it doesn't appear 'clickable'
      // and remove pointer events. Safari needs the display property.
      pointerEvents: 'none',
      cursor: 'default',
      display: 'inline'
    }
  }, children);
};
function Image({
  temporaryURL,
  attributes,
  setAttributes,
  isSingleSelected,
  insertBlocksAfter,
  onReplace,
  onSelectImage,
  onSelectURL,
  onUploadError,
  containerRef,
  context,
  clientId,
  blockEditingMode
}) {
  const {
    url = '',
    alt,
    align,
    id,
    href,
    rel,
    linkClass,
    linkDestination,
    title,
    width,
    height,
    aspectRatio,
    scale,
    linkTarget,
    sizeSlug,
    lightbox,
    metadata
  } = attributes;

  // The only supported unit is px, so we can parseInt to strip the px here.
  const numericWidth = width ? parseInt(width, 10) : undefined;
  const numericHeight = height ? parseInt(height, 10) : undefined;
  const imageRef = (0, _element.useRef)();
  const {
    allowResize = true
  } = context;
  const {
    getBlock,
    getSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  const image = (0, _data.useSelect)(select => id && isSingleSelected ? select(_coreData.store).getMedia(id, {
    context: 'view'
  }) : null, [id, isSingleSelected]);
  const {
    canInsertCover,
    imageEditing,
    imageSizes,
    maxWidth
  } = (0, _data.useSelect)(select => {
    const {
      getBlockRootClientId,
      canInsertBlockType
    } = select(_blockEditor.store);
    const rootClientId = getBlockRootClientId(clientId);
    const settings = getSettings();
    return {
      imageEditing: settings.imageEditing,
      imageSizes: settings.imageSizes,
      maxWidth: settings.maxWidth,
      canInsertCover: canInsertBlockType('core/cover', rootClientId)
    };
  }, [clientId]);
  const {
    replaceBlocks,
    toggleSelection
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    createErrorNotice,
    createSuccessNotice
  } = (0, _data.useDispatch)(_notices.store);
  const isLargeViewport = (0, _compose.useViewportMatch)('medium');
  const isWideAligned = ['wide', 'full'].includes(align);
  const [{
    loadedNaturalWidth,
    loadedNaturalHeight
  }, setLoadedNaturalSize] = (0, _element.useState)({});
  const [isEditingImage, setIsEditingImage] = (0, _element.useState)(false);
  const [externalBlob, setExternalBlob] = (0, _element.useState)();
  const clientWidth = (0, _useClientWidth.default)(containerRef, [align]);
  const hasNonContentControls = blockEditingMode === 'default';
  const isResizable = allowResize && hasNonContentControls && !isWideAligned && isLargeViewport;
  const imageSizeOptions = imageSizes.filter(({
    slug
  }) => image?.media_details?.sizes?.[slug]?.source_url).map(({
    name,
    slug
  }) => ({
    value: slug,
    label: name
  }));

  // If an image is externally hosted, try to fetch the image data. This may
  // fail if the image host doesn't allow CORS with the domain. If it works,
  // we can enable a button in the toolbar to upload the image.
  (0, _element.useEffect)(() => {
    if (!(0, _edit.isExternalImage)(id, url) || !isSingleSelected || !getSettings().mediaUpload) {
      setExternalBlob();
      return;
    }
    if (externalBlob) return;
    window
    // Avoid cache, which seems to help avoid CORS problems.
    .fetch(url.includes('?') ? url : url + '?').then(response => response.blob()).then(blob => setExternalBlob(blob))
    // Do nothing, cannot upload.
    .catch(() => {});
  }, [id, url, isSingleSelected, externalBlob]);

  // Get naturalWidth and naturalHeight from image ref, and fall back to loaded natural
  // width and height. This resolves an issue in Safari where the loaded natural
  // width and height is otherwise lost when switching between alignments.
  // See: https://github.com/WordPress/gutenberg/pull/37210.
  const {
    naturalWidth,
    naturalHeight
  } = (0, _element.useMemo)(() => {
    return {
      naturalWidth: imageRef.current?.naturalWidth || loadedNaturalWidth || undefined,
      naturalHeight: imageRef.current?.naturalHeight || loadedNaturalHeight || undefined
    };
  }, [loadedNaturalWidth, loadedNaturalHeight, imageRef.current?.complete]);
  function onResizeStart() {
    toggleSelection(false);
  }
  function onResizeStop() {
    toggleSelection(true);
  }
  function onImageError() {
    // Check if there's an embed block that handles this URL, e.g., instagram URL.
    // See: https://github.com/WordPress/gutenberg/pull/11472
    const embedBlock = (0, _util.createUpgradedEmbedBlock)({
      attributes: {
        url
      }
    });
    if (undefined !== embedBlock) {
      onReplace(embedBlock);
    }
  }
  function onSetHref(props) {
    setAttributes(props);
  }
  function onSetLightbox(enable) {
    if (enable && !lightboxSetting?.enabled) {
      setAttributes({
        lightbox: {
          enabled: true
        }
      });
    } else if (!enable && lightboxSetting?.enabled) {
      setAttributes({
        lightbox: {
          enabled: false
        }
      });
    } else {
      setAttributes({
        lightbox: undefined
      });
    }
  }
  function onSetTitle(value) {
    // This is the HTML title attribute, separate from the media object
    // title.
    setAttributes({
      title: value
    });
  }
  function updateAlt(newAlt) {
    setAttributes({
      alt: newAlt
    });
  }
  function updateImage(newSizeSlug) {
    const newUrl = image?.media_details?.sizes?.[newSizeSlug]?.source_url;
    if (!newUrl) {
      return null;
    }
    setAttributes({
      url: newUrl,
      sizeSlug: newSizeSlug
    });
  }
  function uploadExternal() {
    const {
      mediaUpload
    } = getSettings();
    if (!mediaUpload) {
      return;
    }
    mediaUpload({
      filesList: [externalBlob],
      onFileChange([img]) {
        onSelectImage(img);
        if ((0, _blob.isBlobURL)(img.url)) {
          return;
        }
        setExternalBlob();
        createSuccessNotice((0, _i18n.__)('Image uploaded.'), {
          type: 'snackbar'
        });
      },
      allowedTypes: _constants2.ALLOWED_MEDIA_TYPES,
      onError(message) {
        createErrorNotice(message, {
          type: 'snackbar'
        });
      }
    });
  }
  (0, _element.useEffect)(() => {
    if (!isSingleSelected) {
      setIsEditingImage(false);
    }
  }, [isSingleSelected]);
  const canEditImage = id && naturalWidth && naturalHeight && imageEditing;
  const allowCrop = isSingleSelected && canEditImage && !isEditingImage;
  function switchToCover() {
    replaceBlocks(clientId, (0, _blocks.switchToBlockType)(getBlock(clientId), 'core/cover'));
  }

  // TODO: Can allow more units after figuring out how they should interact
  // with the ResizableBox and ImageEditor components. Calculations later on
  // for those components are currently assuming px units.
  const dimensionsUnitsOptions = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: ['px']
  });
  const [lightboxSetting] = (0, _blockEditor.useSettings)('lightbox');
  const showLightboxSetting = !!lightbox || lightboxSetting?.allowEditing === true;
  const lightboxChecked = !!lightbox?.enabled || !lightbox && !!lightboxSetting?.enabled;
  const dimensionsControl = (0, _react.createElement)(DimensionsTool, {
    value: {
      width,
      height,
      scale,
      aspectRatio
    },
    onChange: ({
      width: newWidth,
      height: newHeight,
      scale: newScale,
      aspectRatio: newAspectRatio
    }) => {
      // Rebuilding the object forces setting `undefined`
      // for values that are removed since setAttributes
      // doesn't do anything with keys that aren't set.
      setAttributes({
        // CSS includes `height: auto`, but we need
        // `width: auto` to fix the aspect ratio when
        // only height is set due to the width and
        // height attributes set via the server.
        width: !newWidth && newHeight ? 'auto' : newWidth,
        height: newHeight,
        scale: newScale,
        aspectRatio: newAspectRatio
      });
    },
    defaultScale: "cover",
    defaultAspectRatio: "auto",
    scaleOptions: scaleOptions,
    unitsOptions: dimensionsUnitsOptions
  });
  const resetAll = () => {
    setAttributes({
      alt: undefined,
      width: undefined,
      height: undefined,
      scale: undefined,
      aspectRatio: undefined,
      lightbox: undefined
    });
  };
  const sizeControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.__experimentalToolsPanel, {
    label: (0, _i18n.__)('Settings'),
    resetAll: resetAll,
    dropdownMenuProps: _constants.TOOLSPANEL_DROPDOWNMENU_PROPS
  }, isResizable && dimensionsControl));
  const {
    lockUrlControls = false,
    lockAltControls = false,
    lockTitleControls = false
  } = (0, _data.useSelect)(select => {
    if (!isSingleSelected) {
      return {};
    }
    const {
      getBlockBindingsSource
    } = (0, _lockUnlock.unlock)(select(_blockEditor.store));
    const {
      url: urlBinding,
      alt: altBinding,
      title: titleBinding
    } = metadata?.bindings || {};
    return {
      lockUrlControls: !!urlBinding && getBlockBindingsSource(urlBinding?.source)?.lockAttributesEditing === true,
      lockAltControls: !!altBinding && getBlockBindingsSource(altBinding?.source)?.lockAttributesEditing === true,
      lockTitleControls: !!titleBinding && getBlockBindingsSource(titleBinding?.source)?.lockAttributesEditing === true
    };
  }, [isSingleSelected]);
  const controls = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, isSingleSelected && !isEditingImage && !lockUrlControls && (0, _react.createElement)(_blockEditor.__experimentalImageURLInputUI, {
    url: href || '',
    onChangeUrl: onSetHref,
    linkDestination: linkDestination,
    mediaUrl: image && image.source_url || url,
    mediaLink: image && image.link,
    linkTarget: linkTarget,
    linkClass: linkClass,
    rel: rel,
    showLightboxSetting: showLightboxSetting,
    lightboxEnabled: lightboxChecked,
    onSetLightbox: onSetLightbox
  }), allowCrop && (0, _react.createElement)(_components.ToolbarButton, {
    onClick: () => setIsEditingImage(true),
    icon: _icons.crop,
    label: (0, _i18n.__)('Crop')
  }), isSingleSelected && canInsertCover && (0, _react.createElement)(_components.ToolbarButton, {
    icon: _icons.overlayText,
    label: (0, _i18n.__)('Add text over image'),
    onClick: switchToCover
  })), isSingleSelected && !isEditingImage && !lockUrlControls && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: id,
    mediaURL: url,
    allowedTypes: _constants2.ALLOWED_MEDIA_TYPES,
    accept: "image/*",
    onSelect: onSelectImage,
    onSelectURL: onSelectURL,
    onError: onUploadError
  })), isSingleSelected && externalBlob && (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: uploadExternal,
    icon: _icons.upload,
    label: (0, _i18n.__)('Upload image to media library')
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.__experimentalToolsPanel, {
    label: (0, _i18n.__)('Settings'),
    resetAll: resetAll,
    dropdownMenuProps: _constants.TOOLSPANEL_DROPDOWNMENU_PROPS
  }, isSingleSelected && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    label: (0, _i18n.__)('Alternative text'),
    isShownByDefault: true,
    hasValue: () => !!alt,
    onDeselect: () => setAttributes({
      alt: undefined
    })
  }, (0, _react.createElement)(_components.TextareaControl, {
    label: (0, _i18n.__)('Alternative text'),
    value: alt || '',
    onChange: updateAlt,
    disabled: lockAltControls,
    help: lockAltControls ? (0, _react.createElement)(_react.Fragment, null, (0, _i18n.__)('Connected to a custom field')) : (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ExternalLink, {
      href: "https://www.w3.org/WAI/tutorials/images/decision-tree"
    }, (0, _i18n.__)('Describe the purpose of the image.')), (0, _react.createElement)("br", null), (0, _i18n.__)('Leave empty if decorative.')),
    __nextHasNoMarginBottom: true
  })), isResizable && dimensionsControl, !!imageSizeOptions.length && (0, _react.createElement)(ResolutionTool, {
    value: sizeSlug,
    onChange: updateImage,
    options: imageSizeOptions
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Title attribute'),
    value: title || '',
    onChange: onSetTitle,
    disabled: lockTitleControls,
    help: lockTitleControls ? (0, _react.createElement)(_react.Fragment, null, (0, _i18n.__)('Connected to a custom field')) : (0, _react.createElement)(_react.Fragment, null, (0, _i18n.__)('Describe the role of this image on the page.'), (0, _react.createElement)(_components.ExternalLink, {
      href: "https://www.w3.org/TR/html52/dom.html#the-title-attribute"
    }, (0, _i18n.__)('(Note: many devices and browsers do not display this text.)')))
  })));
  const filename = (0, _url.getFilename)(url);
  let defaultedAlt;
  if (alt) {
    defaultedAlt = alt;
  } else if (filename) {
    defaultedAlt = (0, _i18n.sprintf)( /* translators: %s: file name */
    (0, _i18n.__)('This image has an empty alt attribute; its file name is %s'), filename);
  } else {
    defaultedAlt = (0, _i18n.__)('This image has an empty alt attribute');
  }
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const isRounded = attributes.className?.includes('is-style-rounded');
  let img =
  // Disable reason: Image itself is not meant to be interactive, but
  // should direct focus to block.
  /* eslint-disable jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events */
  (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("img", {
    src: temporaryURL || url,
    alt: defaultedAlt,
    onError: () => onImageError(),
    onLoad: event => {
      setLoadedNaturalSize({
        loadedNaturalWidth: event.target?.naturalWidth,
        loadedNaturalHeight: event.target?.naturalHeight
      });
    },
    ref: imageRef,
    className: borderProps.className,
    style: {
      width: width && height || aspectRatio ? '100%' : undefined,
      height: width && height || aspectRatio ? '100%' : undefined,
      objectFit: scale,
      ...borderProps.style
    }
  }), temporaryURL && (0, _react.createElement)(_components.Spinner, null))
  /* eslint-enable jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events */;

  // clientWidth needs to be a number for the image Cropper to work, but sometimes it's 0
  // So we try using the imageRef width first and fallback to clientWidth.
  const fallbackClientWidth = imageRef.current?.width || clientWidth;
  if (canEditImage && isEditingImage) {
    img = (0, _react.createElement)(ImageWrapper, {
      href: href
    }, (0, _react.createElement)(_blockEditor.__experimentalImageEditor, {
      id: id,
      url: url,
      width: numericWidth,
      height: numericHeight,
      clientWidth: fallbackClientWidth,
      naturalHeight: naturalHeight,
      naturalWidth: naturalWidth,
      onSaveImage: imageAttributes => setAttributes(imageAttributes),
      onFinishEditing: () => {
        setIsEditingImage(false);
      },
      borderProps: isRounded ? undefined : borderProps
    }));
  } else if (!isResizable) {
    img = (0, _react.createElement)("div", {
      style: {
        width,
        height,
        aspectRatio
      }
    }, (0, _react.createElement)(ImageWrapper, {
      href: href
    }, img));
  } else {
    const numericRatio = aspectRatio && (0, _utils.evalAspectRatio)(aspectRatio);
    const customRatio = numericWidth / numericHeight;
    const naturalRatio = naturalWidth / naturalHeight;
    const ratio = numericRatio || customRatio || naturalRatio || 1;
    const currentWidth = !numericWidth && numericHeight ? numericHeight * ratio : numericWidth;
    const currentHeight = !numericHeight && numericWidth ? numericWidth / ratio : numericHeight;
    const minWidth = naturalWidth < naturalHeight ? _constants2.MIN_SIZE : _constants2.MIN_SIZE * ratio;
    const minHeight = naturalHeight < naturalWidth ? _constants2.MIN_SIZE : _constants2.MIN_SIZE / ratio;

    // With the current implementation of ResizableBox, an image needs an
    // explicit pixel value for the max-width. In absence of being able to
    // set the content-width, this max-width is currently dictated by the
    // vanilla editor style. The following variable adds a buffer to this
    // vanilla style, so 3rd party themes have some wiggleroom. This does,
    // in most cases, allow you to scale the image beyond the width of the
    // main column, though not infinitely.
    // @todo It would be good to revisit this once a content-width variable
    // becomes available.
    const maxWidthBuffer = maxWidth * 2.5;
    let showRightHandle = false;
    let showLeftHandle = false;

    /* eslint-disable no-lonely-if */
    // See https://github.com/WordPress/gutenberg/issues/7584.
    if (align === 'center') {
      // When the image is centered, show both handles.
      showRightHandle = true;
      showLeftHandle = true;
    } else if ((0, _i18n.isRTL)()) {
      // In RTL mode the image is on the right by default.
      // Show the right handle and hide the left handle only when it is
      // aligned left. Otherwise always show the left handle.
      if (align === 'left') {
        showRightHandle = true;
      } else {
        showLeftHandle = true;
      }
    } else {
      // Show the left handle and hide the right handle only when the
      // image is aligned right. Otherwise always show the right handle.
      if (align === 'right') {
        showLeftHandle = true;
      } else {
        showRightHandle = true;
      }
    }
    /* eslint-enable no-lonely-if */
    img = (0, _react.createElement)(_components.ResizableBox, {
      style: {
        display: 'block',
        objectFit: scale,
        aspectRatio: !width && !height && aspectRatio ? aspectRatio : undefined
      },
      size: {
        width: currentWidth !== null && currentWidth !== void 0 ? currentWidth : 'auto',
        height: currentHeight !== null && currentHeight !== void 0 ? currentHeight : 'auto'
      },
      showHandle: isSingleSelected,
      minWidth: minWidth,
      maxWidth: maxWidthBuffer,
      minHeight: minHeight,
      maxHeight: maxWidthBuffer / ratio,
      lockAspectRatio: ratio,
      enable: {
        top: false,
        right: showRightHandle,
        bottom: true,
        left: showLeftHandle
      },
      onResizeStart: onResizeStart,
      onResizeStop: (event, direction, elt) => {
        onResizeStop();
        // Since the aspect ratio is locked when resizing, we can
        // use the width of the resized element to calculate the
        // height in CSS to prevent stretching when the max-width
        // is reached.
        setAttributes({
          width: `${elt.offsetWidth}px`,
          height: 'auto',
          aspectRatio: ratio === naturalRatio ? undefined : String(ratio)
        });
      },
      resizeRatio: align === 'center' ? 2 : 1
    }, (0, _react.createElement)(ImageWrapper, {
      href: href
    }, img));
  }
  if (!url && !temporaryURL) {
    // Add all controls if the image attributes are connected.
    return metadata?.bindings ? controls : sizeControls;
  }
  return (0, _react.createElement)(_react.Fragment, null, !temporaryURL && controls, img, (0, _react.createElement)(_caption.Caption, {
    attributes: attributes,
    setAttributes: setAttributes,
    isSelected: isSingleSelected,
    insertBlocksAfter: insertBlocksAfter,
    label: (0, _i18n.__)('Image caption text'),
    showToolbarButton: isSingleSelected && hasNonContentControls
  }));
}
//# sourceMappingURL=image.js.map