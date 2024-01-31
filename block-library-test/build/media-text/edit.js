"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _blob = require("@wordpress/blob");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _mediaContainer = _interopRequireDefault(require("./media-container"));
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

// this limits the resize to a safe zone to avoid making broken layouts
const applyWidthConstraints = width => Math.max(_constants.WIDTH_CONSTRAINT_PERCENTAGE, Math.min(width, 100 - _constants.WIDTH_CONSTRAINT_PERCENTAGE));
function getImageSourceUrlBySizeSlug(image, slug) {
  // eslint-disable-next-line camelcase
  return image?.media_details?.sizes?.[slug]?.source_url;
}
function attributesFromMedia({
  attributes: {
    linkDestination,
    href
  },
  setAttributes
}) {
  return media => {
    if (!media || !media.url) {
      setAttributes({
        mediaAlt: undefined,
        mediaId: undefined,
        mediaType: undefined,
        mediaUrl: undefined,
        mediaLink: undefined,
        href: undefined,
        focalPoint: undefined
      });
      return;
    }
    if ((0, _blob.isBlobURL)(media.url)) {
      media.type = (0, _blob.getBlobTypeByURL)(media.url);
    }
    let mediaType;
    let src;
    // For media selections originated from a file upload.
    if (media.media_type) {
      if (media.media_type === 'image') {
        mediaType = 'image';
      } else {
        // only images and videos are accepted so if the media_type is not an image we can assume it is a video.
        // video contain the media type of 'file' in the object returned from the rest api.
        mediaType = 'video';
      }
    } else {
      // For media selections originated from existing files in the media library.
      mediaType = media.type;
    }
    if (mediaType === 'image') {
      // Try the "large" size URL, falling back to the "full" size URL below.
      src = media.sizes?.large?.url ||
      // eslint-disable-next-line camelcase
      media.media_details?.sizes?.large?.source_url;
    }
    let newHref = href;
    if (linkDestination === _constants.LINK_DESTINATION_MEDIA) {
      // Update the media link.
      newHref = media.url;
    }

    // Check if the image is linked to the attachment page.
    if (linkDestination === _constants.LINK_DESTINATION_ATTACHMENT) {
      // Update the media link.
      newHref = media.link;
    }
    setAttributes({
      mediaAlt: media.alt,
      mediaId: media.id,
      mediaType,
      mediaUrl: src || media.url,
      mediaLink: media.link || undefined,
      href: newHref,
      focalPoint: undefined
    });
  };
}
function MediaTextEdit({
  attributes,
  isSelected,
  setAttributes
}) {
  const {
    focalPoint,
    href,
    imageFill,
    isStackedOnMobile,
    linkClass,
    linkDestination,
    linkTarget,
    mediaAlt,
    mediaId,
    mediaPosition,
    mediaType,
    mediaUrl,
    mediaWidth,
    rel,
    verticalAlignment,
    allowedBlocks
  } = attributes;
  const mediaSizeSlug = attributes.mediaSizeSlug || _constants.DEFAULT_MEDIA_SIZE_SLUG;
  const {
    imageSizes,
    image
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    return {
      image: mediaId && isSelected ? select(_coreData.store).getMedia(mediaId, {
        context: 'view'
      }) : null,
      imageSizes: getSettings()?.imageSizes
    };
  }, [isSelected, mediaId]);
  const refMediaContainer = (0, _element.useRef)();
  const imperativeFocalPointPreview = value => {
    const {
      style
    } = refMediaContainer.current.resizable;
    const {
      x,
      y
    } = value;
    style.backgroundPosition = `${x * 100}% ${y * 100}%`;
  };
  const [temporaryMediaWidth, setTemporaryMediaWidth] = (0, _element.useState)(null);
  const onSelectMedia = attributesFromMedia({
    attributes,
    setAttributes
  });
  const onSetHref = props => {
    setAttributes(props);
  };
  const onWidthChange = width => {
    setTemporaryMediaWidth(applyWidthConstraints(width));
  };
  const commitWidthChange = width => {
    setAttributes({
      mediaWidth: applyWidthConstraints(width)
    });
    setTemporaryMediaWidth(null);
  };
  const classNames = (0, _classnames.default)({
    'has-media-on-the-right': 'right' === mediaPosition,
    'is-selected': isSelected,
    'is-stacked-on-mobile': isStackedOnMobile,
    [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    'is-image-fill': imageFill
  });
  const widthString = `${temporaryMediaWidth || mediaWidth}%`;
  const gridTemplateColumns = 'right' === mediaPosition ? `1fr ${widthString}` : `${widthString} 1fr`;
  const style = {
    gridTemplateColumns,
    msGridColumns: gridTemplateColumns
  };
  const onMediaAltChange = newMediaAlt => {
    setAttributes({
      mediaAlt: newMediaAlt
    });
  };
  const onVerticalAlignmentChange = alignment => {
    setAttributes({
      verticalAlignment: alignment
    });
  };
  const imageSizeOptions = imageSizes.filter(({
    slug
  }) => getImageSourceUrlBySizeSlug(image, slug)).map(({
    name,
    slug
  }) => ({
    value: slug,
    label: name
  }));
  const updateImage = newMediaSizeSlug => {
    const newUrl = getImageSourceUrlBySizeSlug(image, newMediaSizeSlug);
    if (!newUrl) {
      return null;
    }
    setAttributes({
      mediaUrl: newUrl,
      mediaSizeSlug: newMediaSizeSlug
    });
  };
  const mediaTextGeneralSettings = (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Stack on mobile'),
    checked: isStackedOnMobile,
    onChange: () => setAttributes({
      isStackedOnMobile: !isStackedOnMobile
    })
  }), mediaType === 'image' && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Crop image to fill entire column'),
    checked: !!imageFill,
    onChange: () => setAttributes({
      imageFill: !imageFill
    })
  }), imageFill && mediaUrl && mediaType === 'image' && (0, _react.createElement)(_components.FocalPointPicker, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Focal point'),
    url: mediaUrl,
    value: focalPoint,
    onChange: value => setAttributes({
      focalPoint: value
    }),
    onDragStart: imperativeFocalPointPreview,
    onDrag: imperativeFocalPointPreview
  }), mediaType === 'image' && (0, _react.createElement)(_components.TextareaControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Alternative text'),
    value: mediaAlt,
    onChange: onMediaAltChange,
    help: (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ExternalLink, {
      href: "https://www.w3.org/WAI/tutorials/images/decision-tree"
    }, (0, _i18n.__)('Describe the purpose of the image.')), (0, _react.createElement)("br", null), (0, _i18n.__)('Leave empty if decorative.'))
  }), mediaType === 'image' && (0, _react.createElement)(_blockEditor.__experimentalImageSizeControl, {
    onChangeImage: updateImage,
    slug: mediaSizeSlug,
    imageSizeOptions: imageSizeOptions,
    isResizable: false,
    imageSizeHelp: (0, _i18n.__)('Select the size of the source image.')
  }), mediaUrl && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Media width'),
    value: temporaryMediaWidth || mediaWidth,
    onChange: commitWidthChange,
    min: _constants.WIDTH_CONSTRAINT_PERCENTAGE,
    max: 100 - _constants.WIDTH_CONSTRAINT_PERCENTAGE
  }));
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classNames,
    style
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    className: 'wp-block-media-text__content'
  }, {
    template: _constants.TEMPLATE,
    allowedBlocks
  });
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, mediaTextGeneralSettings), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, blockEditingMode === 'default' && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockVerticalAlignmentControl, {
    onChange: onVerticalAlignmentChange,
    value: verticalAlignment
  }), (0, _react.createElement)(_components.ToolbarButton, {
    icon: _icons.pullLeft,
    title: (0, _i18n.__)('Show media on left'),
    isActive: mediaPosition === 'left',
    onClick: () => setAttributes({
      mediaPosition: 'left'
    })
  }), (0, _react.createElement)(_components.ToolbarButton, {
    icon: _icons.pullRight,
    title: (0, _i18n.__)('Show media on right'),
    isActive: mediaPosition === 'right',
    onClick: () => setAttributes({
      mediaPosition: 'right'
    })
  })), mediaType === 'image' && (0, _react.createElement)(_blockEditor.__experimentalImageURLInputUI, {
    url: href || '',
    onChangeUrl: onSetHref,
    linkDestination: linkDestination,
    mediaType: mediaType,
    mediaUrl: image && image.source_url,
    mediaLink: image && image.link,
    linkTarget: linkTarget,
    linkClass: linkClass,
    rel: rel
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, mediaPosition === 'right' && (0, _react.createElement)("div", {
    ...innerBlocksProps
  }), (0, _react.createElement)(_mediaContainer.default, {
    className: "wp-block-media-text__media",
    onSelectMedia: onSelectMedia,
    onWidthChange: onWidthChange,
    commitWidthChange: commitWidthChange,
    ref: refMediaContainer,
    enableResize: blockEditingMode === 'default',
    focalPoint,
    imageFill,
    isSelected,
    isStackedOnMobile,
    mediaAlt,
    mediaId,
    mediaPosition,
    mediaType,
    mediaUrl,
    mediaWidth
  }), mediaPosition !== 'right' && (0, _react.createElement)("div", {
    ...innerBlocksProps
  })));
}
var _default = exports.default = MediaTextEdit;
//# sourceMappingURL=edit.js.map