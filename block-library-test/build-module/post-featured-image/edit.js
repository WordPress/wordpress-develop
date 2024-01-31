import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { MenuItem, ToggleControl, PanelBody, Placeholder, Button, TextControl } from '@wordpress/components';
import { InspectorControls, BlockControls, MediaPlaceholder, MediaReplaceFlow, useBlockProps, store as blockEditorStore, __experimentalUseBorderProps as useBorderProps } from '@wordpress/block-editor';
import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { upload } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import DimensionControls from './dimension-controls';
import Overlay from './overlay';
const ALLOWED_MEDIA_TYPES = ['image'];
function getMediaSourceUrlBySizeSlug(media, slug) {
  return media?.media_details?.sizes?.[slug]?.source_url || media?.source_url;
}
const disabledClickProps = {
  onClick: event => event.preventDefault(),
  'aria-disabled': true
};
export default function PostFeaturedImageEdit({
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
  const [storedFeaturedImage, setFeaturedImage] = useEntityProp('postType', postTypeSlug, 'featured_media', postId);

  // Fallback to post content if no featured image is set.
  // This is needed for the "Use first image from post" option.
  const [postContent] = useEntityProp('postType', postTypeSlug, 'content', postId);
  const featuredImage = useMemo(() => {
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
  } = useSelect(select => {
    const {
      getMedia,
      getPostType,
      getEditedEntityRecord
    } = select(coreStore);
    return {
      media: featuredImage && getMedia(featuredImage, {
        context: 'view'
      }),
      postType: postTypeSlug && getPostType(postTypeSlug),
      postPermalink: getEditedEntityRecord('postType', postTypeSlug, postId)?.link
    };
  }, [featuredImage, postTypeSlug, postId]);
  const mediaUrl = getMediaSourceUrlBySizeSlug(media, sizeSlug);
  const imageSizes = useSelect(select => select(blockEditorStore).getSettings().imageSizes, []);
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
  const blockProps = useBlockProps({
    style: {
      width,
      height,
      aspectRatio
    }
  });
  const borderProps = useBorderProps(attributes);
  const placeholder = content => {
    return createElement(Placeholder, {
      className: classnames('block-editor-media-placeholder', borderProps.className),
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
  } = useDispatch(noticesStore);
  const onUploadError = message => {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  };
  const controls = createElement(Fragment, null, createElement(DimensionControls, {
    clientId: clientId,
    attributes: attributes,
    setAttributes: setAttributes,
    imageSizeOptions: imageSizeOptions
  }), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: postType?.labels.singular_name ? sprintf(
    // translators: %s: Name of the post type e.g: "Page".
    __('Link to %s'), postType.labels.singular_name) : __('Link to post'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && createElement(Fragment, null, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }), createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    label: __('Link rel'),
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
    return createElement(Fragment, null, controls, createElement("div", {
      ...blockProps
    }, !!isLink ? createElement("a", {
      href: postPermalink,
      target: linkTarget,
      ...disabledClickProps
    }, placeholder()) : placeholder(), createElement(Overlay, {
      attributes: attributes,
      setAttributes: setAttributes,
      clientId: clientId
    })));
  }
  const label = __('Add a featured image');
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
    image = createElement(MediaPlaceholder, {
      onSelect: onSelectImage,
      accept: "image/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      onError: onUploadError,
      placeholder: placeholder,
      mediaLibraryButton: ({
        open
      }) => {
        return createElement(Button, {
          icon: upload,
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
    image = !media ? placeholder() : createElement("img", {
      className: borderProps.className,
      src: mediaUrl,
      alt: media.alt_text ? sprintf(
      // translators: %s: The image's alt text.
      __('Featured image: %s'), media.alt_text) : __('Featured image'),
      style: imageStyles
    });
  }

  /**
   * When the post featured image block:
   * - Has an image assigned
   * - Is not inside a query loop
   * Then display the image and the image replacement option.
   */
  return createElement(Fragment, null, controls, !!media && !isDescendentOfQueryLoop && createElement(BlockControls, {
    group: "other"
  }, createElement(MediaReplaceFlow, {
    mediaId: featuredImage,
    mediaURL: mediaUrl,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "image/*",
    onSelect: onSelectImage,
    onError: onUploadError
  }, createElement(MenuItem, {
    onClick: () => setFeaturedImage(0)
  }, __('Reset')))), createElement("figure", {
    ...blockProps
  }, !!isLink ? createElement("a", {
    href: postPermalink,
    target: linkTarget,
    ...disabledClickProps
  }, image) : image, createElement(Overlay, {
    attributes: attributes,
    setAttributes: setAttributes,
    clientId: clientId
  })));
}
//# sourceMappingURL=edit.js.map