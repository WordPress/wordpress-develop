"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = enhanceVariations;
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

const variationIconMap = {
  category: _icons.postCategories,
  post_tag: _icons.postTerms
};

// We add `icons` to categories and tags. The remaining ones use
// the block's default icon.
function enhanceVariations(settings, name) {
  if (name !== 'core/post-terms') {
    return settings;
  }
  const variations = settings.variations.map(variation => {
    var _variationIconMap$var;
    return {
      ...variation,
      ...{
        icon: (_variationIconMap$var = variationIconMap[variation.name]) !== null && _variationIconMap$var !== void 0 ? _variationIconMap$var : _icons.postCategories
      }
    };
  });
  return {
    ...settings,
    variations
  };
}
//# sourceMappingURL=hooks.js.map