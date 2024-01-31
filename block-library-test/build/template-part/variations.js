"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.enhanceTemplatePartVariations = enhanceTemplatePartVariations;
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function getTemplatePartIcon(iconName) {
  if ('header' === iconName) {
    return _icons.header;
  } else if ('footer' === iconName) {
    return _icons.footer;
  } else if ('sidebar' === iconName) {
    return _icons.sidebar;
  }
  return _icons.symbolFilled;
}
function enhanceTemplatePartVariations(settings, name) {
  if (name !== 'core/template-part') {
    return settings;
  }
  if (settings.variations) {
    const isActive = (blockAttributes, variationAttributes) => {
      const {
        area,
        theme,
        slug
      } = blockAttributes;
      // We first check the `area` block attribute which is set during insertion.
      // This property is removed on the creation of a template part.
      if (area) return area === variationAttributes.area;
      // Find a matching variation from the created template part
      // by checking the entity's `area` property.
      if (!slug) return false;
      const {
        getCurrentTheme,
        getEntityRecord
      } = (0, _data.select)(_coreData.store);
      const entity = getEntityRecord('postType', 'wp_template_part', `${theme || getCurrentTheme()?.stylesheet}//${slug}`);
      if (entity?.slug) {
        return entity.slug === variationAttributes.slug;
      }
      return entity?.area === variationAttributes.area;
    };
    const variations = settings.variations.map(variation => {
      return {
        ...variation,
        ...(!variation.isActive && {
          isActive
        }),
        ...(typeof variation.icon === 'string' && {
          icon: getTemplatePartIcon(variation.icon)
        })
      };
    });
    return {
      ...settings,
      variations
    };
  }
  return settings;
}
//# sourceMappingURL=variations.js.map