"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostTermsEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _htmlEntities = require("@wordpress/html-entities");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
var _usePostTerms = _interopRequireDefault(require("./use-post-terms"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

// Allowed formats for the prefix and suffix fields.
const ALLOWED_FORMATS = ['core/bold', 'core/image', 'core/italic', 'core/link', 'core/strikethrough', 'core/text-color'];
function PostTermsEdit({
  attributes,
  clientId,
  context,
  isSelected,
  setAttributes,
  insertBlocksAfter
}) {
  const {
    term,
    textAlign,
    separator,
    prefix,
    suffix
  } = attributes;
  const {
    postId,
    postType
  } = context;
  const selectedTerm = (0, _data.useSelect)(select => {
    if (!term) return {};
    const {
      getTaxonomy
    } = select(_coreData.store);
    const taxonomy = getTaxonomy(term);
    return taxonomy?.visibility?.publicly_queryable ? taxonomy : {};
  }, [term]);
  const {
    postTerms,
    hasPostTerms,
    isLoading
  } = (0, _usePostTerms.default)({
    postId,
    term: selectedTerm
  });
  const hasPost = postId && postType;
  const blockInformation = (0, _blockEditor.useBlockDisplayInformation)(clientId);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign,
      [`taxonomy-${term}`]: term
    })
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.AlignmentToolbar, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    autoComplete: "off",
    label: (0, _i18n.__)('Separator'),
    value: separator || '',
    onChange: nextValue => {
      setAttributes({
        separator: nextValue
      });
    },
    help: (0, _i18n.__)('Enter character(s) used to separate terms.')
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, isLoading && hasPost && (0, _react.createElement)(_components.Spinner, null), !isLoading && (isSelected || prefix) && (0, _react.createElement)(_blockEditor.RichText, {
    allowedFormats: ALLOWED_FORMATS,
    className: "wp-block-post-terms__prefix",
    "aria-label": (0, _i18n.__)('Prefix'),
    placeholder: (0, _i18n.__)('Prefix') + ' ',
    value: prefix,
    onChange: value => setAttributes({
      prefix: value
    }),
    tagName: "span"
  }), (!hasPost || !term) && (0, _react.createElement)("span", null, blockInformation.title), hasPost && !isLoading && hasPostTerms && postTerms.map(postTerm => (0, _react.createElement)("a", {
    key: postTerm.id,
    href: postTerm.link,
    onClick: event => event.preventDefault()
  }, (0, _htmlEntities.decodeEntities)(postTerm.name))).reduce((prev, curr) => (0, _react.createElement)(_react.Fragment, null, prev, (0, _react.createElement)("span", {
    className: "wp-block-post-terms__separator"
  }, separator || ' '), curr)), hasPost && !isLoading && !hasPostTerms && (selectedTerm?.labels?.no_terms || (0, _i18n.__)('Term items not found.')), !isLoading && (isSelected || suffix) && (0, _react.createElement)(_blockEditor.RichText, {
    allowedFormats: ALLOWED_FORMATS,
    className: "wp-block-post-terms__suffix",
    "aria-label": (0, _i18n.__)('Suffix'),
    placeholder: ' ' + (0, _i18n.__)('Suffix'),
    value: suffix,
    onChange: value => setAttributes({
      suffix: value
    }),
    tagName: "span",
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  })));
}
//# sourceMappingURL=edit.js.map