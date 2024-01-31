"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _blob = require("@wordpress/blob");
var _data = require("@wordpress/data");
var _viewport = require("@wordpress/viewport");
var _primitives = require("@wordpress/primitives");
var _coreData = require("@wordpress/core-data");
var _sharedIcon = require("../shared-icon");
var _shared = require("./shared");
var _deprecated = require("../deprecated");
var _gallery = _interopRequireDefault(require("./gallery"));
var _constants = require("./constants");
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
  label: (0, _i18n.__)('None')
}];
const ALLOWED_MEDIA_TYPES = ['image'];
const PLACEHOLDER_TEXT = _element.Platform.select({
  web: (0, _i18n.__)('Drag images, upload new ones or select files from your library.'),
  native: (0, _i18n.__)('ADD MEDIA')
});
const MOBILE_CONTROL_PROPS_RANGE_CONTROL = _element.Platform.select({
  web: {},
  native: {
    type: 'stepper'
  }
});
function GalleryEdit(props) {
  const {
    attributes,
    clientId,
    isSelected,
    noticeUI,
    noticeOperations,
    onFocus
  } = props;
  const {
    columns = (0, _deprecated.defaultColumnsNumberV1)(attributes),
    imageCrop,
    images,
    linkTo,
    sizeSlug
  } = attributes;
  const [selectedImage, setSelectedImage] = (0, _element.useState)();
  const [attachmentCaptions, setAttachmentCaptions] = (0, _element.useState)();
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    imageSizes,
    mediaUpload,
    getMedia,
    wasBlockJustInserted
  } = (0, _data.useSelect)(select => {
    const settings = select(_blockEditor.store).getSettings();
    return {
      imageSizes: settings.imageSizes,
      mediaUpload: settings.mediaUpload,
      getMedia: select(_coreData.store).getMedia,
      wasBlockJustInserted: select(_blockEditor.store).wasBlockJustInserted(clientId, 'inserter_menu')
    };
  });
  const resizedImages = (0, _element.useMemo)(() => {
    if (isSelected) {
      var _attributes$ids;
      return ((_attributes$ids = attributes.ids) !== null && _attributes$ids !== void 0 ? _attributes$ids : []).reduce((currentResizedImages, id) => {
        if (!id) {
          return currentResizedImages;
        }
        const image = getMedia(id);
        const sizes = imageSizes.reduce((currentSizes, size) => {
          const defaultUrl = image?.sizes?.[size.slug]?.url;
          const mediaDetailsUrl = image?.media_details?.sizes?.[size.slug]?.source_url;
          return {
            ...currentSizes,
            [size.slug]: defaultUrl || mediaDetailsUrl
          };
        }, {});
        return {
          ...currentResizedImages,
          [parseInt(id, 10)]: sizes
        };
      }, {});
    }
    return {};
  }, [isSelected, attributes.ids, imageSizes]);
  function onFocusGalleryCaption() {
    setSelectedImage();
  }
  function setAttributes(newAttrs) {
    if (newAttrs.ids) {
      throw new Error('The "ids" attribute should not be changed directly. It is managed automatically when "images" attribute changes');
    }
    if (newAttrs.images) {
      newAttrs = {
        ...newAttrs,
        // Unlike images[ n ].id which is a string, always ensure the
        // ids array contains numbers as per its attribute type.
        ids: newAttrs.images.map(({
          id
        }) => parseInt(id, 10))
      };
    }
    props.setAttributes(newAttrs);
  }
  function onSelectImage(index) {
    return () => {
      setSelectedImage(index);
    };
  }
  function onDeselectImage() {
    return () => {
      setSelectedImage();
    };
  }
  function onMove(oldIndex, newIndex) {
    const newImages = [...images];
    newImages.splice(newIndex, 1, images[oldIndex]);
    newImages.splice(oldIndex, 1, images[newIndex]);
    setSelectedImage(newIndex);
    setAttributes({
      images: newImages
    });
  }
  function onMoveForward(oldIndex) {
    return () => {
      if (oldIndex === images.length - 1) {
        return;
      }
      onMove(oldIndex, oldIndex + 1);
    };
  }
  function onMoveBackward(oldIndex) {
    return () => {
      if (oldIndex === 0) {
        return;
      }
      onMove(oldIndex, oldIndex - 1);
    };
  }
  function onRemoveImage(index) {
    return () => {
      const newImages = images.filter((img, i) => index !== i);
      setSelectedImage();
      setAttributes({
        images: newImages,
        columns: attributes.columns ? Math.min(newImages.length, attributes.columns) : attributes.columns
      });
    };
  }
  function selectCaption(newImage) {
    // The image id in both the images and attachmentCaptions arrays is a
    // string, so ensure comparison works correctly by converting the
    // newImage.id to a string.
    const newImageId = newImage.id.toString();
    const currentImage = images.find(({
      id
    }) => id === newImageId);
    const currentImageCaption = currentImage ? currentImage.caption : newImage.caption;
    if (!attachmentCaptions) {
      return currentImageCaption;
    }
    const attachment = attachmentCaptions.find(({
      id
    }) => id === newImageId);

    // If the attachment caption is updated.
    if (attachment && attachment.caption !== newImage.caption) {
      return newImage.caption;
    }
    return currentImageCaption;
  }
  function onSelectImages(newImages) {
    setAttachmentCaptions(newImages.map(newImage => ({
      // Store the attachmentCaption id as a string for consistency
      // with the type of the id in the images attribute.
      id: newImage.id.toString(),
      caption: newImage.caption
    })));
    setAttributes({
      images: newImages.map(newImage => ({
        ...(0, _shared.pickRelevantMediaFiles)(newImage, sizeSlug),
        caption: selectCaption(newImage, images, attachmentCaptions),
        // The id value is stored in a data attribute, so when the
        // block is parsed it's converted to a string. Converting
        // to a string here ensures it's type is consistent.
        id: newImage.id.toString()
      })),
      columns: attributes.columns ? Math.min(newImages.length, attributes.columns) : attributes.columns
    });
  }
  function onUploadError(message) {
    noticeOperations.removeAllNotices();
    noticeOperations.createErrorNotice(message);
  }
  function setLinkTo(value) {
    setAttributes({
      linkTo: value
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
  function setImageAttributes(index, newAttributes) {
    if (!images[index]) {
      return;
    }
    setAttributes({
      images: [...images.slice(0, index), {
        ...images[index],
        ...newAttributes
      }, ...images.slice(index + 1)]
    });
  }
  function getImagesSizeOptions() {
    const resizedImageSizes = Object.values(resizedImages);
    return imageSizes.filter(({
      slug
    }) => resizedImageSizes.some(sizes => sizes[slug])).map(({
      name,
      slug
    }) => ({
      value: slug,
      label: name
    }));
  }
  function updateImagesSize(newSizeSlug) {
    const updatedImages = (images !== null && images !== void 0 ? images : []).map(image => {
      if (!image.id) {
        return image;
      }
      const url = resizedImages[parseInt(image.id, 10)]?.[newSizeSlug];
      return {
        ...image,
        ...(url && {
          url
        })
      };
    });
    setAttributes({
      images: updatedImages,
      sizeSlug: newSizeSlug
    });
  }
  (0, _element.useEffect)(() => {
    if (_element.Platform.OS === 'web' && images && images.length > 0 && images.every(({
      url
    }) => (0, _blob.isBlobURL)(url))) {
      const filesList = images.map(({
        url
      }) => (0, _blob.getBlobByURL)(url));
      images.forEach(({
        url
      }) => (0, _blob.revokeBlobURL)(url));
      mediaUpload({
        filesList,
        onFileChange: onSelectImages,
        allowedTypes: ['image']
      });
    }
  }, []);
  (0, _element.useEffect)(() => {
    // Deselect images when deselecting the block.
    if (!isSelected) {
      setSelectedImage();
    }
  }, [isSelected]);
  (0, _element.useEffect)(() => {
    // linkTo attribute must be saved so blocks don't break when changing
    // image_default_link_type in options.php.
    if (!linkTo) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        linkTo: window?.wp?.media?.view?.settings?.defaultProps?.link || _constants.LINK_DESTINATION_NONE
      });
    }
  }, [linkTo]);
  const hasImages = !!images.length;
  const hasImageIds = hasImages && images.some(image => !!image.id);
  const mediaPlaceholder = (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
    addToGallery: hasImageIds,
    isAppender: hasImages,
    disableMediaButtons: hasImages && !isSelected,
    icon: !hasImages && _sharedIcon.sharedIcon,
    labels: {
      title: !hasImages && (0, _i18n.__)('Gallery'),
      instructions: !hasImages && PLACEHOLDER_TEXT
    },
    onSelect: onSelectImages,
    accept: "image/*",
    allowedTypes: ALLOWED_MEDIA_TYPES,
    multiple: true,
    value: hasImageIds ? images : {},
    onError: onUploadError,
    notices: hasImages ? undefined : noticeUI,
    onFocus: onFocus,
    autoOpenMediaUpload: !hasImages && isSelected && wasBlockJustInserted
  });
  const blockProps = (0, _blockEditor.useBlockProps)();
  if (!hasImages) {
    return (0, _react.createElement)(_primitives.View, {
      ...blockProps
    }, mediaPlaceholder);
  }
  const imageSizeOptions = getImagesSizeOptions();
  const shouldShowSizeOptions = hasImages && imageSizeOptions.length > 0;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, images.length > 1 && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Columns'),
    value: columns,
    onChange: setColumnsNumber,
    min: 1,
    max: Math.min(MAX_COLUMNS, images.length),
    ...MOBILE_CONTROL_PROPS_RANGE_CONTROL,
    required: true
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Crop images'),
    checked: !!imageCrop,
    onChange: toggleImageCrop,
    help: getImageCropHelp
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link to'),
    value: linkTo,
    onChange: setLinkTo,
    options: linkOptions,
    hideCancelButton: true
  }), shouldShowSizeOptions && (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Image size'),
    value: sizeSlug,
    options: imageSizeOptions,
    onChange: updateImagesSize,
    hideCancelButton: true
  }))), noticeUI, (0, _react.createElement)(_gallery.default, {
    ...props,
    selectedImage: selectedImage,
    mediaPlaceholder: mediaPlaceholder,
    onMoveBackward: onMoveBackward,
    onMoveForward: onMoveForward,
    onRemoveImage: onRemoveImage,
    onSelectImage: onSelectImage,
    onDeselectImage: onDeselectImage,
    onSetImageAttributes: setImageAttributes,
    blockProps: blockProps
    // This prop is used by gallery.native.js.
    ,
    onFocusGalleryCaption: onFocusGalleryCaption
  }));
}
var _default = exports.default = (0, _compose.compose)([_components.withNotices, (0, _viewport.withViewportMatch)({
  isNarrow: '< small'
})])(GalleryEdit);
//# sourceMappingURL=edit.js.map