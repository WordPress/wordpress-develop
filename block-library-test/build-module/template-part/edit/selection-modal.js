import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { useDispatch } from '@wordpress/data';
import { parse } from '@wordpress/blocks';
import { useAsyncList } from '@wordpress/compose';
import { __experimentalBlockPatternsList as BlockPatternsList } from '@wordpress/block-editor';
import { SearchControl, __experimentalHStack as HStack } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAlternativeBlockPatterns, useAlternativeTemplateParts, useCreateTemplatePartFromBlocks } from './utils/hooks';
import { createTemplatePartId } from './utils/create-template-part-id';
import { searchPatterns } from '../../utils/search-patterns';
export default function TemplatePartSelectionModal({
  setAttributes,
  onClose,
  templatePartId = null,
  area,
  clientId
}) {
  const [searchValue, setSearchValue] = useState('');
  const {
    templateParts
  } = useAlternativeTemplateParts(area, templatePartId);
  // We can map template parts to block patters to reuse the BlockPatternsList UI
  const filteredTemplateParts = useMemo(() => {
    const partsAsPatterns = templateParts.map(templatePart => ({
      name: createTemplatePartId(templatePart.theme, templatePart.slug),
      title: templatePart.title.rendered,
      blocks: parse(templatePart.content.raw),
      templatePart
    }));
    return searchPatterns(partsAsPatterns, searchValue);
  }, [templateParts, searchValue]);
  const shownTemplateParts = useAsyncList(filteredTemplateParts);
  const blockPatterns = useAlternativeBlockPatterns(area, clientId);
  const filteredBlockPatterns = useMemo(() => {
    return searchPatterns(blockPatterns, searchValue);
  }, [blockPatterns, searchValue]);
  const shownBlockPatterns = useAsyncList(filteredBlockPatterns);
  const {
    createSuccessNotice
  } = useDispatch(noticesStore);
  const onTemplatePartSelect = templatePart => {
    setAttributes({
      slug: templatePart.slug,
      theme: templatePart.theme,
      area: undefined
    });
    createSuccessNotice(sprintf( /* translators: %s: template part title. */
    __('Template Part "%s" inserted.'), templatePart.title?.rendered || templatePart.slug), {
      type: 'snackbar'
    });
    onClose();
  };
  const createFromBlocks = useCreateTemplatePartFromBlocks(area, setAttributes);
  const hasTemplateParts = !!filteredTemplateParts.length;
  const hasBlockPatterns = !!filteredBlockPatterns.length;
  return createElement("div", {
    className: "block-library-template-part__selection-content"
  }, createElement("div", {
    className: "block-library-template-part__selection-search"
  }, createElement(SearchControl, {
    __nextHasNoMarginBottom: true,
    onChange: setSearchValue,
    value: searchValue,
    label: __('Search for replacements'),
    placeholder: __('Search')
  })), hasTemplateParts && createElement("div", null, createElement("h2", null, __('Existing template parts')), createElement(BlockPatternsList, {
    blockPatterns: filteredTemplateParts,
    shownPatterns: shownTemplateParts,
    onClickPattern: pattern => {
      onTemplatePartSelect(pattern.templatePart);
    }
  })), hasBlockPatterns && createElement("div", null, createElement("h2", null, __('Patterns')), createElement(BlockPatternsList, {
    blockPatterns: filteredBlockPatterns,
    shownPatterns: shownBlockPatterns,
    onClickPattern: (pattern, blocks) => {
      createFromBlocks(blocks, pattern.title);
      onClose();
    }
  })), !hasTemplateParts && !hasBlockPatterns && createElement(HStack, {
    alignment: "center"
  }, createElement("p", null, __('No results found.'))));
}
//# sourceMappingURL=selection-modal.js.map