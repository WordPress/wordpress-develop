"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostFeaturedImageEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _notices = require("@wordpress/notices");
var _dimensionControls = _interopRequireDefault(require("./dimension-controls"));
var _overlay = _interopRequireDefault(require("./overlay"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ALLOWED_MEDIA_TYPES = ['image'];
function getMediaSourceUrlBySizeSlug(media, slug) {
  return media?.media_details?.sizes?.[slug]?.source_url || media?.source_url;
}
const disabledClickProps = {
  onClick: event => event.preventDefault(),
  'aria-disabled': true
};
function PostFeaturedImageEdit({
  clientId,
  attributes,
  setAttributes,
  context: {
    postId,
    postType: postTypeSlug,
    queryId
  }
}) {
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const {
    isLink,
    aspectRatio,
    height,
    width,
    scale,
    sizeSlug,
    rel,
    linkTarget,
    useFirstImageFromPost
  } = attributes;
  const [storedFeaturedImage, setFeaturedImage] = (0, _coreData.useEntityProp)('postType', postTypeSlug, 'featured_media', postId);

  // Fallback to post content if no featured image is set.
  // This is needed for the "Use first image from post" option.
  const [postContent] = (0, _coreData.useEntityProp)('postType', postTypeSlug, 'content', postId);
  const featuredImage = (0, _element.useMemo)(() => {
    if (storedFeaturedImage) {
      return storedFeaturedImage;
    }
    if (!useFirstImageFromPost) {
      return;
    }
    const imageOpener = /<!--\s+wp:(?:core\/)?image\s+(?<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*)?}\s+)?-->/.exec(postContent);
    const imageId = imageOpener?.groups?.attrs && JSON.parse(imageOpener.groups.attrs)?.id;
    return imageId;
  }, [storedFeaturedImage, useFirstImageFromPost, postContent]);
  const {
    media,
    postType,
    postPermalink
  } = (0, _data.useSelect)(select => {
    const {
      getMedia,
      getPostType,
      getEditedEntityRecord
    } = select(_coreData.store);
    return {
      media: featuredImage && getMedia(featuredImage, {
        context: 'view'
      }),
      postType: postTypeSlug && getPostType(postTypeSlug),
      postPermalink: getEditedEntityRecord('postType', postTypeSlug, postId)?.link
    };
  }, [featuredImage, postTypeSlug, postId]);
  const mediaUrl = getMediaSourceUrlBySizeSlug(media, sizeSlug);
  const imageSizes = (0, _data.useSelect)(select => select(_blockEditor.store).getSettings().imageSizes, []);
  const imageSizeOptions = imageSizes.filter(({
    slug
  }) => {
    return media?.media_details?.sizes?.[slug]?.source_url;
  }).map(({
    name,
    slug
  }) => ({
    value: slug,
    label: name
  }));
  const blockProps = (0, _blockEditor.useBlockProps)({
    style: {
      width,
      height,
      aspectRatio
    }
  });
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const placeholder = content => {
    return (0, _react.createElement)(_components.Placeholder, {
      className: (0, _classnames.default)('block-editor-media-placeholder', borderProps.className),
      withIllustration: true,
      style: {
        height: !!aspectRatio && '100%',
        width: !!aspectRatio && '100%',
        ...borderProps.style
      }
    }, content);
  };
  const onSelectImage = value => {
    if (value?.id) {
      setFeaturedImage(value.id);
    }
  };
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const onUploadError = message => {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  };
  const controls = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_dimensionControls.default, {
    clientId: clientId,
    attributes: attributes,
    setAttributes: setAttributes,
    imageSizeOptions: imageSizeOptions
  }), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: postType?.labels.singular_name ? (0, _i18n.sprintf)(
    // translators: %s: Name of the post type e.g: "Page".
    (0, _i18n.__)('Link to %s'), postType.labels.singular_name) : (0, _i18n.__)('Link to post'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link rel'),
    value: rel,
    onChange: newRel => setAttributes({
      rel: newRel
    })
  })))));
  let image;

  /**
   * A Post Featured Image block should not have image replacement
   * or upload options in the following cases:
   * - Is placed in a Query Loop. This is a consious decision to
   * prevent content editing of different posts in Query Loop, and
   * this could change in the future.
   * - Is in a context where it does not have a postId (for example
   * in a template or template part).
   */
  if (!featuredImage && (isDescendentOfQueryLoop || !postId)) {
    return (0, _react.createElement)(_react.Fragment, null, controls, (0, _react.createElement)("div", {
      ...blockProps
    }, !!isLink ? (0, _react.createElement)("a", {
      href: postPermalink,
      target: linkTarget,
      ...disabledClickProps
    }, placeholder()) : placeholder(), (0, _react.createElement)(_overlay.default, {
      attributes: attributes,
      setAttributes: setAttributes,
      clientId: clientId
    })));
  }
  const label = (0, _i18n.__)('Add a featured image');
  const imageStyles = {
    ...borderProps.style,
    height: aspectRatio ? '100%' : height,
    width: !!aspectRatio && '100%',
    objectFit: !!(height || aspectRatio) && scale
  };

  /**
   * When the post featured image block is placed in a context where:
   * - It has a postId (for example in a single post)
   * - It is not inside a query loop
   * - It has no image assigned yet
   * Then display the placeholder with the image upload option.
   */
  if (!featuredImage) {
    image = (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      onSelect: onSelectImage,
      accept: "image/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      onError: onUploadError,
      placeholder: placeholder,
      mediaLibraryButton: ({
        open
      }) => {
        return (0, _react.createElement)(_components.Button, {
          icon: _icons.upload,
          variant: "primary",
          label: label,
          showTooltip: true,
          tooltipPosition: "top center",
          onClick: () => {
            open();
          }
        });
      }
    });
  } else {
    // We have a Featured image so show a Placeholder if is loading.
    image = !media ? placeholder() : (0, _react.createElement)("img", {
      className: borderProps.className,
      src: mediaUrl,
      alt: media.alt_text ? (0, _i18n.sprintf)(
      // translators: %s: The image's alt text.
      (0, _i18n.__)('Featured image: %s'), media.alt_text) : (0, _i18n.__)('Featured image'),
      style: imageStyles
    });
  }

  /**
   * When the post featured image block:
   * - Has an image assigned
   * - Is not inside a query loop
   * Then display the image and the image replacement option.
   */
  return (0, _react.createElement)(_react.Fragment, null, controls, !!media && !isDescendentOfQueryLoop && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: featuredImage,
    mediaURL: mediaUrl,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "image/*",
    onSelect: onSelectImage,
    onError: onUploadError
  }, (0, _react.createElement)(_components.MenuItem, {
    onClick: () => setFeaturedImage(0)
  }, (0, _i18n.__)('Reset')))), (0, _react.createElement)("figure", {
    ...blockProps
  }, !!isLink ? (0, _react.createElement)("a", {
    href: postPermalink,
    target: linkTarget,
    ...disabledClickProps
  }, image) : image, (0, _react.createElement)(_overlay.default, {
    attributes: attributes,
    setAttributes: setAttributes,
    clientId: clientId
  })));
}
//# sourceMappingURL=edit.js.map