"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
var _richText = require("@wordpress/rich-text");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    isMultiBlock: true,
    blocks: ['core/button'],
    transform: buttons =>
    // Creates the buttons block.
    (0, _blocks.createBlock)('core/buttons', {},
    // Loop the selected buttons.
    buttons.map(attributes =>
    // Create singular button in the buttons block.
    (0, _blocks.createBlock)('core/button', attributes)))
  }, {
    type: 'block',
    isMultiBlock: true,
    blocks: ['core/paragraph'],
    transform: buttons =>
    // Creates the buttons block.
    (0, _blocks.createBlock)('core/buttons', {},
    // Loop the selected buttons.
    buttons.map(attributes => {
      const element = (0, _richText.__unstableCreateElement)(document, attributes.content);
      // Remove any HTML tags.
      const text = element.innerText || '';
      // Get first url.
      const link = element.querySelector('a');
      const url = link?.getAttribute('href');
      // Create singular button in the buttons block.
      return (0, _blocks.createBlock)('core/button', {
        text,
        url
      });
    })),
    isMatch: paragraphs => {
      return paragraphs.every(attributes => {
        const element = (0, _richText.__unstableCreateElement)(document, attributes.content);
        const text = element.innerText || '';
        const links = element.querySelectorAll('a');
        return text.length <= 30 && links.length <= 1;
      });
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map