import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { getBlobByURL, isBlobURL } from '@wordpress/blob';
import { BaseControl, Button, Disabled, PanelBody, Spinner, Placeholder } from '@wordpress/components';
import { BlockControls, BlockIcon, InspectorControls, MediaPlaceholder, MediaUpload, MediaUploadCheck, MediaReplaceFlow, useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';
import { useRef, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useInstanceId } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { video as icon } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { createUpgradedEmbedBlock } from '../embed/util';
import VideoCommonSettings from './edit-common-settings';
import TracksEditor from './tracks-editor';
import Tracks from './tracks';
import { Caption } from '../utils/caption';

// Much of this description is duplicated from MediaPlaceholder.
const placeholder = content => {
  return createElement(Placeholder, {
    className: "block-editor-media-placeholder",
    withIllustration: true,
    icon: icon,
    label: __('Video'),
    instructions: __('Upload a video file, pick one from your media library, or add one with a URL.')
  }, content);
};
const ALLOWED_MEDIA_TYPES = ['video'];
const VIDEO_POSTER_ALLOWED_MEDIA_TYPES = ['image'];
function VideoEdit({
  isSelected: isSingleSelected,
  attributes,
  className,
  setAttributes,
  insertBlocksAfter,
  onReplace
}) {
  const instanceId = useInstanceId(VideoEdit);
  const videoPlayer = useRef();
  const posterImageButton = useRef();
  const {
    id,
    controls,
    poster,
    src,
    tracks
  } = attributes;
  const isTemporaryVideo = !id && isBlobURL(src);
  const {
    getSettings
  } = useSelect(blockEditorStore);
  useEffect(() => {
    if (!id && isBlobURL(src)) {
      const file = getBlobByURL(src);
      if (file) {
        getSettings().mediaUpload({
          filesList: [file],
          onFileChange: ([media]) => onSelectVideo(media),
          onError: onUploadError,
          allowedTypes: ALLOWED_MEDIA_TYPES
        });
      }
    }
  }, []);
  useEffect(() => {
    // Placeholder may be rendered.
    if (videoPlayer.current) {
      videoPlayer.current.load();
    }
  }, [poster]);
  function onSelectVideo(media) {
    if (!media || !media.url) {
      // In this case there was an error
      // previous attributes should be removed
      // because they may be temporary blob urls.
      setAttributes({
        src: undefined,
        id: undefined,
        poster: undefined,
        caption: undefined
      });
      return;
    }

    // Sets the block's attribute and updates the edit component from the
    // selected media.
    setAttributes({
      src: media.url,
      id: media.id,
      poster: media.image?.src !== media.icon ? media.image?.src : undefined,
      caption: media.caption
    });
  }
  function onSelectURL(newSrc) {
    if (newSrc !== src) {
      // Check if there's an embed block that handles this URL.
      const embedBlock = createUpgradedEmbedBlock({
        attributes: {
          url: newSrc
        }
      });
      if (undefined !== embedBlock && onReplace) {
        onReplace(embedBlock);
        return;
      }
      setAttributes({
        src: newSrc,
        id: undefined,
        poster: undefined
      });
    }
  }
  const {
    createErrorNotice
  } = useDispatch(noticesStore);
  function onUploadError(message) {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  }
  const classes = classnames(className, {
    'is-transient': isTemporaryVideo
  });
  const blockProps = useBlockProps({
    className: classes
  });
  if (!src) {
    return createElement("div", {
      ...blockProps
    }, createElement(MediaPlaceholder, {
      icon: createElement(BlockIcon, {
        icon: icon
      }),
      onSelect: onSelectVideo,
      onSelectURL: onSelectURL,
      accept: "video/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      value: attributes,
      onError: onUploadError,
      placeholder: placeholder
    }));
  }
  function onSelectPoster(image) {
    setAttributes({
      poster: image.url
    });
  }
  function onRemovePoster() {
    setAttributes({
      poster: undefined
    });

    // Move focus back to the Media Upload button.
    posterImageButton.current.focus();
  }
  const videoPosterDescription = `video-block__poster-image-description-${instanceId}`;
  return createElement(Fragment, null, isSingleSelected && createElement(Fragment, null, createElement(BlockControls, null, createElement(TracksEditor, {
    tracks: tracks,
    onChange: newTracks => {
      setAttributes({
        tracks: newTracks
      });
    }
  })), createElement(BlockControls, {
    group: "other"
  }, createElement(MediaReplaceFlow, {
    mediaId: id,
    mediaURL: src,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "video/*",
    onSelect: onSelectVideo,
    onSelectURL: onSelectURL,
    onError: onUploadError
  }))), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(VideoCommonSettings, {
    setAttributes: setAttributes,
    attributes: attributes
  }), createElement(MediaUploadCheck, null, createElement(BaseControl, {
    className: "editor-video-poster-control"
  }, createElement(BaseControl.VisualLabel, null, __('Poster image')), createElement(MediaUpload, {
    title: __('Select poster image'),
    onSelect: onSelectPoster,
    allowedTypes: VIDEO_POSTER_ALLOWED_MEDIA_TYPES,
    render: ({
      open
    }) => createElement(Button, {
      variant: "primary",
      onClick: open,
      ref: posterImageButton,
      "aria-describedby": videoPosterDescription
    }, !poster ? __('Select') : __('Replace'))
  }), createElement("p", {
    id: videoPosterDescription,
    hidden: true
  }, poster ? sprintf( /* translators: %s: poster image URL. */
  __('The current poster image url is %s'), poster) : __('There is no poster image currently selected')), !!poster && createElement(Button, {
    onClick: onRemovePoster,
    variant: "tertiary"
  }, __('Remove')))))), createElement("figure", {
    ...blockProps
  }, createElement(Disabled, {
    isDisabled: !isSingleSelected
  }, createElement("video", {
    controls: controls,
    poster: poster,
    src: src,
    ref: videoPlayer
  }, createElement(Tracks, {
    tracks: tracks
  }))), isTemporaryVideo && createElement(Spinner, null), createElement(Caption, {
    attributes: attributes,
    setAttributes: setAttributes,
    isSelected: isSingleSelected,
    insertBlocksAfter: insertBlocksAfter,
    label: __('Video caption text'),
    showToolbarButton: isSingleSelected
  })));
}
export default VideoEdit;
//# sourceMappingURL=edit.js.map