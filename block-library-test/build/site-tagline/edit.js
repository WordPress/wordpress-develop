"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SiteTaglineEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _blocks = require("@wordpress/blocks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function SiteTaglineEdit({
  attributes,
  setAttributes,
  insertBlocksAfter
}) {
  const {
    textAlign
  } = attributes;
  const {
    canUserEdit,
    tagline
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
      canUserEdit: canUser('update', 'settings'),
      tagline: canEdit ? settings?.description : readOnlySettings?.description
    };
  }, []);
  const {
    editEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  function setTagline(newTagline) {
    editEntityRecord('root', 'site', undefined, {
      description: newTagline
    });
  }
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign,
      'wp-block-site-tagline__placeholder': !canUserEdit && !tagline
    })
  });
  const siteTaglineContent = canUserEdit ? (0, _react.createElement)(_blockEditor.RichText, {
    allowedFormats: [],
    onChange: setTagline,
    "aria-label": (0, _i18n.__)('Site tagline text'),
    placeholder: (0, _i18n.__)('Write site tagline…'),
    tagName: "p",
    value: tagline,
    disableLineBreaks: true,
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)())),
    ...blockProps
  }) : (0, _react.createElement)("p", {
    ...blockProps
  }, tagline || (0, _i18n.__)('Site Tagline placeholder'));
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    }),
    value: textAlign
  })), siteTaglineContent);
}
//# sourceMappingURL=edit.js.map