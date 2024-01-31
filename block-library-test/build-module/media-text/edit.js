import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useState, useRef } from '@wordpress/element';
import { BlockControls, BlockVerticalAlignmentControl, useInnerBlocksProps, InspectorControls, useBlockProps, __experimentalImageURLInputUI as ImageURLInputUI, __experimentalImageSizeControl as ImageSizeControl, store as blockEditorStore, useBlockEditingMode } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextareaControl, ToggleControl, ToolbarButton, ExternalLink, FocalPointPicker } from '@wordpress/components';
import { isBlobURL, getBlobTypeByURL } from '@wordpress/blob';
import { pullLeft, pullRight } from '@wordpress/icons';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import MediaContainer from './media-container';
import { DEFAULT_MEDIA_SIZE_SLUG, WIDTH_CONSTRAINT_PERCENTAGE, LINK_DESTINATION_MEDIA, LINK_DESTINATION_ATTACHMENT, TEMPLATE } from './constants';

// this limits the resize to a safe zone to avoid making broken layouts
const applyWidthConstraints = width => Math.max(WIDTH_CONSTRAINT_PERCENTAGE, Math.min(width, 100 - WIDTH_CONSTRAINT_PERCENTAGE));
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
    if (isBlobURL(media.url)) {
      media.type = getBlobTypeByURL(media.url);
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
    if (linkDestination === LINK_DESTINATION_MEDIA) {
      // Update the media link.
      newHref = media.url;
    }

    // Check if the image is linked to the attachment page.
    if (linkDestination === LINK_DESTINATION_ATTACHMENT) {
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
  const mediaSizeSlug = attributes.mediaSizeSlug || DEFAULT_MEDIA_SIZE_SLUG;
  const {
    imageSizes,
    image
  } = useSelect(select => {
    const {
      getSettings
    } = select(blockEditorStore);
    return {
      image: mediaId && isSelected ? select(coreStore).getMedia(mediaId, {
        context: 'view'
      }) : null,
      imageSizes: getSettings()?.imageSizes
    };
  }, [isSelected, mediaId]);
  const refMediaContainer = useRef();
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
  const [temporaryMediaWidth, setTemporaryMediaWidth] = useState(null);
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
  const classNames = classnames({
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
  const mediaTextGeneralSettings = createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Stack on mobile'),
    checked: isStackedOnMobile,
    onChange: () => setAttributes({
      isStackedOnMobile: !isStackedOnMobile
    })
  }), mediaType === 'image' && createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Crop image to fill entire column'),
    checked: !!imageFill,
    onChange: () => setAttributes({
      imageFill: !imageFill
    })
  }), imageFill && mediaUrl && mediaType === 'image' && createElement(FocalPointPicker, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Focal point'),
    url: mediaUrl,
    value: focalPoint,
    onChange: value => setAttributes({
      focalPoint: value
    }),
    onDragStart: imperativeFocalPointPreview,
    onDrag: imperativeFocalPointPreview
  }), mediaType === 'image' && createElement(TextareaControl, {
    __nextHasNoMarginBottom: true,
    label: __('Alternative text'),
    value: mediaAlt,
    onChange: onMediaAltChange,
    help: createElement(Fragment, null, createElement(ExternalLink, {
      href: "https://www.w3.org/WAI/tutorials/images/decision-tree"
    }, __('Describe the purpose of the image.')), createElement("br", null), __('Leave empty if decorative.'))
  }), mediaType === 'image' && createElement(ImageSizeControl, {
    onChangeImage: updateImage,
    slug: mediaSizeSlug,
    imageSizeOptions: imageSizeOptions,
    isResizable: false,
    imageSizeHelp: __('Select the size of the source image.')
  }), mediaUrl && createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    label: __('Media width'),
    value: temporaryMediaWidth || mediaWidth,
    onChange: commitWidthChange,
    min: WIDTH_CONSTRAINT_PERCENTAGE,
    max: 100 - WIDTH_CONSTRAINT_PERCENTAGE
  }));
  const blockProps = useBlockProps({
    className: classNames,
    style
  });
  const innerBlocksProps = useInnerBlocksProps({
    className: 'wp-block-media-text__content'
  }, {
    template: TEMPLATE,
    allowedBlocks
  });
  const blockEditingMode = useBlockEditingMode();
  return createElement(Fragment, null, createElement(InspectorControls, null, mediaTextGeneralSettings), createElement(BlockControls, {
    group: "block"
  }, blockEditingMode === 'default' && createElement(Fragment, null, createElement(BlockVerticalAlignmentControl, {
    onChange: onVerticalAlignmentChange,
    value: verticalAlignment
  }), createElement(ToolbarButton, {
    icon: pullLeft,
    title: __('Show media on left'),
    isActive: mediaPosition === 'left',
    onClick: () => setAttributes({
      mediaPosition: 'left'
    })
  }), createElement(ToolbarButton, {
    icon: pullRight,
    title: __('Show media on right'),
    isActive: mediaPosition === 'right',
    onClick: () => setAttributes({
      mediaPosition: 'right'
    })
  })), mediaType === 'image' && createElement(ImageURLInputUI, {
    url: href || '',
    onChangeUrl: onSetHref,
    linkDestination: linkDestination,
    mediaType: mediaType,
    mediaUrl: image && image.source_url,
    mediaLink: image && image.link,
    linkTarget: linkTarget,
    linkClass: linkClass,
    rel: rel
  })), createElement("div", {
    ...blockProps
  }, mediaPosition === 'right' && createElement("div", {
    ...innerBlocksProps
  }), createElement(MediaContainer, {
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
  }), mediaPosition !== 'right' && createElement("div", {
    ...innerBlocksProps
  })));
}
export default MediaTextEdit;
//# sourceMappingURL=edit.js.map