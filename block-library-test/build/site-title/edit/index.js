"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SiteTitleEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _blocks = require("@wordpress/blocks");
var _htmlEntities = require("@wordpress/html-entities");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const HEADING_LEVELS = [0, 1, 2, 3, 4, 5, 6];
function SiteTitleEdit({
  attributes,
  setAttributes,
  insertBlocksAfter
}) {
  const {
    level,
    textAlign,
    isLink,
    linkTarget
  } = attributes;
  const {
    canUserEdit,
    title
  } = (0, _data.useSelect)(select => {
    const {
      canUser,
      getEntityRecord,
      getEditedEntityRecord
    } = select(_coreData.store);
    const canEdit = canUser('update', 'settings');
    const settings = canEdit ? getEditedEntityRecord('root', 'site') : {};
    const readOnlySettings = getEntityRecord('root', '__unstableBase');
    return {
      canUserEdit: canEdit,
      title: canEdit ? settings?.title : readOnlySettings?.name
    };
  }, []);
  const {
    editEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  function setTitle(newTitle) {
    editEntityRecord('root', 'site', undefined, {
      title: newTitle
    });
  }
  const TagName = level === 0 ? 'p' : `h${level}`;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign,
      'wp-block-site-title__placeholder': !canUserEdit && !title
    })
  });
  const siteTitleContent = canUserEdit ? (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: isLink ? 'a' : 'span',
    href: isLink ? '#site-title-pseudo-link' : undefined,
    "aria-label": (0, _i18n.__)('Site title text'),
    placeholder: (0, _i18n.__)('Write site title…'),
    value: title,
    onChange: setTitle,
    allowedFormats: [],
    disableLineBreaks: true,
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  })) : (0, _react.createElement)(TagName, {
    ...blockProps
  }, isLink ? (0, _react.createElement)("a", {
    href: "#site-title-pseudo-link",
    onClick: event => event.preventDefault()
  }, (0, _htmlEntities.decodeEntities)(title) || (0, _i18n.__)('Site Title placeholder')) : (0, _react.createElement)("span", null, (0, _htmlEntities.decodeEntities)(title) || (0, _i18n.__)('Site Title placeholder')));
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.HeadingLevelDropdown, {
    options: HEADING_LEVELS,
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
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Make title link to home'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), isLink && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), siteTitleContent);
}
//# sourceMappingURL=index.js.map