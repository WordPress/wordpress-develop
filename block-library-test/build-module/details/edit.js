import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, useInnerBlocksProps, store as blockEditorStore, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
const TEMPLATE = [['core/paragraph', {
  placeholder: __('Type / to add a hidden block')
}]];
function DetailsEdit({
  attributes,
  setAttributes,
  clientId
}) {
  const {
    showContent,
    summary
  } = attributes;
  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    template: TEMPLATE,
    __experimentalCaptureToolbars: true
  });

  // Check if either the block or the inner blocks are selected.
  const hasSelection = useSelect(select => {
    const {
      isBlockSelected,
      hasSelectedInnerBlock
    } = select(blockEditorStore);
    /* Sets deep to true to also find blocks inside the details content block. */
    return hasSelectedInnerBlock(clientId, true) || isBlockSelected(clientId);
  }, [clientId]);
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    label: __('Open by default'),
    checked: showContent,
    onChange: () => setAttributes({
      showContent: !showContent
    })
  }))), createElement("details", {
    ...innerBlocksProps,
    open: hasSelection || showContent
  }, createElement("summary", {
    onClick: event => event.preventDefault()
  }, createElement(RichText, {
    "aria-label": __('Write summary'),
    placeholder: __('Write summary…'),
    allowedFormats: [],
    withoutInteractiveFormatting: true,
    value: summary,
    onChange: newSummary => setAttributes({
      summary: newSummary
    })
  })), innerBlocksProps.children));
}
export default DetailsEdit;
//# sourceMappingURL=edit.js.map