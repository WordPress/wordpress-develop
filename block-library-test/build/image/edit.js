"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.ImageEdit = ImageEdit;
exports.pickRelevantMediaFiles = exports.isExternalImage = exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blob = require("@wordpress/blob");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _notices = require("@wordpress/notices");
var _lockUnlock = require("../lock-unlock");
var _image = _interopRequireDefault(require("./image"));
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Module constants
 */

const pickRelevantMediaFiles = (image, size) => {
  const imageProps = Object.fromEntries(Object.entries(image !== null && image !== void 0 ? image : {}).filter(([key]) => ['alt', 'id', 'link', 'caption'].includes(key)));
  imageProps.url = image?.sizes?.[size]?.url || image?.media_details?.sizes?.[size]?.source_url || image.url;
  return imageProps;
};

/**
 * Is the URL a temporary blob URL? A blob URL is one that is used temporarily
 * while the image is being uploaded and will not have an id yet allocated.
 *
 * @param {number=} id  The id of the image.
 * @param {string=} url The url of the image.
 *
 * @return {boolean} Is the URL a Blob URL
 */
exports.pickRelevantMediaFiles = pickRelevantMediaFiles;
const isTemporaryImage = (id, url) => !id && (0, _blob.isBlobURL)(url);

/**
 * Is the url for the image hosted externally. An externally hosted image has no
 * id and is not a blob url.
 *
 * @param {number=} id  The id of the image.
 * @param {string=} url The url of the image.
 *
 * @return {boolean} Is the url an externally hosted url?
 */
const isExternalImage = (id, url) => url && !id && !(0, _blob.isBlobURL)(url);

/**
 * Checks if WP generated the specified image size. Size generation is skipped
 * when the image is smaller than the said size.
 *
 * @param {Object} image
 * @param {string} size
 *
 * @return {boolean} Whether or not it has default image size.
 */
