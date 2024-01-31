/**
 * WordPress dependencies
 */
import { postCategories, postTerms } from '@wordpress/icons';
const variationIconMap = {
  category: postCategories,
  post_tag: postTerms
};

// We add `icons` to categories and tags. The remaining ones use
// the block's default icon.
export default function enhanceVariations(settings, name) {
  if (name !== 'core/post-terms') {
    return settings;
  }
  const variations = settings.variations.map(variation => {
    var _variationIconMap$var;
    return {
      ...variation,
      ...{
        icon: (_variationIconMap$var = variationIconMap[variation.name]) !== null && _variationIconMap$var !== void 0 ? _variationIconMap$var : postCategories
      }
    };
  });
  return {
    ...settings,
    variations
  };
}
//# sourceMappingURL=hooks.js.map