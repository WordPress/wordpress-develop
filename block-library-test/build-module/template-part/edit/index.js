import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { BlockSettingsMenuControls, useBlockProps, Warning, store as blockEditorStore, RecursionProvider, useHasRecursion, InspectorControls } from '@wordpress/block-editor';
import { Spinner, Modal, MenuItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import TemplatePartPlaceholder from './placeholder';
import TemplatePartSelectionModal from './selection-modal';
import { TemplatePartAdvancedControls } from './advanced-controls';
import TemplatePartInnerBlocks from './inner-blocks';
import { createTemplatePartId } from './utils/create-template-part-id';
import { useAlternativeBlockPatterns, useAlternativeTemplateParts, useTemplatePartArea } from './utils/hooks';
function ReplaceButton({
  isEntityAvailable,
  area,
  clientId,
  templatePartId,
  isTemplatePartSelectionOpen,
  setIsTemplatePartSelectionOpen
}) {
  const {
    templateParts
  } = useAlternativeTemplateParts(area, templatePartId);
  const blockPatterns = useAlternativeBlockPatterns(area, clientId);
  const hasReplacements = !!templateParts.length || !!blockPatterns.length;
  const canReplace = isEntityAvailable && hasReplacements && (area === 'header' || area === 'footer');
  if (!canReplace) {
    return null;
  }
  return createElement(MenuItem, {
    onClick: () => {
      setIsTemplatePartSelectionOpen(true);
    },
    "aria-expanded": isTemplatePartSelectionOpen,
    "aria-haspopup": "dialog"
  }, __('Replace'));
}
export default function TemplatePartEdit({
  attributes,
  setAttributes,
  clientId
}) {
  const currentTheme = useSelect(select => select(coreStore).getCurrentTheme()?.stylesheet, []);
  const {
    slug,
    theme = currentTheme,
    tagName,
    layout = {}
  } = attributes;
  const templatePartId = createTemplatePartId(theme, slug);
  const hasAlreadyRendered = useHasRecursion(templatePartId);
  const [isTemplatePartSelectionOpen, setIsTemplatePartSelectionOpen] = useState(false);

  // Set the postId block attribute if it did not exist,
  // but wait until the inner blocks have loaded to allow
  // new edits to trigger this.
  const {
    isResolved,
    innerBlocks,
    isMissing,
    area
  } = useSelect(select => {
    const {
      getEditedEntityRecord,
      hasFinishedResolution
    } = select(coreStore);
    const {
      getBlocks
    } = select(blockEditorStore);
    const getEntityArgs = ['postType', 'wp_template_part', templatePartId];
    const entityRecord = templatePartId ? getEditedEntityRecord(...getEntityArgs) : null;
    const _area = entityRecord?.area || attributes.area;
    const hasResolvedEntity = templatePartId ? hasFinishedResolution('getEditedEntityRecord', getEntityArgs) : false;
    return {
      innerBlocks: getBlocks(clientId),
      isResolved: hasResolvedEntity,
      isMissing: hasResolvedEntity && (!entityRecord || Object.keys(entityRecord).length === 0),
      area: _area
    };
  }, [templatePartId, attributes.area, clientId]);
  const areaObject = useTemplatePartArea(area);
  const blockProps = useBlockProps();
  const isPlaceholder = !slug;
  const isEntityAvailable = !isPlaceholder && !isMissing && isResolved;
  const TagName = tagName || areaObject.tagName;

  // We don't want to render a missing state if we have any inner blocks.
  // A new template part is automatically created if we have any inner blocks but no entity.
  if (innerBlocks.length === 0 && (slug && !theme || slug && isMissing)) {
    return createElement(TagName, {
      ...blockProps
    }, createElement(Warning, null, sprintf( /* translators: %s: Template part slug */
    __('Template part has been deleted or is unavailable: %s'), slug)));
  }
  if (isEntityAvailable && hasAlreadyRendered) {
    return createElement(TagName, {
      ...blockProps
    }, createElement(Warning, null, __('Block cannot be rendered inside itself.')));
  }
  return createElement(Fragment, null, createElement(RecursionProvider, {
    uniqueId: templatePartId
  }, createElement(InspectorControls, {
    group: "advanced"
  }, createElement(TemplatePartAdvancedControls, {
    tagName: tagName,
    setAttributes: setAttributes,
    isEntityAvailable: isEntityAvailable,
    templatePartId: templatePartId,
    defaultWrapper: areaObject.tagName,
    hasInnerBlocks: innerBlocks.length > 0
  })), isPlaceholder && createElement(TagName, {
    ...blockProps
  }, createElement(TemplatePartPlaceholder, {
    area: attributes.area,
    templatePartId: templatePartId,
    clientId: clientId,
    setAttributes: setAttributes,
    onOpenSelectionModal: () => setIsTemplatePartSelectionOpen(true)
  })), createElement(BlockSettingsMenuControls, null, ({
    selectedClientIds
  }) => {
    // Only enable for single selection that matches the current block.
    // Ensures menu item doesn't render multiple times.
    if (!(selectedClientIds.length === 1 && clientId === selectedClientIds[0])) {
      return null;
    }
    return createElement(ReplaceButton, {
      isEntityAvailable,
      area,
      clientId,
      templatePartId,
      isTemplatePartSelectionOpen,
      setIsTemplatePartSelectionOpen
    });
  }), isEntityAvailable && createElement(TemplatePartInnerBlocks, {
    tagName: TagName,
    blockProps: blockProps,
    postId: templatePartId,
    hasInnerBlocks: innerBlocks.length > 0,
    layout: layout
  }), !isPlaceholder && !isResolved && createElement(TagName, {
    ...blockProps
  }, createElement(Spinner, null))), isTemplatePartSelectionOpen && createElement(Modal, {
    overlayClassName: "block-editor-template-part__selection-modal",
    title: sprintf(
    // Translators: %s as template part area title ("Header", "Footer", etc.).
    __('Choose a %s'), areaObject.label.toLowerCase()),
    onRequestClose: () => setIsTemplatePartSelectionOpen(false),
    isFullScreen: true
  }, createElement(TemplatePartSelectionModal, {
    templatePartId: templatePartId,
    clientId: clientId,
    area: area,
    setAttributes: setAttributes,
    onClose: () => setIsTemplatePartSelectionOpen(false)
  })));
}
//# sourceMappingURL=index.js.map