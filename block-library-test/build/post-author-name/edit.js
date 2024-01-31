"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostAuthorNameEdit({
  context: {
    postType,
    postId
  },
  attributes: {
    textAlign,
    isLink,
    linkTarget
  },
  setAttributes
}) {
  const {
    authorName
  } = (0, _data.useSelect)(select => {
    const {
      getEditedEntityRecord,
      getUser
    } = select(_coreData.store);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return {
      authorName: _authorId ? getUser(_authorId) : null
    };
  }, [postType, postId]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const displayName = authorName?.name || (0, _i18n.__)('Author Name');
  const displayAuthor = isLink ? (0, _react.createElement)("a", {
    href: "#author-pseudo-link",
    onClick: event => event.preventDefault(),
    className: "wp-block-post-author-name__link"
  }, displayName) : displayName;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
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
    label: (0, _i18n.__)('Link to author archive'),
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
  }))), (0, _react.createElement)("div", {
    ...blockProps
  }, " ", displayAuthor, " "));
}
var _default = exports.default = PostAuthorNameEdit;
//# sourceMappingURL=edit.js.map