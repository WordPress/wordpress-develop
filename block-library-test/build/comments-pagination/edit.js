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
var _commentsPaginationArrowControls = require("./comments-pagination-arrow-controls");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const TEMPLATE = [['core/comments-pagination-previous'], ['core/comments-pagination-numbers'], ['core/comments-pagination-next']];
function QueryPaginationEdit({
  attributes: {
    paginationArrow
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
     * Show the `paginationArrow` control only if a
     * Comments Pagination Next or Comments Pagination Previous
     * block exists.
     */
    return innerBlocks?.find(innerBlock => {
      return ['core/comments-pagination-previous', 'core/comments-pagination-next'].includes(innerBlock.name);
    });
  }, []);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE
  });

  // Get the Discussion settings
  const pageComments = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      __experimentalDiscussionSettings
    } = getSettings();
    return __experimentalDiscussionSettings?.pageComments;
  }, []);

  // If paging comments is not enabled in the Discussion Settings then hide the pagination
  // controls. We don't want to remove them from the template so that when the user enables
  // paging comments, the controls will be visible.
  if (!pageComments) {
    return (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Comments Pagination block: paging comments is disabled in the Discussion Settings'));
  }
  return (0, _react.createElement)(_react.Fragment, null, hasNextPreviousBlocks && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_commentsPaginationArrowControls.CommentsPaginationArrowControls, {
    value: paginationArrow,
    onChange: value => {
      setAttributes({
        paginationArrow: value
      });
    }
  }))), (0, _react.createElement)("div", {
    ...innerBlocksProps
  }));
}
//# sourceMappingURL=edit.js.map