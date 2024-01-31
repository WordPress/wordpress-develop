"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TemplatePartEdit;
var _react = require("react");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _placeholder = _interopRequireDefault(require("./placeholder"));
var _selectionModal = _interopRequireDefault(require("./selection-modal"));
var _advancedControls = require("./advanced-controls");
var _innerBlocks = _interopRequireDefault(require("./inner-blocks"));
var _createTemplatePartId = require("./utils/create-template-part-id");
var _hooks = require("./utils/hooks");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

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
  } = (0, _hooks.useAlternativeTemplateParts)(area, templatePartId);
  const blockPatterns = (0, _hooks.useAlternativeBlockPatterns)(area, clientId);
  const hasReplacements = !!templateParts.length || !!blockPatterns.length;
  const canReplace = isEntityAvailable && hasReplacements && (area === 'header' || area === 'footer');
  if (!canReplace) {
    return null;
  }
  return (0, _react.createElement)(_components.MenuItem, {
    onClick: () => {
      setIsTemplatePartSelectionOpen(true);
    },
    "aria-expanded": isTemplatePartSelectionOpen,
    "aria-haspopup": "dialog"
  }, (0, _i18n.__)('Replace'));
}
function TemplatePartEdit({
  attributes,
  setAttributes,
  clientId
}) {
  const currentTheme = (0, _data.useSelect)(select => select(_coreData.store).getCurrentTheme()?.stylesheet, []);
  const {
    slug,
    theme = currentTheme,
    tagName,
    layout = {}
  } = attributes;
  const templatePartId = (0, _createTemplatePartId.createTemplatePartId)(theme, slug);
  const hasAlreadyRendered = (0, _blockEditor.useHasRecursion)(templatePartId);
  const [isTemplatePartSelectionOpen, setIsTemplatePartSelectionOpen] = (0, _element.useState)(false);

  // Set the postId block attribute if it did not exist,
  // but wait until the inner blocks have loaded to allow
  // new edits to trigger this.
  const {
    isResolved,
    innerBlocks,
    isMissing,
    area
  } = (0, _data.useSelect)(select => {
    const {
      getEditedEntityRecord,
      hasFinishedResolution
    } = select(_coreData.store);
    const {
      getBlocks
    } = select(_blockEditor.store);
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
  const areaObject = (0, _hooks.useTemplatePartArea)(area);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const isPlaceholder = !slug;
  const isEntityAvailable = !isPlaceholder && !isMissing && isResolved;
  const TagName = tagName || areaObject.tagName;

  // We don't want to render a missing state if we have any inner blocks.
  // A new template part is automatically created if we have any inner blocks but no entity.
  if (innerBlocks.length === 0 && (slug && !theme || slug && isMissing)) {
    return (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.sprintf)( /* translators: %s: Template part slug */
    (0, _i18n.__)('Template part has been deleted or is unavailable: %s'), slug)));
  }
  if (isEntityAvailable && hasAlreadyRendered) {
    return (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Block cannot be rendered inside itself.')));
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.RecursionProvider, {
    uniqueId: templatePartId
  }, (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_advancedControls.TemplatePartAdvancedControls, {
    tagName: tagName,
    setAttributes: setAttributes,
    isEntityAvailable: isEntityAvailable,
    templatePartId: templatePartId,
    defaultWrapper: areaObject.tagName,
    hasInnerBlocks: innerBlocks.length > 0
  })), isPlaceholder && (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)(_placeholder.default, {
    area: attributes.area,
    templatePartId: templatePartId,
    clientId: clientId,
    setAttributes: setAttributes,
    onOpenSelectionModal: () => setIsTemplatePartSelectionOpen(true)
  })), (0, _react.createElement)(_blockEditor.BlockSettingsMenuControls, null, ({
    selectedClientIds
  }) => {
    // Only enable for single selection that matches the current block.
    // Ensures menu item doesn't render multiple times.
    if (!(selectedClientIds.length === 1 && clientId === selectedClientIds[0])) {
      return null;
    }
    return (0, _react.createElement)(ReplaceButton, {
      isEntityAvailable,
      area,
      clientId,
      templatePartId,
      isTemplatePartSelectionOpen,
      setIsTemplatePartSelectionOpen
    });
  }), isEntityAvailable && (0, _react.createElement)(_innerBlocks.default, {
    tagName: TagName,
    blockProps: blockProps,
    postId: templatePartId,
    hasInnerBlocks: innerBlocks.length > 0,
    layout: layout
  }), !isPlaceholder && !isResolved && (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)(_components.Spinner, null))), isTemplatePartSelectionOpen && (0, _react.createElement)(_components.Modal, {
    overlayClassName: "block-editor-template-part__selection-modal",
    title: (0, _i18n.sprintf)(
    // Translators: %s as template part area title ("Header", "Footer", etc.).
    (0, _i18n.__)('Choose a %s'), areaObject.label.toLowerCase()),
    onRequestClose: () => setIsTemplatePartSelectionOpen(false),
    isFullScreen: true
  }, (0, _react.createElement)(_selectionModal.default, {
    templatePartId: templatePartId,
    clientId: clientId,
    area: area,
    setAttributes: setAttributes,
    onClose: () => setIsTemplatePartSelectionOpen(false)
  })));
}
//# sourceMappingURL=index.js.map