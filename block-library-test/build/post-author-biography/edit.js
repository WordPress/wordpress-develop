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
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostAuthorBiographyEdit({
  context: {
    postType,
    postId
  },
  attributes: {
    textAlign
  },
  setAttributes
}) {
  const {
    authorDetails
  } = (0, _data.useSelect)(select => {
    const {
      getEditedEntityRecord,
      getUser
    } = select(_coreData.store);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return {
      authorDetails: _authorId ? getUser(_authorId) : null
    };
  }, [postType, postId]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const displayAuthorBiography = authorDetails?.description || (0, _i18n.__)('Author Biography');
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps,
    dangerouslySetInnerHTML: {
      __html: displayAuthorBiography
    }
  }));
}
var _default = exports.default = PostAuthorBiographyEdit;
//# sourceMappingURL=edit.js.map