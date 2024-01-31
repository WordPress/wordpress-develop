"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blob = require("@wordpress/blob");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _notices = require("@wordpress/notices");
var _util = require("../embed/util");
var _caption = require("../utils/caption");
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
  className,
  setAttributes,
  onReplace,
  isSelected: isSingleSelected,
  insertBlocksAfter
}) {
  const {
    id,
    autoplay,
    loop,
    preload,
    src
  } = attributes;
  const isTemporaryAudio = !id && (0, _blob.isBlobURL)(src);
  const {
    getSettings
  } = (0, _data.useSelect)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    if (!id && (0, _blob.isBlobURL)(src)) {
      const file = (0, _blob.getBlobByURL)(src);
      if (file) {
        getSettings().mediaUpload({
          filesList: [file],
          onFileChange: ([media]) => onSelectAudio(media),
          onError: e => onUploadError(e),
          allowedTypes: ALLOWED_MEDIA_TYPES
        });
      }
    }
  }, []);
  function toggleAttribute(attribute) {
    return newValue => {
      setAttributes({
        [attribute]: newValue
      });
    };
  }
  function onSelectURL(newSrc) {
    // Set the block's src from the edit component's state, and switch off
    // the editing UI.
    if (newSrc !== src) {
      // Check if there's an embed block that handles this URL.
      const embedBlock = (0, _util.createUpgradedEmbedBlock)({
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
        id: undefined
      });
    }
  }
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  function onUploadError(message) {
    createErrorNotice(message, {
      type: 'snackbar'
    });
  }
  function getAutoplayHelp(checked) {
    return checked ? (0, _i18n.__)('Autoplay may cause usability issues for some users.') : null;
  }
  function onSelectAudio(media) {
    if (!media || !media.url) {
      // In this case there was an error and we should continue in the editing state
      // previous attributes should be removed because they may be temporary blob urls.
      setAttributes({
        src: undefined,
        id: undefined,
        caption: undefined
      });
      return;
    }
    // Sets the block's attribute and updates the edit component from the
    // selected media, then switches off the editing UI.
    setAttributes({
      src: media.url,
      id: media.id,
      caption: media.caption
    });
  }
  const classes = (0, _classnames.default)(className, {
    'is-transient': isTemporaryAudio
  });
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: classes
  });
  if (!src) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.audio
      }),
      onSelect: onSelectAudio,
      onSelectURL: onSelectURL,
      accept: "audio/*",
      allowedTypes: ALLOWED_MEDIA_TYPES,
      value: attributes,
      onError: onUploadError
    }));
  }
  return (0, _react.createElement)(_react.Fragment, null, isSingleSelected && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: id,
    mediaURL: src,
    allowedTypes: ALLOWED_MEDIA_TYPES,
    accept: "audio/*",
    onSelect: onSelectAudio,
    onSelectURL: onSelectURL,
    onError: onUploadError
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Autoplay'),
    onChange: toggleAttribute('autoplay'),
    checked: autoplay,
    help: getAutoplayHelp
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Loop'),
    onChange: toggleAttribute('loop'),
    checked: loop
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
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
      label: (0, _i18n._x)('None', 'Preload value')
    }]
  }))), (0, _react.createElement)("figure", {
    ...blockProps
  }, (0, _react.createElement)(_components.Disabled, {
    isDisabled: !isSingleSelected
  }, (0, _react.createElement)("audio", {
    controls: "controls",
    src: src
  })), isTemporaryAudio && (0, _react.createElement)(_components.Spinner, null), (0, _react.createElement)(_caption.Caption, {
    attributes: attributes,
    setAttributes: setAttributes,
    isSelected: isSingleSelected,
    insertBlocksAfter: insertBlocksAfter,
    label: (0, _i18n.__)('Audio caption text'),
    showToolbarButton: isSingleSelected
  })));
}
var _default = exports.default = AudioEdit;
//# sourceMappingURL=edit.js.map