exports.isExternalImage = isExternalImage;
function hasSize(image, size) {
  var _image$sizes$size, _image$media_details$;
  return 'url' in ((_image$sizes$size = image?.sizes?.[size]) !== null && _image$sizes$size !== void 0 ? _image$sizes$size : {}) || 'source_url' in ((_image$media_details$ = image?.media_details?.sizes?.[size]) !== null && _image$media_details$ !== void 0 ? _image$media_details$ : {});
}
function ImageEdit({
  attributes,
  setAttributes,
  isSelected: isSingleSelected,
  className,
  insertBlocksAfter,
  onReplace,
  context,
  clientId
}) {
  const {
    url = '',
    alt,
    caption,
    id,
    width,
    height,
    sizeSlug,
    aspectRatio,
    scale,
    align,
    metadata
  } = attributes;
  const [temporaryURL, setTemporaryURL] = (0, _element.useState)();
  const altRef = (0, _element.useRef)();
  (0, _element.useEffect)(() => {
    altRef.current = alt;
  }, [alt]);
  const captionRef = (0, _element.useRef)();
  (0, _element.useEffect)(() => {
    captionRef.current = caption;
  }, [caption]);
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    if (['wide', 'full'].includes(align)) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        width: undefined,
        height: undefined,
        aspectRatio: undefined,
        scale: undefined
      });
    }
  }, [align]);
  const ref = (0, _element.useRef)();
  const {
    getSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  function onUploadError(message) {
    createErrorNotice(message, {
      type: 'snackbar'
    });
    setAttributes({
      src: undefined,
      id: undefined,
      url: undefined
    });
    setTemporaryURL(undefined);
  }
  function onSelectImage(media) {
    if (!media || !media.url) {
      setAttributes({
        url: undefined,
        alt: undefined,
        id: undefined,
        title: undefined,
        caption: undefined
      });
      return;
    }
    if ((0, _blob.isBlobURL)(media.url)) {
      setTemporaryURL(media.url);
      return;
    }
    setTemporaryURL();
    const {
      imageDefaultSize
    } = getSettings();

    // Try to use the previous selected image size if its available
    // otherwise try the default image size or fallback to "full"
    let newSize = 'full';
    if (sizeSlug && hasSize(media, sizeSlug)) {
      newSize = sizeSlug;
    } else if (hasSize(media, imageDefaultSize)) {
      newSize = imageDefaultSize;
    }
    let mediaAttributes = pickRelevantMediaFiles(media, newSize);

    // If a caption text was meanwhile written by the user,
    // make sure the text is not overwritten by empty captions.
    if (captionRef.current && !mediaAttributes.caption) {
      const {
        caption: omittedCaption,
        ...restMediaAttributes
      } = mediaAttributes;
      mediaAttributes = restMediaAttributes;
    }
    let additionalAttributes;
    // Reset the dimension attributes if changing to a different image.
    if (!media.id || media.id !== id) {
      additionalAttributes = {
        sizeSlug: newSize
      };
    } else {
      // Keep the same url when selecting the same file, so "Resolution"
      // option is not changed.
      additionalAttributes = {
        url
      };
    }

    // Check if default link setting should be used.
    let linkDestination = attributes.linkDestination;
    if (!linkDestination) {
      // Use the WordPress option to determine the proper default.
      // The constants used in Gutenberg do not match WP options so a little more complicated than ideal.
      // TODO: fix this in a follow up PR, requires updating media-text and ui component.
      switch (window?.wp?.media?.view?.settings?.defaultProps?.link || _constants.LINK_DESTINATION_NONE) {
        case 'file':
        case _constants.LINK_DESTINATION_MEDIA:
          linkDestination = _constants.LINK_DESTINATION_MEDIA;
          break;
        case 'post':
        case _constants.LINK_DESTINATION_ATTACHMENT:
          linkDestination = _constants.LINK_DESTINATION_ATTACHMENT;
          break;
        case _constants.LINK_DESTINATION_CUSTOM:
          linkDestination = _constants.LINK_DESTINATION_CUSTOM;
          break;
        case _constants.LINK_DESTINATION_NONE:
          linkDestination = _constants.LINK_DESTINATION_NONE;
          break;
      }
    }

    // Check if the image is linked to it's media.
    let href;
    switch (linkDestination) {
      case _constants.LINK_DESTINATION_MEDIA:
        href = media.url;
        break;
      case _constants.LINK_DESTINATION_ATTACHMENT:
        href = media.link;
        break;
    }
    mediaAttributes.href = href;
    setAttributes({
      ...mediaAttributes,
      ...additionalAttributes,
      linkDestination
    });
  }
  function onSelectURL(newURL) {
    if (newURL !== url) {
      setAttributes({
        url: newURL,
        id: undefined,
        sizeSlug: getSettings().imageDefaultSize
      });
    }
  }
  let isTemp = isTemporaryImage(id, url);

  // Upload a temporary image on mount.
  (0, _element.useEffect)(() => {
    if (!isTemp) {
      return;
    }
    const file = (0, _blob.getBlobByURL)(url);
    if (file) {
      const {
        mediaUpload
      } = getSettings();
      if (!mediaUpload) {
        return;
      }
      mediaUpload({
        filesList: [file],
        onFileChange: ([img]) => {
          onSelectImage(img);
        },
        allowedTypes: _constants.ALLOWED_MEDIA_TYPES,
        onError: message => {
          isTemp = false;
          onUploadError(message);
        }
      });
    }
  }, []);

  // If an image is temporary, revoke the Blob url when it is uploaded (and is
  // no longer temporary).
  (0, _element.useEffect)(() => {
    if (isTemp) {
      setTemporaryURL(url);
      return;
    }
    (0, _blob.revokeBlobURL)(temporaryURL);
  }, [isTemp, url]);
  const isExternal = isExternalImage(id, url);
  const src = isExternal ? url : undefined;
  const mediaPreview = !!url && (0, _react.createElement)("img", {
    alt: (0, _i18n.__)('Edit image'),
    title: (0, _i18n.__)('Edit image'),
    className: 'edit-image-preview',
    src: url
  });
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const classes = (0, _classnames.default)(className, {
    'is-transient': temporaryURL,
    'is-resized': !!width || !!height,
    [`size-${sizeSlug}`]: sizeSlug,
    'has-custom-border': !!borderProps.className || borderProps.style && Object.keys(borderProps.style).length > 0
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref,
    className: classes
  });

  // Much of this description is duplicated from MediaPlaceholder.
  const {
    lockUrlControls = false
  } = (0, _data.useSelect)(select => {
    if (!isSingleSelected) {
      return {};
    }
    const {
      getBlockBindingsSource
    } = (0, _lockUnlock.unlock)(select(_blockEditor.store));
    return {
      lockUrlControls: !!metadata?.bindings?.url && getBlockBindingsSource(metadata?.bindings?.url?.source)?.lockAttributesEditing === true
    };
  }, [isSingleSelected]);
  const placeholder = content => {
    return (0, _react.createElement)(_components.Placeholder, {
      className: (0, _classnames.default)('block-editor-media-placeholder', {
        [borderProps.className]: !!borderProps.className && !isSingleSelected
      }),
      withIllustration: true,
      icon: lockUrlControls ? _icons.plugins : _icons.image,
      label: (0, _i18n.__)('Image'),
      instructions: !lockUrlControls && (0, _i18n.__)('Upload an image file, pick one from your media library, or add one with a URL.'),
      style: {
        aspectRatio: !(width && height) && aspectRatio ? aspectRatio : undefined,
        width: height && aspectRatio ? '100%' : width,
        height: width && aspectRatio ? '100%' : height,
        objectFit: scale,
        ...borderProps.style
      }
    }, lockUrlControls ? (0, _react.createElement)("span", {
      className: 'block-bindings-media-placeholder-message'
    }, (0, _i18n.__)('Connected to a custom field')) : content);
  };
  return (0, _react.createElement)("figure", {
    ...blockProps
  }, (0, _react.createElement)(_image.default, {
    temporaryURL: temporaryURL,
    attributes: attributes,
    setAttributes: setAttributes,
    isSingleSelected: isSingleSelected,
    insertBlocksAfter: insertBlocksAfter,
    onReplace: onReplace,
    onSelectImage: onSelectImage,
    onSelectURL: onSelectURL,
    onUploadError: onUploadError,
    containerRef: ref,
    context: context,
    clientId: clientId,
    blockEditingMode: blockEditingMode
  }), (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
    icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: _icons.image
    }),
    onSelect: onSelectImage,
    onSelectURL: onSelectURL,
    onError: onUploadError,
    placeholder: placeholder,
    accept: "image/*",
    allowedTypes: _constants.ALLOWED_MEDIA_TYPES,
    value: {
      id,
      src
    },
    mediaPreview: mediaPreview,
    disableMediaButtons: temporaryURL || url
  }));
}
var _default = exports.default = ImageEdit;
//# sourceMappingURL=edit.js.map