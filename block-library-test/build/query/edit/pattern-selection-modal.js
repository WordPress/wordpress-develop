"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PatternSelectionModal;
var _react = require("react");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _utils = require("../utils");
var _searchPatterns = require("../../utils/search-patterns");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function PatternSelectionModal({
  clientId,
  attributes,
  setIsPatternSelectionModalOpen
}) {
  const [searchValue, setSearchValue] = (0, _element.useState)('');
  const {
    replaceBlock,
    selectBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const onBlockPatternSelect = (pattern, blocks) => {
    const {
      newBlocks,
      queryClientIds
    } = (0, _utils.getTransformedBlocksFromPattern)(blocks, attributes);
    replaceBlock(clientId, newBlocks);
    if (queryClientIds[0]) {
      selectBlock(queryClientIds[0]);
    }
  };
  // When we preview Query Loop blocks we should prefer the current
  // block's postType, which is passed through block context.
  const blockPreviewContext = (0, _element.useMemo)(() => ({
    previewPostType: attributes.query.postType
  }), [attributes.query.postType]);
  const blockNameForPatterns = (0, _utils.useBlockNameForPatterns)(clientId, attributes);
  const blockPatterns = (0, _utils.usePatterns)(clientId, blockNameForPatterns);
  const filteredBlockPatterns = (0, _element.useMemo)(() => {
    return (0, _searchPatterns.searchPatterns)(blockPatterns, searchValue);
  }, [blockPatterns, searchValue]);
  const shownBlockPatterns = (0, _compose.useAsyncList)(filteredBlockPatterns);
  return (0, _react.createElement)(_components.Modal, {
    overlayClassName: "block-library-query-pattern__selection-modal",
    title: (0, _i18n.__)('Choose a pattern'),
    onRequestClose: () => setIsPatternSelectionModalOpen(false),
    isFullScreen: true
  }, (0, _react.createElement)("div", {
    className: "block-library-query-pattern__selection-content"
  }, (0, _react.createElement)("div", {
    className: "block-library-query-pattern__selection-search"
  }, (0, _react.createElement)(_components.SearchControl, {
    __nextHasNoMarginBottom: true,
    onChange: setSearchValue,
    value: searchValue,
    label: (0, _i18n.__)('Search for patterns'),
    placeholder: (0, _i18n.__)('Search')
  })), (0, _react.createElement)(_blockEditor.BlockContextProvider, {
    value: blockPreviewContext
  }, (0, _react.createElement)(_blockEditor.__experimentalBlockPatternsList, {
    blockPatterns: filteredBlockPatterns,
    shownPatterns: shownBlockPatterns,
    onClickPattern: onBlockPatternSelect
  }))));
}
//# sourceMappingURL=pattern-selection-modal.js.map