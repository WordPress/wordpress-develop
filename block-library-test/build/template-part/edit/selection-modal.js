"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TemplatePartSelectionModal;
var _react = require("react");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _notices = require("@wordpress/notices");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _compose = require("@wordpress/compose");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _hooks = require("./utils/hooks");
var _createTemplatePartId = require("./utils/create-template-part-id");
var _searchPatterns = require("../../utils/search-patterns");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function TemplatePartSelectionModal({
  setAttributes,
  onClose,
  templatePartId = null,
  area,
  clientId
}) {
  const [searchValue, setSearchValue] = (0, _element.useState)('');
  const {
    templateParts
  } = (0, _hooks.useAlternativeTemplateParts)(area, templatePartId);
  // We can map template parts to block patters to reuse the BlockPatternsList UI
  const filteredTemplateParts = (0, _element.useMemo)(() => {
    const partsAsPatterns = templateParts.map(templatePart => ({
      name: (0, _createTemplatePartId.createTemplatePartId)(templatePart.theme, templatePart.slug),
      title: templatePart.title.rendered,
      blocks: (0, _blocks.parse)(templatePart.content.raw),
      templatePart
    }));
    return (0, _searchPatterns.searchPatterns)(partsAsPatterns, searchValue);
  }, [templateParts, searchValue]);
  const shownTemplateParts = (0, _compose.useAsyncList)(filteredTemplateParts);
  const blockPatterns = (0, _hooks.useAlternativeBlockPatterns)(area, clientId);
  const filteredBlockPatterns = (0, _element.useMemo)(() => {
    return (0, _searchPatterns.searchPatterns)(blockPatterns, searchValue);
  }, [blockPatterns, searchValue]);
  const shownBlockPatterns = (0, _compose.useAsyncList)(filteredBlockPatterns);
  const {
    createSuccessNotice
  } = (0, _data.useDispatch)(_notices.store);
  const onTemplatePartSelect = templatePart => {
    setAttributes({
      slug: templatePart.slug,
      theme: templatePart.theme,
      area: undefined
    });
    createSuccessNotice((0, _i18n.sprintf)( /* translators: %s: template part title. */
    (0, _i18n.__)('Template Part "%s" inserted.'), templatePart.title?.rendered || templatePart.slug), {
      type: 'snackbar'
    });
    onClose();
  };
  const createFromBlocks = (0, _hooks.useCreateTemplatePartFromBlocks)(area, setAttributes);
  const hasTemplateParts = !!filteredTemplateParts.length;
  const hasBlockPatterns = !!filteredBlockPatterns.length;
  return (0, _react.createElement)("div", {
    className: "block-library-template-part__selection-content"
  }, (0, _react.createElement)("div", {
    className: "block-library-template-part__selection-search"
  }, (0, _react.createElement)(_components.SearchControl, {
    __nextHasNoMarginBottom: true,
    onChange: setSearchValue,
    value: searchValue,
    label: (0, _i18n.__)('Search for replacements'),
    placeholder: (0, _i18n.__)('Search')
  })), hasTemplateParts && (0, _react.createElement)("div", null, (0, _react.createElement)("h2", null, (0, _i18n.__)('Existing template parts')), (0, _react.createElement)(_blockEditor.__experimentalBlockPatternsList, {
    blockPatterns: filteredTemplateParts,
    shownPatterns: shownTemplateParts,
    onClickPattern: pattern => {
      onTemplatePartSelect(pattern.templatePart);
    }
  })), hasBlockPatterns && (0, _react.createElement)("div", null, (0, _react.createElement)("h2", null, (0, _i18n.__)('Patterns')), (0, _react.createElement)(_blockEditor.__experimentalBlockPatternsList, {
    blockPatterns: filteredBlockPatterns,
    shownPatterns: shownBlockPatterns,
    onClickPattern: (pattern, blocks) => {
      createFromBlocks(blocks, pattern.title);
      onClose();
    }
  })), !hasTemplateParts && !hasBlockPatterns && (0, _react.createElement)(_components.__experimentalHStack, {
    alignment: "center"
  }, (0, _react.createElement)("p", null, (0, _i18n.__)('No results found.'))));
}
//# sourceMappingURL=selection-modal.js.map