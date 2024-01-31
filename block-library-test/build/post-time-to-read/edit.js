"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _coreData = require("@wordpress/core-data");
var _wordcount = require("@wordpress/wordcount");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Average reading rate - based on average taken from
 * https://irisreading.com/average-reading-speed-in-various-languages/
 * (Characters/minute used for Chinese rather than words).
 */
const AVERAGE_READING_RATE = 189;
function PostTimeToReadEdit({
  attributes,
  setAttributes,
  context
}) {
  const {
    textAlign
  } = attributes;
  const {
    postId,
    postType
  } = context;
  const [contentStructure] = (0, _coreData.useEntityProp)('postType', postType, 'content', postId);
  const [blocks] = (0, _coreData.useEntityBlockEditor)('postType', postType, {
    id: postId
  });
  const minutesToReadString = (0, _element.useMemo)(() => {
    // Replicates the logic found in getEditedPostContent().
    let content;
    if (contentStructure instanceof Function) {
      content = contentStructure({
        blocks
      });
    } else if (blocks) {
      // If we have parsed blocks already, they should be our source of truth.
      // Parsing applies block deprecations and legacy block conversions that
      // unparsed content will not have.
      content = (0, _blocks.__unstableSerializeAndClean)(blocks);
    } else {
      content = contentStructure;
    }

    /*
     * translators: If your word count is based on single characters (e.g. East Asian characters),
     * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
     * Do not translate into your own language.
     */
    const wordCountType = (0, _i18n._x)('words', 'Word count type. Do not translate!');
    const minutesToRead = Math.max(1, Math.round((0, _wordcount.count)(content, wordCountType) / AVERAGE_READING_RATE));
    return (0, _i18n.sprintf)( /* translators: %d is the number of minutes the post will take to read. */
    (0, _i18n._n)('%d minute', '%d minutes', minutesToRead), minutesToRead);
  }, [contentStructure, blocks]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
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
    ...blockProps
  }, minutesToReadString));
}
var _default = exports.default = PostTimeToReadEdit;
//# sourceMappingURL=edit.js.map