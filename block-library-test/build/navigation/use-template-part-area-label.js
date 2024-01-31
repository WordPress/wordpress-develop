"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useTemplatePartAreaLabel;
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _createTemplatePartId = require("../template-part/edit/utils/create-template-part-id");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

// TODO: this util should perhaps be refactored somewhere like core-data.

function useTemplatePartAreaLabel(clientId) {
  return (0, _data.useSelect)(select => {
    // Use the lack of a clientId as an opportunity to bypass the rest
    // of this hook.
    if (!clientId) {
      return;
    }
    const {
      getBlock,
      getBlockParentsByBlockName
    } = select(_blockEditor.store);
    const withAscendingResults = true;
    const parentTemplatePartClientIds = getBlockParentsByBlockName(clientId, 'core/template-part', withAscendingResults);
    if (!parentTemplatePartClientIds?.length) {
      return;
    }

    // FIXME: @wordpress/block-library should not depend on @wordpress/editor.
    // Blocks can be loaded into a *non-post* block editor.
    // This code is lifted from this file:
    // packages/block-library/src/template-part/edit/advanced-controls.js
    /* eslint-disable @wordpress/data-no-store-string-literals */
    const definedAreas = select('core/editor').__experimentalGetDefaultTemplatePartAreas();
    /* eslint-enable @wordpress/data-no-store-string-literals */
    const {
      getCurrentTheme,
      getEditedEntityRecord
    } = select(_coreData.store);
    for (const templatePartClientId of parentTemplatePartClientIds) {
      const templatePartBlock = getBlock(templatePartClientId);

      // The 'area' usually isn't stored on the block, but instead
      // on the entity.
      const {
        theme = getCurrentTheme()?.stylesheet,
        slug
      } = templatePartBlock.attributes;
      const templatePartEntityId = (0, _createTemplatePartId.createTemplatePartId)(theme, slug);
      const templatePartEntity = getEditedEntityRecord('postType', 'wp_template_part', templatePartEntityId);

      // Look up the `label` for the area in the defined areas so
      // that an internationalized label can be used.
      if (templatePartEntity?.area) {
        return definedAreas.find(definedArea => definedArea.area !== 'uncategorized' && definedArea.area === templatePartEntity.area)?.label;
      }
    }
  }, [clientId]);
}
//# sourceMappingURL=use-template-part-area-label.js.map