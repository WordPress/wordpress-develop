"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryPlaceholder;
var _react = require("react");
var _data = require("@wordpress/data");
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _utils = require("../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function QueryPlaceholder({
  attributes,
  clientId,
  name,
  openPatternSelectionModal,
  setAttributes
}) {
  const [isStartingBlank, setIsStartingBlank] = (0, _element.useState)(false);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const blockNameForPatterns = (0, _utils.useBlockNameForPatterns)(clientId, attributes);
  const {
    blockType,
    activeBlockVariation,
    hasPatterns
  } = (0, _data.useSelect)(select => {
    const {
      getActiveBlockVariation,
      getBlockType
    } = select(_blocks.store);
    const {
      getBlockRootClientId,
      getPatternsByBlockTypes
    } = select(_blockEditor.store);
    const rootClientId = getBlockRootClientId(clientId);
    return {
      blockType: getBlockType(name),
      activeBlockVariation: getActiveBlockVariation(name, attributes),
      hasPatterns: !!getPatternsByBlockTypes(blockNameForPatterns, rootClientId).length
    };
  }, [name, blockNameForPatterns, clientId, attributes]);
  const icon = activeBlockVariation?.icon?.src || activeBlockVariation?.icon || blockType?.icon?.src;
  const label = activeBlockVariation?.title || blockType?.title;
  if (isStartingBlank) {
    return (0, _react.createElement)(QueryVariationPicker, {
      clientId: clientId,
      attributes: attributes,
      setAttributes: setAttributes,
      icon: icon,
      label: label
    });
  }
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_components.Placeholder, {
    icon: icon,
    label: label,
    instructions: (0, _i18n.__)('Choose a pattern for the query loop or start blank.')
  }, !!hasPatterns && (0, _react.createElement)(_components.Button, {
    variant: "primary",
    onClick: openPatternSelectionModal
  }, (0, _i18n.__)('Choose')), (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    onClick: () => {
      setIsStartingBlank(true);
    }
  }, (0, _i18n.__)('Start blank'))));
}
function QueryVariationPicker({
  clientId,
  attributes,
  setAttributes,
  icon,
  label
}) {
  const scopeVariations = (0, _utils.useScopedBlockVariations)(attributes);
  const {
    replaceInnerBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.__experimentalBlockVariationPicker, {
    icon: icon,
    label: label,
    variations: scopeVariations,
    onSelect: variation => {
      if (variation.attributes) {
        setAttributes({
          ...variation.attributes,
          query: {
            ...variation.attributes.query,
            postType: attributes.query.postType || variation.attributes.query.postType
          },
          namespace: attributes.namespace
        });
      }
      if (variation.innerBlocks) {
        replaceInnerBlocks(clientId, (0, _blocks.createBlocksFromInnerBlocksTemplate)(variation.innerBlocks), false);
      }
    }
  }));
}
//# sourceMappingURL=query-placeholder.js.map