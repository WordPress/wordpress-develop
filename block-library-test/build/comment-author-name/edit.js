"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Renders the `core/comment-author-name` block on the editor.
 *
 * @param {Object} props                       React props.
 * @param {Object} props.setAttributes         Callback for updating block attributes.
 * @param {Object} props.attributes            Block attributes.
 * @param {string} props.attributes.isLink     Whether the author name should be linked.
 * @param {string} props.attributes.linkTarget Target of the link.
 * @param {string} props.attributes.textAlign  Text alignment.
 * @param {Object} props.context               Inherited context.
 * @param {string} props.context.commentId     The comment ID.
 *
 * @return {JSX.Element} React element.
 */
function Edit({
  attributes: {
    isLink,
    linkTarget,
    textAlign
  },
  context: {
    commentId
  },
  setAttributes
}) {
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  let displayName = (0, _data.useSelect)(select => {
    const {
      getEntityRecord
    } = select(_coreData.store);
    const comment = getEntityRecord('root', 'comment', commentId);
    const authorName = comment?.author_name; // eslint-disable-line camelcase

    if (comment && !authorName) {
      var _user$name;
      const user = getEntityRecord('root', 'user', comment.author);
      return (_user$name = user?.name) !== null && _user$name !== void 0 ? _user$name : (0, _i18n.__)('Anonymous');
    }
    return authorName !== null && authorName !== void 0 ? authorName : '';
  }, [commentId]);
  const blockControls = (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  }));
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link to authors URL'),
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
  })));
  if (!commentId || !displayName) {
    displayName = (0, _i18n._x)('Comment Author', 'block title');
  }
  const displayAuthor = isLink ? (0, _react.createElement)("a", {
    href: "#comment-author-pseudo-link",
    onClick: event => event.preventDefault()
  }, displayName) : displayName;
  return (0, _react.createElement)(_react.Fragment, null, inspectorControls, blockControls, (0, _react.createElement)("div", {
    ...blockProps
  }, displayAuthor));
}
//# sourceMappingURL=edit.js.map