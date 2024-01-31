"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.enhanceNavigationLinkVariations = enhanceNavigationLinkVariations;
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function getIcon(variationName) {
  switch (variationName) {
    case 'post':
      return _icons.postList;
    case 'page':
      return _icons.page;
    case 'tag':
      return _icons.tag;
    case 'category':
      return _icons.category;
    default:
      return _icons.customPostType;
  }
}
function enhanceNavigationLinkVariations(settings, name) {
  if (name !== 'core/navigation-link') {
    return settings;
  }

  // Otherwise decorate server passed variations with an icon and isActive function.
  if (settings.variations) {
    const isActive = (blockAttributes, variationAttributes) => {
      return blockAttributes.type === variationAttributes.type;
    };
    const variations = settings.variations.map(variation => {
      return {
        ...variation,
        ...(!variation.icon && {
          icon: getIcon(variation.name)
        }),
        ...(!variation.isActive && {
          isActive
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
//# sourceMappingURL=hooks.js.map