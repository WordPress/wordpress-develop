"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryPaginationEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _queryPaginationArrowControls = require("./query-pagination-arrow-controls");
var _queryPaginationLabelControl = require("./query-pagination-label-control");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TEMPLATE = [['core/query-pagination-previous'], ['core/query-pagination-numbers'], ['core/query-pagination-next']];
function QueryPaginationEdit({
  attributes: {
    paginationArrow,
    showLabel
  },
  setAttributes,
  clientId
}) {
  const hasNextPreviousBlocks = (0, _data.useSelect)(select => {
    const {
      getBlocks
    } = select(_blockEditor.store);
    const innerBlocks = getBlocks(clientId);
    /**
     * Show the `paginationArrow` and `showLabel` controls only if a
     * `QueryPaginationNext/Previous` block exists.
     */
    return innerBlocks?.find(innerBlock => {
      return ['core/query-pagination-next', 'core/query-pagination-previous'].includes(innerBlock.name);
    });
  }, [clientId]);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE
  });
  // Always show label text if paginationArrow is set to 'none'.
  (0, _element.useEffect)(() => {
    if (paginationArrow === 'none' && !showLabel) {
      setAttributes({
        showLabel: true
      });
    }
  }, [paginationArrow, setAttributes, showLabel]);
  return (0, _react.createElement)(_react.Fragment, null, hasNextPreviousBlocks && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_queryPaginationArrowControls.QueryPaginationArrowControls, {
    value: paginationArrow,
    onChange: value => {
      setAttributes({
        paginationArrow: value
      });
    }
  }), paginationArrow !== 'none' && (0, _react.createElement)(_queryPaginationLabelControl.QueryPaginationLabelControl, {
    value: showLabel,
    onChange: value => {
      setAttributes({
        showLabel: value
      });
    }
  }))), (0, _react.createElement)("nav", {
    ...innerBlocksProps
  }));
}
//# sourceMappingURL=edit.js.map