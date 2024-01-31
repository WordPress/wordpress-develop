"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _queryContent = _interopRequireDefault(require("./query-content"));
var _queryPlaceholder = _interopRequireDefault(require("./query-placeholder"));
var _patternSelectionModal = _interopRequireDefault(require("./pattern-selection-modal"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const QueryEdit = props => {
  const {
    clientId,
    attributes
  } = props;
  const [isPatternSelectionModalOpen, setIsPatternSelectionModalOpen] = (0, _element.useState)(false);
  const hasInnerBlocks = (0, _data.useSelect)(select => !!select(_blockEditor.store).getBlocks(clientId).length, [clientId]);
  const Component = hasInnerBlocks ? _queryContent.default : _queryPlaceholder.default;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(Component, {
    ...props,
    openPatternSelectionModal: () => setIsPatternSelectionModalOpen(true)
  }), isPatternSelectionModalOpen && (0, _react.createElement)(_patternSelectionModal.default, {
    clientId: clientId,
    attributes: attributes,
    setIsPatternSelectionModalOpen: setIsPatternSelectionModalOpen
  }));
};
var _default = exports.default = QueryEdit;
//# sourceMappingURL=index.js.map