"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryTitleEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const SUPPORTED_TYPES = ['archive', 'search'];
function QueryTitleEdit({
  attributes: {
    type,
    level,
    textAlign,
    showPrefix,
    showSearchTerm
  },
  setAttributes
}) {
  const {
    archiveTypeTitle,
    archiveNameLabel
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      __experimentalArchiveTitleNameLabel,
      __experimentalArchiveTitleTypeLabel
    } = getSettings();
    return {
      archiveTypeTitle: __experimentalArchiveTitleTypeLabel,
      archiveNameLabel: __experimentalArchiveTitleNameLabel
    };
  });
  const TagName = `h${level}`;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)('wp-block-query-title__placeholder', {
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  if (!SUPPORTED_TYPES.includes(type)) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Provided type is not supported.')));
  }
  let titleElement;
  if (type === 'archive') {
    let title;
    if (archiveTypeTitle) {
      if (showPrefix) {
        if (archiveNameLabel) {
          title = (0, _i18n.sprintf)( /* translators: 1: Archive type title e.g: "Category", 2: Label of the archive e.g: "Shoes" */
          (0, _i18n.__)('%1$s: %2$s'), archiveTypeTitle, archiveNameLabel);
        } else {
          title = (0, _i18n.sprintf)( /* translators: %s: Archive type title e.g: "Category", "Tag"... */
          (0, _i18n.__)('%s: Name'), archiveTypeTitle);
        }
      } else if (archiveNameLabel) {
        title = archiveNameLabel;
      } else {
        title = (0, _i18n.sprintf)( /* translators: %s: Archive type title e.g: "Category", "Tag"... */
        (0, _i18n.__)('%s name'), archiveTypeTitle);
      }
    } else {
      title = showPrefix ? (0, _i18n.__)('Archive type: Name') : (0, _i18n.__)('Archive title');
    }
    titleElement = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Settings')
    }, (0, _react.createElement)(_components.ToggleControl, {
      __nextHasNoMarginBottom: true,
      label: (0, _i18n.__)('Show archive type in title'),
      onChange: () => setAttributes({
        showPrefix: !showPrefix
      }),
      checked: showPrefix
    }))), (0, _react.createElement)(TagName, {
      ...blockProps
    }, title));
  }
  if (type === 'search') {
    titleElement = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
      title: (0, _i18n.__)('Settings')
    }, (0, _react.createElement)(_components.ToggleControl, {
      __nextHasNoMarginBottom: true,
      label: (0, _i18n.__)('Show search term in title'),
      onChange: () => setAttributes({
        showSearchTerm: !showSearchTerm
      }),
      checked: showSearchTerm
    }))), (0, _react.createElement)(TagName, {
      ...blockProps
    }, showSearchTerm ? (0, _i18n.__)('Search results for: “search term”') : (0, _i18n.__)('Search results')));
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.HeadingLevelDropdown, {
    value: level,
    onChange: newLevel => setAttributes({
      level: newLevel
    })
  }), (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), titleElement);
}
//# sourceMappingURL=edit.js.map