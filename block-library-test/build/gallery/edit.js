"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _viewport = require("@wordpress/viewport");
var _primitives = require("@wordpress/primitives");
var _blocks = require("@wordpress/blocks");
var _blob = require("@wordpress/blob");
var _notices = require("@wordpress/notices");
var _sharedIcon = require("./shared-icon");
var _shared = require("./shared");
var _utils = require("./utils");
var _utils2 = require("../image/utils");
var _gallery = _interopRequireDefault(require("./gallery"));
var _constants = require("./constants");
var _useImageSizes = _interopRequireDefault(require("./use-image-sizes"));
var _useGetNewImages = _interopRequireDefault(require("./use-get-new-images"));
var _useGetMedia = _interopRequireDefault(require("./use-get-media"));
var _gapStyles = _interopRequireDefault(require("./gap-styles"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const MAX_COLUMNS = 8;
const linkOptions = [{
  value: _constants.LINK_DESTINATION_ATTACHMENT,
  label: (0, _i18n.__)('Attachment Page')
}, {
  value: _constants.LINK_DESTINATION_MEDIA,
  label: (0, _i18n.__)('Media File')
}, {
  value: _constants.LINK_DESTINATION_NONE,
  label: (0, _i18n._x)('None', 'Media item link option')
}];
const ALLOWED_MEDIA_TYPES = ['image'];
const PLACEHOLDER_TEXT = _element.Platform.isNative ? (0, _i18n.__)('Add media') : (0, _i18n.__)('Drag images, upload new ones or select files from your library.');
const MOBILE_CONTROL_PROPS_RANGE_CONTROL = _element.Platform.isNative ? {
  type: 'stepper'
} : {};
const EMPTY_ARRAY = [];
function GalleryEdit(props) {
  const {
    setAttributes,
    attributes,
    className,
    clientId,
    isSelected,
    insertBlocksAfter,
    isContentLocked,
    onFocus
  } = props;
  const {
    columns,
    imageCrop,
    randomOrder,
    linkTarget,
    linkTo,
    sizeSlug
  } = attributes;
  const {
    __unstableMarkNextChangeAsNotPersistent,
    replaceInnerBlocks,
    updateBlockAttributes,
    selectBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    createSuccessNotice,
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const {
    getBlock,
    getSettings,
    preferredStyle,
    innerBlockImages,
    blockWasJustInserted,
    multiGallerySelection
  } = (0, _data.useSelect)(select => {
    var _getBlock$innerBlocks;
    const {
      getBlockName,
      getMultiSelectedBlockClientIds,
      getSettings: _getSettings,
      getBlock: _getBlock,
      wasBlockJustInserted
    } = select(_blockEditor.store);
    const preferredStyleVariations = _getSettings().__experimentalPreferredStyleVariations;
    const multiSelectedClientIds = getMultiSelectedBlockClientIds();
    return {
      getBlock: _getBlock,
      getSettings: _getSettings,
      preferredStyle: preferredStyleVariations?.value?.['core/image'],
      innerBlockImages: (_getBlock$innerBlocks = _getBlock(clientId)?.innerBlocks) !== null && _getBlock$innerBlocks !== void 0 ? _getBlock$innerBlocks : EMPTY_ARRAY,
      blockWasJustInserted: wasBlockJustInserted(clientId, 'inserter_menu'),
      multiGallerySelection: multiSelectedClientIds.length && multiSelectedClientIds.every(_clientId => getBlockName(_clientId) === 'core/gallery')
    };
  }, [clientId]);
  const images = (0, _element.useMemo)(() => innerBlockImages?.map(block => ({
    clientId: block.clientId,
    id: block.attributes.id,
    url: block.attributes.url,
    attributes: block.attributes,
    fromSavedContent: Boolean(block.originalContent)
  })), [innerBlockImages]);
  const imageData = (0, _useGetMedia.default)(innerBlockImages);
  const newImages = (0, _useGetNewImages.default)(images, imageData);
  (0, _element.useEffect)(() => {
    newImages?.forEach(newImage => {
      // Update the images data without creating new undo levels.
      __unstableMarkNextChangeAsNotPersistent();
      updateBlockAttributes(newImage.clientId, {
        ...buildImageAttributes(newImage.attributes),
        id: newImage.id,
        align: undefined
      });
    });
  }, [newImages]);
  const imageSizeOptions = (0, _useImageSizes.default)(imageData, isSelected, getSettings);

  /**
   * Determines the image attributes that should be applied to an image block
   * after the gallery updates.
   *
   * The gallery will receive the full collection of images when a new image
   * is added. As a result we need to reapply the image's original settings if
   * it already existed in the gallery. If the image is in fact new, we need
   * to apply the gallery's current settings to the image.
   *
   * @param {Object} imageAttributes Media object for the actual image.
   * @return {Object}                Attributes to set on the new image block.
   */
  function buildImageAttributes(imageAttributes) {
    const image = imageAttributes.id ? imageData.find(({
      id
    }) => id === imageAttributes.id) : null;
    let newClassName;
    if (imageAttributes.className && imageAttributes.className !== '') {
      newClassName = imageAttributes.className;
    } else {
      newClassName = preferredStyle ? `is-style-${preferredStyle}` : undefined;
    }
    let newLinkTarget;
    if (imageAttributes.linkTarget || imageAttributes.rel) {
      // When transformed from image blocks, the link destination and rel attributes are inherited.
      newLinkTarget = {
        linkTarget: imageAttributes.linkTarget,
        rel: imageAttributes.rel
      };
    } else {
      // When an image is added, update the link destination and rel attributes according to the gallery settings
      newLinkTarget = (0, _utils2.getUpdatedLinkTargetSettings)(linkTarget, attributes);
    }
    return {
      ...(0, _shared.pickRelevantMediaFiles)(image, sizeSlug),
      ...(0, _utils.getHrefAndDestination)(image, linkTo, imageAttributes?.linkDestination),
      ...newLinkTarget,
      className: newClassName,
      sizeSlug,
      caption: imageAttributes.caption || image.caption?.raw,
      alt: imageAttributes.alt || image.alt_text
    };
  }
  function isValidFileType(file) {
    // It's necessary to retrieve the media type from the raw image data for already-uploaded images on native.
    const nativeFileData = _element.Platform.isNative && file.id ? imageData.find(({
      id
    }) => id === file.id) : null;
    const mediaTypeSelector = nativeFileData ? nativeFileData?.media_type : file.type;
    return ALLOWED_MEDIA_TYPES.some(mediaType => mediaTypeSelector?.indexOf(mediaType) === 0) || file.url?.indexOf('blob:') === 0;
  }
  function updateImages(selectedImages) {
    const newFileUploads = Object.prototype.toString.call(selectedImages) === '[object FileList]';
    const imageArray = newFileUploads ? Array.from(selectedImages).map(file => {
      if (!file.url) {
        return (0, _shared.pickRelevantMediaFiles)({
          url: (0, _blob.createBlobURL)(file)
        });
      }
      return file;
    }) : selectedImages;
    if (!imageArray.every(isValidFileType)) {
      createErrorNotice((0, _i18n.__)('If uploading to a gallery all files need to be image formats'), {
        id: 'gallery-upload-invalid-file',
        type: 'snackbar'
      });
    }
    const processedImages = imageArray.filter(file => file.url || isValidFileType(file)).map(file => {
      if (!file.url) {
        return (0, _shared.pickRelevantMediaFiles)({
          url: (0, _blob.createBlobURL)(file)
        });
      }
      return file;
    });

    // Because we are reusing existing innerImage blocks any reordering
    // done in the media library will be lost so we need to reapply that ordering
    // once the new image blocks are merged in with existing.
    const newOrderMap = processedImages.reduce((result, image, index) => (result[image.id] = index, result), {});
    const existingImageBlocks = !newFileUploads ? innerBlockImages.filter(block => processedImages.find(img => img.id === block.attributes.id)) : innerBlockImages;
    const newImageList = processedImages.filter(img => !existingImageBlocks.find(existingImg => img.id === existingImg.attributes.id));
    const newBlocks = newImageList.map(image => {
      return (0, _blocks.createBlock)('core/image', {
        id: image.id,
        url: image.url,
        caption: image.caption,
        alt: image.alt
      });
    });
    replaceInnerBlocks(clientId, existingImageBlocks.concat(newBlocks).sort((a, b) => newOrderMap[a.attributes.id] - newOrderMap[b.attributes.id]));

    // Select the first block to scroll into view when new blocks are added.
    if (newBlocks?.length > 0) {
      selectBlock(newBlocks[0].clientId);
    }
  }
  function onUploadError(message) {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  }
  function setLinkTo(value) {
    setAttributes({
      linkTo: value
    });
    const changedAttributes = {};
    const blocks = [];
    getBlock(clientId).innerBlocks.forEach(block => {
      blocks.push(block.clientId);
      const image = block.attributes.id ? imageData.find(({
        id
      }) => id === block.attributes.id) : null;
      changedAttributes[block.clientId] = (0, _utils.getHrefAndDestination)(image, value);
    });
    updateBlockAttributes(blocks, changedAttributes, true);
    const linkToText = [...linkOptions].find(linkType => linkType.value === value);
    createSuccessNotice((0, _i18n.sprintf)( /* translators: %s: image size settings */
    (0, _i18n.__)('All gallery image links updated to: %s'), linkToText.label), {
      id: 'gallery-attributes-linkTo',
      type: 'snackbar'
    });
  }
  function setColumnsNumber(value) {
    setAttributes({
      columns: value
    });
  }
  function toggleImageCrop() {
    setAttributes({
      imageCrop: !imageCrop
    });
  }
  function getImageCropHelp(checked) {
    return checked ? (0, _i18n.__)('Thumbnails are cropped to align.') : (0, _i18n.__)('Thumbnails are not cropped.');
  }
  function toggleRandomOrder() {
    setAttributes({
      randomOrder: !randomOrder
    });
  }
  function toggleOpenInNewTab(openInNewTab) {
    const newLinkTarget = openInNewTab ? '_blank' : undefined;
    setAttributes({
      linkTarget: newLinkTarget
    });
    const changedAttributes = {};
    const blocks = [];
    getBlock(clientId).innerBlocks.forEach(block => {
      blocks.push(block.clientId);
      changedAttributes[block.clientId] = (0, _utils2.getUpdatedLinkTargetSettings)(newLinkTarget, block.attributes);
    });
    updateBlockAttributes(blocks, changedAttributes, true);
    const noticeText = openInNewTab ? (0, _i18n.__)('All gallery images updated to open in new tab') : (0, _i18n.__)('All gallery images updated to not open in new tab');
    createSuccessNotice(noticeText, {
      id: 'gallery-attributes-openInNewTab',
      type: 'snackbar'
    });
  }
  function updateImagesSize(newSizeSlug) {
    setAttributes({
      sizeSlug: newSizeSlug
    });
    const changedAttributes = {};
    const blocks = [];
    getBlock(clientId).innerBlocks.forEach(block => {
      blocks.push(block.clientId);
      const image = block.attributes.id ? imageData.find(({
        id
      }) => id === block.attributes.id) : null;
      changedAttributes[block.clientId] = (0, _utils2.getImageSizeAttributes)(image, newSizeSlug);
    });
    updateBlockAttributes(blocks, changedAttributes, true);
    const imageSize = imageSizeOptions.find(size => size.value === newSizeSlug);
    createSuccessNotice((0, _i18n.sprintf)( /* translators: %s: image size settings */
    (0, _i18n.__)('All gallery image sizes updated to: %s'), imageSize.label), {
      id: 'gallery-attributes-sizeSlug',
      type: 'snackbar'
    });
  }
  (0, _element.useEffect)(() => {
    // linkTo attribute must be saved so blocks don't break when changing image_default_link_type in options.php.
    if (!linkTo) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        linkTo: window?.wp?.media?.view?.settings?.defaultProps?.link || _constants.LINK_DESTINATION_NONE
      });
    }
  }, [linkTo]);
  const hasImages = !!images.length;
  const hasImageIds = hasImages && images.some(image => !!image.id);
  const imagesUploading = images.some(img => !_element.Platform.isNative ? !img.id && img.url?.indexOf('blob:') === 0 : img.url?.indexOf('file:') === 0);

  // MediaPlaceholder props are different between web and native hence, we provide a platform-specific set.
  const mediaPlaceholderProps = _element.Platform.select({
    web: {
      addToGallery: false,
      disableMediaButtons: imagesUploading,
      value: {}
    },
    native: {
      addToGallery: hasImageIds,
      isAppender: hasImages,
      disableMediaButtons: hasImages && !isSelected || imagesUploading,
      value: hasImageIds ? images : {},
      autoOpenMediaUpload: !hasImages && isSelected && blockWasJustInserted,
      onFocus
    }
  });
  const mediaPlaceholder = (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
    handleUpload: false,
    icon: _sharedIcon.sharedIcon,
    labels: {
      title: (0, _i18n.__)('Gallery'),
      instructions: PLACEHOLDER_TEXT
    },
    onSelect: updateImages,
    accept: "image/*",
    allowedTypes: ALLOWED_MEDIA_TYPES,
    multiple: true,
    onError: onUploadError,
    ...mediaPlaceholderProps
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)(className, 'has-nested-images')
  });
  const nativeInnerBlockProps = _element.Platform.isNative && {
    marginHorizontal: 0,
    marginVertical: 0
  };
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    orientation: 'horizontal',
    renderAppender: false,
    ...nativeInnerBlockProps
  });
  if (!hasImages) {
    return (0, _react.createElement)(_primitives.View, {
      ...innerBlocksProps
    }, innerBlocksProps.children, mediaPlaceholder);
  }
  const hasLinkTo = linkTo && linkTo !== 'none';
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, images.length > 1 && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Columns'),
    value: columns ? columns : (0, _shared.defaultColumnsNumber)(images.length),
    onChange: setColumnsNumber,
    min: 1,
    max: Math.min(MAX_COLUMNS, images.length),
    ...MOBILE_CONTROL_PROPS_RANGE_CONTROL,
    required: true,
    __next40pxDefaultSize: true
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Crop images'),
    checked: !!imageCrop,
    onChange: toggleImageCrop,
    help: getImageCropHelp
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Random order'),
    checked: !!randomOrder,
    onChange: toggleRandomOrder
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link to'),
    value: linkTo,
    onChange: setLinkTo,
    options: linkOptions,
    hideCancelButton: true,
    size: "__unstable-large"
  }), hasLinkTo && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    checked: linkTarget === '_blank',
    onChange: toggleOpenInNewTab
  }), imageSizeOptions?.length > 0 && (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Resolution'),
    help: (0, _i18n.__)('Select the size of the source images.'),
    value: sizeSlug,
    options: imageSizeOptions,
    onChange: updateImagesSize,
    hideCancelButton: true,
    size: "__unstable-large"
  }), _element.Platform.isWeb && !imageSizeOptions && hasImageIds && (0, _react.createElement)(_components.BaseControl, {
    className: 'gallery-image-sizes'
  }, (0, _react.createElement)(_components.BaseControl.VisualLabel, null, (0, _i18n.__)('Resolution')), (0, _react.createElement)(_primitives.View, {
    className: 'gallery-image-sizes__loading'
  }, (0, _react.createElement)(_components.Spinner, null), (0, _i18n.__)('Loading options…'))))), _element.Platform.isWeb && (0, _react.createElement)(_react.Fragment, null, !multiGallerySelection && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "image/*",
    handleUpload: false,
    onSelect: updateImages,
    name: (0, _i18n.__)('Add'),
    multiple: true,
    mediaIds: images.filter(image => image.id).map(image => image.id),
    addToGallery: hasImageIds
  })), (0, _react.createElement)(_gapStyles.default, {
    blockGap: attributes.style?.spacing?.blockGap,
    clientId: clientId
  })), (0, _react.createElement)(_gallery.default, {
    ...props,
    isContentLocked: isContentLocked,
    images: images,
    mediaPlaceholder: !hasImages || _element.Platform.isNative ? mediaPlaceholder : undefined,
    blockProps: innerBlocksProps,
    insertBlocksAfter: insertBlocksAfter,
    multiGallerySelection: multiGallerySelection
  }));
}
var _default = exports.default = (0, _compose.compose)([(0, _viewport.withViewportMatch)({
  isNarrow: '< small'
})])(GalleryEdit);
//# sourceMappingURL=edit.js.map