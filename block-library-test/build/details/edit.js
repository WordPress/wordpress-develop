"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const TEMPLATE = [['core/paragraph', {
  placeholder: (0, _i18n.__)('Type / to add a hidden block')
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
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    __experimentalCaptureToolbars: true
  });

  // Check if either the block or the inner blocks are selected.
  const hasSelection = (0, _data.useSelect)(select => {
    const {
      isBlockSelected,
      hasSelectedInnerBlock
    } = select(_blockEditor.store);
    /* Sets deep to true to also find blocks inside the details content block. */
    return hasSelectedInnerBlock(clientId, true) || isBlockSelected(clientId);
  }, [clientId]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Open by default'),
    checked: showContent,
    onChange: () => setAttributes({
      showContent: !showContent
    })
  }))), (0, _react.createElement)("details", {
    ...innerBlocksProps,
    open: hasSelection || showContent
  }, (0, _react.createElement)("summary", {
    onClick: event => event.preventDefault()
  }, (0, _react.createElement)(_blockEditor.RichText, {
    "aria-label": (0, _i18n.__)('Write summary'),
    placeholder: (0, _i18n.__)('Write summary…'),
    allowedFormats: [],
    withoutInteractiveFormatting: true,
    value: summary,
    onChange: newSummary => setAttributes({
      summary: newSummary
    })
  })), innerBlocksProps.children));
}
var _default = exports.default = DetailsEdit;
//# sourceMappingURL=edit.js.map