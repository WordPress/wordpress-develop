"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = HTMLEditPreview;
var _react = require("react");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

// Default styles used to unset some of the styles
// that might be inherited from the editor style.
const DEFAULT_STYLES = `
	html,body,:root {
		margin: 0 !important;
		padding: 0 !important;
		overflow: visible !important;
		min-height: auto !important;
	}
`;
function HTMLEditPreview({
  content,
  isSelected
}) {
  const settingStyles = (0, _data.useSelect)(select => select(_blockEditor.store).getSettings().styles);
  const styles = (0, _element.useMemo)(() => [DEFAULT_STYLES, ...(0, _blockEditor.transformStyles)(settingStyles.filter(style => style.css))], [settingStyles]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.SandBox, {
    html: content,
    styles: styles,
    title: (0, _i18n.__)('Custom HTML Preview'),
    tabIndex: -1
  }), !isSelected && (0, _react.createElement)("div", {
    className: "block-library-html__preview-overlay"
  }));
}
//# sourceMappingURL=preview.js.map