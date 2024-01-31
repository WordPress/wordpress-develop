"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TracksEditor;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _icons = require("@wordpress/icons");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _url = require("@wordpress/url");
/**
 * WordPress dependencies
 */

const ALLOWED_TYPES = ['text/vtt'];
const DEFAULT_KIND = 'subtitles';
const KIND_OPTIONS = [{
  label: (0, _i18n.__)('Subtitles'),
  value: 'subtitles'
}, {
  label: (0, _i18n.__)('Captions'),
  value: 'captions'
}, {
  label: (0, _i18n.__)('Descriptions'),
  value: 'descriptions'
}, {
  label: (0, _i18n.__)('Chapters'),
  value: 'chapters'
}, {
  label: (0, _i18n.__)('Metadata'),
  value: 'metadata'
}];
function TrackList({
  tracks,
  onEditPress
}) {
  let content;
  if (tracks.length === 0) {
    content = (0, _react.createElement)("p", {
      className: "block-library-video-tracks-editor__tracks-informative-message"
    }, (0, _i18n.__)('Tracks can be subtitles, captions, chapters, or descriptions. They help make your content more accessible to a wider range of users.'));
  } else {
    content = tracks.map((track, index) => {
      return (0, _react.createElement)(_components.__experimentalHStack, {
        key: index,
        className: "block-library-video-tracks-editor__track-list-track"
      }, (0, _react.createElement)("span", null, track.label, " "), (0, _react.createElement)(_components.Button, {
        variant: "tertiary",
        onClick: () => onEditPress(index),
        "aria-label": (0, _i18n.sprintf)( /* translators: %s: Label of the video text track e.g: "French subtitles" */
        (0, _i18n.__)('Edit %s'), track.label)
      }, (0, _i18n.__)('Edit')));
    });
  }
  return (0, _react.createElement)(_components.MenuGroup, {
    label: (0, _i18n.__)('Text tracks'),
    className: "block-library-video-tracks-editor__track-list"
  }, content);
}
function SingleTrackEditor({
  track,
  onChange,
  onClose,
  onRemove
}) {
  const {
    src = '',
    label = '',
    srcLang = '',
    kind = DEFAULT_KIND
  } = track;
  const fileName = src.startsWith('blob:') ? '' : (0, _url.getFilename)(src) || '';
  return (0, _react.createElement)(_components.NavigableMenu, null, (0, _react.createElement)(_components.__experimentalVStack, {
    className: "block-library-video-tracks-editor__single-track-editor",
    spacing: "4"
  }, (0, _react.createElement)("span", {
    className: "block-library-video-tracks-editor__single-track-editor-edit-track-label"
  }, (0, _i18n.__)('Edit track')), (0, _react.createElement)("span", null, (0, _i18n.__)('File'), ": ", (0, _react.createElement)("b", null, fileName)), (0, _react.createElement)(_components.__experimentalGrid, {
    columns: 2,
    gap: 4
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true
    /* eslint-disable jsx-a11y/no-autofocus */,
    autoFocus: true
    /* eslint-enable jsx-a11y/no-autofocus */,
    onChange: newLabel => onChange({
      ...track,
      label: newLabel
    }),
    label: (0, _i18n.__)('Label'),
    value: label,
    help: (0, _i18n.__)('Title of track')
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    onChange: newSrcLang => onChange({
      ...track,
      srcLang: newSrcLang
    }),
    label: (0, _i18n.__)('Source language'),
    value: srcLang,
    help: (0, _i18n.__)('Language tag (en, fr, etc.)')
  })), (0, _react.createElement)(_components.__experimentalVStack, {
    spacing: "8"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    className: "block-library-video-tracks-editor__single-track-editor-kind-select",
    options: KIND_OPTIONS,
    value: kind,
    label: (0, _i18n.__)('Kind'),
    onChange: newKind => {
      onChange({
        ...track,
        kind: newKind
      });
    }
  }), (0, _react.createElement)(_components.__experimentalHStack, {
    className: "block-library-video-tracks-editor__single-track-editor-buttons-container"
  }, (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    onClick: () => {
      const changes = {};
      let hasChanges = false;
      if (label === '') {
        changes.label = (0, _i18n.__)('English');
        hasChanges = true;
      }
      if (srcLang === '') {
        changes.srcLang = 'en';
        hasChanges = true;
      }
      if (track.kind === undefined) {
        changes.kind = DEFAULT_KIND;
        hasChanges = true;
      }
      if (hasChanges) {
        onChange({
          ...track,
          ...changes
        });
      }
      onClose();
    }
  }, (0, _i18n.__)('Close')), (0, _react.createElement)(_components.Button, {
    isDestructive: true,
    variant: "link",
    onClick: onRemove
  }, (0, _i18n.__)('Remove track'))))));
}
function TracksEditor({
  tracks = [],
  onChange
}) {
  const mediaUpload = (0, _data.useSelect)(select => {
    return select(_blockEditor.store).getSettings().mediaUpload;
  }, []);
  const [trackBeingEdited, setTrackBeingEdited] = (0, _element.useState)(null);
  if (!mediaUpload) {
    return null;
  }
  return (0, _react.createElement)(_components.Dropdown, {
    contentClassName: "block-library-video-tracks-editor",
    renderToggle: ({
      isOpen,
      onToggle
    }) => (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
      label: (0, _i18n.__)('Text tracks'),
      showTooltip: true,
      "aria-expanded": isOpen,
      "aria-haspopup": "true",
      onClick: onToggle
    }, (0, _i18n.__)('Text tracks'))),
    renderContent: () => {
      if (trackBeingEdited !== null) {
        return (0, _react.createElement)(SingleTrackEditor, {
          track: tracks[trackBeingEdited],
          onChange: newTrack => {
            const newTracks = [...tracks];
            newTracks[trackBeingEdited] = newTrack;
            onChange(newTracks);
          },
          onClose: () => setTrackBeingEdited(null),
          onRemove: () => {
            onChange(tracks.filter((_track, index) => index !== trackBeingEdited));
            setTrackBeingEdited(null);
          }
        });
      }
      return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.NavigableMenu, null, (0, _react.createElement)(TrackList, {
        tracks: tracks,
        onEditPress: setTrackBeingEdited
      }), (0, _react.createElement)(_components.MenuGroup, {
        className: "block-library-video-tracks-editor__add-tracks-container",
        label: (0, _i18n.__)('Add tracks')
      }, (0, _react.createElement)(_blockEditor.MediaUpload, {
        onSelect: ({
          url
        }) => {
          const trackIndex = tracks.length;
          onChange([...tracks, {
            src: url
          }]);
          setTrackBeingEdited(trackIndex);
        },
        allowedTypes: ALLOWED_TYPES,
        render: ({
          open
        }) => (0, _react.createElement)(_components.MenuItem, {
          icon: _icons.media,
          onClick: open
        }, (0, _i18n.__)('Open Media Library'))
      }), (0, _react.createElement)(_blockEditor.MediaUploadCheck, null, (0, _react.createElement)(_components.FormFileUpload, {
        onChange: event => {
          const files = event.target.files;
          const trackIndex = tracks.length;
          mediaUpload({
            allowedTypes: ALLOWED_TYPES,
            filesList: files,
            onFileChange: ([{
              url
            }]) => {
              const newTracks = [...tracks];
              if (!newTracks[trackIndex]) {
                newTracks[trackIndex] = {};
              }
              newTracks[trackIndex] = {
                ...tracks[trackIndex],
                src: url
              };
              onChange(newTracks);
              setTrackBeingEdited(trackIndex);
            }
          });
        },
        accept: ".vtt,text/vtt",
        render: ({
          openFileDialog
        }) => {
          return (0, _react.createElement)(_components.MenuItem, {
            icon: _icons.upload,
            onClick: () => {
              openFileDialog();
            }
          }, (0, _i18n.__)('Upload'));
        }
      })))));
    }
  });
}
//# sourceMappingURL=tracks-editor.js.map