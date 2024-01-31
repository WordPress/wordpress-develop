"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _primitives = require("@wordpress/primitives");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _notices = require("@wordpress/notices");
var _url = require("@wordpress/url");
var _style = _interopRequireDefault(require("./style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ALLOWED_MEDIA_TYPES = ['audio'];
function AudioEdit({
  attributes,
  setAttributes,
  isSelected,
  insertBlocksAfter,
  onFocus,
  onBlur,
  clientId
}) {
  const {
    id,
    autoplay,
    loop,
    preload,
    src
  } = attributes;
  const [isCaptionSelected, setIsCaptionSelected] = (0, _element.useState)(false);
  const onFileChange = ({
    mediaId,
    mediaUrl
  }) => {
    setAttributes({
      id: mediaId,
      src: mediaUrl
    });
  };
  const {
    wasBlockJustInserted
  } = (0, _data.useSelect)(select => ({
    wasBlockJustInserted: select(_blockEditor.store).wasBlockJustInserted(clientId, 'inserter_menu')
  }));
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  function toggleAttribute(attribute) {
    return newValue => {
      setAttributes({
        [attribute]: newValue
      });
    };
  }
  function onSelectURL(newSrc) {
    if (newSrc !== src) {
      if ((0, _url.isURL)(newSrc) && /^https?:/.test((0, _url.getProtocol)(newSrc))) {
        setAttributes({
          src: newSrc,
          id: undefined
        });
      } else {
        createErrorNotice((0, _i18n.__)('Invalid URL. Audio file not found.'));
      }
    }
  }
  function onSelectAudio(media) {
    if (!media || !media.url) {
      // In this case there was an error and we should continue in the editing state
      // previous attributes should be removed because they may be temporary blob urls.
      setAttributes({
        src: undefined,
        id: undefined
      });
      return;
    }
    // Sets the block's attribute and updates the edit component from the
    // selected media, then switches off the editing UI.
    setAttributes({
      src: media.url,
      id: media.id
    });
  }
  function onAudioPress() {
    setIsCaptionSelected(false);
  }
  function onFocusCaption() {
    if (!isCaptionSelected) {
      setIsCaptionSelected(true);
    }
  }
  if (!src) {
    return (0, _react.createElement)(_primitives.View, null, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.audio
      }),
      onSelect: onSelectAudio,
      onSelectURL: onSelectURL,
      accept: "audio/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      value: attributes,
      onFocus: onFocus,
      autoOpenMediaUpload: isSelected && wasBlockJustInserted
    }));
  }
  function getBlockControls(open) {
    return (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
      title: (0, _i18n.__)('Replace audio'),
      icon: _icons.replace,
      onClick: open
    })));
  }
  function getBlockUI(open, getMediaOptions) {
    return (0, _react.createElement)(_blockEditor.MediaUploadProgress, {
      mediaId: id,
      onFinishMediaUploadWithSuccess: onFileChange,
      onMediaUploadStateReset: onFileChange,
      containerStyle: _style.default.progressContainer,
      progressBarStyle: _style.default.progressBar,
      spinnerStyle: _style.default.spinner,
      renderContent: ({
        isUploadInProgress,
        isUploadFailed,
        retryMessage
      }) => {
        return (0, _react.createElement)(_react.Fragment, null, !isCaptionSelected && !isUploadInProgress && getBlockControls(open), getMediaOptions(), (0, _react.createElement)(_components.AudioPlayer, {
          isUploadInProgress: isUploadInProgress,
          isUploadFailed: isUploadFailed,
          retryMessage: retryMessage,
          attributes: attributes,
          isSelected: isSelected
        }));
      }
    });
  }
  return (0, _react.createElement)(_reactNative.TouchableWithoutFeedback, {
    accessible: !isSelected,
    onPress: onAudioPress,
    disabled: !isSelected
  }, (0, _react.createElement)(_primitives.View, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Autoplay'),
    onChange: toggleAttribute('autoplay'),
    checked: autoplay,
    help: (0, _i18n.__)('Autoplay may cause usability issues for some users.')
  }), (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Loop'),
    onChange: toggleAttribute('loop'),
    checked: loop
  }), (0, _react.createElement)(_components.SelectControl, {
    label: (0, _i18n._x)('Preload', 'noun; Audio block parameter'),
    value: preload || ''
    // `undefined` is required for the preload attribute to be unset.
    ,
    onChange: value => setAttributes({
      preload: value || undefined
    }),
    options: [{
      value: '',
      label: (0, _i18n.__)('Browser default')
    }, {
      value: 'auto',
      label: (0, _i18n.__)('Auto')
    }, {
      value: 'metadata',
      label: (0, _i18n.__)('Metadata')
    }, {
      value: 'none',
      label: (0, _i18n._x)('None', '"Preload" value')
    }],
    hideCancelButton: true
  }))), (0, _react.createElement)(_blockEditor.MediaUpload, {
    allowedTypes: ALLOWED_MEDIA_TYPES,
    isReplacingMedia: true,
    onSelect: onSelectAudio,
    onSelectURL: onSelectURL,
    render: ({
      open,
      getMediaOptions
    }) => {
      return getBlockUI(open, getMediaOptions);
    }
  }), (0, _react.createElement)(_blockEditor.BlockCaption, {
    accessible: true,
    accessibilityLabelCreator: caption => _blockEditor.RichText.isEmpty(caption) ? /* translators: accessibility text. Empty Audio caption. */
    (0, _i18n.__)('Audio caption. Empty') : (0, _i18n.sprintf)( /* translators: accessibility text. %s: Audio caption. */
    (0, _i18n.__)('Audio caption. %s'), caption),
    clientId: clientId,
    isSelected: isCaptionSelected,
    onFocus: onFocusCaption,
    onBlur: onBlur,
    insertBlocksAfter: insertBlocksAfter
  })));
}
var _default = exports.default = AudioEdit;
//# sourceMappingURL=edit.native.js.map