"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TemplatePartPlaceholder;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _hooks = require("./utils/hooks");
var _titleModal = _interopRequireDefault(require("./title-modal"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function TemplatePartPlaceholder({
  area,
  clientId,
  templatePartId,
  onOpenSelectionModal,
  setAttributes
}) {
  const {
    templateParts,
    isResolving
  } = (0, _hooks.useAlternativeTemplateParts)(area, templatePartId);
  const blockPatterns = (0, _hooks.useAlternativeBlockPatterns)(area, clientId);
  const [showTitleModal, setShowTitleModal] = (0, _element.useState)(false);
  const areaObject = (0, _hooks.useTemplatePartArea)(area);
  const createFromBlocks = (0, _hooks.useCreateTemplatePartFromBlocks)(area, setAttributes);
  return (0, _react.createElement)(_components.Placeholder, {
    icon: areaObject.icon,
    label: areaObject.label,
    instructions: (0, _i18n.sprintf)(
    // Translators: %s as template part area title ("Header", "Footer", etc.).
    (0, _i18n.__)('Choose an existing %s or create a new one.'), areaObject.label.toLowerCase())
  }, isResolving && (0, _react.createElement)(_components.Spinner, null), !isResolving && !!(templateParts.length || blockPatterns.length) && (0, _react.createElement)(_components.Button, {
    variant: "primary",
    onClick: onOpenSelectionModal
  }, (0, _i18n.__)('Choose')), !isResolving && (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    onClick: () => {
      setShowTitleModal(true);
    }
  }, (0, _i18n.__)('Start blank')), showTitleModal && (0, _react.createElement)(_titleModal.default, {
    areaLabel: areaObject.label,
    onClose: () => setShowTitleModal(false),
    onSubmit: title => {
      createFromBlocks([], title);
    }
  }));
}
//# sourceMappingURL=placeholder.js.map