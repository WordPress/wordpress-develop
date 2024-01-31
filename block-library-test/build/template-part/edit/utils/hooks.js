"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useAlternativeBlockPatterns = useAlternativeBlockPatterns;
exports.useAlternativeTemplateParts = useAlternativeTemplateParts;
exports.useCreateTemplatePartFromBlocks = useCreateTemplatePartFromBlocks;
exports.useTemplatePartArea = useTemplatePartArea;
var _changeCase = require("change-case");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _blocks = require("@wordpress/blocks");
var _i18n = require("@wordpress/i18n");
var _createTemplatePartId = require("./create-template-part-id");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Retrieves the available template parts for the given area.
 *
 * @param {string} area       Template part area.
 * @param {string} excludedId Template part ID to exclude.
 *
 * @return {{ templateParts: Array, isResolving: boolean }} array of template parts.
 */
function useAlternativeTemplateParts(area, excludedId) {
  const {
    templateParts,
    isResolving
  } = (0, _data.useSelect)(select => {
    const {
      getEntityRecords,
      isResolving: _isResolving
    } = select(_coreData.store);
    const query = {
      per_page: -1
    };
    return {
      templateParts: getEntityRecords('postType', 'wp_template_part', query),
      isResolving: _isResolving('getEntityRecords', ['postType', 'wp_template_part', query])
    };
  }, []);
  const filteredTemplateParts = (0, _element.useMemo)(() => {
    if (!templateParts) {
      return [];
    }
    return templateParts.filter(templatePart => (0, _createTemplatePartId.createTemplatePartId)(templatePart.theme, templatePart.slug) !== excludedId && (!area || 'uncategorized' === area || templatePart.area === area)) || [];
  }, [templateParts, area, excludedId]);
  return {
    templateParts: filteredTemplateParts,
    isResolving
  };
}

/**
 * Retrieves the available block patterns for the given area.
 *
 * @param {string} area     Template part area.
 * @param {string} clientId Block Client ID. (The container of the block can impact allowed blocks).
 *
 * @return {Array} array of block patterns.
 */
function useAlternativeBlockPatterns(area, clientId) {
  return (0, _data.useSelect)(select => {
    const blockNameWithArea = area ? `core/template-part/${area}` : 'core/template-part';
    const {
      getBlockRootClientId,
      getPatternsByBlockTypes
    } = select(_blockEditor.store);
    const rootClientId = getBlockRootClientId(clientId);
    return getPatternsByBlockTypes(blockNameWithArea, rootClientId);
  }, [area, clientId]);
}
function useCreateTemplatePartFromBlocks(area, setAttributes) {
  const {
    saveEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  return async (blocks = [], title = (0, _i18n.__)('Untitled Template Part')) => {
    // Currently template parts only allow latin chars.
    // Fallback slug will receive suffix by default.
    const cleanSlug = (0, _changeCase.paramCase)(title).replace(/[^\w-]+/g, '') || 'wp-custom-part';

    // If we have `area` set from block attributes, means an exposed
    // block variation was inserted. So add this prop to the template
    // part entity on creation. Afterwards remove `area` value from
    // block attributes.
    const record = {
      title,
      slug: cleanSlug,
      content: (0, _blocks.serialize)(blocks),
      // `area` is filterable on the server and defaults to `UNCATEGORIZED`
      // if provided value is not allowed.
      area
    };
    const templatePart = await saveEntityRecord('postType', 'wp_template_part', record);
    setAttributes({
      slug: templatePart.slug,
      theme: templatePart.theme,
      area: undefined
    });
  };
}

/**
 * Retrieves the template part area object.
 *
 * @param {string} area Template part area identifier.
 *
 * @return {{icon: Object, label: string, tagName: string}} Template Part area.
 */
function useTemplatePartArea(area) {
  return (0, _data.useSelect)(select => {
    var _selectedArea$area_ta;
    // FIXME: @wordpress/block-library should not depend on @wordpress/editor.
    // Blocks can be loaded into a *non-post* block editor.
    /* eslint-disable @wordpress/data-no-store-string-literals */
    const definedAreas = select('core/editor').__experimentalGetDefaultTemplatePartAreas();
    /* eslint-enable @wordpress/data-no-store-string-literals */

    const selectedArea = definedAreas.find(definedArea => definedArea.area === area);
    const defaultArea = definedAreas.find(definedArea => definedArea.area === 'uncategorized');
    return {
      icon: selectedArea?.icon || defaultArea?.icon,
      label: selectedArea?.label || (0, _i18n.__)('Template Part'),
      tagName: (_selectedArea$area_ta = selectedArea?.area_tag) !== null && _selectedArea$area_ta !== void 0 ? _selectedArea$area_ta : 'div'
    };
  }, [area]);
}
//# sourceMappingURL=hooks.js.map