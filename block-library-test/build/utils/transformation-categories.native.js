"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.transformationCategory = exports.default = void 0;
const transformationCategories = {
  richText: ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/pullquote', 'core/preformatted', 'core/verse', 'core/shortcode', 'core/code'],
  media: ['core/image', 'core/video', 'core/gallery', 'core/cover', 'core/file', 'core/audio', 'core/media-text', 'core/embed'],
  grouped: ['core/columns', 'core/group', 'core/text-columns'],
  other: ['core/more', 'core/nextpage', 'core/separator', 'core/spacer', 'core/latest-posts', 'core/buttons']
};
const transformationCategory = blockName => {
  const found = Object.entries(transformationCategories).find(([, value]) => value.includes(blockName));
  if (!found) {
    return [];
  }
  const group = found[0];
  return transformationCategories[group];
};
exports.transformationCategory = transformationCategory;
var _default = exports.default = transformationCategories;
//# sourceMappingURL=transformation-categories.native.js.map